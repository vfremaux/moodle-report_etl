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

namespace report_etl;

use \StdClass;

defined('MOODLE_INTERNAL') || die();

abstract class etl_extractor {

    public $id;
    public $name;
    public $parms;
    public $query;
    public $etlqueries;
    public $method;

    // Extraction results.
    public $firstrectime;
    public $lastrectime;
    public $resultcount;

    /**
     * get an instance of a subplugin, given its class name and instance
     * id.
     * @param string $plugin the ETL plugin name.
     * @param int $id the plugin instance id
     * @param string $key auth key if required, can be an encrypted or clear jsoned associative array of query params.
     * @param string $method auth method (algorithm)
     */
    public static function instance($plugin, $id, $key = null, $method = null, $local = false) {
        global $CFG;

        if (file_exists($CFG->dirroot.'/report/etl/plugins/'.$plugin.'/extractor.class.php')) {
            include_once($CFG->dirroot.'/report/etl/plugins/'.$plugin.'/extractor.class.php');
            $classname = "\\report_etl\\{$plugin}_extractor";

            $plugin = new $classname($id, $key, $method, $local);

            return $plugin;
        }
        return null;
    }

    /**
     * Extractor builder.
     * @param string $key a (possibly encrypted) set of extraction parameters inclugin query (dataset name)
     * @param string $method method (algorithm) for decoding the extraction key.
     * @param bool $local if true, instanciates an empty extractor.
     */
    protected function __construct($key, $method = 'des', $local = false) {

        $this->method = $method;
        $this->parms = new StdClass();

        // This is an instance constructed from inside for local servicing.
        if ($local) {
            return;
        }

        $keyobject = $this->decode($key);
        if (!$keyobject) {
            throw new Exception('key error');
        } else {
            $this->query = $keyobject->query;
            $keyarray = get_object_vars($keyobject);
            if (!empty($keyarray)) {
                foreach ($keyarray as $key => $value) {
                    if ($key != 'query') {
                        $this->parms->$key = $value;
                    }
                }
            }

            $this->etlqueries = [];
        }
    }

    /**
     * decodes information hidden in the etl key 
     * default accepts a JSON encoded array;
     * @param string $key
     */
    abstract function decode($key);

    /**
     * deletes the current instance, removing all what needs to be removed.
     */
     abstract function delete();

    /**
     *
     */
     abstract function get_name();

    /**
     * Reset extraction dates
     */
     abstract function reset();

    /**
     * read config and setup stored queries
     */
    public function config() {
        global $DB;

        if (empty($this->name)) {
            print_error('cannotconfigure', 'report_etl');
            return;
        }

        $queries = $DB->get_records('report_etl_query', array('plugin' => $this->name));

        if (!empty($queries)) {
            foreach ($queries as $query) {
                $querystring = $query->query;

                // We replace some placeholders in queries.
                $params = get_object_vars($this->parms);
                if (!empty($params)) {
                    foreach ($params as $key => $value) {
                        $querystring = preg_replace("/\\?$key\\b/", $value, $querystring);
                    }
                }
                $this->etlqueries[$query->name] = $querystring;
            }
        }
    }

    /**
     * extracts data and generate output with it. The output is given as
     * a string stream reference on which output can be concatenated.
     * @param bool $testmode
     * @param reference $results metadata over results as output.
     */
    public function extract($testmode = false, &$results = null) {
        global $DB;

        if (empty($this->query)) {
            throw new \moodle_exception("Empty query in extractor");
        }

        // Checks "in progress" status and mark it.
        debug_trace("Starting ETL extraction on $this->query ");

        $results = new Stdclass;
        $results->recordset = $this->query;

        $str = '';

        if (get_config('etlinprogress', 'etl') == 1) {
            if ($testmode == 'test') {
                $str .= get_string('etlbusy', 'reportetl');
            } else {
                $str .= "<?xml version=\"1.0\"  encoding=\"UTF-8\" ?>\n<etlerror>\n";
                $str .= "<errorcode>ETLBUSY</errorcode>";
                $str .= "<errormessage>ETL already in progress. Only one extraction is allowed</errormessage>";
                $str .= "</etlerror>";
            }
        } else {
            set_config('etlinprogress', 1, 'etl');
        }

        // Allows locally derouting the extraction for customization.
        if (preg_match("/^special_(.*)/", $this->query, $matches)) {
            $functionname = 'extract_'.$matches[1];
            debug_trace("Special ETL query $this->query. Calling method $functionname");
            if (method_exists($this, $functionname)) {
                $perfs = $this->$functionname($output, $testmode);

                // Performance.
                $results->perfreport = '';
                if ($perfs) {
                    $results->perfreport = "[overall:{$perfs['TOTAL']},records:{$perfs['RECORDS']},qualifiers:{$perfs['QUALIFIERS']},";
                    $results->perfreport .= "indicators:{$perfs['INDICATORS']},output:{$perfs['OUTPUT']}]";
                    $results->records = $perfs['NUMRECORDS'];
                    $results->firsttime = $perfs['FIRSTTIME'];
                    $results->lasttime = $perfs['LASTTIME'];
                }

                set_config('etlinprogress', 0, 'etl');
                return $output;
                // Finish here.
            } else {
                set_config('etlinprogress', 0, 'etl');
                print_error('errormissingfunction', 'report_etl', $functionname);
            }
        }

        // Starting default extraction.
        if (!array_key_exists($this->query, $this->etlqueries)) {
            set_config('etlinprogress', 0, 'etl');
            return false;
        }

        $testclause = '';
        if ($testmode == 'test') {
            $testclause = ' LIMIT 0,30 ';
        }

        debug_trace("Starting Default ETL extraction for $this->query ");
        $sql = $this->etlqueries[$this->query].$testclause;

        $str .= "<?xml version=\"1.0\"  encoding=\"UTF-8\" ?>\n<logrecords>\n";

        $lasttimes = array();

        $rs = $DB->get_recordset_sql($sql);
        $i = 0;
        if ($rs->RecordCount()) {
            while ($u = $rs->fetch_next_record()) {
                $u->host = $CFG->wwwroot;

                // Search nearest log of this user in the past.
                if (!array_key_exists($u->userid, $lasttimes)) {
                    $fetchbacksql = "
                        SELECT
                            id,
                            MAX(timecreated) as nearest
                        FROM
                            {logstore_standard_log}
                        WHERE
                            `timecreated` <= ? AND
                            userid = ?
                        GROUP BY
                            userid
                    ";
                    if ($rec = $DB->get_record_sql($fetchbacksql, array($this->parms->from, $u->userid))) {
                        $lasttimes[$u->userid] = $rec->nearest;
                    }
                }

                if (array_key_exists($u->userid, $lasttimes)) {
                    $u->gap = $u->time - $lasttimes[$u->userid];
                    $lasttimes[$u->userid] = $u->time;
                } else {
                    $u->gap = -1;
                }
                $str .= recordtoxml($u, $i, 'logrecord', '');
                $i++;
            }
            $rs->close();

            // Don't forget last record !!
            if ($i > 0) {
                $str .= recordtoxml($u, $i, 'logrecord', '');
            }
        }

        $str .= "\n</logrecords>";
        set_config('etlinprogress', 0, 'etl');
        return $str;
    }

    /**
     *
     *
     */
    abstract function get_access_url();

    /**
     * adds a param value for place holders
     * @param string $key
     * @param string $value
     */
    public function setparam($key, $value) {
        $this->parms->{$key} = $value;
    }
}
