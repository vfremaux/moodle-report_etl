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
 * @author     Valery Fremaux <valery@valeisti.fr>
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
    
    // this is an extra location we can use while etl is not integrated.
    $etllanglocation = $CFG->dirroot.'/admin/report/etl/lang/';
    
    $plugin = required_param('plugin', PARAM_TEXT);
    $action = optional_param('what', '', PARAM_TEXT);
    
    if (!empty($action)){
        $result = include $CFG->dirroot.'/admin/report/etl/managequeries.controller.php';
        if ($result == -1){
            admin_externalpage_print_footer();
            return;
        }
    }

    print_heading(get_string('managequeries', 'report_etl', '', $etllanglocation), 1);

    $queries = get_records('etl_query', 'plugin', $plugin);

    echo '<center>';

    if (!empty($queries)){

        $namestr = get_string('name');
        $querystr = get_string('query', 'report_etl', '', $etllanglocation);
        $cmdstr = get_string('cmds', 'report_etl', '', $etllanglocation);
        $table->head = array("<b>$namestr</b>", "<b>$querystr</b>", "<b>$cmdstr</b>");
        $table->align = array('left', 'left', 'left');
        $table->size = array('20%', '60%', '20%');
        $table->width = '90%';

        foreach($queries as $query){
            $commands = "<a href=\"{$CFG->wwwroot}/admin/report/etl/managequeries.php?plugin={$plugin}&amp;what=edit&amp;id={$query->id}\" title=\"".get_string('edit')."\"><img src=\"{$CFG->pixpath}/t/edit.gif\"></a>";
            $commands .= "<a href=\"{$CFG->wwwroot}/admin/report/etl/managequeries.php?plugin={$plugin}&amp;what=delete&amp;id={$query->id}\" title=\"".get_string('delete')."\"><img src=\"{$CFG->pixpath}/t/delete.gif\"></a>";
            $table->data[] = array($query->name, '<pre>'.$query->query.'</pre>', $commands);
        }
        
        print_table($table);
        unset($table);
    } else {
        echo get_string('noqueries', 'report_etl', '', $etllanglocation);
    }        

    $opts = array( 'plugin' => $plugin, 'what' => 'add' );
    print_single_button($CFG->wwwroot.'/admin/report/etl/managequeries.php', $opts, get_string('addaquery', 'report_etl', '', $etllanglocation));

    $opts = array( 'plugin' => $plugin);
    print_single_button($CFG->wwwroot.'/admin/report/etl/index.php', $opts, get_string('backtoetl', 'report_etl', '', $etllanglocation));

    echo '</center>';

    admin_externalpage_print_footer();

?>