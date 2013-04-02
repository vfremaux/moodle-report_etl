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
 *
 */

    require_once('../../../config.php');
    require_once($CFG->dirroot.'/admin/report/etl/lib.php');
    require_once($CFG->libdir.'/adminlib.php');

    admin_externalpage_setup('reportetl');

    admin_externalpage_print_header();
    
    print_heading(get_string('etl', 'report_etl', '', $CFG->dirroot.'/admin/report/etl/lang/'), 1);
    
    $currentetlplugin = optional_param('etlplugin', '', PARAM_TEXT);

    $etlplugins = etl_get_plugins();

    if (!empty($etlplugins)){
        
        if (empty($currentetlplugin)){
            $currentetlplugin = $etlplugins[0];
        }
        
        foreach($etlplugins as $etlplugin){
            $row[] = new tabobject($etlplugin, $CFG->dirroot.'/admin/report/etl/index.php?etlplugin='.$currentetlplugin, get_string('name', $etlplugin, '', $CFG->dirroot.'/admin/report/etl/plugins/'.$etlplugin.'/lang/'));
        }
        
        $tabs[0] = $row;
        print_tabs($tabs, $currentetlplugin);

        print_heading(get_string('manageplugin', 'report_etl', '', $CFG->dirroot.'/admin/report/etl/lang/'), '3');

        if (etl_plugin_has_config($currentetlplugin));

        // TODO : extend to multiple instances
        $manageparmsstr = get_string('manageparms', 'report_etl', '', $CFG->dirroot.'/admin/report/etl/lang/');
        echo "<a href=\"{$CFG->wwwroot}/admin/report/etl/manageparms.php?plugin={$currentetlplugin}&amp;id=1\">$manageparmsstr</a><br/>";
        
        $managequerystr = get_string('managequeries', 'report_etl', '', $CFG->dirroot.'/admin/report/etl/lang/');
        
        echo "<a href=\"{$CFG->wwwroot}/admin/report/etl/managequeries.php?plugin={$currentetlplugin}\">$managequerystr</a>";

        print_heading(get_string('getdata', 'report_etl', '', $CFG->dirroot.'/admin/report/etl/lang/'), '3');

        include($CFG->dirroot.'/admin/report/etl/plugins/'.$currentetlplugin.'/index.php');
    } else {
        notice(get_string('noetlplugins', 'report_etl'));
    }
        
    admin_externalpage_print_footer();
?>