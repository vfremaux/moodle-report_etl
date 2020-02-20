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
 * the ETL submodule index page
 * presents summary of tools for this ETL plugin
 *
 */

require_once($CFG->dirroot.'/report/etl/plugins/toamoodle/locallib.php');
require_once($CFG->dirroot.'/report/etl/plugins/toamoodle/extractor.class.php');

if (!$counttoainstances = $DB->count_records('reportetl_toamoodle')) {
    $defaultinstance = new StdClass();
    $defaultinstance->wwwroot = $CFG->wwwroot;
    $defaultinstance->outputencoding = 'ISO-8859-1';
    $DB->insert_record('reportetl_toamoodle', $defaultinstance);
}

$etlssotoastr = 'ETL SSO Toa';
$etlssotoateststr = 'ETL SSO Toa (Test)';
$etldestoastr = 'ETL DES Toa';
$etldestoateststr = 'ETL DES Toa (Test)';

echo $OUTPUT->heading('Test RSA');

echo "Test Requête 1 : Requête test";
$queryurl = new moodle_url('/report/etl/plugins/toamoodle/test/sso_check.php', array('id' => 1, 'plugin' => 'toamoodle', 'query' => 'special_test'));
echo ' <a href="'.$queryurl.'">'.$etlssotoateststr.'</a><br/>';

echo "Test Requête 2 : assignations de rôles à date";
$queryurl = new moodle_url('/report/etl/plugins/toamoodle/test/sso_check.php', array('id' => 1, 'plugin' => 'toamoodle', 'query' => 'special_actions'));
echo ' <a href="'.$queryurl.'">'.$etlssotoateststr.'</a><br/>';

echo $OUTPUT->heading('Test DES');

$etldestoateststr = get_string('test_actions', 'reportetl_toamoodle');
$queryurl = new moodle_url('/report/etl/plugins/toamoodle/test/des_check.php', array('id' => 1, 'plugin' => 'toamoodle', 'query' => 'special_actions'));
echo ' <a href="'.$queryurl.'">'.$etldestoateststr.'</a><br/>';

$etldestoateststr = get_string('test_academics', 'reportetl_toamoodle');
$queryurl = new moodle_url('/report/etl/plugins/toamoodle/test/des_check.php', array('id' => 1, 'plugin' => 'toamoodle', 'query' => 'special_academics'));
echo ' <a href="'.$queryurl.'">'.$etldestoateststr.'</a><br/>';

$etldestoateststr = get_string('test_documents', 'reportetl_toamoodle');
$queryurl = new moodle_url('/report/etl/plugins/toamoodle/test/des_check.php', array('id' => 1, 'plugin' => 'toamoodle', 'query' => 'special_documents'));
echo ' <a href="'.$queryurl.'">'.$etldestoateststr.'</a><br/>';

$etldestoateststr = get_string('test_communications', 'reportetl_toamoodle');
$queryurl = new moodle_url('/report/etl/plugins/toamoodle/test/des_check.php', array('id' => 1, 'plugin' => 'toamoodle', 'query' => 'special_communications'));
echo ' <a href="'.$queryurl.'">'.$etldestoateststr.'</a><br/>';

$etldestoateststr = get_string('test_grades', 'reportetl_toamoodle');
$queryurl = new moodle_url('/report/etl/plugins/toamoodle/test/des_check.php', array('id' => 1, 'plugin' => 'toamoodle', 'query' => 'special_grades'));
echo ' <a href="'.$queryurl.'">'.$etldestoateststr.'</a><br/>';

$toa_environment = new report_etl\toamoodle_extractor(1, null, null, true);

echo $OUTPUT->heading(get_string('testssoprofile', 'reportetl_toamoodle'));

$identquery = new StdClass();
$identquery->date = time();
$identquery->login = $USER->username;
$identquery->query = '';
$identquery->fields = 'firstname,lastname,organisation,country';
$ssoprofileticket = toa_make_ticket($identquery, $toa_environment->publickey, 'des');
$ssourl = new moodle_url('/report/etl/plugins/toamoodle/sso.php', array('id' => 1, 'method' => 'des', 'key' => $ssoprofileticket));
$ssourlteststr = get_string('ssourltest', 'reportetl_toamoodle');
echo ' <a href="'.$ssourl.'" target="_blank">'.$ssourlteststr.'</a><br/>';

echo $OUTPUT->heading(get_string('testssoaccess', 'reportetl_toamoodle'));

$accessurlteststr = get_string('accessurltest', 'reportetl_toamoodle');
$accessurl = $toa_environment->get_access_url();
echo ' <a href="'.$accessurl.'">'.$accessurlteststr.'</a><br/>';
