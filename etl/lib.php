<?php

/**
 * Moodle - Modular Object-Oriented Dynamic Learning Environment
 *          http://moodle.org
 * Copyright (C) 1999 onwards Martin Dougiamas  http://dougiamas.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    moodle
 * @subpackage etl
 * @author     Valery Fremaux <valery.Fremaux@club-internet.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 1999 onwards Martin Dougiamas  http://dougiamas.com
 *
 * A general ETL related library and base classes
 *
 */

include_once $CFG->libdir."/pear/HTML/AJAX/JSON.php";

abstract class etl_extractor{

    var $id;
    var $name;
    var $parms;
    var $query;
    var $etlqueries;
    var $method;
    
    function __construct($key, $method = 'des', $local = false){

        $this->method = $method;
        $this->parms = new StdClass;

        // this is an instance constructed from inside for local servicing.        
        if ($local) return ;

        $keyobject = $this->decode($key);
        if (!$keyobject){
            throw new Exception('key error');
        } else {
            $this->query = $keyobject->query;
            $keyarray = get_object_vars($keyobject);
            if (!empty($keyarray)){
                foreach($keyarray as $key => $value){
                    if ($key != 'query')
                        $this->parms->$key = $value;
                }
            }
            
            $this->etlqueries = array();
        }
        
    }

    /** 
    * decodes information hidden in the etl key 
    * default accepts a JSON encoded array;
    * @param string $key
    **/
    abstract function decode($key);

    /** 
    * read config and setup stored queries
    **/
    function config(){
        if (empty($this->name)){
            error('This is a dummy etl object. Something must be wrong. Cannot configure.');
            return;
        }
        $queries = get_records('etl_query', 'plugin', $this->name);
        if (!empty($queries)){
            foreach($queries as $query){
                $querystring = $query->query;
                
                // we replace some placeholders in queries
                $params = get_object_vars($this->parms);
                if(!empty($params)){                
                    foreach($params as $key => $value){
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
    * @param reference $output the output text stream
    */
    function extract($testmode = false){
        global $CFG;

        /// checks "in progress" status and mark it
        
        if (get_config('etlinprogress', 'etl') == 1){        
            if ($testmode == 'test'){
                error("ETLBUSY: ETL already in progress. Only one extraction is allowed");
            } else {
                echo "<?xml version=\"1.0\"  encoding=\"UTF-8\" ?>\n<etlerror>\n";
                echo "<errorcode>ETLBUSY</errorcode>";
                echo "<errormessage>ETL already in progress. Only one extraction is allowed</errormessage>";
                echo "</etlerror>";
            }
        } else {
            set_config('etlinprogress', 1, 'etl');
        }

        // allows locally derouting the extraction for customization
        if (preg_match("/^special_(.*)/", $this->query, $matches)){
            $functionname = 'extract_'.$matches[1];
            if (method_exists($this, $functionname)){
                $perfs = $this->$functionname($output, $testmode);
                
                //** Performance **//
                $perfreport = '';
                if ($perfs){
                    $perfreport = "[overall:{$perfs['TOTAL']},records:{$perfs['RECORDS']},qualifiers:{$perfs['QUALIFIERS']},indicators:{$perfs['INDICATORS']},output:{$perfs['OUTPUT']}]";
                }
                //**//
                
                set_config('etlinprogress', 0, 'etl');                
                add_to_log(SITEID, 'report etl', 'extract', '', $this->query.'<br/>'.$perfreport);
                return;
                // finish here.
            } else {
                set_config('etlinprogress', 0, 'etl');
                error ("Missing extraction function $functionname");
            }
        }

        // starting default extraction
        if (!array_key_exists($this->query, $this->etlqueries)){
            set_config('etlinprogress', 0, 'etl');
            return false;
        }

        $testclause = '';
        if ($testmode == 'test'){
            $testclause = ' LIMIT 0,30 ';
        }        

        $sql = $this->etlqueries[$this->query].$testclause;
        
        if ($testmode == 'test'){            
        } else {
            add_to_log($SITEID, 'report etl', 'extract', '', $this->query);
            echo "<?xml version=\"1.0\"  encoding=\"UTF-8\" ?>\n<logrecords>\n";
        }
        
        $lasttimes = array();

        $rs = get_recordset_sql($sql);
        $i = 0;
        if ($rs->RecordCount()) {
            while ($u = rs_fetch_next_record($rs)) {
                $u->host = $CFG->wwwroot;

                // search nearest log of this user in the past.
                if (!array_key_exists($u->userid, $lasttimes)){
                    $fetchbacksql = "
                        SELECT
                            id,
                            MAX(time) as nearest
                        FROM
                            {$CFG->prefix}log
                        WHERE
                            `time` <= {$this->parms->from} AND
                            userid = {$u->userid}
                        GROUP BY
                            userid
                    ";
                    if($rec = get_record_sql($fetchbacksql)){
                        $lasttimes[$u->userid] = $rec->nearest;
                    }
                }

                if (array_key_exists($u->userid, $lasttimes)){
                    $u->gap = $u->time - $lasttimes[$u->userid];
                    $lasttimes[$u->userid] = $u->time;
                } else {
                    $u->gap = -1;
                }
                if ($testmode == 'test'){
                    echo htmlentities(recordtoxml($u, $i, 'logrecord', ''), ENT_QUOTES, 'UTF-8');
                } else {
                    echo recordtoxml($u, $i, 'logrecord', '');
                }
                $i++;
            }

            // don't forget last record !!
            if ($i > 0){
                if ($testmode == 'test'){
                    echo htmlentities(recordtoxml($u, $i, 'logrecord', ''), ENT_QUOTES, 'UTF-8');
                } else {
                    echo recordtoxml($u, $i, 'logrecord', '');
                }
            }
        }
        rs_close($rs);        

        echo "\n</logrecords>";
        set_config('etlinprogress', 0, 'etl');
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
    function setparam($key, $value){
        $this->parms->{$key} = $value;
    }
}

/**
* get all subplugins of ETL
*
*/
function etl_get_plugins(){
    
    $plugins = get_list_of_plugins('admin/report/etl/plugins', '', $basedir='');
    
    return $plugins;
    
}

/**
* get an instance of a subplugin, given its class name and instance
* id.
*
*/
function etl_get_plugin_instance($plugin, $id){
    global $CFG;
    
    if (file_exists($CFG->dirroot."/admin/report/etl/plugins/{$plugin}/extractor.class.php")){
        include_once $CFG->dirroot."/admin/report/etl/plugins/{$plugin}/extractor.class.php";
        $classname = "{$plugin}_extractor";
        $plugin = new $classname($id, null, null, true); // instanciate internally without a remote key.
        return $plugin;
    }
    
    return null;
}

/**
* get an instance of a subplugin
*
*/
function etl_plugin_has_config($plugin){
    global $CFG;

    return file_exists($CFG->dirroot."/report/etl/plugins/{$plugin}/config_form.php");
    
}

/**       
*
*
*/
function etl_error($msg){
    $testmode = optional_param('mode', '', PARAM_INT);
    if ($testmode == 'test'){
        error($msg);
    } else {
        echo "<?xml version=\"1.0\"  encoding=\"UTF-8\" ?>\n";
        echo "<etlerror>\n";
        echo "\t<errormsg>$msg</errormsg>";
        echo "</etlerror>\n";
        die;
    }
}

?>