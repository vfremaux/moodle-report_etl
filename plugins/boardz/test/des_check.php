<?php
/**
 * @package    report_etl
 * @author     Valery Fremaux <valery.Fremaux@club-internet.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 1999 onwards Martin Dougiamas  http://dougiamas.com
 *
 * This utility file allows to : 
 * - generate a DES crypted test ticket to be used on extractor
 * - give a link to test the GET webservice
 *
 */

require_once('../../../../../config.php');
require_once($CFG->dirroot.'/report/etl/lib.php');
require_once($CFG->dirroot.'/report/etl/plugins/boardz/locallib.php');
require_once($CFG->dirroot.'/report/etl/plugins/boardz/extractor.class.php');

$systemcontext = context_system::instance();
$url = new moodle_url('/report/etl/plugins/boardz/tests/des_check.php');
$PAGE->set_context($systemcontext);
$PAGE->set_url($url);

// Security checks. Non admins CANNOT use as backdoor
require_login();
require_capability('moodle/site:config', $systemcontext);

/// Navigation

$PAGE->set_heading(get_string('descheck', 'reportetl_boardz'));
$PAGE->set_pagelayout('admin');
$PAGE->navbar->add('BOARDZ ETL Access check');

echo $OUTPUT->header();

/// Navigation

echo $OUTPUT->heading(get_string('descheck', 'reportetl_boardz'));

echo $OUTPUT->box_start();
echo get_string('descheckadvice', 'reportetl_boardz');
echo $OUTPUT->box_end();

$id = optional_param('boardz', 1, PARAM_INT);

// sets up test realm 
if (! $boardzinstance = $DB->get_record('reportetl_boardz', array('id' => $id))) {
    $boardz = new StdClass;
    $boardz->id = $id;
    $boardz->wwwroot = $CFG->wwwroot;
    $boardz->publickey = 'boardz';
    $DB->insert_record('reportetl_boardz', $boardz);
} else {
    if (empty($boardzinstance->publickey)) {
        $DB->set_field('reportetl_boardz', 'publickey', 'boardz', array('id' => $id));
    }
}

// get record back
$boardz = $DB->get_record('reportetl_boardz', array('id' => $id));

$info = new StdClass();
$info->from = 0;
$info->query = optional_param('query', 'special_test', PARAM_TEXT);
$ticket = boardz_make_ticket($info, $boardz->publickey);

echo "<pre>";
echo chunk_split(bin2hex($ticket),64, "\n");
echo "</pre>";

echo "<br/>";
$actionurl = new moodle_url('/report/etl/get.php');
echo '<form name="launchform" method="POST" action="'.$actionurl.'">';
echo '<input type="hidden" name="key" value="'.urlencode($ticket).'" />';
echo '<input type="hidden" name="id" value="'.$id.'" />';
echo '<input type="hidden" name="plugin" value="boardz" />';
echo '<input type="hidden" name="method" value="des" />';
echo '<input type="checkbox" name="mode" value="test" /> '.get_string('testmode', 'reportetl_boardz').'<br/>';
echo '<input type="submit" name="go_btn" value="'.get_string('testextraction', 'reportetl_boardz').'" />';
echo '</form>';

echo $OUTPUT->footer();
