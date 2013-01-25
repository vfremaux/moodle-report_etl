<?php

/**
 * Moodle - Modular Object-Oriented Dynamic Learning Environment
 *          http://moodle.org
 * Copyright (C) 1999 onwards Martin Dougiamas  http://dougiamas.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    moodle
 * @subpackage etl
 * @author     Valery Fremaux <valery.Fremaux@club-internet.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 1999 onwards Martin Dougiamas  http://dougiamas.com
 *
 * Provides actual implementation of the extractor for TOA (Trimane Open Analytics)
 * for a standard Moodle installation
 *
 */

require_once $CFG->dirroot.'/admin/report/etl/lib.php';
require_once $CFG->dirroot.'/admin/report/etl/xmllib.php';

define('SSL_SAFE_GUARD', 5000); // safe window in seconds

class toamoodle_extractor extends etl_extractor{
    function __construct($id, $key, $method, $local = false){
        global $CFG;

        $this->id = $id;
        
        if($toa = get_record('toamoodle', 'id', $id)){
            $this->wwwroot = $toa->wwwroot;
            $this->publickey = $toa->publickey;
            $this->lastextract = $toa->lastextract;
            $this->lastextract_special_actions = $toa->lastextractactions;
            $this->lastextract_special_academics = $toa->lastextractacademics;
            $this->lastextract_special_documents = $toa->lastextractdocuments;
            $this->lastextract_special_communications = $toa->lastextractcommunications;
            $this->lastextract_special_grades = $toa->lastextractgrades;
            $this->outputencoding = $toa->outputencoding;
            $this->accessurl = $toa->accessurl;
            $this->externalaccessurl = $toa->externalaccessurl;
            $this->toahost = $toa->toahost;
            $this->toaipmask = $toa->toaipmask;
            $this->masquerade = $toa->masquerade;
        }
        
        $this->name = 'toamoodle';

        if ($local) return;

        // this will authenticate the access to the etl instance
        parent::__construct($key, $method, $local);

        /// fix to and from situation when both are empty
        // defaults to "last diff"
        if (empty($this->parms->from) && empty($this->parms->to)){
            if (preg_match('/^special_/', $this->query)){
                $extractname = 'lastextract_'.$this->query;
                $this->parms->from = @$this->$extractname;
            } else {
                $this->parms->from = $this->lastextract;
            }
            $this->parms->to = time();
        }
        
        // defaults from 0 to "to"
        if (empty($this->parms->from)){
            $this->parms->from = 0;
        }

        // defaults from "from" to "now"
        if (empty($this->parms->to)){
            $this->parms->to = time();
        }

        $this->config();
        
        $toclause = '';
        if (!empty($this->parms->to)){
            $toclause = " AND l.time <= {$this->parms->to} ";
        }

        $fromclause = '';
        if (!empty($this->parms->from)){
            $fromclause = " AND l.time > {$this->parms->from} ";
        }
        
    }

    /**
    * plugin specific decoder. Uses TOA internally stored
    * public key to get data from. 
    */
    function decode($key){
        
        if($info = $this->get_key_info($key, $this->method)){

            // check if SSO/DES ticket is not obsolete
            if ($info->date < time() - SSL_SAFE_GUARD){
                etl_error('ticket is too old');
                return false;
            }
            return $info;
        } else {
            etl_error('could not read ticket information');
        }
    }

    /** 
    * decrypts and get ticket
    */
    function get_key_info($key, $method = 'des'){

        if ($method == 'rsa'){
            /* using RSA */
    
            $toa = get_record('toamoodle', 'id' , $this->id);
            if (empty($toa->publickey)){
                etl_error("Cannot use unsecured TAO connector");
            }
            
            $pkey = openssl_pkey_get_public($toa->publickey);
    
            if(!openssl_public_decrypt(urldecode($key), $decrypted, $pkey)){
                etl_error("Failed reading key");
            }
        } else {        
            /* using MySQL AES_DECRYPT */

            $sql = "
                SELECT
                    AES_DECRYPT(UNHEX('$key'), '{$this->publickey}') AS result
            ";
            if ($result = get_record_sql($sql)){
                $decrypted = $result->result;
            } else {                
                return null;
            }
        }

        if (!$keyinfo = json_decode($decrypted)){
            etl_error('Error while deserializing');
        }

        return $keyinfo;
    }

    /**
    *
    *
    */
    function save_config(){
        $toa->id = $this->id;
        $toa->wwwroot = $this->wwwroot;
        $toa->publickey = $this->publickey;
        $toa->lastextract = $this->lastextract;
        $toa->lastextractactions = $this->lastextract_special_actions;
        $toa->lastextractacademics = $this->lastextract_special_academics;
        $toa->lastextractdocuments = $this->lastextract_special_documents;
        $toa->lastextractcommunications = $this->lastextract_special_communications;
        $toa->lastextractgrades = $this->lastextract_special_grades;
        $toa->outputencoding = $this->outputencoding;
        $toa->masquerade = $this->masquerade;

        if (!update_record('toamoodle', $toa)){
            etl_error("Could not save TOAMOODLE config");
        }
    }

    /**
    * allows a dataless query for testing connexion only
    *
    */
    function extract_test(&$output, $testmode = false){

        // get current plugin version
        include 'version.php';

        if ($testmode == 'test'){            
        } else {
            echo "<?xml version=\"1.0\"  encoding=\"{$this->outputencoding}\" ?>\n";
        }
        echo "<connection>\n";
        echo "\t<status>success</status>\n";
        echo "\t<time>".time()."</time>\n";
        echo "\t<version>".$plugin->version."</version>\n";
        echo "</connection>\n";
        return;
    }
    
