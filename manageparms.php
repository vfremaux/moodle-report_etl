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
 *
 * The GET page extracts data from Moodle using an ETL extractor.
 */
require('../../config.php');
require_once($CFG->dirroot.'/report/etl/lib.php');
require_once($CFG->libdir.'/adminlib.php');

$systemcontext = context_system::instance();

// Security.
//admin_externalpage_setup('reportetlext');
require_login();
require_capability('report/etl:export', $systemcontext);

$url = new moodle_url('/report/etl/index.php');
$PAGE->set_url($url);
$PAGE->set_context($systemcontext);
$PAGE->set_title(get_string('pluginname', 'report_etl'));
$PAGE->set_pagelayout('admin');

$plugin = required_param('etlplugin', PARAM_ALPHA);
$id = required_param('id', PARAM_INT);

if (!file_exists($CFG->dirroot.'/report/etl/plugins/'.$plugin.'/config_form.php')) {
    throw new moodle_exception();
}

include($CFG->dirroot.'/report/etl/plugins/'.$plugin.'/config_form.php');

$classfunc = 'etl_'.$plugin.'_config_form';
$mform = new $classfunc();

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/report/etl/index.php', ['etlplugin' => $plugin]));
} else if ($formdata = $mform->get_data()) {
    require_sesskey();
    if ($formdata->id) {
        $DB->update_record('reportetl_'.$plugin, $formdata);
    } else {
        $DB->insert_record('reportetl_'.$plugin, $formdata);
    }

    redirect(new moodle_url('/report/etl/index.php', array('etlplugin' => $plugin)));
}

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('updateparms', 'report_etl'));

if ($id) {
    $formdata = $DB->get_record('reportetl_'.$plugin, ['id' => $id]);
    $formdata->sesskey = sesskey();
    $mform->set_data($formdata);
}

$mform->display();

echo $OUTPUT->footer();