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
 * External Web Service access for querying information about user account
 *
 */

    require_once('../../../../../config.php');
    require_once($CFG->dirroot.'/admin/report/etl/lib.php');
    require_once($CFG->dirroot.'/admin/report/etl/plugins/toamoodle/extractor.class.php');

    $toaid = required_param('id', PARAM_INT);
    $key = required_param('key', PARAM_RAW);
    $method = optional_param('method', 'des', PARAM_ALPHA);


    $etl_environment = new toamoodle_extractor($toaid, $key, $method);

    // check reliability of the source.
    $remotename = gethostbyaddr($_SERVER['REMOTE_ADDR']);
    if (!empty($etl_environment->toaipmask) && ($remotename != $etl_environment->toahost)) {
        send_xml_error("Error. TOA host not matched : $remotehostname", $etl_environment);
    }

    $ipmask = str_replace('.', '\\.', $etl_environment->toaipmask);
    $ipmask = str_replace('*', '.*', $ipmask);
    if (!empty($etl_environment->toaipmask) && !ereg($ipmask, $_SERVER['REMOTE_ADDR'])) {
        send_xml_error('Error. TOA Ip range not acceptable', $etl_environment);
    }
    
    // get user and relevant fields
    if ($user = get_record_select('user', " username = '{$etl_environment->parms->login}' AND deleted != 1 ")){
        $fields = explode(',', $etl_environment->parms->fields);
        
        $response = new StdClass;
        foreach($fields as $afield){
            if (isset($user->$afield)){
                $response->$afield = $user->$afield;
            }
            // silently ignore if not existant
        }
        
        add_toa_user_context($response, $user);
        
        // print_object($response);
        
        $outputticket = toa_make_ticket($response, $etl_environment->publickey, $method);
        
        echo "<?xml version=\"1.0\"  encoding=\"{$etl_environment->outputencoding}\" ?>\n<profile>\n";
        echo "<encrypteduser>$outputticket</encrypteduser>\n";
        echo "</profile>\n";                
    } else {
        send_xml_error('Error. No user', $etl_environment);
    }
    
    /**
    *
    *
    */
    function add_toa_user_context(&$response, &$user){
        
        $context = get_context_instance(CONTEXT_COURSE, SITEID);
        if ($roles = get_user_roles($context, $user->id, true, 'r.sortorder DESC,c.contextlevel ASC')){
            $roleobjects = array_values($roles);
            $response->role = $roleobjects[0]->shortname;
            $rolecontext = get_context_instance_by_id($roleobjects[0]->contextid);
            if ($rolecontext->contextlevel == CONTEXT_COURSE){
                $response->context = 'site';
            } else {
                $response->context = 'system';
            }
        } else {
            $response->role = '';
            $response->context = '';
        }
    }

    /**
    * a small error function to generate XML error report.
    *
    */
    function send_xml_error($errstring, &$etl_environment){        
        echo "<?xml version=\"1.0\"  encoding=\"{$etl_environment->outputencoding}\" ?>\n<error>\n";
        echo "<errormsg>$errstring</errormsg>\n";
        echo "</error>\n";  
        die;              
    }
?>