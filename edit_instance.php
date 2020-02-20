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
 *
 * @package     report_etl
 * @category    report
 * @author      Valery Fremaux <valery.Fremaux@club-internet.fr>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright   (C) 1999 onwards Martin Dougiamas  http://dougiamas.com
 *
 * A general ETL related library and base classes
 */

require('../../config.php');

$id = optional_param('id', '', PARAM_INT);
$plugin = required_param('etlplugin', PARAM_ALPHA);
require_once($CFG->dirroot.'/report/etl/plugins/'.$plugin.'/config_form.php');

$url = new moodle_url('/report/etl/edit_instance.php', ['etlplugin' => $plugin, 'id' => $id]);

// Security
$context = context_system::instance();

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_heading(get_string('editinstance', 'report_etl'));

require_login();
if (!has_any_capability(['report/etl:export', 'moodle/site:config'], $context)) {
    print_error("No access");
}

$formclass = 'etl_'.$plugin.'_config_form';
$mform = new $formclass();

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/report/etl/index.php', ['etlplugin' => $plugin]));
}

if ($data = $mform->get_data()) {

    $table = 'reportetl_'.$plugin;

    if (!empty($id)) {
        $oldrec = $DB->get_record($table, ['id' => $id]);
        $DB->update_record($table, $data);
    } else {
        $DB->insert_record($table, $data);
    }

    redirect(new moodle_url('/report/etl/index.php', ['etlplugin' => $plugin]));
}

if ($id) {
    $table = 'report_etl_'.$plugin;
    $instance = $DB->get_record($table, ['id' => $id]);
    $mform->set_data($instance);
}

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('editinstance', 'report_etl'));

$mform->display();

echo $OUTPUT->footer();