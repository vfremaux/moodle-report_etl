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

/*
 * Implements
 * get()
 */

/**
 * @package report_etl
 * @category report
 * @author Valery Fremaux (valery.fremaux@gmail.com)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");
require_once($CFG->dirroot."/report/etl/classes/extractor.class.php");
require_once($CFG->dirroot.'/report/etl/lib.php');
require_once($CFG->dirroot.'/report/etl/xmllib.php');

use report_etl\etl_extractor;

class report_etl_external extends external_api {

    public static function get_parameters() {
        return new external_function_parameters([
            'plugin' => new external_value(PARAM_TEXT, 'The etl plugin'),
            'id' => new external_value(PARAM_INT, 'Extractor instance id'),
            'queryparams' => new external_value(PARAM_TEXT, 'Json serialized query params'),
            'testmode' => new external_value(PARAM_TEXT, ''),
        ]);
    }

    public static function get($plugin, $id, $queryparams, $testmode) {

        $configparamdefs = self::get_parameters();
        $inputs = [
            'plugin' => $plugin,
            'id' => $id,
            'queryparams' => $queryparams,
            'testmode' => $testmode
        ];
        // Standard validation for input data types.
        self::validate_parameters($configparamdefs, $inputs);

        if ($id == 0) {
            throw new invalid_parameter_exception("ETL id cannot be null");
        }

        debug_trace("ws::get() Invoking WS instance for $plugin, $id, $queryparams, 'none' auth with testmode=$testmode");

        // $queryparams needs to be a json encoded string, not encrypted (WS api already authentifies transaction by itself).
        $etlenvironment = etl_extractor::instance($plugin, $id, $queryparams, 'none');

        debug_trace("ws::get() Instance OK");

        @set_time_limit(0);
        ini_set('memory_limit', '512M');

        $result = new StdClass;
        debug_trace("ws::get() Calling extraction");
        $result->xmlcontent = $etlenvironment->extract($testmode, $results);
        $result->recordset = $etlenvironment->query;
        $result->records = $results->records;
        $result->firsttime = $results->firsttime;
        $result->lasttime = $results->lasttime;

        return $result;

    }

    public static function get_returns() {
        return new external_function_parameters([
            'recordset' => new external_value(PARAM_TEXT, 'Name of the record set'),
            'records' => new external_value(PARAM_INT, 'Count of extracted records'),
            'firsttime' => new external_value(PARAM_INT, 'First timstamp in records'),
            'lasttime' => new external_value(PARAM_INT, 'Last timstamp in records'),
            'xmlcontent' => new external_value(PARAM_RAW, 'Xml formated ouput flow'),
        ]);
    }

    public static function get_sql_parameters() {
        return new external_function_parameters([
            'sql' => new external_value(PARAM_TEXT, 'The etl plugin'),
        ]);
    }

    public static function get_sql($sql) {
        global $DB;

        debug_trace("ETL/Get SQL : $sql");

        try {
            $records = $DB->get_records_sql($sql, []);
        } catch (Exception $ex) {
            $sqlerror = $DB->get_last_error();
            $xmlout = "<?xml version=\"1.0\"  encoding=\"UTF-8\" ?>\n<error>\n";
            $xmlout .= "<errormsg>Query error: {$sqlerror}</errormsg>\n";
            $xmlout .= "</error>\n"; 

            $result = new StdClass;
            $result->recordset = 'Raw SQL Result (Error)';
            $result->records = count($records);
            $result->xmlcontent = $xmlout;
        }

        $result = new StdClass;
        $result->recordset = 'Raw SQL Result';
        $result->records = count($records);

        $xmlout = "<?xml version=\"1.0\"  encoding=\"UTF-8\" ?>\n<sqlrecords>\n";
        $i = 0;
        foreach ($records as $record) {
            $xmlout .= recordtoxml($record, $i, 'sqlrecord', '');
            $i++;
        }
        $xmlout .= '</sqlrecords>';

        $result->xmlcontent = $xmlout;

        return $result;
    }

    public static function get_sql_returns() {
        return new external_function_parameters([
            'recordset' => new external_value(PARAM_TEXT, 'Name of the record set'),
            'records' => new external_value(PARAM_INT, 'Count of extracted records'),
            'xmlcontent' => new external_value(PARAM_RAW, 'Xml formated ouput flow'),
        ]);
    }

}
