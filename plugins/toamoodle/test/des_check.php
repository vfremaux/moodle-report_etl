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
require_once($CFG->dirroot.'/report/etl/plugins/toamoodle/locallib.php');
require_once($CFG->dirroot.'/report/etl/plugins/toamoodle/extractor.class.php');

$systemcontext = context_system::instance();
$url = new moodle_url('/report/etl/plugins/toamoodle/tests/des_check.php');
$PAGE->set_context($systemcontext);
$PAGE->set_url($url);

// Security checks. Non admins CANNOT use as backdoor
require_login();
require_capability('moodle/site:config', $systemcontext);

/// Navigation

$PAGE->set_heading(get_string('descheck', 'reportetl_toamoodle'));
$PAGE->set_pagelayout('admin');
$PAGE->navbar->add('TOA ETL Access check');

echo $OUTPUT->header();

/// Navigation

echo $OUTPUT->heading(get_string('descheck', 'reportetl_toamoodle'));

echo $OUTPUT->box_start();
echo get_string('descheckadvice', 'reportetl_toamoodle');
echo $OUTPUT->box_end();

$id = optional_param('toa', 1, PARAM_INT);

// sets up test realm 
if (! $toainstance = $DB->get_record('reportetl_toamoodle', array('id' => $id))) {
    $toa = new StdClass;
    $toa->id = $id;
    $toa->wwwroot = $CFG->wwwroot;
    $toa->publickey = 'toamoodle';
    $DB->insert_record('reportetl_toamoodle', $toa);
} else {
    if (empty($toainstance->publickey)) {
        $DB->set_field('reportetl_toamoodle', 'publickey', 'toamoodle', array('id' => $id));
    }
}

// get record back
$toa = $DB->get_record('toamoodle', array('id' => $id));

$info = new StdClass();
$info->from = 0;
$info->query = optional_param('query', 'special_test', PARAM_TEXT);
$ticket = toa_make_ticket($info, $toa->publickey);

echo "<pre>";
echo chunk_split(bin2hex($ticket),64, "\n");
echo "</pre>";

echo "<br/>";
$actionurl = new moodle_url('/report/etl/get.php');
echo '<form name="launchform" method="POST" action="'.$actionurl.'">';
echo '<input type="hidden" name="key" value="'.urlencode($ticket).'" />';
echo '<input type="hidden" name="id" value="'.$id.'" />';
echo '<input type="hidden" name="plugin" value="toamoodle" />';
echo '<input type="hidden" name="method" value="des" />';
echo '<input type="checkbox" name="mode" value="test" /> '.get_string('testmode', 'reportetl_toamoodle').'<br/>';
echo '<input type="submit" name="go_btn" value="'.get_string('testextraction', 'reportetl_toamoodle').'" />';
echo '</form>';

echo $OUTPUT->footer();

