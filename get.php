<?php
// This file keeps track of upgrades to
// the chat module
//
// Sometimes, changes between versions involve
// alterations to database structures and other
// major things that may break installations.
//
// The upgrade function in this file will attempt
// to perform all the necessary actions to upgrade
// your older installtion to the current version.
//
// If there's something it cannot do itself, it
// will tell you what you need to do.
//
// The commands in here will all be database-neutral,
// using the functions defined in lib/ddllib.php

/**
 * @package    moodle_etl
 * @author     Valery Fremaux <valery.Fremaux@club-internet.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 1999 onwards Martin Dougiamas  http://dougiamas.com
 *
 * The GET page extracts data from Moodle using an ETL extractor.
 */

require_once('../../config.php');
require_once($CFG->dirroot.'/report/etl/lib.php');
require_once($CFG->dirroot.'/report/etl/classes/extractor.class.php');

$id = required_param('id', PARAM_INT);
$key = required_param('key', PARAM_RAW);
$testmode = optional_param('mode', '', PARAM_TEXT);
$plugin = optional_param('plugin', 'toamoodle', PARAM_TEXT);

$systemcontext = context_system::instance();
$url = new moodle_url('/report/etl/get.php', array('plugin' => $plugin, 'key' => $key, 'mode' => $testmode, 'id' => $id));

// Security : defered into the extractor class.

$PAGE->set_context($systemcontext);
$PAGE->set_url($url);

if (!file_exists($CFG->dirroot.'/report/etl/plugins/'.$plugin.'/extractor.class.php')) {
    print_error('errornoplugin', 'report_etl');
}

require_once($CFG->dirroot.'/report/etl/plugins/'.$plugin.'/extractor.class.php');

$method = optional_param('method', 'des', PARAM_ALPHA);

try {
    $extractorclass = 'report_etl\\'.$plugin.'_extractor';
    $etl_environment = new $extractorclass($id, $key, $method);
} catch (Exception $e) {
    assert(true);
}

if (!empty($etl_environment)) {
    if ($testmode != 'test') {
        header("Document-Type:text/xml");
    } else {
        echo $OUTPUT->header();
        echo userdate($etl_environment->parms->from).'<br/>';
        echo '<pre>';
    }

    // Raise some physical parameters.
    @set_time_limit(0);
    ini_set('memory_limit', '512M');

    $etl_environment->extract($testmode);
    if ($testmode == 'test') {
        echo '</pre>';
        echo '<br/>';
        echo $OUTPUT->continue_button(new moodle_url('/report/etl/index.php', array('plugin' => $plugin)));
        echo $OUTPUT->footer();
    }
} else {
    if ($testmode == 'test') {
        print_error('errorkey', 'report_etl');
    } else {
        echo "<?xml version=\"1.0\"  encoding=\"UTF-8\" ?>\n<error>\n";
        echo "<errormsg>Something is wrong with your key, or you do not have access to this service.</errormsg>\n";
        echo "</error>\n"; 
        die;
    }
}