    /**
    * get aggregated source information for all real courses
    *
    */
    function extract_actions(&$output, $testmode = false){
        global $CFG, $SITE;
        
        //* Performance *//
        list($usec, $sec) = explode(" ",microtime()); 
        $perfs['TOTAL'] = (float)$sec + (float)$usec;
        $perfs['RECORDS'] = (float)$sec + (float)$usec; 
        $perfs['INDICATORS'] = 0; 
        $perfs['QUALIFIERS'] = 0; 
        $perfs['OUTPUT'] = 0; 
        //**//

        $locallangroot = $CFG->dirroot.'/admin/report/etl/plugins/toamoodle/lang/';

        /// set extraction boundaries

        $toclause = '';
        if (!empty($this->parms->to)){
            $toclause = " AND l.time <= {$this->parms->to} ";
        }

        $fromclause = '';
        if (!empty($this->parms->from)){
            $fromclause = " AND l.time > {$this->parms->from} ";
        }
           
        /// get standard log

        $testclause = '';
        if ($testmode == 'test'){
            $testclause = ' LIMIT 0,30 ';
        }        
        
        $sql = "SELECT 
                    l.*,
                    u.username,
                    mh.id as userhostid,
                    mh.name as userhostname,
                    mh.wwwroot as userhost
                FROM
                    mdl_log l,
                    mdl_user u,
                    mdl_mnet_host mh        
                WHERE
                    l.userid = u.id AND               
                    u.mnethostid = mh.id AND
                    l.course != 0
                    $fromclause
                    $toclause
                ORDER BY
                    l.time
                $testclause
        ";

    /// put some cache structures

        $COURSECACHE = array();
        
    /// start producing

        if ($testmode == 'test'){            
        } else {
            echo "<?xml version=\"1.0\"  encoding=\"{$this->outputencoding}\" ?>\n<logrecords>\n";
        }
        
        $i = 0;

        $rs = get_recordset_sql($sql);

        //** Performance **//
        list($usec, $sec) = explode(" ",microtime()); 
        $perfs['RECORDS'] = (float)$sec + (float)$usec - (float)$perfs['RECORDS']; 
        //**//

        if ($rs->RecordCount()) {
            while ($u = rs_fetch_next_record($rs)) {

            /// QUALIFIERS

                //** Performance **//
                list($usec, $sec) = explode(" ",microtime()); 
                $qtick = (float)$sec + (float)$usec; 
                //**//

                $u->host = $SITE->shortname;
                $u->from = $this->parms->from;
                $u->to = $this->parms->to;

                // get module or block name.
                if (preg_match('/^block_/', $u->module)){
                    // we are assuming here some complex blocks might have to log something
                    // and will naturally choose to tag the module as block_xxxxx
                    
                    // we will identify a block as [blocktype] : [instanceid] : [position]
                    if ($u->cmid){
                        $blockinstance = get_record('block_instance', 'id', $u->cmid);
                        $block = get_record('block', 'id', $blockinstance->blockid);
                        $blockname = get_string('blockname', $block->name);
                        $u->instance = format_string($blockname.' :: ['.$blockinstance->position.','.$blockinstance->weight.'] :: '.$u->cmid);
                        $context = get_context_instance(CONTEXT_BLOCK, $blockinstance->id);
                        // check visibility
                        $u->visible = $blockinstance->visible;                        
                        $u->groupmode = null;
                    }
                } elseif ($u->module == 'course') {
                    // see later after course information retrieval
                    $u->visible = 1;
                } elseif ($u->module == 'user') {
                    // see later after course information retrieval
                    $u->instance = get_string('nc', 'toamoodle', '', $locallangroot);
                    $u->visible = 1;                        
                    $u->groupmode = null;
                } else {
                    // should be a module
                    if ($u->cmid){
                        if (!$cm = get_record('course_modules', 'id', $u->cmid)) continue;
                        if ($module = get_record('modules', 'id', $cm->module)){ // if is some valid module we can...
    
                            if (preg_match('/label$/', $module->name)) continue; // ignore labels and customlabels
        
                            $modulerec = get_record($u->module, 'id', $cm->instance);
                            $idnumber = (empty($cm->idnumber)) ? '---' : $cm->idnumber ;
                            $u->instance = "[{$modulerec->id}] :: " . $idnumber . ' :: '.format_string($modulerec->name);
        
                            // get pagename if format page.
                            // NOTE This is not used for standard TOA4Moodle but not ripped out for the future
                            /*
                            if ($pageitem = get_record_select('format_page_items', "cmid = $u->cmid AND blockinstance = 0")){
                                if ($page = get_record('format_page', 'id', $pageitem->pageid)){
                                    $u->pagename = $page->nameone;
                                } else {
                                    $u->pagename = get_string('badpage', 'toamoodle', '', $CFG->dirroot.'/admin/report/etl/plugins/toamoodle/lang/');
                                }
                            } 
                            */
                        }
                        $u->visible = $cm->visible;
                        $u->groupmode = $cm->groupmode;
                        $context = get_context_instance(CONTEXT_MODULE, $u->cmid);
                    } else {
                        $u->visible = get_string('nc', 'toamoodle', '', $locallangroot);
                        $u->groupmode = null;
                        $u->instance = get_string('nc', 'toamoodle', '', $locallangroot);
                    }
                }

                // get course information
                $u->courseid = $u->course; // save the course id
                if (!array_key_exists($u->courseid, $COURSECACHE)){
                    if (!$course = get_record('course', 'id', $u->course)){
                        $course->idnumber = '---';
                        $course->groupmode = get_string('nc', 'toamoodle', '', $locallangroot);
                        $course->shortname = get_string('nc', 'toamoodle', '', $locallangroot);
                        $course->context = get_context_instance(CONTEXT_SYSTEM);
                    } else {
                        $course->context = get_context_instance(CONTEXT_COURSE, $u->course);
                    }
                    $COURSECACHE[$u->courseid] = $course;
                } else {
                    $course = $COURSECACHE[$u->courseid];
                }
                if ($course->idnumber === '') $course->idnumber = '---';
                $u->course = $course->idnumber.' :: '.$course->shortname;
                $u->visible = $u->visible && $course->visible;

                // post processing of course instance
                
                if ($u->module == 'course'){
                    $u->instance = '['.$course->id.'] :: '.$course->idnumber.' :: '.format_string($course->fullname);                    
                    $u->groupmode = $course->groupmode;
                }

                // group mode
                if (is_null($u->groupmode)) $u->groupmode = $course->groupmode;
                switch($u->groupmode){
                    case NOGROUPS:
                        $u->groupmode = get_string('nogroups', 'toamoodle', '', $locallangroot);
                        break;
                    case SEPARATEGROUPS:
                        $u->groupmode = get_string('separatedgroups', 'toamoodle', '', $locallangroot);
                        break;
                    case VISIBLEGROUPS:
                        $u->groupmode = get_string('visiblegroups', 'toamoodle', '', $locallangroot);
                        break;
                }

                // Get category chain                
                toa_get_additional_course_info($u, $course);

                // Get category chain                
                toa_get_upper_categories($u, $course);

                // PATCH : Pairformance additions to the analysis model
                if (function_exists('tao_is_learning_format') && tao_is_learning_format($course)){
                    toa_learning_get_classifiers($u);
                } else {
                    $u->classifier1 = get_string('nc', 'toamoodle', '', $locallangroot);
                    $u->classifier2 = get_string('nc', 'toamoodle', '', $locallangroot);
                    $u->classifier3 = get_string('nc', 'toamoodle', '', $locallangroot);
                }
                // /PATCH
                
                toa_reshape_visible($u);
                
                // Get role assignation if available
                if (empty($context)) $context = $course->context; // may be system level
                toa_get_user_roles($u, $course, null, '', $context);

                // reshape user's origin
                toa_reshape_user_origin($u);

                // anonymise user references in extraction
                if (!empty($this->masquerade)){
                    $u->username = md5($u->username.@$CFG->passwordsaltmain);
                }                

                //** Performance **//
                list($usec, $sec) = explode(" ",microtime()); 
                $perfs['QUALIFIERS'] += (float)$sec + (float)$usec - (float)$qtick; 
                //**//

            /// INDICATORS 

                //* Performance *//
                list($usec, $sec) = explode(" ",microtime()); 
                $itick = (float)$sec + (float)$usec;
                //**//
                            
                // search nearest log of this user in the future.
                $fetchbacksql = "
                    SELECT
                        id,
                        time as nearest
                    FROM
                        {$CFG->prefix}log
                    WHERE
                        `time` > {$u->time} AND
                        userid = {$u->userid}
                    ORDER BY
                        `time` ASC
                ";
                if($rec = get_record_sql($fetchbacksql)){
                    $u->gap = $rec->nearest - $u->time;
                } else {
                    $u->gap = MINSECS * 10; // give a mean positive time
                }

                //* Performance *//
                list($usec, $sec) = explode(" ",microtime()); 
                $perfs['INDICATORS'] += (float)$sec + (float)$usec - (float)$itick;
                //**//

                // clean output record
                unset($u->userid);
                unset($u->cmid);
                unset($u->url);
                unset($u->info);
                unset($u->ip);
                unset($u->id);
                unset($u->userhostid);
                unset($u->userhostname);
                unset($u->courseid);
                $visiblestr = get_string('visible', 'toamoodle', '', $locallangroot);
                $nonvisiblestr = get_string('nonvisible', 'toamoodle', '', $locallangroot);
                $u->visible = ($u->visible) ? $visiblestr : $nonvisiblestr ;

                //* Performance *//
                list($usec, $sec) = explode(" ",microtime()); 
                $otick = (float)$sec + (float)$usec;
                //**//
                
                if ($testmode == 'test'){
                    echo mb_convert_encoding(htmlentities(recordtoxml($u, $i, 'logrecord', ''), ENT_QUOTES, 'UTF-8'), $this->outputencoding, 'UTF-8');
                } else {
                    echo mb_convert_encoding(recordtoxml($u, $i, 'logrecord', ''), $this->outputencoding, 'UTF-8');
                }

                //* Performance *//
                list($usec, $sec) = explode(" ",microtime()); 
                $perfs['OUTPUT'] += (float)$sec + (float)$usec - (float)$otick;
                //**//

                $i++;
            }

            // don't forget last record !!
            if ($i > 0){
                if ($testmode == 'test'){
                    echo mb_convert_encoding(htmlentities(recordtoxml($u, $i, 'logrecord', ''), ENT_QUOTES, 'UTF-8'), $this->outputencoding, 'UTF-8');
                } else {
                    echo mb_convert_encoding(recordtoxml($u, $i, 'logrecord', ''), $this->outputencoding, 'UTF-8');
                }
            }
        }
        rs_close($rs);

        if ($testmode == 'test'){
            echo htmlentities("\n</logrecords>");
        } else {
            echo "\n</logrecords>";
        }
        
        flush();

        //* Performance *//
        list($usec, $sec) = explode(" ",microtime()); 
        $perfs['TOTAL'] = (float)$sec + (float)$usec - (float)$perfs['TOTAL'];
        //**//

        // prepare the "till when" temporary marker
        if (!empty($u->time)){
            $this->lastextract = $u->time;
        } else { // empty extraction
            $this->lastextract = time();
        }
        $this->save_config();

        return $perfs;
    }
    
