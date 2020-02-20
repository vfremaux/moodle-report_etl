<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package    reportetl_toamoodle
 * @author     Valery Fremaux <valery.Fremaux@club-internet.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 1999 onwards Martin Dougiamas  http://dougiamas.com
 *
 * Provides actual implementation of the extractor for TOA (Trimane Open Analytics)
 * for a standard Moodle installation
 *
 */

namespace report_etl;
use \StdClass;

require_once($CFG->dirroot.'/report/etl/xmllib.php');
require_once($CFG->dirroot.'/report/etl/plugins/toamoodle/locallib.php');
require_once($CFG->dirroot.'/report/etl/classes/extractor.class.php');

define('SSL_SAFE_GUARD', 5000); // Safe window in seconds.

class toamoodle_extractor extends etl_extractor {

    public static $pluginname = 'toamoodle';

    function __construct($id, $key, $authmethod, $local = false) {
        global $DB;

        $this->id = $id;

        if ($toa = $DB->get_record('reportetl_'.self::$plugingname, array('id' => $id))) {
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

        if ($local) {
            return;
        }

        // This will authenticate the access to the etl instance.
        parent::__construct($key, $authmethod, $local);

        /*
         * fix to and from situation when both are empty
         * defaults to "last diff"
         */
        if (empty($this->parms->from) && empty($this->parms->to)) {
            if (preg_match('/^special_/', $this->query)) {
                $extractname = 'lastextract_'.$this->query;
                $this->parms->from = @$this->$extractname;
            } else {
                $this->parms->from = $this->lastextract;
            }
            $this->parms->to = time();
        }

        // Defaults from 0 to "to".
        if (empty($this->parms->from)) {
            $this->parms->from = 0;
        }

        // Defaults from "from" to "now".
        if (empty($this->parms->to)) {
            $this->parms->to = time();
        }

        $this->config();

        $toclause = '';
        if (!empty($this->parms->to)) {
            $toclause = " AND l.time <= {$this->parms->to} ";
        }

        $fromclause = '';
        if (!empty($this->parms->from)) {
            $fromclause = " AND l.time > {$this->parms->from} ";
        }
    }

    public function delete() {
        global $DB;

        $DB->delete_records('reportetl_'.self::$plugingname, ['id' => $id]);
    }

    public function get_name() {
        return $this->toahost;
    }

    /**
     * plugin specific decoder. Uses TOA internally stored
     * public key to get data from. 
     */
    function decode($key) {

        if ($info = $this->get_key_info($key, $this->method)) {

            // Check if SSO/DES ticket is not obsolete.
            if ($info->date < time() - SSL_SAFE_GUARD) {
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
    function get_key_info($key, $method = 'des') {
        global $DB;

        if ($method == 'rsa') {

            $toa = $DB->get_record('reportetl_toamoodle', array('id'  => $this->id));
            if (empty($toa->publickey)) {
                etl_error("Cannot use unsecured TAO connector");
            }

            $pkey = openssl_pkey_get_public($toa->publickey);

            if (!openssl_public_decrypt(urldecode($key), $decrypted, $pkey)) {
                etl_error("Failed reading key");
            }
        } else {

            $sql = "
                SELECT
                    AES_DECRYPT(UNHEX('$key'), '{$this->publickey}') AS result
            ";
            if ($result = $DB->get_record_sql($sql)) {
                $decrypted = $result->result;
            } else {
                return null;
            }
        }

        if (!$keyinfo = json_decode($decrypted)) {
            etl_error('Error while deserializing');
        }

        return $keyinfo;
    }

    /**
     *
     *
     */
    function save_config() {
        global $DB;

        $toa = new StdClass();
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

        if (!$DB->update_record('reportetl_toamoodle', $toa)) {
            etl_error("Could not save TOAMOODLE config");
        }
    }

    /**
     * allows a dataless query for testing connexion only
     * @param objectref &$output
     $ @param bool $testmode
     */ 
    function extract_test(&$output, $testmode = false) {
        global $CFG;

        // Get current plugin version.
        $plugin = new StdClass;
        include_once($CFG->dirroot.'/report/etl/plugins/toamoodle/version.php');

        if ($testmode == 'test') {
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
     * @param stringref &$output
     * @param bool $testmode
     */
    function extract_actions(&$output, $testmode = false) {
        global $CFG, $SITE, $DB;

        list($usec, $sec) = explode(" ",microtime()); 
        $perfs['TOTAL'] = (float)$sec + (float)$usec;
        $perfs['RECORDS'] = (float)$sec + (float)$usec; 
        $perfs['INDICATORS'] = 0; 
        $perfs['QUALIFIERS'] = 0; 
        $perfs['OUTPUT'] = 0; 

        // Set extraction boundaries.

        $toclause = '';
        if (!empty($this->parms->to)) {
            $toclause = " AND l.time <= {$this->parms->to} ";
        }

        $fromclause = '';
        if (!empty($this->parms->from)){
            $fromclause = " AND l.time > {$this->parms->from} ";
        }

        // Get standard log.

        $logmanager = get_log_manager();
        $readers = $logmanager->get_readers('\core\log\sql_select_reader');
        $reader = reset($readers);

        if (empty($reader)) {
            return false; // No log reader found.
        }

        if ($reader instanceof \logstore_standard\log\store) {
            $courseparm = 'courseid';
            $timeparam = 'timecreated';
        } else if ($reader instanceof \logstore_legacy\log\store) {
            $courseparm = 'course';
            $timeparam = 'time';
        } else {
            return;
        }

        $testclause = '';
        if ($testmode == 'test') {
            $testclause = ' LIMIT 0,30 ';
        }

        $sql = "SELECT
                l.id,
                l.$timeparam as time,
                l.*,
                u.username,
                mh.id as userhostid,
                mh.name as userhostname,
                mh.wwwroot as userhost
            FROM
                {log} l,
                {user} u,
                {mnet_host} mh
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

        // Put some cache structures.

        $COURSECACHE = array();

        // Start producing.

        if ($testmode == 'test') {
            echo htmlentities('<logrecords>')."\n";
        } else {
            echo '<?xml version="1.0" encoding="'.$this->outputencoding."\" ?>\n<logrecords>\n";
        }

        $i = 0;

        $rs = $DB->get_recordset_sql($sql);

        // Performance.
        list($usec, $sec) = explode(" ",microtime()); 
        $perfs['RECORDS'] = (float)$sec + (float)$usec - (float)$perfs['RECORDS']; 

        if ($rs) {
            foreach ($rs as $u) {

                // QUALIFIERS.

                // Performance.
                list($usec, $sec) = explode(" ",microtime()); 
                $qtick = (float)$sec + (float)$usec; 

                $u->host = $SITE->shortname;
                $u->from = $this->parms->from;
                $u->to = $this->parms->to;

                // Get module or block name.
                if (preg_match('/^block_/', $u->module)) {
                    /*
                     * we are assuming here some complex blocks might have to log something
                     * and will naturally choose to tag the module as block_xxxxx
                     */

                    // We will identify a block as [blocktype] : [instanceid] : [position].
                    if ($u->cmid) {
                        $blockinstance = get_record('block_instance', 'id', $u->cmid);
                        $block = get_record('block', 'id', $blockinstance->blockid);
                        $blockname = get_string('blockname', $block->name);
                        $u->instance = format_string($blockname.' :: ['.$blockinstance->position.','.$blockinstance->weight.'] :: '.$u->cmid);
                        $context = context_block::instance($blockinstance->id);

                        // Check visibility.
                        $u->visible = $blockinstance->visible;
                        $u->groupmode = null;
                    }
                } else if ($u->module == 'course') {
                    // See later after course information retrieval.
                    $u->visible = 1;
                } else if ($u->module == 'user') {
                    // See later after course information retrieval.
                    $u->instance = get_string('nc', 'reportetl_toamoodle', '');
                    $u->visible = 1;
                    $u->groupmode = null;
                } else {
                    // Should be a module.
                    if ($u->cmid) {
                        if (!$cm = $DB->get_record('course_modules', array('id' => $u->cmid))) {
                            continue;
                        }
                        if ($module = $DB->get_record('modules', array('id' => $cm->module))) {
                            // If is some valid module we can...
                            if (preg_match('/label$/', $module->name)) {
                                // Ignore labels and customlabels.
                                continue;
                            }

                            $modulerec = $DB->get_record($u->module, array('id' => $cm->instance));
                            $idnumber = (empty($cm->idnumber)) ? '---' : $cm->idnumber;
                            $u->instance = "[{$modulerec->id}] :: " . $idnumber . ' :: '.format_string($modulerec->name);
                        }
                        $u->visible = $cm->visible;
                        $u->groupmode = $cm->groupmode;
                        $context = context_module::instance($u->cmid);
                    } else {
                        $u->visible = get_string('nc', 'reportetl_toamoodle');
                        $u->groupmode = null;
                        $u->instance = get_string('nc', 'reportetl_toamoodle');
                    }
                }

                // Get course information.
                $u->courseid = $u->course; // Save the course id.
                if (!array_key_exists($u->courseid, $COURSECACHE)) {
                    if (!$course = $DB->get_record('course', array('id' => $u->course))) {
                        $course->idnumber = '---';
                        $course->groupmode = get_string('nc', 'reportetl_toamoodle');
                        $course->shortname = get_string('nc', 'reportetl_toamoodle');
                        $course->context = context_system::instance();
                    } else {
                        $course->context = context_course::instance($u->course);
                    }
                    $COURSECACHE[$u->courseid] = $course;
                } else {
                    $course = $COURSECACHE[$u->courseid];
                }
                if ($course->idnumber === '') $course->idnumber = '---';
                $u->course = $course->idnumber.' :: '.$course->shortname;
                $u->visible = $u->visible && $course->visible;

                // Post processing of course instance.

                if ($u->module == 'course') {
                    $u->instance = '['.$course->id.'] :: '.$course->idnumber.' :: '.format_string($course->fullname);
                    $u->groupmode = $course->groupmode;
                }

                // Group mode.
                if (is_null($u->groupmode)) $u->groupmode = $course->groupmode;
                switch ($u->groupmode) {
                    case NOGROUPS:
                        $u->groupmode = get_string('nogroups', 'reportetl_toamoodle');
                        break;

                    case SEPARATEGROUPS:
                        $u->groupmode = get_string('separatedgroups', 'reportetl_toamoodle');
                        break;

                    case VISIBLEGROUPS:
                        $u->groupmode = get_string('visiblegroups', 'reportetl_toamoodle');
                        break;
                }

                // Get category chain.
                toa_get_additional_course_info($u, $course);

                // Get category chain.
                toa_get_upper_categories($u, $course);

                if (function_exists('tao_is_learning_format') && tao_is_learning_format($course)){
                    toa_learning_get_classifiers($u);
                } else {
                    $u->classifier1 = get_string('nc', 'reportetl_toamoodle');
                    $u->classifier2 = get_string('nc', 'reportetl_toamoodle');
                    $u->classifier3 = get_string('nc', 'reportetl_toamoodle');
                }

                toa_reshape_visible($u);

                // Get role assignation if available.
                if (empty($context)) $context = $course->context; // May be system level.
                toa_get_user_roles($u, $course, null, '', $context);

                // Reshape user's origin.
                toa_reshape_user_origin($u);

                // Anonymise user references in extraction.
                if (!empty($this->masquerade)) {
                    $u->username = md5($u->username.@$CFG->passwordsaltmain);
                }

                // Performance.
                list($usec, $sec) = explode(' ', microtime());
                $perfs['QUALIFIERS'] += (float)$sec + (float)$usec - (float)$qtick;

                // INDICATORS.

                // Performance.
                list($usec, $sec) = explode(" ", microtime());
                $itick = (float)$sec + (float)$usec;

                // Search nearest log of this user in the future.
                $fetchbacksql = "
                    SELECT
                        id,
                        time as nearest
                    FROM
                        {log}
                    WHERE
                        `time` > {$u->time} AND
                        userid = {$u->userid}
                    ORDER BY
                        `time` ASC
                ";
                if ($rec = $DB->get_record_sql($fetchbacksql)) {
                    $u->gap = $rec->nearest - $u->time;
                } else {
                    $u->gap = MINSECS * 10; // Give a mean positive time.
                }

                // Performance.
                list($usec, $sec) = explode(" ", microtime());
                $perfs['INDICATORS'] += (float)$sec + (float)$usec - (float)$itick;

                // Clean output record.
                unset($u->userid);
                unset($u->cmid);
                unset($u->url);
                unset($u->info);
                unset($u->ip);
                unset($u->id);
                unset($u->userhostid);
                unset($u->userhostname);
                unset($u->courseid);
                $visiblestr = get_string('visible', 'reportetl_toamoodle');
                $nonvisiblestr = get_string('nonvisible', 'reportetl_toamoodle');
                $u->visible = ($u->visible) ? $visiblestr : $nonvisiblestr;

                // Performance.
                list($usec, $sec) = explode(' ', microtime());
                $otick = (float)$sec + (float)$usec;

                if ($testmode == 'test') {
                    echo mb_convert_encoding(htmlentities(recordtoxml($u, $i, 'logrecord', ''), ENT_QUOTES, 'UTF-8'), $this->outputencoding, 'UTF-8');
                } else {
                    echo mb_convert_encoding(recordtoxml($u, $i, 'logrecord', ''), $this->outputencoding, 'UTF-8');
                }

                // Performance.
                list($usec, $sec) = explode(" ", microtime());
                $perfs['OUTPUT'] += (float)$sec + (float)$usec - (float)$otick;

                $i++;
            }
            $rs->close();

            // Don't forget last record !!
            if ($i > 0) {
                if ($testmode == 'test') {
                    echo mb_convert_encoding(htmlentities(recordtoxml($u, $i, 'logrecord', ''), ENT_QUOTES, 'UTF-8'), $this->outputencoding, 'UTF-8');
                } else {
                    echo mb_convert_encoding(recordtoxml($u, $i, 'logrecord', ''), $this->outputencoding, 'UTF-8');
                }
            }
        }

        if ($testmode == 'test') {
            echo htmlentities("\n</logrecords>");
        } else {
            echo "\n</logrecords>";
        }

        flush();

        // Performance.
        list($usec, $sec) = explode(" ", microtime());
        $perfs['TOTAL'] = (float)$sec + (float)$usec - (float)$perfs['TOTAL'];

        // Prepare the "till when" temporary marker.
        if (!empty($u->time)) {
            $this->lastextract = $u->time;
        } else {
            // Empty extraction.
            $this->lastextract = time();
        }
        $this->save_config();

        return $perfs;
    }

    /**
     * get "statefull" (or eventless) information about courses and assignations at the time the extraction is required.
     *
     */
    function extract_academics(&$output, $testmode = false) {
        global $CFG, $SITE, $DB;

        // Get user to course assignation.

        $testclause = '';
        if ($testmode == 'test') {
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
                {user} u,
                {context} co,
                {role} r,
                {role_assignments} ra,
                {mnet_host} mh
            WHERE
                co.id = ra.contextid AND
                r.id = ra.roleid AND
                ra.userid = u.id AND
                u.mnethostid = mh.id AND
                u.deleted = 0 AND
                u.confirmed = 1
            ORDER BY
                ra.timemodified,
                r.name
            $testclause
        ";

        if ($testmode != 'test') {
            echo "<?xml version=\"1.0\"  encoding=\"{$this->outputencoding}\" ?>\n<roleassignrecords>\n";
        }

        $lasttimes = array();

        // Context.
        $sitestr = get_string('sitecontext', 'reportetl_toamoodle');
        $categorystr = get_string('categorycontext', 'reportetl_toamoodle');
        $coursestr = get_string('coursecontext', 'reportetl_toamoodle');
        $modulestr = get_string('modcontext', 'reportetl_toamoodle');
        $blockstr = get_string('blockcontext', 'reportetl_toamoodle');
        $userstr = get_string('usercontext', 'reportetl_toamoodle');
        $CONTEXTS = array(CONTEXT_SYSTEM => $sitestr,
                          CONTEXT_COURSECAT => $categorystr,
                          CONTEXT_COURSE => $coursestr,
                          CONTEXT_MODULE => $modulestr,
                          CONTEXT_BLOCK => $blockstr,
                          CONTEXT_USER => $userstr);

        $UIDS = array(); // Used to collect assigned users ids.
        $CIDS = array(); // Used to collect assigned context ids.

        $rs = $DB->get_recordset_sql($sql);
        $i = 0;
        if ($rs) {
            foreach ($rs as $u) {

                // Capture assigned IDs.
                $UIDS[$u->userid] = 1;
                $CIDS[$u->contextid] = 1;

                // QUALIFIERS.

                // Current site identity.
                $u->host = $SITE->shortname;
                $u->from = $this->parms->from;
                $u->to = $this->parms->to;
                $u->role = format_string($u->role);
                $u->context = $CONTEXTS[$u->contextlevel];

                // Get user categorization.
                toa_get_user_info($u);

                // Get instance categorisation : some instance may tell us we have to discard record.
                if (!toa_get_assignation_instance_info($u)) {
                    continue;
                }

                $u->section = get_string('realassigns', 'reportetl_toamoodle');

                // INDICATORS.

                // Last cleanup of the record.
                unset($u->userid);
                unset($u->contextid);
                unset($u->instanceid);
                unset($u->path);

                if ($testmode == 'test') {
                    echo mb_convert_encoding(htmlentities(recordtoxml($u, $i, 'roleassignrecord', ''), ENT_QUOTES, 'UTF-8'), $this->outputencoding, 'UTF-8');
                } else {
                    echo mb_convert_encoding(recordtoxml($u, $i, 'roleassignrecord', ''), $this->outputencoding, 'UTF-8');
                }
                $i++;
            }
            $rs->close();

            // Don't forget last record !!
            if ($i > 0) {
                if ($testmode == 'test') {
                    echo mb_convert_encoding(htmlentities(recordtoxml($u, $i, 'roleassignrecord', ''), ENT_QUOTES, 'UTF-8'), $this->outputencoding, 'UTF-8');
                } else {
                    echo mb_convert_encoding(recordtoxml($u, $i, 'roleassignrecord', ''), $this->outputencoding, 'UTF-8');
                }
            }
        }

        // Fetching orphan users.

        $uidlist = implode("','", array_keys($UIDS));

        $sql = "
            SELECT
                u.id as userid,
                u.username,
                mh.wwwroot as userhost,
                u.deleted
            FROM
                {user} u,
                {mnet_host} mh
            WHERE
                u.mnethostid = mh.id AND
                u.id NOT IN ('$uidlist') AND
                u.confirmed = 1 AND
                u.deleted = 0 AND
                u.firstaccess <= {$this->parms->to}
        ";
        $rs = $DB->get_recordset_sql($sql);
        if ($rs) {
            foreach ($rs as $u) {

                $u->host = $SITE->shortname;
                $u->from = $this->parms->from;
                $u->to = $this->parms->to;
                $u->context = get_string('nc', 'reportetl_toamoodle');
                $u->contextlevel = -1;
                $u->instance = get_string('nc', 'reportetl_toamoodle');
                $u->role = get_string('nc', 'reportetl_toamoodle');
                $u->section = get_string('orphans', 'reportetl_toamoodle');
                $u->object = get_string('nc', 'reportetl_toamoodle');

                toa_get_user_info($u);

                // Last cleanup.
                unset($u->userid);

                if ($testmode == 'test') {
                    echo mb_convert_encoding(htmlentities(recordtoxml($u, $i, 'roleassignrecord', ''), ENT_QUOTES, 'UTF-8'), $this->outputencoding, 'UTF-8');
                } else {
                    echo mb_convert_encoding(recordtoxml($u, $i, 'roleassignrecord', ''), $this->outputencoding, 'UTF-8');
                }
                $i++;
            }
            $rs->close();

            // Don't forget last record !!
            if ($i > 0) {
                if ($testmode == 'test') {
                    echo mb_convert_encoding(htmlentities(recordtoxml($u, $i, 'roleassignrecord', ''), ENT_QUOTES, 'UTF-8'), $this->outputencoding, 'UTF-8');
                } else {
                    echo mb_convert_encoding(recordtoxml($u, $i, 'roleassignrecord', ''), $this->outputencoding, 'UTF-8');
                }
            }
        }

        // Fetching orphan contexts.

        $cidlist = implode("','", array_keys($CIDS));

        $sql = "
            SELECT
                co.contextlevel,
                co.instanceid,
                co.path
            FROM
                {context} co
            WHERE
                co.id NOT IN ('$cidlist')
        ";
        $rs = $DB->get_recordset_sql($sql);
        if ($rs) {
            foreach ($rs as $c) {

                $c->host = $SITE->shortname;
                $c->from = $this->parms->from;
                $c->to = $this->parms->to;
                $c->username = get_string('nc', 'reportetl_toamoodle');
                $c->section = get_string('unusedcontexts', 'reportetl_toamoodle');
                $c->role = get_string('nc', 'reportetl_toamoodle');
                $c->context = $CONTEXTS[$c->contextlevel];

                // Get instance categorisation : some instancemay tell us we have to discard record.
                if (!toa_get_assignation_instance_info($c)) {
                    continue;
                }

                // Get fake records as there is no user.
                toa_get_user_info($c);

                // Cleanup record.
                unset($c->contextid);
                unset($c->instanceid);
                unset($c->path);
                $c->contextlevel = 0; // Tell it is not a real assign.

                // Generate record.
                if ($testmode == 'test') {
                    echo mb_convert_encoding(htmlentities(recordtoxml($c, $i, 'roleassignrecord', ''), ENT_QUOTES, 'UTF-8'), $this->outputencoding, 'UTF-8');
                } else {
                    echo mb_convert_encoding(recordtoxml($c, $i, 'roleassignrecord', ''), $this->outputencoding, 'UTF-8');
                }
                $i++;
            }
            $re->close();

            // Don't forget last record !!
            if ($i > 0) {
                if ($testmode == 'test') {
                    echo mb_convert_encoding(htmlentities(recordtoxml($u, $i, 'roleassignrecord', ''), ENT_QUOTES, 'UTF-8'), $this->outputencoding, 'UTF-8');
                } else {
                    echo mb_convert_encoding(recordtoxml($u, $i, 'roleassignrecord', ''), $this->outputencoding, 'UTF-8');
                }
            }
        }

        if ($testmode == 'test') {
            echo htmlentities("\n</roleassignrecords>");
        } else {
            echo "\n</roleassignrecords>";
        }

        flush();

        // Prepare the "till when" temporary marker.
        if (!empty($u->time)) {
            $this->lastextract = $u->time;
        } else {
            // Empty extraction.
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
    function extract_documents(&$output, $testmode = false) {
        global $CFG, $SITE, $DB;

        include($CFG->dirroot.'/search/lib.php');
        ini_set('include_path', $CFG->dirroot.DIRECTORY_SEPARATOR.'search'.PATH_SEPARATOR.ini_get('include_path'));
        require_once($CFG->dirroot.'/search/Zend/Search/Lucene.php');

        // Set extraction boundaries.
        $toclause = '';
        if (!empty($this->parms->to)) {
            $toclause = " AND docdate <= {$this->parms->to} ";
        }

        $fromclause = '';
        if (!empty($this->parms->from)) {
            $fromclause = " AND docdate > {$this->parms->from} ";
        }

        // Get standard log.

        $testclause = '';
        if ($testmode == 'test') {
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
                {".SEARCH_DATABASE_TABLE."}
            WHERE
                doctype NOT LIKE '%label'
            $testclause
        ";

    // Put some cache structures.

    $COURSECACHE = array();

        // Start producing.

        if ($testmode == 'test') {
            echo htmlentities("<documentrecords>\n", ENT_QUOTES, 'UTF-8');
        } else {
            echo "<?xml version=\"1.0\"  encoding=\"{$this->outputencoding}\" ?>\n<documentrecords>\n";
        }

        $rs = $DB->get_recordset_sql($sql);
        if ($rs) {
            $i = 0;
            foreach ($rs as $u) {

                // QUALIFIERS.

                // Current site.
                $u->host = $SITE->shortname;
                $u->from = $this->parms->from;
                $u->to = $this->parms->to;

                // Get module name.
                // WE NEED CMID FROM SOMEWHERE... CHECK !!
                $searchables = search_collect_searchables(false, false);
                $searchable_instance = $searchables[$u->doctype];
                if ($searchable_instance->location == 'internal') {
                    include_once("{$CFG->dirroot}/search/documents/{$u->doctype}_document.php");
                } else {
                    include_once("{$CFG->dirroot}/{$searchable_instance->location}/{$u->doctype}/search_document.php");
                }

                $document_function = "{$u->doctype}_search_get_objectinfo";

                if (function_exists($document_function)) {
                    $moduleinfo = $document_function($u->itemtype, $u->docid);
                }

                // Avoid polluting records with missing implementations.
                if (empty($moduleinfo)) {
                    if ($testmode == 'test') echo "skipping $u->itemtype, $u->docid <br/>";
                    continue;
                }

                // Get course information.
                if ($u->courseid) {
                    if (!array_key_exists($u->courseid, $COURSECACHE)) {
                        if (!$course = $DB->get_record('course', array('id' => $u->courseid))) {
                            $course->idnumber = '---';
                            $course->shortname = get_string('nc', 'reportetl_toamoodle');
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
                    $u->course = get_string('nc', 'reportetl_toamoodle');
                    $u->visible = true;
                }

                $u->instance = $u->docid.' :: '.format_string($u->title);

                toa_get_upper_categories($u, $course);

                toa_reshape_visible($u);

                // Get media and document tech type.
                $u->mediatype = $moduleinfo->mediatype;
                $u->contenttype = $moduleinfo->contenttype;

               // INDICATORS.

                $itemid = $moduleinfo->instance->id;

                // Search count view logs.
                $fetchbacksql = "
                    SELECT
                        COUNT(*)
                    FROM
                        {log}
                    WHERE
                        `time` > {$u->from} AND
                        `time` < {$u->to} AND
                        module = '{$u->doctype}' AND
                        info = {$itemid} AND
                        action LIKE '%view%'
                ";

                $u->viewhits = $DB->count_records_sql($fetchbacksql);

                // Search count write logs.
                $fetchbacksql = "
                    SELECT
                        COUNT(*)
                    FROM
                        {log}
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

                // Search count view logs.
                $fetchbacksql = "
                    SELECT
                        COUNT(*)
                    FROM
                        {log}
                    WHERE
                        `time` > {$u->from} AND
                        `time` < {$u->to} AND
                        module = '{$u->doctype}' AND
                        info = {$itemid}
                ";

                $u->totalhits = $DB->count_records_sql($fetchbacksql);

                // Final cleanup.
                unset($u->idnumber);
                unset($u->shortname);
                unset($u->docid);
                unset($u->courseid);
                unset($u->title);

                if ($testmode == 'test') {
                    echo mb_convert_encoding(htmlentities(recordtoxml($u, $i, 'documentrecord', ''), ENT_QUOTES, 'UTF-8'), $this->outputencoding, 'UTF-8');
                } else {
                    echo mb_convert_encoding(recordtoxml($u, $i, 'documentrecord', ''), $this->outputencoding, 'UTF-8');
                }
                $i++;
            }
            $rs->close();

            // Don't forget last record !!
            if ($i > 0) {
                if ($testmode == 'test') {
                    echo mb_convert_encoding(htmlentities(recordtoxml($u, $i, 'documentrecord', ''), ENT_QUOTES, 'UTF-8'), $this->outputencoding, 'UTF-8');
                } else {
                    echo mb_convert_encoding(recordtoxml($u, $i, 'documentrecord', ''), $this->outputencoding, 'UTF-8');
                }
            }
        }

        if ($testmode == 'test') {
            echo htmlentities("\n</documentrecords>");
        } else {
            echo "\n</documentrecords>";
        }

        flush();

        // Prepare the "till when" temporary marker.
        if (!empty($u->time)) {
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
    function extract_communications(&$output, $testmode = false) {
        global $CFG, $SITE, $DB;

        // General setup.

        if ($testmode == 'test') {
        } else {
            echo "<?xml version=\"1.0\"  encoding=\"{$this->outputencoding}\" ?>\n<communicationrecords>\n";
        }

        $testclause = '';
        if ($testmode == 'test') {
            $testclause = ' LIMIT 0,30 ';
        }

        // Get communications in forums.

        // Set extraction boundaries.
        $toclause = '';
        if (!empty($this->parms->to)) {
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
                {forum_posts} fp,
                {forum_discussions} fd,
                {forum} f,
                {user} u,
                {mnet_host} mh
            WHERE
                fp.userid = u.id AND
                fp.discussion = fd.id AND
                fd.forum = f.id AND
                u.mnethostid = mh.id
                $fromclause
                $toclause
                $testclause
        ";

        $rs = $DB->get_recordset_sql($sql);
        if ($rs) {

            $FORUM_SUBSCRIBERS = array(); // A cache for subscriber lists.

            $i = 0;
            foreach ($rs as $u) {

                $u->host = $SITE->shortname;
                $u->from = $this->parms->from;
                $u->to = $this->parms->to;

                if (empty($u->parent)) {
                    // If no parent, the message is considered emitted to the forum community.

                    if (!array_key_exists($u->forum, $FORUM_SUBSCRIBERS)){
                        $sql = "
                            SELECT
                                u.id as userid_to,
                                u.username as username_to,
                                mh.id as userhostid_to,
                                mh.name as userhost_to
                            FROM
                                {forum_subscriptions} fs,
                                {user} u,
                                {mnet_host} mh
                           WHERE
                                fs.userid = u.id AND
                                u.mnethostid = mh.id AND
                                u.id != {$u->userid}
                        "; 
                        $subscribers = $DB->get_records_sql($sql);
                        $FORUM_SUBSCRIBERS[$u->forum] = $subscribers;
                    } else {
                        // Do not fetch them twice !!
                        $subscribers = $FORUM_SUBSCRIBERS[$u->forum];
                    }
               } else {
                    // If we have a parent, the message is considered as being sent from sender to parent owner.
                    $to = $DB->get_field('forum_posts', 'userid', array('id' => $u->parent));
                    $touser = $DB->get_record('user', array('id' => $to), 'id,username,mnethostid');
                    if (!empty($this->masquerade)) {
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
                $context = context_module::instance($cm->id);
                toa_get_user_roles($u, $course, $u->userid_from, 'from', $context);

                $u->section = get_string('modulenameplural', 'forum');
                $idnumber = (empty($cm->idnumber)) ? '---' : $cm->idnumber ;
                $u->instance = "[{$u->forum}] :: ".$idnumber. ' :: '.format_string($u->name);
 
                toa_reshape_user_origin($u, 'from');
                toa_get_user_info($u, $u->userid_from, 'from');

                // Masquerade if required.
                if (!empty($this->masquerade)){
                    $u->username_from = md5($u->username_from.@$CFG->passwordsaltmain);
                }

                // Pre loop cleanup.
                unset($u->userid_from);
                unset($u->userhostid_from);
                unset($u->courseid);
                unset($u->forum);
                unset($u->parent);
                unset($u->name);
                unset($u->userid);

                // Generate as many records as one to one messages.
                foreach ($subscribers as $subscriber) {

                    $r = clone($u);

                    // Clean records before production.
                    toa_get_user_roles($r, $course, $subscriber->userid_to, 'to', $context);

                    $r->username_to = $subscriber->username_to;
                    $r->userhost_to = $subscriber->userhost_to;
                    $r->userhostid_to = $subscriber->userhostid_to;

                    toa_reshape_user_origin($r, 'to');
                    toa_get_user_info($r, $subscriber->userid_to, 'to');

                    // Masquerade if required.
                    if (!empty($this->masquerade)) {
                        $r->username_to = md5($r->username_to.@$CFG->passwordsaltmain);
                    }

                    // Inloop cleanup.
                    unset($r->userhostid_to);

                    // Produce communication records.
                    if ($testmode == 'test') {
                        echo mb_convert_encoding(htmlentities(recordtoxml($r, $i, 'communicationrecord', ''), ENT_QUOTES, 'UTF-8'), $this->outputencoding, 'UTF-8');
                    } else {
                        echo mb_convert_encoding(recordtoxml($r, $i, 'communicationrecord', ''), $this->outputencoding, 'UTF-8');
                    }
                    $i++;
                }
                if ($i > 0) {
                    if ($testmode == 'test') {
                        echo mb_convert_encoding(htmlentities(recordtoxml($r, $i, 'communicationrecord', ''), ENT_QUOTES, 'UTF-8'), $this->outputencoding, 'UTF-8');
                    } else {
                        echo mb_convert_encoding(recordtoxml($r, $i, 'communicationrecord', ''), $this->outputencoding, 'UTF-8');
                    }
                }
            }
            $rs->close();
        }

        // Get communications in messaging.

        // Set extraction boundaries.
        $toclause = '';
        if (!empty($this->parms->to)) {
            $toclause = " AND m.timecreated <= {$this->parms->to} ";
        }

        $fromclause = '';
        if (!empty($this->parms->from)) {
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
                {message_read} m,
                {user} uf,
                {user} ut,
                {mnet_host} mhf, 
                {mnet_host} mht 
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
                {message} m,
                {user} uf,
                {user} ut,
                {mnet_host} mhf,
                {mnet_host} mht
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
        if ($rs) {

            foreach ($rs as $u) {

                $u->host = $SITE->shortname;
                $u->from = $this->parms->from;
                $u->to = $this->parms->to;

                $context = context_system::instance();
                toa_get_user_roles($u, $course, $u->userid_from, 'from', $context);
                toa_get_user_roles($u, $course, $u->userid_to, 'to', $context);

                toa_reshape_user_origin($u, 'from');
                toa_reshape_user_origin($u, 'to');

                toa_get_user_info($u, $u->userid_from, 'from');
                toa_get_user_info($u, $u->userid_to, 'to');

                // Masquerade if required.
                if (!empty($this->masquerade)) {
                    $u->username_from = md5($u->username_from.@$CFG->passwordsaltmain);
                    $u->username_to = md5($u->username_to.@$CFG->passwordsaltmain);
                }

                $u->section = get_string('messaging', 'message');
                $u->instance = get_string('nc', 'reportetl_toamoodle');

                // Pre loop cleanup.
                unset($u->userid_from);
                unset($u->userid_to);
                unset($u->userhostid_from);
                unset($u->userhostid_to);
                unset($u->courseid);
                $u->userhost_from = format_string($u->userhost_from);
                $u->userhost_to = format_string($u->userhost_to);

                // Produce communication records.
                if ($testmode == 'test') {
                    echo mb_convert_encoding(htmlentities(recordtoxml($u, $i, 'communicationrecord', ''), ENT_QUOTES, 'UTF-8'), $this->outputencoding, 'UTF-8');
                } else {
                    echo mb_convert_encoding(recordtoxml($u, $i, 'communicationrecord', ''), $this->outputencoding, 'UTF-8');
                }
                $i++;
            }
            $rs->close();

            // Don't forget last record !!
            if ($i > 0) {
                if ($testmode == 'test') {
                    echo mb_convert_encoding(htmlentities(recordtoxml($u, $i, 'communicationrecord', ''), ENT_QUOTES, 'UTF-8'), $this->outputencoding, 'UTF-8');
                } else {
                    echo mb_convert_encoding(recordtoxml($u, $i, 'communicationrecord', ''), $this->outputencoding, 'UTF-8');
                }
            }
        }

        // Get communications in chatrooms.

        // Set extraction boundaries.
        $toclause = '';
        if (!empty($this->parms->to)) {
            $toclause = " AND cm.timestamp <= {$this->parms->to} ";
        }

        $fromclause = '';
        if (!empty($this->parms->from)) {
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
                {chat} c,
                {chat_messages} cm,
                {user} u,
                {mnet_host} mh 
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

        // Setup recipients.
        $recipients = array();

        $rs = $DB->get_recordset_sql($sql);
        if ($rs) {

            // $i = 0; DO NOT : continue numbering
            foreach ($rs as $u) {

                // if system message, update
                if ($u->system) {
                    if ($u->message == 'enter') {
                        $recipients[$u->chatid][$u->userid_from] = 1;
                    } elseif ($u->message == 'exit') {
                        unset($recipients[$u->chatid][$u->userid_from]);
                    }
                    continue;
                }

                // backtrack last presents
                // we search back in chat trace last presents before we are extracting information
                if (!array_key_exists($u->chatid, $recipients)) {
                    $recipients[$u->chatid] == array();

                    $sql = "
                        SELECT 
                            userid,
                            message
                        FROM
                            {chat_messages}
                        WHERE
                            chatid = $u->chatid AND
                            system = 1 AND
                            timestamp < $u->timestamp
                        ORDER BY
                            timestamp DESC
                    ";

                    $exited = array();

                    if ($retroscans = get_records_sql($sql)) {
                        foreach ($retroscans as $retroscan) {
                            // if not checked as existed or present
                            if (!array_key_exists($retroscan->userid, $exited) || !array_key_exists($retroscan->userid, $recipients[$u->chatid])) {
                                if ($retroscan->message == 'enter') {
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

                $module = $DB->get_record('modules', array('name' => 'chat'));
                $cm = $DB->get_record('course_modules', array('module' => $module->id, 'instance' => $u->chatid));
                $context = context_module::instance($cm->id);
                toa_get_user_roles($u, $course, $u->userid_from, 'from', $context);
                toa_reshape_user_origin($u, 'from');
                toa_get_user_info($u, $u->userid_from, 'from');

                // masquerade if required
                if (!empty($this->masquerade)) {
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
                foreach (array_keys($recipients[$u->chatid]) as $recipient){

                    $r = clone($u);

                    if ($recipient == $r->userid_from) {
                        continue; // do not count self communication
                    }

                    $r->userid_to = $recipient;
                    $r->username_to = $DB->get_field('user', 'username', array('id' => $r->userid_to));
                    $r->userhostid_to = $DB->get_field('user', 'mnethostid', array('id' => $r->userid_to));
                    $r->userhost_to = $DB->get_field('mnet_host', 'name', array('id' => $r->userhostid_to));

                    toa_reshape_user_origin($r, 'to');
                    toa_get_user_info($r, $r->userid_to, 'to');
                    toa_get_user_roles($r, $course, $r->userid_to, 'to', $context);
    
                    if (!empty($this->masquerade)) {
                        $r->username_to = md5($r->username_to.@$CFG->passwordsaltmain);
                    }

                    // final cleanup 
                    unset($r->userhostid_to);
                    unset($r->userid_to);
                    unset($r->chatid);
                    unset($r->userid_from);
    
                    // produce communication records
                    if ($testmode == 'test') {
                        echo mb_convert_encoding(htmlentities(recordtoxml($r, $i, 'communicationrecord', ''), ENT_QUOTES, 'UTF-8'), $this->outputencoding, 'UTF-8');
                    } else {
                        echo mb_convert_encoding(recordtoxml($r, $i, 'communicationrecord', ''), $this->outputencoding, 'UTF-8');
                    }
                    $i++;
                }
                $rs->close();

                if ($i > 0) {
                    if ($testmode == 'test') {
                        echo mb_convert_encoding(htmlentities(recordtoxml($r, $i, 'communicationrecord', ''), ENT_QUOTES, 'UTF-8'), $this->outputencoding, 'UTF-8');
                    } else {
                        echo mb_convert_encoding(recordtoxml($r, $i, 'communicationrecord', ''), $this->outputencoding, 'UTF-8');
                    }
                }
            }
        }

        /// get communications in user data

        if ($testmode == 'test') {
            echo htmlentities("\n</communicationrecords>");
        } else {
            echo "\n</communicationrecords>";
        }

        flush();

        // prepare the "till when" temporary marker
        if (!empty($u->time)) {
            $this->lastextract = $u->time;
        } else { // empty extraction
            $this->lastextract = time();
        }
        $this->save_config();
    }

    /**
     * get eventful information about grades accumulated by users.
     * the reference time date of the record is the grade attribution time.
     */
    function extract_grades(&$output, $testmode = false) {
        global $CFG, $SITE, $DB;

        // General setup 

        if ($testmode == 'test') {
        } else {
            echo "<?xml version=\"1.0\"  encoding=\"{$this->outputencoding}\" ?>\n<graderecords>\n";
        }

        $testclause = '';
        if ($testmode == 'test') {
            $testclause = ' LIMIT 0,30 ';
        }

        /// get communications in forums

        // set extraction boundaries
        $toclause = '';
        if (!empty($this->parms->to)) {
            $toclause = " AND gg.timecreated <= {$this->parms->to} ";
        }

        $fromclause = '';
        if (!empty($this->parms->from)) {
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
                {grade_grades} gg,
                {grade_items} gi
            WHERE
                gg.itemid = gi.id
                $toclause
                $fromclause
                $testclause
        ";

        $rs = $DB->get_recordset_sql($sql);
        if ($rs) {

            // $i = 0; DO NOT : continue numbering
            $i = 0;
            foreach ($rs as $u) {

                $u->host = $SITE->shortname;
                $u->from = $this->parms->from;
                $u->to = $this->parms->to;

                toa_get_user_info($u);

                $course = $DB->get_record('course', array('id' => $u->courseid));
                toa_get_additional_course_info($u, $course);

                // cleanup record

                unset($u->itemtype);
                unset($u->userid);
                // produce communication records

                if ($testmode == 'test') {
                    echo mb_convert_encoding(htmlentities(recordtoxml($u, $i, 'graderecord', ''), ENT_QUOTES, 'UTF-8'), $this->outputencoding, 'UTF-8');
                } else {
                    echo mb_convert_encoding(recordtoxml($u, $i, 'graderecord', ''), $this->outputencoding, 'UTF-8');
                }
                $i++;
            }
            $rs->close();

            if ($i > 0) {
                if ($testmode == 'test') {
                    echo mb_convert_encoding(htmlentities(recordtoxml($r, $i, 'graderecord', ''), ENT_QUOTES, 'UTF-8'), $this->outputencoding, 'UTF-8');
                } else {
                    echo mb_convert_encoding(recordtoxml($r, $i, 'graderecord', ''), $this->outputencoding, 'UTF-8');
                }
            }
        }

        if ($testmode == 'test') {
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
    function extract_acknowledge(&$output, $testmode = false) {

        $changed = false;
        if ($this->lastextract) {
            if (!empty($this->parms->ackquery)) {
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

        if ($testmode == 'test') {
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
    function get_access_url() {
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

    public function reset() {
        // not implemented.
    }
}
