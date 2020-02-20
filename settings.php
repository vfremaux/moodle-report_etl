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
defined('MOODLE_INTERNAL') || die;

$hasconfig = false;
$hassiteconfig = false;
if (is_dir($CFG->dirroot.'/local/adminsettings')) {
    // Integration driven code
    if (has_capability('local/adminsettings:nobody', context_system::instance())) {
        $hasconfig = true;
        $hassiteconfig = true;
    } else if (has_capability('moodle/site:config', context_system::instance())) {
        $hasconfig = true;
        $hassiteconfig = false;
    }
} else {
    // Standard Moodle code
    $hassiteconfig = true;
    $hasconfig = true;
}

if ($hassiteconfig) {
    $label = get_string('pluginname', 'report_etl');
    $pageurl = new moodle_url('/report/etl/index.php"');
    $ADMIN->add('reports', new admin_externalpage('reportetlext', $label, $pageurl, 'moodle/site:config'));

    $key = 'report_etl/maxxmlrecordsperget';
    $label = get_string('configmaxxmlrecordsperget', 'report_etl');
    $desc = get_string('configmaxxmlrecordsperget_desc', 'report_etl');
    $default = 5000;
    $settings->add(new admin_setting_configtext($key, $label, $desc, $default));
}