    /**
    * get "statefull" (or eventless) information about courses and assignations at the time the extraction is required.
    *
    */
    function extract_academics(&$output, $testmode = false){
        global $CFG, $SITE;

        $locallangroot = $CFG->dirroot.'/admin/report/etl/plugins/toamoodle/lang/';

        /// "to" means "up to"
        
        /// get user to course assignation

        $testclause = '';
        if ($testmode == 'test'){
            $testclause = ' LIMIT 0,30 ';
        }        
        
        $now = time();
        
        $sql = "
            SELECT 
                ra.userid,
                co.id as contextid,
                co.contextlevel,
                co.instanceid,
                co.path,
                mh.wwwroot as userhost,
                r.name as role
            FROM
                mdl_user u,
                mdl_context co,
                mdl_role r,
                mdl_role_assignments ra,
                mdl_mnet_host mh
            WHERE
                (ra.timeend = 0 OR ra.timeend > $now) AND
                co.id = ra.contextid AND
                r.id = ra.roleid AND
                ra.userid = u.id AND               
                u.mnethostid = mh.id AND
                u.deleted = 0 AND
                u.confirmed = 1
            ORDER BY
                ra.timemodified,r.name
            $testclause
        ";

        if ($testmode == 'test'){            
        } else {
            echo "<?xml version=\"1.0\"  encoding=\"{$this->outputencoding}\" ?>\n<roleassignrecords>\n";
        }

        $lasttimes = array();
        
        //context
        $sitestr = get_string('sitecontext', 'toamoodle', '', $locallangroot);
        $categorystr = get_string('categorycontext', 'toamoodle', '', $locallangroot);
        $coursestr = get_string('coursecontext', 'toamoodle', '', $locallangroot);
        $modulestr = get_string('modcontext', 'toamoodle', '', $locallangroot);
        $groupstr = get_string('groupcontext', 'toamoodle', '', $locallangroot);
        $blockstr = get_string('blockcontext', 'toamoodle', '', $locallangroot);
        $userstr = get_string('usercontext', 'toamoodle', '', $locallangroot);
        $CONTEXTS = array(CONTEXT_SYSTEM => $sitestr, CONTEXT_COURSECAT => $categorystr, CONTEXT_COURSE => $coursestr, CONTEXT_MODULE => $modulestr, CONTEXT_GROUP => $groupstr, CONTEXT_BLOCK => $blockstr, CONTEXT_USER => $userstr);
        
        $UIDS = array(); // used to collect assigned users ids
        $CIDS = array(); // used to collect assigned context ids

        $rs = get_recordset_sql($sql);
        $i = 0;
        if ($rs->RecordCount()) {
            while ($u = rs_fetch_next_record($rs)) {

                // capture assigned IDs
                $UIDS[$u->userid] = 1;
                $CIDS[$u->contextid] = 1;
                
                /// QUALIFIERS

                // current site identity
                $u->host = $SITE->shortname;
                $u->from = $this->parms->from;
                $u->to = $this->parms->to;
                $u->role = format_string($u->role);
                $u->context = $CONTEXTS[$u->contextlevel];

                // get user categorization
                toa_get_user_info($u);

                // anonymise user references in extraction
                // Do not anonymize academic assignation !! No user generated information
                /*
                if (!empty($this->masquerade)){
                    $u->username = md5($u->username.@$CFG->passwordsaltmain);
                } 
                */               

                // get instance categorisation : some instance may tell us we have to discard record
                if (!toa_get_assignation_instance_info($u)) continue;

                $u->section = get_string('realassigns', 'toamoodle', '', $locallangroot);

                /// INDICATORS
                
                // last cleanup of the record
                unset($u->userid);
                unset($u->contextid);
                unset($u->instanceid);
                unset($u->path);
                
                if ($testmode == 'test'){
                    echo mb_convert_encoding(htmlentities(recordtoxml($u, $i, 'roleassignrecord', ''), ENT_QUOTES, 'UTF-8'), $this->outputencoding, 'UTF-8');
                } else {
                    echo mb_convert_encoding(recordtoxml($u, $i, 'roleassignrecord', ''), $this->outputencoding, 'UTF-8');
                }
                $i++;
            }

            // don't forget last record !!
            if ($i > 0){
                if ($testmode == 'test'){
                    echo mb_convert_encoding(htmlentities(recordtoxml($u, $i, 'roleassignrecord', ''), ENT_QUOTES, 'UTF-8'), $this->outputencoding, 'UTF-8');
                } else {
                    echo mb_convert_encoding(recordtoxml($u, $i, 'roleassignrecord', ''), $this->outputencoding, 'UTF-8');
                }
            }
        }
        rs_close($rs);   
        
        // fetching orphan users

        $uidlist = implode("','", array_keys($UIDS));

        $sql = "
            SELECT
                u.id as userid,
                u.username,
                mh.wwwroot as userhost,
                u.deleted
            FROM
                {$CFG->prefix}user u,
                {$CFG->prefix}mnet_host mh
            WHERE
                u.mnethostid = mh.id AND
                u.id NOT IN ('$uidlist') AND
                u.confirmed = 1 AND
                u.deleted = 0 AND
                u.firstaccess <= {$this->parms->to} 
        ";
        $rs = get_recordset_sql($sql);
        if ($rs->RecordCount()) {
            while ($u = rs_fetch_next_record($rs)) {

                // anonymise user references in extraction*
                /*
                if (!empty($this->masquerade)){
                    $u->username = md5($u->username.@$CFG->passwordsaltmain);
                }
                */

                $u->host = $SITE->shortname;
                $u->from = $this->parms->from;
                $u->to = $this->parms->to;
                $u->context = get_string('nc', 'toamoodle', '', $locallangroot);
                $u->contextlevel = -1;
                $u->instance = get_string('nc', 'toamoodle', '', $locallangroot);
                $u->role = get_string('nc', 'toamoodle', '', $locallangroot);
                $u->section = get_string('orphans', 'toamoodle', '', $locallangroot);
                $u->object = get_string('nc', 'toamoodle', '', $locallangroot);

                toa_get_user_info($u);

                // anonymise user references in extraction
                /*
                if (!empty($this->masquerade)){
                    $u->username = md5($u->username.@$CFG->passwordsaltmain);
                }
                */
                
                // last cleanup
                unset($u->userid);

                if ($testmode == 'test'){
                    echo mb_convert_encoding(htmlentities(recordtoxml($u, $i, 'roleassignrecord', ''), ENT_QUOTES, 'UTF-8'), $this->outputencoding, 'UTF-8');
                } else {
                    echo mb_convert_encoding(recordtoxml($u, $i, 'roleassignrecord', ''), $this->outputencoding, 'UTF-8');
                }
                $i++;
            }

            // don't forget last record !!
            if ($i > 0){
                if ($testmode == 'test'){
                    echo mb_convert_encoding(htmlentities(recordtoxml($u, $i, 'roleassignrecord', ''), ENT_QUOTES, 'UTF-8'), $this->outputencoding, 'UTF-8');
                } else {
                    echo mb_convert_encoding(recordtoxml($u, $i, 'roleassignrecord', ''), $this->outputencoding, 'UTF-8');
                }
            }
        }
    
        // fetching orphan contexts

        $cidlist = implode("','", array_keys($CIDS));

        $sql = "
            SELECT
                co.contextlevel,
                co.instanceid,
                co.path
            FROM
                {$CFG->prefix}context co
            WHERE
                co.id NOT IN ('$cidlist')
        ";
        $rs = get_recordset_sql($sql);
        if ($rs->RecordCount()) {
            while ($c = rs_fetch_next_record($rs)) {

                $c->host = $SITE->shortname;
                $c->from = $this->parms->from;
                $c->to = $this->parms->to;
                $c->username = get_string('nc', 'toamoodle', '', $locallangroot);
                $c->section = get_string('unusedcontexts', 'toamoodle', '', $locallangroot);
                $c->role = get_string('nc', 'toamoodle', '', $locallangroot);
                $c->context = $CONTEXTS[$c->contextlevel];

                // get instance categorisation : some instancemay tell us we have to discard record
                if (!toa_get_assignation_instance_info($c)) continue;

                // get fake records as there is no user.
                toa_get_user_info($c);

                // cleanup record
                unset($c->contextid);
                unset($c->instanceid);
                unset($c->path);
                $c->contextlevel = 0; // tell it is not a real assign

                // generate record
                if ($testmode == 'test'){
                    echo mb_convert_encoding(htmlentities(recordtoxml($c, $i, 'roleassignrecord', ''), ENT_QUOTES, 'UTF-8'), $this->outputencoding, 'UTF-8');
                } else {
                    echo mb_convert_encoding(recordtoxml($c, $i, 'roleassignrecord', ''), $this->outputencoding, 'UTF-8');
                }
                $i++;
            }

            // don't forget last record !!
            if ($i > 0){
                if ($testmode == 'test'){
                    echo mb_convert_encoding(htmlentities(recordtoxml($u, $i, 'roleassignrecord', ''), ENT_QUOTES, 'UTF-8'), $this->outputencoding, 'UTF-8');
                } else {
                    echo mb_convert_encoding(recordtoxml($u, $i, 'roleassignrecord', ''), $this->outputencoding, 'UTF-8');
                }
            }
        }

        if ($testmode == 'test'){
            echo htmlentities("\n</roleassignrecords>");
        } else {
            echo "\n</roleassignrecords>";
        }

        flush();

        // prepare the "till when" temporary marker
        if (!empty($u->time)){
            $this->lastextract = $u->time;
        } else { // empty extraction
            $this->lastextract = time();
        }
        $this->save_config();
    }
    
