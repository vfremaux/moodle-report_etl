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
 * @package    report_etl
 * @author     Valery Fremaux <valery.Fremaux@club-internet.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 1999 onwards Martin Dougiamas  http://dougiamas.com
 *
 * @usecase add
 * @usecase edit
 * @usecase save
 * @usecase delete
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/report/etl/etl_forms.php');

// Add Query form -------------------------------------.
if ($action == 'add') {
    echo $OUTPUT->heading(get_string('addaquery', 'report_etl'), 1);

    $queryform = new etl_addquery_form($plugin);

    $queryform->display();
    return -1;
}
// Edit Query form -----------------------------------.
if ($action == 'edit') {
    echo $OUTPUT->heading(get_string('editaquery', 'report_etl'));

    $queryid = required_param('id', PARAM_INT);
    $query = $DB->get_record('report_etl_query', array('id' => $queryid));
    $queryform = new etl_addquery_form($plugin, $queryid, $query->name, $query->query);

    $queryform->display();
    return -1;
}
// Save Query form -----------------------------------.
if ($action == 'save') {
    $queryform = new etl_addquery_form($plugin);

    if ($data = $queryform->get_data()) {
        if (!$queryform->is_cancelled()) {
            $query = new StdClass();
            $query->plugin = $plugin;
            $query->name = $data->name;
            $query->query = $data->query;
            if ($data->id) {
                $query->id = $data->id;
                $DB->update_record('report_etl_query', $query);
            } else {
                $DB->insert_record('report_etl_query', $query);
            }
        }
    }
}
// Delete a query --------------------------------------.
if ($action == 'delete') {
    $queryid = required_param('id', PARAM_INT);
    $DB->delete_records('report_etl_query', array('id' => $queryid));
}
