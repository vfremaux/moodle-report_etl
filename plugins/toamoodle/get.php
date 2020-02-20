<?php
/**
 * @package    moodle
 * @subpackage etl
 * @author     Valery Fremaux <valery.Fremaux@club-internet.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 1999 onwards Martin Dougiamas  http://dougiamas.com
 *
 * The GET page extracts data from Moodle using an ETL extractor.
 *
 */

require_once('../../../../config.php');
require_once($CFG->dirroot.'/report/etl/lib.php');
require_once($CFG->dirroot.'/report/etl/plugins/toamoodle/extractor.class.php');

$systemcontext = context_system::instance();
$url = new moodle_url('/report/etl/plugins/toamoodle/get.php');

$PAGE->set_context($systemcontext);
$PAGE->set_url($url);

$toaid = required_param('id', PARAM_INT);
$key = required_param('key', PARAM_RAW);
$testmode = optional_param('mode', '', PARAM_TEXT);
$plugin = optional_param('plugin', 'toamoodle', PARAM_TEXT);
$method = optional_param('method', 'des', PARAM_ALPHA);

try {
    $etl_environment = new toamoodle_extractor($toaid, $key, $method);
} catch (Exception $e) {
}

if (!empty($etl_environment)) {
    if ($testmode != 'test') {
        header("Document-Type:text/xml");
    } else {
        echo $OUTPUT->header();
        echo userdate($etl_environment->parms->from).'<br/>';
        echo '<pre>';
    }

    // raise some physical parameters
    @set_time_limit(0);
    ini_set('memory_limit', '512M'); 

    $etl_environment->extract($testmode);
    if ($testmode == 'test') {
        echo '</pre>';
        echo '<br/>';
        print_continue(new moodle_url('/report/etl/index.php', array('plugin' => $plugin)));
        echo $OUTPUT->footer();
    }
} else {
    if ($testmode == 'test') {
        print_error('errorkey', 'reportetl_toamoodle');
    } else {
        echo "<?xml version=\"1.0\"  encoding=\"UTF-8\" ?>\n<error>\n";
        echo "<errormsg>Something is wrong with your key, or you do not have access to this service.</errormsg>\n";
        echo "</error>\n"; 
        die;
    }
}