    /**
    * get eventless information about existing documents from the global search engine.
    * This gets all documents present in search database index and calculates some
    * indicators on each through log exploration : 
    * - how many reads in the period
    * - how many changes in the period
    * - the size of the document if available
    * the reference time date of the record is the sampling time.
    * We may just know if the document has been updated in the period, 
    *
    */
    function extract_documents(&$output, $testmode = false){
        global $CFG, $SITE;

        include $CFG->dirroot.'/search/lib.php';
        ini_set('include_path', $CFG->dirroot.DIRECTORY_SEPARATOR.'search'.PATH_SEPARATOR.ini_get('include_path'));
        require_once($CFG->dirroot.'/search/Zend/Search/Lucene.php');

        $locallangroot = $CFG->dirroot.'/admin/report/etl/plugins/toamoodle/lang/';

        /// set extraction boundaries
        $toclause = '';
        if (!empty($this->parms->to)){
            $toclause = " AND docdate <= {$this->parms->to} ";
        }

        $fromclause = '';
        if (!empty($this->parms->from)){
            $fromclause = " AND docdate > {$this->parms->from} ";
        }
           
        /// get standard log

        $testclause = '';
        if ($testmode == 'test'){
            $testclause = ' LIMIT 0,30 ';
        }        
        
        $sql = "
            SELECT 
                docid,
                doctype,
                itemtype,
                courseid,
                title,
                docdate as time
            FROM
                {$CFG->prefix}".SEARCH_DATABASE_TABLE."
            WHERE
                doctype NOT LIKE '%label'
            $testclause
        ";

    /// put some cache structures

    $COURSECACHE = array();
        
    /// start producing

        if ($testmode == 'test'){
            echo htmlentities("<documentrecords>\n", ENT_QUOTES, 'UTF-8');
        } else {
            echo "<?xml version=\"1.0\"  encoding=\"{$this->outputencoding}\" ?>\n<documentrecords>\n";
        }

        $rs = get_recordset_sql($sql);
        if ($rs->RecordCount()) {
            $i = 0;
            while ($u = rs_fetch_next_record($rs)) {
                
            // QUALIFIERS

                /// current site
                $u->host = $SITE->shortname;
                $u->from = $this->parms->from;
                $u->to = $this->parms->to;

                // get module name.
                // WE NEED CMID FROM SOMEWHERE... CHECK !!
                $searchables = search_collect_searchables(false, false);
                $searchable_instance = $searchables[$u->doctype];
                if ($searchable_instance->location == 'internal'){
                    include_once "{$CFG->dirroot}/search/documents/{$u->doctype}_document.php";
                } else {
                    include_once "{$CFG->dirroot}/{$searchable_instance->location}/{$u->doctype}/search_document.php";
                }

                $document_function = "{$u->doctype}_search_get_objectinfo";
                
                if (function_exists($document_function)){
                    $moduleinfo = $document_function($u->itemtype, $u->docid);
                }
                
                // avoid polluting records with missing implementations
                if (empty($moduleinfo)){
                    if ($testmode == 'test') echo "skipping $u->itemtype, $u->docid <br/>";
                    continue;
                }

                // get course information                
                if ($u->courseid){                    
                    if (!array_key_exists($u->courseid, $COURSECACHE)){
                        if (!$course = get_record('course', 'id', $u->courseid)){
                            $course->idnumber = '---';
                            $course->shortname = get_string('nc', 'toamoodle', '', $locallangroot);
                        }
                        $COURSECACHE[$u->courseid] = $course;
                    } else {
                        $course = $COURSECACHE[$u->courseid];
                    }
                    $u->idnumber = ($course->idnumber == '') ? '---' : $course->idnumber;
                    $u->shortname = $course->shortname;
                    $u->course = $u->idnumber.' :: '.$u->shortname;
                    $u->visible = $course->visible;
                } else {
                    $u->course = get_string('nc', 'toamoodle', '', $locallangroot);
                    $u->visible = true;
                }
                                
                $u->instance = $u->docid.' :: '.format_string($u->title);

                toa_get_upper_categories($u, $course);

                // PATCH : Pairformance additions to the analysis model
                if (function_exists('tao_is_learning_format') && tao_is_learning_format($course)){
                    toa_learning_get_classifiers($u);
                } else {
                    $u->classifier1 = get_string('nc', 'toamoodle', '', $locallangroot);
                    $u->classifier2 = get_string('nc', 'toamoodle', '', $locallangroot);
                    $u->classifier3 = get_string('nc', 'toamoodle', '', $locallangroot);
                }
                // /PATCH
                
                toa_reshape_visible($u);
                
                // get media and document tech type
                $u->mediatype = $moduleinfo->mediatype;
                $u->contenttype = $moduleinfo->contenttype;

           // INDICATORS

                $itemid = $moduleinfo->instance->id;
                
                // search count view logs.
                $fetchbacksql = "
                    SELECT
                        COUNT(*)
                    FROM
                        {$CFG->prefix}log
                    WHERE
                        `time` > {$u->from} AND
                        `time` < {$u->to} AND
                        module = '{$u->doctype}' AND
                        info = {$itemid} AND
                        action LIKE '%view%'
                ";
                
                $u->viewhits = count_records_sql($fetchbacksql);

                // search count write logs.
                $fetchbacksql = "
                    SELECT
                        COUNT(*)
                    FROM
                        {$CFG->prefix}log
                    WHERE
                        `time` > {$u->from} AND
                        `time` < {$u->to} AND
                        module = '{$u->doctype}' AND
                        info = {$itemid} AND
                        (action LIKE 'add' OR
                        action LIKE 'doadd%' OR
                        action LIKE '%update%' OR
                        action LIKE '%write%')
                ";

                $u->writehits = count_records_sql($fetchbacksql);

                // search count view logs.
                $fetchbacksql = "
                    SELECT
                        COUNT(*)
                    FROM
                        {$CFG->prefix}log
                    WHERE
                        `time` > {$u->from} AND
                        `time` < {$u->to} AND
                        module = '{$u->doctype}' AND
                        info = {$itemid}
                ";
                
                $u->totalhits = count_records_sql($fetchbacksql);
                
                // final cleanup
                unset($u->idnumber);
                unset($u->shortname);
                unset($u->docid);
                unset($u->courseid);
                unset($u->title);
                
                if ($testmode == 'test'){
                    echo mb_convert_encoding(htmlentities(recordtoxml($u, $i, 'documentrecord', ''), ENT_QUOTES, 'UTF-8'), $this->outputencoding, 'UTF-8');
                } else {
                    echo mb_convert_encoding(recordtoxml($u, $i, 'documentrecord', ''), $this->outputencoding, 'UTF-8');
                }
                $i++;
            }

            // don't forget last record !!
            if ($i > 0){
                if ($testmode == 'test'){
                    echo mb_convert_encoding(htmlentities(recordtoxml($u, $i, 'documentrecord', ''), ENT_QUOTES, 'UTF-8'), $this->outputencoding, 'UTF-8');
                } else {
                    echo mb_convert_encoding(recordtoxml($u, $i, 'documentrecord', ''), $this->outputencoding, 'UTF-8');
                }
            }
        }
        rs_close($rs);        

        if ($testmode == 'test'){
            echo htmlentities("\n</documentrecords>");
        } else {
            echo "\n</documentrecords>";
        }

        flush();

        // prepare the "till when" temporary marker
        if (!empty($u->time)){
            $this->lastextract = $u->time;
        } else { // empty extraction
            $this->lastextract = time();
        }
        $this->save_config();
    }

