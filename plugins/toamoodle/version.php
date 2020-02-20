<?php // $Id: version.php,v 1.1 2010-02-15 15:35:57 cvsprf Exp $

////////////////////////////////////////////////////////////////////////////////
//  Code fragment to define the report plugin version etc.
//  This fragment is called by /admin/index.php
////////////////////////////////////////////////////////////////////////////////

$plugin->version  = 2019071400;
$plugin->requires = 2018112800;  // Requires this Moodle version
$plugin->maturity = MATURITY_BETA;
$plugin->release = "3.6.0 (Build 2019071400)";
$plugin->component = 'reportetl_toamoodle';
$plugin->dependencies = array('report_etl' => '2019071400');