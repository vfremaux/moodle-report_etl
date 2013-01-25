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
 * The GET page extracts data from Moodle using an ETL extractor.
 *
 */

    require_once('../../../../../config.php');
    require_once($CFG->dirroot.'/admin/report/etl/lib.php');
    require_once($CFG->dirroot.'/admin/report/etl/plugins/toamoodle/extractor.class.php');

    $toaid = required_param('id', PARAM_INT);
    $key = required_param('key', PARAM_RAW);
    $testmode = optional_param('mode', '', PARAM_TEXT);
    $plugin = optional_param('plugin', 'toamoodle', PARAM_TEXT);
    $method = optional_param('method', 'des', PARAM_ALPHA);

    try{
        $etl_environment = new toamoodle_extractor($toaid, $key, $method);
    } catch (Exception $e){
    }

    if (!empty($etl_environment)){
        if ($testmode != 'test'){
            header("Document-Type:text/xml");        
        } else {
            print_header_simple('', '', build_navigation(array('name' => 'etl', 'link' => '', 'type' => 'title')));
            echo userdate($etl_environment->parms->from).'<br/>';
            echo '<pre>';
        }

        // raise some physical parameters
        @set_time_limit(0);
        ini_set('memory_limit', '512M'); 

        $etl_environment->extract($testmode);        
        if ($testmode == 'test'){
            echo '</pre>';
            echo '<br/>';
            print_continue($CFG->wwwroot."/admin/report/etl/index.php?plugin={$plugin}");
            print_footer();
        }            
    } else {
        if ($testmode == 'test'){
            error('Something is wrong with your key, or you do not have access to this service.');
        } else {
            echo "<?xml version=\"1.0\"  encoding=\"UTF-8\" ?>\n<error>\n";
            echo "<errormsg>Something is wrong with your key, or you do not have access to this service.</errormsg>\n";
            echo "</error>\n"; 
            die;             
        }
    }
    
?>