    /**
    * get eventfull information about messages exchanged between users.
    * standard construction of the fact record is : 
    * 
    * indicators : 
    * $u->count
    * $u->readdelay
    * $u->answerdelay
    *
    * Axis data :
    * $u->username_from (may be masked)
    * $u->username_to
    * $u->roles_from
    * $u->roles_to
    * $u->allroles_from (may be combined to roles)
    * $u->allroles_to (may be combined to roles)
    * $u->department_from
    * $u->department_to 
    * $u->institution_from
    * $u->institution_to 
    * $u->city_from
    * $u->city_to 
    * $u->userhost_from
    * $u->userhost_to
    * $u->section
    * $u->time
    * $u->section (messagetype)
    * $u->instance
    * $u->course
    *
    */
    function extract_communications(&$output, $testmode = false){
        global $CFG, $SITE;

        /// General setup 

        $locallangroot = $CFG->dirroot.'/admin/report/etl/plugins/toamoodle/lang/';

        if ($testmode == 'test'){            
        } else {
            echo "<?xml version=\"1.0\"  encoding=\"{$this->outputencoding}\" ?>\n<communicationrecords>\n";
        }

        $testclause = '';
        if ($testmode == 'test'){
            $testclause = ' LIMIT 0,30 ';
        }        
                   
        /// get communications in forums

        // set extraction boundaries
        $toclause = '';
        if (!empty($this->parms->to)){
            $toclause = " AND fp.created <= {$this->parms->to} ";
        }

        $fromclause = '';
        if (!empty($this->parms->from)){
            $fromclause = " AND fp.created > {$this->parms->from} ";
        }

        $sql = "
            SELECT 
                fp.id,
                fp.parent,
                fp.userid,
                fp.created as time,
                fd.course as courseid,
                f.id as forum,
                f.name,
                u.id as userid_from,
                u.username as username_from,
                mh.id as userhostid_from,
                mh.name as userhost_from
            FROM
                {$CFG->prefix}forum_posts fp,
                {$CFG->prefix}forum_discussions fd,
                {$CFG->prefix}forum f,
                {$CFG->prefix}user u,
                {$CFG->prefix}mnet_host mh 
            WHERE
                fp.userid = u.id AND
                fp.discussion = fd.id AND
                fd.forum = f.id AND
                u.mnethostid = mh.id
                $fromclause
                $toclause
                $testclause
        ";
        
        $rs = get_recordset_sql($sql);
        if ($rs->RecordCount()) {
            
            $FORUM_SUBSCRIBERS = array(); // a cache for subscriber lists

            $i = 0;
            while ($u = rs_fetch_next_record($rs)) {
                
                $u->host = $SITE->shortname;
                $u->from = $this->parms->from;
                $u->to = $this->parms->to;

                if (empty($u->parent)){
                    // if no parent, the message is considered emitted to the forum community                    

                    if (!array_key_exists($u->forum, $FORUM_SUBSCRIBERS)){
                        $sql = "
                            SELECT 
                                u.id as userid_to,
                                u.username as username_to,
                                mh.id as userhostid_to,
                                mh.name as userhost_to
                            FROM
                                {$CFG->prefix}forum_subscriptions fs,
                                {$CFG->prefix}user u,
                                 {$CFG->prefix}mnet_host mh
                           WHERE
                                fs.userid = u.id AND
                                u.mnethostid = mh.id AND
                                u.id != {$u->userid}
                        "; 
                        $subscribers = get_records_sql($sql);
                        $FORUM_SUBSCRIBERS[$u->forum] = $subscribers;
                    } else {
                        // do not fetch them twice !!
                        $subscribers = $FORUM_SUBSCRIBERS[$u->forum];
                    }
               } else {
                    // if we have a parent, the message is considered as being sent from sender to parent owner
                    $to = get_field('forum_posts', 'userid', 'id', $u->parent);
                    $touser = get_record('user', 'id', $to, '', 'id,username,mnethostid');
                    if (!empty($this->masquerade)){
                        $subscribers[$touser->id]->username_to = md5($touser->username.@$CFG->passwordsaltmain);
                    } else {
                        $subscribers[$touser->id]->username_to = $touser->username;
                    }
                    $subscribers[$touser->id]->userid_to = $touser->id;
                    $subscribers[$touser->id]->userhostid_to = $touser->mnethostid;
                    $subscribers[$touser->id]->userhost_to = format_string(get_field('mnet_host', 'name', 'id', $touser->mnethostid));
               }

                $course->id = $u->courseid;
                $module = get_record('modules', 'name', 'forum');
                $cm = get_record('course_modules', 'module', $module->id, 'instance', $u->forum);
                $context = get_context_instance(CONTEXT_MODULE, $cm->id);
                toa_get_user_roles($u, $course, $u->userid_from, 'from', $context);
                
                $u->section = get_string('modulenameplural', 'forum');
                $idnumber = (empty($cm->idnumber)) ? '---' : $cm->idnumber ;
                $u->instance = "[{$u->forum}] :: ".$idnumber. ' :: '.format_string($u->name);
 
                toa_reshape_user_origin($u, 'from');
                toa_get_user_info($u, $u->userid_from, 'from');

                // masquerade if required
                if (!empty($this->masquerade)){
                    $u->username_from = md5($u->username_from.@$CFG->passwordsaltmain);
                }               

                // pre loop cleanup
                unset($u->userid_from);
                unset($u->userhostid_from);
                unset($u->courseid);
                unset($u->forum);
                unset($u->parent);
                unset($u->name);
                unset($u->userid);

                // generate as many records as one to one messages
                foreach($subscribers as $subscriber){
                    
                    $r = clone($u);
                    
                    // clean records before production
                    toa_get_user_roles($r, $course, $subscriber->userid_to, 'to', $context);

                    $r->username_to = $subscriber->username_to;    
                    $r->userhost_to = $subscriber->userhost_to;    
                    $r->userhostid_to = $subscriber->userhostid_to;    

                    toa_reshape_user_origin($r, 'to');
                    toa_get_user_info($r, $subscriber->userid_to, 'to');

                    // masquerade if required
                    if (!empty($this->masquerade)){
                        $r->username_to = md5($r->username_to.@$CFG->passwordsaltmain);
                    }
                    
                    // inloop cleanup 
                    unset($r->userhostid_to);

                    // produce communication records
                    if ($testmode == 'test'){
                        echo mb_convert_encoding(htmlentities(recordtoxml($r, $i, 'communicationrecord', ''), ENT_QUOTES, 'UTF-8'), $this->outputencoding, 'UTF-8');
                    } else {
                        echo mb_convert_encoding(recordtoxml($r, $i, 'communicationrecord', ''), $this->outputencoding, 'UTF-8');
                    }
                    $i++;
                }
                if ($i > 0){
                    if ($testmode == 'test'){
                        echo mb_convert_encoding(htmlentities(recordtoxml($r, $i, 'communicationrecord', ''), ENT_QUOTES, 'UTF-8'), $this->outputencoding, 'UTF-8');
                    } else {
                        echo mb_convert_encoding(recordtoxml($r, $i, 'communicationrecord', ''), $this->outputencoding, 'UTF-8');
                    }
                }
            }
        }
                
        /// get communications in messaging
        
        // set extraction boundaries
        $toclause = '';
        if (!empty($this->parms->to)){
            $toclause = " AND m.timecreated <= {$this->parms->to} ";
        }

        $fromclause = '';
        if (!empty($this->parms->from)){
            $fromclause = " AND m.timecreated > {$this->parms->from} ";
        }

        $sql = "
            SELECT
                m.timecreated as time,
                m.timeread - m.timecreated as readdelay,
                m.useridfrom as userid_from,
                m.useridto as userid_to,
                uf.username,
                ut.username,
                mhf.id as userhostid_from,
                mhf.name as userhost_from,
                mht.name as userhost_to,
                mht.id as userhostid_to
            FROM
                {$CFG->prefix}message_read m,
                {$CFG->prefix}user uf,
                {$CFG->prefix}user ut,
                {$CFG->prefix}mnet_host mhf, 
                 {$CFG->prefix}mnet_host mht 
           WHERE
                m.useridfrom = uf.id AND
                m.useridto = ut.id AND
                uf.mnethostid = mhf.id AND
                ut.mnethostid = mht.id
                $fromclause
                $toclause
                $testclause
       UNION
            SELECT 
                m.timecreated as time,
                -1 as readdelay,
                m.useridfrom as userid_from,
                m.useridto as userid_to,
                uf.username,
                ut.username,
                mhf.name as userhost_from,
                mhf.id as userhostid_from,
                mht.name as userhost_to,
                mht.id as userhostid_to
            FROM
                {$CFG->prefix}message m,
                {$CFG->prefix}user uf,
                {$CFG->prefix}user ut,
                {$CFG->prefix}mnet_host mhf, 
                 {$CFG->prefix}mnet_host mht 
           WHERE
                m.useridfrom = uf.id AND
                m.useridto = ut.id AND
                uf.mnethostid = mhf.id AND
                ut.mnethostid = mht.id
                $fromclause
                $toclause
                $testclause
        ";

        $rs = get_recordset_sql($sql);
        if ($rs->RecordCount()) {
            
            // $i = 0; DO NOT : continue numbering
            while ($u = rs_fetch_next_record($rs)) {
                
                $u->host = $SITE->shortname;
                $u->from = $this->parms->from;
                $u->to = $this->parms->to;

                $context = get_context_instance(CONTEXT_SYSTEM);
                toa_get_user_roles($u, $course, $u->userid_from, 'from', $context);
                toa_get_user_roles($u, $course, $u->userid_to, 'to', $context);

                toa_reshape_user_origin($u, 'from');
                toa_reshape_user_origin($u, 'to');

                toa_get_user_info($u, $u->userid_from, 'from');
                toa_get_user_info($u, $u->userid_to, 'to');

                // masquerade if required
                if (!empty($this->masquerade)){
                    $u->username_from = md5($u->username_from.@$CFG->passwordsaltmain);
                    $u->username_to = md5($u->username_to.@$CFG->passwordsaltmain);
                }
                                
                $u->section = get_string('messaging', 'message');
                $u->instance = get_string('nc', 'toamoodle', '', $locallangroot);

                // pre loop cleanup
                unset($u->userid_from);
                unset($u->userid_to);
                unset($u->userhostid_from);
                unset($u->userhostid_to);
                unset($u->courseid);
                $u->userhost_from = format_string($u->userhost_from);
                $u->userhost_to = format_string($u->userhost_to);

                // produce communication records
                if ($testmode == 'test'){
                    echo mb_convert_encoding(htmlentities(recordtoxml($u, $i, 'communicationrecord', ''), ENT_QUOTES, 'UTF-8'), $this->outputencoding, 'UTF-8');
                } else {
                    echo mb_convert_encoding(recordtoxml($u, $i, 'communicationrecord', ''), $this->outputencoding, 'UTF-8');
                }
                $i++;
            }
            // don't forget last record !!
            if ($i > 0){
                if ($testmode == 'test'){
                    echo mb_convert_encoding(htmlentities(recordtoxml($u, $i, 'communicationrecord', ''), ENT_QUOTES, 'UTF-8'), $this->outputencoding, 'UTF-8');
                } else {
                    echo mb_convert_encoding(recordtoxml($u, $i, 'communicationrecord', ''), $this->outputencoding, 'UTF-8');
                }
            }
        }        

        /// get communications in chatrooms
        
        // set extraction boundaries
        $toclause = '';
        if (!empty($this->parms->to)){
            $toclause = " AND cm.timestamp <= {$this->parms->to} ";
        }

        $fromclause = '';
        if (!empty($this->parms->from)){
            $fromclause = " AND cm.timestamp > {$this->parms->from} ";
        }

        $sql = "
            SELECT 
                c.id as chatid,
                c.name,
                c.course,
                cm.timestamp as time,
                cm.system,
                cm.message,
                cm.chatid as chatroom,
                cm.userid as userid_from,
                u.username as username_from,
                mh.id as userhostid_from,
                mh.name as userhost_from
            FROM
                {$CFG->prefix}chat c,
                {$CFG->prefix}chat_messages cm,
                {$CFG->prefix}user u,
                {$CFG->prefix}mnet_host mh 
            WHERE
                cm.userid = u.id AND
                cm.chatid = c.id AND
                u.mnethostid = mh.id
                $fromclause
                $toclause
            ORDER BY
                timestamp
            $testclause
        ";
        
        // setup recipients
        $recipients = array();
        
        $rs = get_recordset_sql($sql);
        if ($rs->RecordCount()) {
            
            // $i = 0; DO NOT : continue numbering
            while ($u = rs_fetch_next_record($rs)) {

                // if system message, update
                if ($u->system){
                    if ($u->message == 'enter'){
                        $recipients[$u->chatid][$u->userid_from] = 1;
                    } elseif ($u->message == 'exit'){
                        unset($recipients[$u->chatid][$u->userid_from]);
                    }
                    continue;
                }
                
                // backtrack last presents
                // we search back in chat trace last presents before we are extracting information
                if (!array_key_exists($u->chatid, $recipients)){
                    $recipients[$u->chatid] == array();
                    
                    $sql = "
                        SELECT 
                            userid,
                            message
                        FROM
                            {$CFG->prefix}chat_messages
                        WHERE
                            chatid = $u->chatid AND
                            system = 1 AND
                            timestamp < $u->timestamp
                        ORDER BY
                            timestamp DESC
                    ";
                    
                    $exited = array();
                    
                    if ($retroscans = get_records_sql($sql)){                    
                        foreach($retroscans as $retroscan){
                            // if not checked as existed or present
                            if (!array_key_exists($retroscan->userid, $exited) || !array_key_exists($retroscan->userid, $recipients[$u->chatid])){
                                if ($retroscan->message == 'enter'){
                                    $recipients[$u->chatid][$retroscan->userid] = 1;
                                } elseif ($retroscan->message == 'exit') {
                                    $exited[$retroscan->userid] = 1;
                                }
                            }
                        }
                    }
                }
                
                //make complete record
                $u->host = $SITE->shortname;
                $u->from = $this->parms->from;
                $u->to = $this->parms->to;
                $u->readdelay = 0;

                $course->id = $u->course;

                $module = get_record('modules', 'name', 'chat');
                $cm = get_record('course_modules', 'module', $module->id, 'instance', $u->chatid);
                $context = get_context_instance(CONTEXT_MODULE, $cm->id);
                toa_get_user_roles($u, $course, $u->userid_from, 'from', $context);
                toa_reshape_user_origin($u, 'from');
                toa_get_user_info($u, $u->userid_from, 'from');
                
                // masquerade if required
                if (!empty($this->masquerade)){
                    $u->username_from = md5($u->username_from.@$CFG->passwordsaltmain);
                }
                
                $u->section = get_string('modulenameplural', 'chat');
                $idnumber = (empty($cm->idnumber)) ? '---' : $cm->idnumber ;
                $u->instance = "[{$u->chatid}] :: ".$idnumber.' :: '.format_string($u->name);

                // pre loop cleanup
                unset($u->course);
                unset($u->userhostid_from);
                unset($u->system);
                unset($u->message);
                unset($u->chatroom);
                unset($u->name);

                $u->userhost_from = format_string($u->userhost_from);
                
                // scan all chat users and make a communication entry for each one
                foreach(array_keys($recipients[$u->chatid]) as $recipient){

                    $r = clone($u);

                    if ($recipient == $r->userid_from) continue; // do not count self communication
                    
                    $r->userid_to = $recipient;
                    $r->username_to = get_field('user', 'username', 'id', $r->userid_to);
                    $r->userhostid_to = get_field('user', 'mnethostid', 'id', $r->userid_to);
                    $r->userhost_to = get_field('mnet_host', 'name', 'id', $r->userhostid_to);

                    toa_reshape_user_origin($r, 'to');
                    toa_get_user_info($r, $r->userid_to, 'to');
                    toa_get_user_roles($r, $course, $r->userid_to, 'to', $context);
    
                    if (!empty($this->masquerade)){
                        $r->username_to = md5($r->username_to.@$CFG->passwordsaltmain);
                    }
                    
                    // final cleanup 
                    unset($r->userhostid_to);
                    unset($r->userid_to);
                    unset($r->chatid);
                    unset($r->userid_from);
    
                    // produce communication records
                    if ($testmode == 'test'){
                        echo mb_convert_encoding(htmlentities(recordtoxml($r, $i, 'communicationrecord', ''), ENT_QUOTES, 'UTF-8'), $this->outputencoding, 'UTF-8');
                    } else {
                        echo mb_convert_encoding(recordtoxml($r, $i, 'communicationrecord', ''), $this->outputencoding, 'UTF-8');
                    }
                    $i++;
                }
                if ($i > 0){
                    if ($testmode == 'test'){
                        echo mb_convert_encoding(htmlentities(recordtoxml($r, $i, 'communicationrecord', ''), ENT_QUOTES, 'UTF-8'), $this->outputencoding, 'UTF-8');
                    } else {
                        echo mb_convert_encoding(recordtoxml($r, $i, 'communicationrecord', ''), $this->outputencoding, 'UTF-8');
                    }
                }
            }
        }

        /// get communications in user data        
        
        if ($testmode == 'test'){
            echo htmlentities("\n</communicationrecords>");
        } else {
            echo "\n</communicationrecords>";
        }
        
        flush();

        // prepare the "till when" temporary marker
        if (!empty($u->time)){
            $this->lastextract = $u->time;
        } else { // empty extraction
            $this->lastextract = time();
        }
        $this->save_config();
    }

