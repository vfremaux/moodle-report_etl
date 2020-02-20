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
 ù @category    report
 * @author      Valery Fremaux <valery@valeisti.fr>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright   (C) 1999 onwards Martin Dougiamas  http://dougiamas.com
 */
require_once('../../config.php');
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

echo $OUTPUT->header();

$plugin = required_param('etlplugin', PARAM_ALPHA);
$action = optional_param('what', '', PARAM_TEXT);

if (!empty($action)) {
    $result = include($CFG->dirroot.'/report/etl/managequeries.controller.php');
    if ($result == -1) {
        echo $OUTPUT->footer();
        return;
    }
}

echo $OUTPUT->heading(get_string('managequeries', 'report_etl'), 1);

$queries = $DB->get_records('report_etl_query', array('plugin' => $plugin));

echo '<center>';

if (!empty($queries)) {

    $namestr = get_string('name');
    $querystr = get_string('query', 'report_etl');
    $cmdstr = get_string('cmds', 'report_etl');

    $table = new html_table();
    $table->head = array("<b>$namestr</b>", "<b>$querystr</b>", "<b>$cmdstr</b>");
    $table->align = array('left', 'left', 'left');
    $table->size = array('20%', '60%', '20%');
    $table->width = '90%';

    foreach ($queries as $query) {
        $params = array('plugin' => $plugin, 'what' => 'edit', 'id' => $query->id);
        $queriesurl = new moodle_url('/report/etl/managequeries.php', $params);
        $commands = '<a href="'.$queriesurl.'" title="'.get_string('edit').'"><img src="'.$OUTPUT->pixurl('/t/edit').'"></a>';

        $params = array('plugin' => $plugin, 'what' => 'delete', 'id' => $query->id);
        $queriesurl = new moodle_url('/report/etl/managequeries.php', $params);
        $commands .= '<a href="'.$queriesurl.'" title="'.get_string('delete').'"><img src="'.$OUTPUT->pix_url('/t/delete').'"></a>';
        $table->data[] = array($query->name, '<pre>'.$query->query.'</pre>', $commands);
    }

    html_writer::table($table);
    unset($table);
} else {
    echo get_string('noqueries', 'report_etl');
}

$params = array( 'etlplugin' => $plugin, 'what' => 'add' );
echo $OUTPUT->single_button(new moodle_url('/report/etl/managequeries.php', $params), get_string('addaquery', 'report_etl'));

$opts = array( 'plugin' => $plugin);
echo $OUTPUT->single_button(new moodle_url('/report/etl/index.php', $opts), get_string('backtoetl', 'report_etl'));

echo '</center>';

echo $OUTPUT->footer();
