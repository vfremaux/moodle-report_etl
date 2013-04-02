<?php

// This file keeps track of upgrades to
// the chat module
//
// Sometimes, changes between versions involve
// alterations to database structures and other
// major things that may break installations.
//
// The upgrade function in this file will attempt
// to perform all the necessary actions to upgrade
// your older installtion to the current version.
//
// If there's something it cannot do itself, it
// will tell you what you need to do.
//
// The commands in here will all be database-neutral,
// using the functions defined in lib/ddllib.php

function xmldb_report_etl_toamoodle_upgrade($oldversion=0){
    
    global $CFG, $THEME, $db;

    $result = true;
    
    if ($oldversion < 2009111600){
    /// Define field course to be added to magtest
        $table = new XMLDBTable('toamoodle');
        $field = new XMLDBField('masquerade');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, 0, 'toaipmask');

    /// Launch add field course
        $result = $result && add_field($table, $field);
    }

    if ($oldversion < 2009121400){
    /// Define field course to be added to magtest
        $table = new XMLDBTable('toamoodle');

        $field = new XMLDBField('lastextractactions');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '11', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, 0, 'lastextract');

    /// Launch add field course
        $result = $result && add_field($table, $field);

        $field = new XMLDBField('lastextractacademics');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '11', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, 0, 'lastextractactions');

    /// Launch add field course
        $result = $result && add_field($table, $field);

    /// Launch add field course
        $result = $result && add_field($table, $field);

        $field = new XMLDBField('lastextractdocuments');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '11', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, 0, 'lastextractacademics');

    /// Launch add field course
        $result = $result && add_field($table, $field);

    /// Launch add field course
        $result = $result && add_field($table, $field);

        $field = new XMLDBField('lastextractcommunications');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '11', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, 0, 'lastextractdocuments');

    /// Launch add field course
        $result = $result && add_field($table, $field);

    /// Launch add field course
        $result = $result && add_field($table, $field);

        $field = new XMLDBField('lastextractgrades');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '11', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, 0, 'lastextractcommunications');

    /// Launch add field course
        $result = $result && add_field($table, $field);
    }

    return $result;
}

?>