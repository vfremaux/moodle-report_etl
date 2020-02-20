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
require_once($CFG->dirroot.'/report/etl/plugins/boardz/extractor.class.php');

$systemcontext = context_system::instance();
$url = new moodle_url('/report/etl/plugins/boardz/get.php');

$PAGE->set_context($systemcontext);
$PAGE->set_url($url);

$boardzid = required_param('id', PARAM_INT);
$key = required_param('key', PARAM_RAW);
$testmode = optional_param('mode', '', PARAM_TEXT);
$plugin = optional_param('plugin', 'boardz', PARAM_TEXT);
$method = optional_param('method', 'des', PARAM_ALPHA);

if (!in_array($method, ['des', 'rsa']) {
    print_error("$method cannot be invoked in direct URL Web feed.");
}

try {
    $etl_environment = new boardz_extractor($boardzid, $key, $method);
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

    echo $etl_environment->extract($testmode);
    if ($testmode == 'test') {
        echo '</pre>';
        echo '<br/>';
        print_continue(new moodle_url('/report/etl/index.php', array('plugin' => $plugin)));
        echo $OUTPUT->footer();
    }
} else {
    if ($testmode == 'test') {
        print_error('errorkey', 'reportetl_boardz');
    } else {
        echo "<?xml version=\"1.0\"  encoding=\"UTF-8\" ?>\n<error>\n";
        echo "<errormsg>Something is wrong with your key, or you do not have access to this service.</errormsg>\n";
        echo "</error>\n"; 
        die;
    }
}
