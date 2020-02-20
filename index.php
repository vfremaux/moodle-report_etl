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
 * @package     report_etl
 * @category    report
 * @author      Valery Fremaux <valery.Fremaux@club-internet.fr>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright   (C) 1999 onwards Martin Dougiamas  http://dougiamas.com
 */
require('../../config.php');
require_once($CFG->dirroot.'/report/etl/lib.php');
require_once($CFG->libdir.'/adminlib.php');

$systemcontext = context_system::instance();

// Security.
admin_externalpage_setup('reportetlext');

$url = new moodle_url('/report/etl/index.php');
$PAGE->set_url($url);
$PAGE->set_context($systemcontext);
$PAGE->set_title(get_string('pluginname', 'report_etl'));
$PAGE->set_pagelayout('admin');

$action = optional_param('what', '', PARAM_ALPHA);
if (!empty($action)) {
    include_once($CFG->dirroot.'/report/etl/index.controller.php');
    $controller = new \report_etl\index_controller();
    $controller->receive($action);
    if ($returnurl = $controller->process($action)) {
        redirect($returnurl);
    }
}

$renderer = $PAGE->get_renderer('report_etl');

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('etl', 'report_etl'), 1);

$currentetlplugin = optional_param('etlplugin', '', PARAM_TEXT);

$etlplugins = etl_get_plugins();

if (empty($etlplugins)) {
    // This should not happen, unless all subplugins are removed from the codebase.
    echo $OUTPUT->notification(get_string('noetlplugins', 'report_etl'));
    echo $OUTPUT->footer();
    die;
}

if (empty($currentetlplugin)) {
    $currentetlplugin = $etlplugins[0];
}

// Print tabs.
$renderer->print_tabs($etlplugins, $currentetlplugin);

echo $OUTPUT->heading(get_string('manageplugininstances', 'report_etl'));

$output = optional_param('output', '', PARAM_TEXT);
if (!empty($output)) {
    echo $OUTPUT->notification($output, 'success');
}

$instances = etl_get_instances($currentetlplugin);

echo $renderer->instances_table($instances, $currentetlplugin);

echo $renderer->add_instance_link($currentetlplugin);

echo $OUTPUT->footer();