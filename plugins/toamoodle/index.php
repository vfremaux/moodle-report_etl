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
 * the ETL submodule index page
 * presents summary of tools for this ETL plugin
 *
 */

    require_once $CFG->dirroot.'/admin/report/etl/plugins/toamoodle/extractor.class.php';
    
    $locallangroot = $CFG->dirroot.'/admin/report/etl/plugins/toamoodle/lang/';
    
    if (!$counttoainstances = count_records('toamoodle')){
        $defaultinstance->wwwroot = $CFG->wwwroot;
        $defaultinstance->outputencoding = 'ISO-8859-1';
        insert_record('toamoodle', $defaultinstance);
    }

    $etlssotoastr = "ETL SSO Toa";
    $etlssotoateststr = "ETL SSO Toa (Test)";
    $etldestoastr = "ETL DES Toa";
    $etldestoateststr = "ETL DES Toa (Test)";

    print_heading('Test RSA');

    echo "Test Requête 1 : enregistrements logs 'à partir de'";
    echo " <a href=\"{$CFG->wwwroot}/admin/report/etl/plugins/toamoodle/test/sso_check.php?id=1&amp;plugin=toamoodle&query=special_prf1\">{$etlssotoateststr}</a><br/>";

    echo "Test Requête 2 : assignations de rôles à date";
    echo " <a href=\"{$CFG->wwwroot}/admin/report/etl/plugins/toamoodle/test/sso_check.php?id=1&amp;plugin=toamoodle&query=special_prf2\">{$etlssotoateststr}</a><br/>";

    print_heading('Test DES');

    $etldestoateststr = get_string('test_actions', 'toamoodle', '', $locallangroot);
    echo " <a href=\"{$CFG->wwwroot}/admin/report/etl/plugins/toamoodle/test/des_check.php?id=1&amp;plugin=toamoodle&query=special_actions\">{$etldestoateststr}</a><br/>";

    $etldestoateststr = get_string('test_academics', 'toamoodle', '', $locallangroot);
    echo " <a href=\"{$CFG->wwwroot}/admin/report/etl/plugins/toamoodle/test/des_check.php?id=1&amp;plugin=toamoodle&query=special_academics\">{$etldestoateststr}</a><br/>";

    $etldestoateststr = get_string('test_documents', 'toamoodle', '', $locallangroot);
    echo " <a href=\"{$CFG->wwwroot}/admin/report/etl/plugins/toamoodle/test/des_check.php?id=1&amp;plugin=toamoodle&query=special_documents\">{$etldestoateststr}</a><br/>";

    $etldestoateststr = get_string('test_communications', 'toamoodle', '', $locallangroot);
    echo " <a href=\"{$CFG->wwwroot}/admin/report/etl/plugins/toamoodle/test/des_check.php?id=1&amp;plugin=toamoodle&query=special_communications\">{$etldestoateststr}</a><br/>";

    $etldestoateststr = get_string('test_grades', 'toamoodle', '', $locallangroot);
    echo " <a href=\"{$CFG->wwwroot}/admin/report/etl/plugins/toamoodle/test/des_check.php?id=1&amp;plugin=toamoodle&query=special_grades\">{$etldestoateststr}</a><br/>";

    $toa_environment = new toamoodle_extractor(1, null, null, true); 

    print_heading(get_string('testssoprofile', 'toamoodle', '', $locallangroot));
    
    $identquery->date = time();
    $identquery->login = $USER->username;
    $identquery->query = '';
    $identquery->fields = 'firstname,lastname,organisation,country';
    $ssoprofileticket = toa_make_ticket($identquery, $toa_environment->publickey, 'des');
    $ssourl = $CFG->wwwroot."/admin/report/etl/plugins/toamoodle/sso.php?id=1&amp;method=des&amp;key=$ssoprofileticket";
    $ssourlteststr = get_string('ssourltest', 'toamoodle', '', $locallangroot);
    echo " <a href=\"$ssourl\" target=\"_blank\">{$ssourlteststr}</a><br/>";    

    print_heading(get_string('testssoaccess', 'toamoodle', '', $locallangroot));

    $accessurlteststr = get_string('accessurltest', 'toamoodle', '', $locallangroot);
    $accessurl = $toa_environment->get_access_url();
    echo " <a href=\"$accessurl\">{$accessurlteststr}</a><br/>";

?>