    /**
    * get eventful information about grades accumulated by users.
    * the reference time date of the record is the grade attribution time.
    *
    */
    function extract_grades(&$output, $testmode = false){
        global $CFG, $SITE;        

        /// General setup 

        $locallangroot = $CFG->dirroot.'/admin/report/etl/plugins/toamoodle/lang/';

        if ($testmode == 'test'){            
        } else {
            echo "<?xml version=\"1.0\"  encoding=\"{$this->outputencoding}\" ?>\n<graderecords>\n";
        }

        $testclause = '';
        if ($testmode == 'test'){
            $testclause = ' LIMIT 0,30 ';
        }        
                   
        /// get communications in forums

        // set extraction boundaries
        $toclause = '';
        if (!empty($this->parms->to)){
            $toclause = " AND gg.timecreated <= {$this->parms->to} ";
        }

        $fromclause = '';
        if (!empty($this->parms->from)){
            $fromclause = " AND gg.timecreated > {$this->parms->from} ";
        }

        $sql = "
            SELECT 
                finalgrade,
                userid,
                courseid,
                itemtype,
                itemmodule,
                iteminstance,
                gg.locked,
                overridden                
            FROM
                {$CFG->prefix}grade_grades gg,
                {$CFG->prefix}grade_items gi
            WHERE
                gg.itemid = gi.id
                $toclause
                $fromclause
                $testclause
        ";

        $rs = get_recordset_sql($sql);
        if ($rs->RecordCount()) {
            
            // $i = 0; DO NOT : continue numbering
            $i = 0;
            while ($u = rs_fetch_next_record($rs)) {

                $u->host = $SITE->shortname;
                $u->from = $this->parms->from;
                $u->to = $this->parms->to;
                
                toa_get_user_info($u);

                $course = get_record('course', 'id', $u->courseid);
                toa_get_additional_course_info($u, $course);
                
                // cleanup record
                
                unset($u->itemtype);
                unset($u->userid);
                // produce communication records

                if ($testmode == 'test'){
                    echo mb_convert_encoding(htmlentities(recordtoxml($u, $i, 'graderecord', ''), ENT_QUOTES, 'UTF-8'), $this->outputencoding, 'UTF-8');
                } else {
                    echo mb_convert_encoding(recordtoxml($u, $i, 'graderecord', ''), $this->outputencoding, 'UTF-8');
                }
                $i++;
            }
            if ($i > 0){
                if ($testmode == 'test'){
                    echo mb_convert_encoding(htmlentities(recordtoxml($r, $i, 'graderecord', ''), ENT_QUOTES, 'UTF-8'), $this->outputencoding, 'UTF-8');
                } else {
                    echo mb_convert_encoding(recordtoxml($r, $i, 'graderecord', ''), $this->outputencoding, 'UTF-8');
                }
            }
        }
        
        if ($testmode == 'test'){
            echo htmlentities("\n</graderecords>");
        } else {
            echo "\n</graderecords>";
        }
        
        flush();

    }
    
