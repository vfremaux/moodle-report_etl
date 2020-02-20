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
 *
 * @package     report_etl
 * @category    report
 * @author      Valery Fremaux <valery.Fremaux@club-internet.fr>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright   (C) 1999 onwards Martin Dougiamas  http://dougiamas.com
 *
 * A general ETL related library and base classes
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/report/etl/classes/extractor.class.php');

/**
 * get all subplugins of ETL
 *
 */
function etl_get_plugins() {

    $plugins = get_list_of_plugins('/report/etl/plugins', '', $basedir = '');

    return $plugins;
}

/**
 * get an instance of a subplugin
 *
 */
function etl_plugin_has_config($plugin) {
    global $CFG;

    return file_exists($CFG->dirroot.'/report/etl/plugins/'.$plugin.'/config_form.php');
}

/**
 * Deprecate or merge with following.
 *
 */
function etl_error($msg) {
    $testmode = optional_param('mode', '', PARAM_INT);
    if ($testmode == 'test') {
        print_error($msg);
    } else {
        echo "<?xml version=\"1.0\"  encoding=\"UTF-8\" ?>\n";
        echo "<etlerror>\n";
        echo "\t<errormsg>$msg</errormsg>";
        echo "</etlerror>\n";
        die;
    }
}

/**
 * a small error function to generate XML error report.
 *
 */
function send_xml_error($errstring, &$etl_environment) {
    echo "<?xml version=\"1.0\"  encoding=\"{$etl_environment->outputencoding}\" ?>\n<error>\n";
    echo "<errormsg>$errstring</errormsg>\n";
    echo "</error>\n";
    die;
}

/**
 * Get all instances (objects) of some etl plugin type.
 * @param string $plugintype
 * @return array of objects.
 */
function etl_get_instances($plugintype) {
    global $DB;

    $instances = [];

    $table = 'reportetl_'.$plugintype;

    $records = $DB->get_records($table, [], 'id', 'id,id');

    if (!empty($records)) {
        foreach ($records as $rec) {
            $instances[] = \report_etl\etl_extractor::instance($plugintype, $rec->id, null, null, true);
        }
    }

    return $instances;
}