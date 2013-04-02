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
 * This utility file allows to : 
 * - generate a certificate to test keyed extraction credentials
 * - generate a crypted test ticket to be used on extractor
 * - give a link to test the GET webservice
 *
 */

    require_once('../../../../../../config.php');
    require_once($CFG->dirroot.'/admin/report/etl/lib.php');
    require_once($CFG->dirroot.'/admin/report/etl/plugins/toamoodle/extractor.class.php');

    // Security checks. Non admins CANNOT use as backdoor
    require_login();

    $systemcontext = get_context_instance(CONTEXT_SYSTEM, 0);
    if (!has_capability('moodle/site:doanything', $systemcontext)){
        error("This page cannot be used by non admins");
        die;
    }

/// Navigation

    $navlinks[] = array('title' => 'TOA ETL Access check', 'link' => '', 'name' => '', 'type' => 'title');

    print_header_simple('', '', build_navigation($navlinks));

/// Navigation
    
    print_heading(get_string('sslcheck', 'toamoodle', '', $CFG->dirroot.'/admin/report/etl/plugins/toamoodle/lang/'));
    
    print_box_start();
    echo get_string('sslcheckadvice', 'toamoodle', '', $CFG->dirroot.'/admin/report/etl/plugins/toamoodle/lang/');
    print_box_end();
    
    $toaid = optional_param('toa', 1, PARAM_INT);

    $new_key = openssl_pkey_new($CFG->opensslcnf);

    $dn = array(
       "countryName" => "FR",
       "stateOrProvinceName" => "VO",
       "localityName" => "Cergy",
       "organizationName" => "ValEISTI",
       "organizationalUnitName" => 'Moodle',
       "commonName" => $CFG->wwwroot,
       "emailAddress" => "valery@valeisti.fr" );
    $dn["commonName"] = preg_replace(':/$:', '', $dn["commonName"]);

    // make a certificate for signing and making public part

    $csr_rsc = openssl_csr_new($dn, $new_key, array('private_key_bits' => 2048, 'config' => $CFG->opensslcnf));
    $selfSignedCert = openssl_csr_sign($csr_rsc, null, $new_key, 365);
    unset($csr_rsc); // Free up the resource

    openssl_x509_export($selfSignedCert, $string_cert);
    openssl_x509_free($selfSignedCert);

    $priv = openssl_pkey_get_private($new_key);
    openssl_pkey_export($priv, $outp);

    echo "<pre>";
    echo $outp;
    echo "</pre>";
    echo "<pre>";
    echo $string_cert;
    echo "</pre>";

    if (!record_exists('toamoodle', 'id', $toaid)){
        $toa->id = $toaid;
        $toa->publickey = $string_cert;
        $toa->wwwroot = $CFG->wwwroot;
        insert_record('toamoodle', $toa);
    } else {
        set_field('toamoodle', 'publickey', $string_cert, 'id', $toaid);
    }
    
    $info->from = 0;
    $info->query = optional_param('query', 'special_prf1', PARAM_TEXT);
    $ticket = toa_make_ticket($info, $priv, 'rsa');

    echo "<pre>";
    echo chunk_split(bin2hex($ticket),64, "\n");
    echo "</pre>";

    echo "<br/>";
    echo "<form name=\"launchform\" method=\"POST\" action=\"{$CFG->wwwroot}/admin/report/etl/plugins/toamoodle/get.php\">";
    echo "<input type=\"hidden\" name=\"key\" value=\"".urlencode($ticket)."\" />";
    echo "<input type=\"hidden\" name=\"id\" value=\"$toaid\" />";
    echo "<input type=\"hidden\" name=\"plugin\" value=\"toa\" />";
    echo "<input type=\"hidden\" name=\"method\" value=\"rsa\" />";
    echo "<input type=\"checkbox\" name=\"mode\" value=\"test\" /> ".get_string('testmode', 'toamoodle', '', $CFG->dirroot.'/admin/report/etl/plugins/toamoodle/lang/').'<br/>';
    echo "<input type=\"submit\" name=\"go_btn\" value=\"".get_string('testextraction', 'toamoodle', '', $CFG->dirroot.'/admin/report/etl/plugins/toamoodle/lang/')."\" />";
    echo "</form>";

    print_footer();

?>