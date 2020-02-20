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

// Capabilities
$string['etl:export'] = 'Export data through ETL feeders';

$string['addinstance'] = 'Add ETL instance';
$string['editinstance'] = 'Edit ETL instance';
$string['pluginname'] = 'Data extraction (ETL)';
$string['configmaxxmlrecordsperget'] =  'Max output records per get query';
$string['configmaxxmlrecordsperget_desc'] =  'An absolute max amount of etl records that can be processed and output for a single get call.';
$string['deletecomplete'] = 'Instance deleted';
$string['etl'] = 'ETL';
$string['query'] = 'Query';
$string['noqueries'] = 'No queries';
$string['noinstances'] = 'No instances';
$string['queries'] = 'ETL Queries';
$string['addaquery'] = 'Add a new query';
$string['backtoetl'] = 'Back to etl index';
$string['manageplugin'] = 'Manage the plugin';
$string['manageparms'] = 'Manage parameters';
$string['managequeries'] = 'Manage queries';
$string['manageplugininstances'] = 'Manage plugin instances';
$string['getdata'] = 'Data extraction';
$string['updateparms'] = 'Update parameters';
$string['cannotconfigure'] = 'This is a dummy etl object. Something must be wrong. Cannot configure.';
$string['etlbusy'] = 'ETLBUSY: ETL already in progress. Only one extraction is allowed';
$string['reset'] = 'Reset';
$string['resetcomplete'] = 'Reset has completed';
$string['errormissingfunction'] = 'Missing extraction function {$a}';