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
 * @usecase add
 * @usecase edit
 * @usecase save
 * @usecase delete
 */


include_once $CFG->dirroot.'/admin/report/etl/etl_forms.php';

//------------------------------- Add Query form -------------------//
if ($action == 'add'){
    print_heading(get_string('addaquery', 'etl', '', $etllanglocation), 1);

    $queryform = new etl_addquery_form($plugin);    
    
    $queryform->display();
    return -1;
}
//------------------------------- Edit Query form -------------------//
if ($action == 'edit'){
    print_heading(get_string('editaquery', 'etl', '', $etllanglocation), 1);

    $queryid = required_param('id', PARAM_INT);
    $query = get_record('etl_query', 'id', $queryid);
    $queryform = new etl_addquery_form($plugin, $queryid, $query->name, $query->query);
    
    $queryform->display();
    return -1;
}
//------------------------------- Save Query form -------------------//
if ($action == 'save'){
    $queryform = new etl_addquery_form($plugin);    

    if ($data = $queryform->get_data()){
        if (!$queryform->is_cancelled()){
            $query->plugin = $plugin;
            $query->name = $data->name;
            $query->query = $data->query;
            if ($data->id){
                $query->id = $data->id;
                update_record('etl_query', $query);
            } else {
                insert_record('etl_query', $query);
            }
        }
    }
}
//------------------------------- Delete a query -------------------//
if ($action == 'delete'){
    $queryid = required_param('id', PARAM_INT);
    delete_records('etl_query', 'id', $queryid);
}

?>