    /**
    * a special pseudo-query for aknowledging the previous extraction
    *
    */
    function extract_acknowledge(&$output, $testmode = false){
        
        $changed = false;
        if ($this->lastextract){
            if (!empty($this->parms->ackquery)){
                $extractname = 'lastextract_'.$this->parms->ackquery;
                $this->$extractname = $this->lastextract;
            } else {
                etl_error("Not a valid taomoodle query to acknowledge");
                die;
            }
            $this->lastextract = 0;
            $changed = true;
        }
        $this->save_config();
        
        if ($testmode == 'test'){            
        } else {
            echo "<?xml version=\"1.0\"  encoding=\"{$this->outputencoding}\" ?>\n";
            echo "<acknowledge>\n";
            if ($changed){
                echo "\t<lastextract>{$this->lastextract}</lastextract>\n";
            } else { 
                echo "\t<nochange>{$this->lastextract}</nochange>\n";
            }
            echo "</acknowledge>\n";
        }        
            
            // TODO : organize purge of yet unnecessary logs
    }

    /**
    * provides an url to an access door that allows direc identification 
    * of a moodle user.
    * @uses $USER
    */
    function get_access_url(){
        global $USER;
        
        if (!isloggedin()) return $this->externalaccessurl;
        
        $url = $this->accessurl;
        
        $info = new StdClass;
        $info->date = time();
        $info->login = $USER->username;
        
        $ticket = toa_make_ticket($info, $this->publickey, $method='des');

        $url .= "?ssoticket=$ticket";
        
        return $url;
        
    }
}
/**
* plugin specific decoder. Uses TOA internally stored
* public key to get data from. 
*/
function toa_make_ticket($info, $pkey, $method='des'){
    
    // check if SSO ticket is not obsolete
    $info->date = time();
    $keyinfo = json_encode($info);
    
    // echo "$keyinfo";

    if ($method == 'rsa'){
        if(!openssl_private_encrypt($keyinfo, $encrypted, $pkey)){
            error("Failed making key");
        }
    } else {    
        // method is Triple-DES
        $sql = "
            SELECT
                HEX(AES_ENCRYPT('$keyinfo', '$pkey')) as result
        ";
        if($result = get_record_sql($sql)){
            $encrypted = $result->result;
        } else {
            $encrypted = 'encryption error';
        }
    }
    
    return $encrypted; 
}


/**
* the four upper categoies of a course
*/
function toa_get_upper_categories(&$u, &$course){
    global $CFG;
    static $COURSECATCACHE;

    $locallangroot = $CFG->dirroot.'/admin/report/etl/plugins/toamoodle/lang/';
    
    // out of course context    
    if (empty($course)){
        // complete to 4 categories
        for($i = 1; $i <= 4 ; $i++){
            $key = "category$i";
            $u->$key = get_string('nc', 'toamoodle', '', $locallangroot);
        }
        return;
    }

    if (is_null($COURSECATCACHE)) $COURSECATCACHE = array();
    
    if (!array_key_exists($course->id, $COURSECATCACHE)){
        if ($course->id == 1 || $course->category == 0){
            // site course is not in category organisation
            if ($course->id == SITEID){
                $COURSECATCACHE[1][] = get_string('sitecat', 'toamoodle', '', $locallangroot);
            } else {
                $COURSECATCACHE[1][] = get_string('coursecaterror', 'toamoodle', '', $locallangroot);
            }
        } else {
            $categories = array();
            $cat = get_record('course_categories', 'id', $course->category);
            $u->visible = $u->visible && $cat->visible;
            $categories[] = format_string($cat->name);
            while ($cat->parent != 0){
                $cat = get_record('course_categories', 'id', $cat->parent);
                $u->visible = $u->visible && $cat->visible;
                $categories[] = format_string($cat->name);
            }
            array_reverse($categories);
            $COURSECATCACHE[$course->id] = $categories;
        }
    }
    
    $i = 1;
    foreach($COURSECATCACHE[$course->id] as $category){
        $key = "category$i";
        $u->$key = $category;
        $i++;
    }
    // complete to 4 categories
    for(; $i <= 4 ; $i++){
        $key = "category$i";
        $u->$key = get_string('nc', 'toamoodle', '', $locallangroot);
    }
}

/**
* get roles directly assigned or inherited
* needs $r->userid as a default
* @param reference &$r
* @param reference &$course
* @param int $userid
*/
function toa_get_user_roles(&$u, &$course, $userid = null, $extension = '', $context = null){
    global $CFG;
    static $USERROLESCACHE;

    $locallangroot = $CFG->dirroot.'/admin/report/etl/plugins/toamoodle/lang/';

    if (is_null($USERROLESCACHE)) $USERROLESCACHE = array();

    // wraps the default userid from extracted record.
    if (empty($userid)) $userid = @$u->userid;

    $hasdirectrolefield = (empty($extension)) ? 'hasdirectroleincourse' : 'hasdirectroleincourse_'.$extension ;
    $allrolesfield = (empty($extension)) ? 'allroles' : 'allroles_'.$extension ;
    
    // in case of a fake user
    if (empty($userid)){
        $u->$allrolesfield = get_string('nc', 'toamoodle', '', $locallangroot);
        $u->$hasdirectrolefield = get_string('nc', 'toamoodle', '', $locallangroot);
        return;
    }
    
    if (!$context){
        if (!empty($course) && $course->id > 0){
            $context = get_context_instance(CONTEXT_COURSE, $course->id);
        } else {
            $context = get_context_instance(CONTEXT_SYSTEM);
        }
    }

    // we want do determine direct assignation in the surrounding course
    
    if (!array_key_exists("{$context->id}-{$userid}", $USERROLESCACHE)){
        if ($context->contextlevel == CONTEXT_COURSE){
            $roles = count_records_select('role_assignments', " userid = $userid AND contextid = $context->id AND (timestart = 0 OR timestart < $u->to) AND (timeend = 0 OR timeend > $u->from) "); 
        } elseif ($context->contextlevel == CONTEXT_MODULE || $context->contextlevel == CONTEXT_BLOCK){
            $pathparts = explode('/', $context->path);
            $coursecontextid = $pathparts[count($pathparts) - 2]; // get id of course context
            $roles = count_records_select('role_assignments', " userid = $userid AND contextid = $coursecontextid AND (timestart = 0 OR timestart < $u->to) AND (timeend = 0 OR timeend > $u->from) "); 
        } else {
            $roles = null;
        }
        if (empty($roles)){
            $USERROLESCACHE["{$context->id}-{$userid}"] = get_string('no');
        } else {
            $USERROLESCACHE["{$context->id}-{$userid}"] = get_string('yes');
        }
    }
    $u->$hasdirectrolefield = $USERROLESCACHE["{$context->id}-{$userid}"];

    if (!array_key_exists("{$context->id}-{$userid}-all", $USERROLESCACHE)){
        $u->$allrolesfield = get_string('nc', 'toamoodle', '', $locallangroot);
        $roles = get_user_roles($context, $userid, true, 'r.name ASC'); 
        $rolenames = array();
        foreach($roles as $arole){
            if (!in_array($arole->name, $rolenames))
                $rolenames[] = format_string($arole->name);
        }

        if (!empty($rolenames)){
            $u->$allrolesfield = implode(',', $rolenames);
        }
        
        $USERROLESCACHE["{$context->id}-{$userid}-all"] = $u->$allrolesfield;
    } else {
        $u->$allrolesfield = $USERROLESCACHE["{$context->id}-{$userid}-all"];
    }

}

/**
*
* @param reference &$u the current extraction record
* @return boolean. If false the record must be jumped over.
*/
function toa_get_assignation_instance_info(&$u){
    global $CFG, $SITE;
    
    $locallangroot = $CFG->dirroot.'/admin/report/etl/plugins/toamoodle/lang/';
    
    // Context instance expliciter with object name
    switch($u->contextlevel){
        case CONTEXT_SYSTEM: // SITE
            $u->instance = format_string($SITE->fullname);
            $u->object = get_string('site');
            break;
        case CONTEXT_COURSECAT: // CATEGORY
            $u->object = get_string('categories');
            if ($cat = get_record('course_categories', 'id', $u->instanceid)){
                $u->instance = '['.$u->instanceid.'] :: --- :: '.format_string($cat->name);
            } else {
                $u->instance = "Cat in error:{$u->instanceid}";
                $u->section = get_string('caterror', 'toamoodle', '', $locallangroot);
            }
            break;
        case CONTEXT_COURSE: // COURSES
            if ($course = get_record('course', 'id', $u->instanceid)){
                $courseformatname = get_string("format{$course->format}","format_{$course->format}");
                if($courseformatname == "[[format{$course->format}]]") {
                    $courseformatname = get_string("format{$course->format}");
                }
                $u->object =  $course->format . ' :: ' . $courseformatname;
                $idnumber = (empty($course->idnumber)) ? '---' : $course->idnumber ;
                $u->instance = "[{$course->id}] :: ".$idnumber.' :: '.format_string($course->fullname);
            } else {
                $u->object = get_string('nc', 'toamoodle', '', $locallangroot);
                $u->instance = "Course in Error:$u->instanceid";
                $u->section = get_string('courseerror', 'toamoodle', '', $locallangroot);
            }
            break;
        case CONTEXT_MODULE: // MODULES
            if ($cm = get_record('course_modules', 'id', $u->instanceid)){
                $modname = get_field('modules', 'name', 'id', $cm->module);
                $u->object = get_string('modulenameplural', $modname);
                
                // discard all kind of labels
                if (preg_match('/label$/', $modname)) return false;
                
                $instancename = get_field($modname, 'name', 'id', $cmid->instance);
                $idnumber = (empty($cm->idnumber)) ? '---' : $cm->idnumber ;
                $u->instance = "[{$u->instanceid}] :: ".$idnumber.' :: '.format_string($instancename);
            } else {
                $u->object = 'Module in Error';
                $u->instance = "Module in Error:$u->instanceid";
                $u->section = get_string('moderror', 'toamoodle', '', $locallangroot);
            }
            break;
        case CONTEXT_BLOCK: // BLOCKS
            if ($blockinstance = get_record('block_instance', 'id', $u->instanceid)){
                $blockname = get_field('block', 'name', 'id', $blockinstance->blockid);
                $u->instance = $u->instanceid.' :: ['.$blockinstance->pageid.','.$blockinstance->position.','.$blockinstance->weight.'] :: '.format_string($blockname);
                $u->object = get_string('blockname', 'block_'.$blockname);
                // this is a failover technique for getting blockname
                if (preg_match('/\[\[/', $u->object)) $u->object = get_string('blocktitle', 'block_'.$blockname);
                if (preg_match('/\[\[/', $u->object)) $u->object = $blockname;
            } else {
                $u->object = 'Block in error';
                $u->instance = "Block in Error:$u->instanceid";
                $u->section = get_string('blockerror', 'toamoodle', '', $locallangroot);
            }
            break;
    }

    return true;
}

/**
* Get the user axis
* @param reference &$u;
* @param int $userid if not null, tell for which user we want the data, elsewhere we get userid in &$u
* @param string $extension adds a suffix to the fieldnames related to a user axis (allows making alternate axis)
*/
function toa_get_user_info(&$u, $userid = null, $extension = ''){
    global $CFG;
    
    $locallangroot = $CFG->dirroot.'/admin/report/etl/plugins/toamoodle/lang/';

    if (empty($userid)) $userid = @$u->userid;
    
    if (empty($userid)){
        $ncstr = get_string('nc', 'toamoodle', '', $locallangroot);
        
        $a = (empty($extension)) ? 'username' : 'username_'.$extension;    
        $u->$a = $ncstr;
        $a = (empty($extension)) ? 'city' : 'city_'.$extension;    
        $u->$a = $ncstr;
        $a = (empty($extension)) ? 'department' : 'department_'.$extension;    
        $u->$a = $ncstr;
        $a = (empty($extension)) ? 'institution' : 'institution_'.$extension;    
        $u->$a = $ncstr;
        $a = (empty($extension)) ? 'country' : 'country_'.$extension;    
        $u->$a = $ncstr;
        $a = (empty($extension)) ? 'lang' : 'lang_'.$extension;    
        $u->$a = $ncstr;
        return;
    }

    $user = get_record('user', 'id', $userid, '', '', '', '', 'username,country,city,institution,department,lang');

    $a = (empty($extension)) ? 'username' : 'username_'.$extension;    
    $u->$a = $user->username;

    $a = (empty($extension)) ? 'city' : 'city_'.$extension;    
    $u->$a = (!empty($user->city)) ? $user->city : get_string('nc', 'toamoodle', '', $locallangroot) ;

    $a = (empty($extension)) ? 'department' : 'department_'.$extension;    
    $u->$a = (!empty($user->department)) ? $user->department : get_string('nc', 'toamoodle', '', $locallangroot) ;

    $a = (empty($extension)) ? 'institution' : 'institution_'.$extension;    
    $u->$a = (!empty($user->institution)) ? $user->institution : get_string('nc', 'toamoodle', '', $locallangroot) ;

    $a = (empty($extension)) ? 'country' : 'country_'.$extension;    
    $u->$a = (!empty($user->country)) ? $user->country : get_string('nc', 'toamoodle', '', $locallangroot) ;

    $a = (empty($extension)) ? 'lang' : 'lang_'.$extension;    
    $u->$a = (!empty($user->lang)) ? $user->lang : get_string('nc', 'toamoodle', '', $locallangroot) ;
}

/**
* Formats the userhost information
*/
function toa_reshape_user_origin(&$u, $extension = ''){
    global $CFG;

    $locallangroot = $CFG->dirroot.'/admin/report/etl/plugins/toamoodle/lang/';
    
    $userhostidfield = (empty($extension)) ? 'userhostid' : 'userhostid_'.$extension ;
    $userhostfield = (empty($extension)) ? 'userhost' : 'userhost_'.$extension ;
    
    if ($u->$userhostidfield == 1){
        $u->$userhostfield = get_string('local', 'toamoodle', '', $locallangroot);
    } elseif ($u->$userhostidfield == 2){
        $u->$userhostfield = get_string('allhosts', 'toamoodle', '', $locallangroot);
    } else {
        $u->$userhostfield = format_string($u->$userhostfield);
    }
}

function toa_get_additional_course_info(&$u, &$course){
    global $CFG;

    $locallangroot = $CFG->dirroot.'/admin/report/etl/plugins/toamoodle/lang/';
    
    if (empty($course)){
        $u->enrol = get_string('nc', 'toamoodle', '', $locallangroot);
        $u->enrollable = get_string('nc', 'toamoodle', '', $locallangroot);
    } else {
        $u->enrol = ($course->enrol) ? get_string('enrolname', 'enrol_'.$course->enrol) : get_string('default');
        $u->enrollable = ($course->enrollable) ? get_string('yes') : get_string('no') ;
    }  
}

/**
*
*/
function toa_reshape_visible(&$u){
    global $CFG;

    $locallangroot = $CFG->dirroot.'/admin/report/etl/plugins/toamoodle/lang/';

    if (is_null($u->visible)){
        $u->visible = get_string('nc', 'toamoodle', '', $locallangroot);
    } else {
        $u->visible = ($u->visible) ? get_string('visible', 'toamoodle', '', $locallangroot) : get_string('nonvisible', 'toamoodle', '', $locallangroot) ;
    }
}


// PATCH : Pairformance additions
/**
* load classifier value domains
*
*/
function toa_learning_get_classifiers(&$u){
    global $CFG;

    $locallangroot = $CFG->dirroot.'/admin/report/etl/plugins/toamoodle/lang/';

    list($courseclassifiers1, $courseclassifiers2, $courseclassifiers3) = toa_learning_load_classifiers();

    // integrate classifiers
    if (array_key_exists($u->courseid, $courseclassifiers1)){
        $u->classifier1 = $courseclassifiers1[$u->courseid]->classifier;
    } else {
        $u->classifier1 = get_string('nc', 'toamoodle', '', $locallangroot);
    }
    

    // integrate classifiers
    if (array_key_exists($u->courseid, $courseclassifiers2)){
        $u->classifier2 = $courseclassifiers2[$u->courseid]->classifier;
    } else {
        $u->classifier2 = get_string('nc', 'toamoodle', '', $locallangroot);
    }

    // integrate classifiers
    if (array_key_exists($u->course, $courseclassifiers3)){
        $u->classifier3 = $courseclassifiers3[$u->courseid]->classifier;
    } else {
        $u->classifier3 = get_string('nc', 'toamoodle', '', $locallangroot);
    }

}

/**
* load classifier value domains, with some caching for optimization.
*
*/
function toa_learning_load_classifiers(){
    global $CFG;
    
    static $classifiers1, $classifiers2, $classifiers3;

    /// get preliminary classifications

    if (!isset($classifiers1)){    
        $sql = "
            SELECT 
                cc.course,
                GROUP_CONCAT(cv.code) as classifier
            FROM
                {$CFG->prefix}course_classification cc,
                {$CFG->prefix}classification_value cv
            WHERE
                cv.id = cc.value AND
                cv.type = 4
            GROUP BY
                cc.course
        ";
    
        $classifiers1 = get_records_sql($sql);
    }

    $return[] = $classifiers1;

    if (!isset($classifiers2)){
        $sql = "
            SELECT 
                cc.course,
                GROUP_CONCAT(cv.code) as classifier
            FROM
                {$CFG->prefix}course_classification cc,
                {$CFG->prefix}classification_value cv
            WHERE
                cv.id = cc.value AND
                cv.type = 5
            GROUP BY
                cc.course
        ";
    
        $classifiers2 = get_records_sql($sql);
    }

    $return[] = $classifiers2;

    if (!isset($classifiers3)){
        $sql = "
            SELECT 
                cc.course,
                GROUP_CONCAT(cv.code) as classifier
            FROM
                {$CFG->prefix}course_classification cc,
                {$CFG->prefix}classification_value cv
            WHERE
                cv.id = cc.value AND
                cv.type = 6
            GROUP BY
                cc.course
        ";
    
        $classifiers3 = get_records_sql($sql);
    }

    $return[] = $classifiers3;
    
    return $return;
}
// /PATCH
?>