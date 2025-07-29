<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/ddllib.php'); // Include ddllib for xmldb constants

/**
 * Upgrade script for block_aiassistant
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool result
 */
function xmldb_block_aiassistant_upgrade($oldversion) {
    global $DB;
    
    $dbman = $DB->get_manager();

    // Add upgrade steps here as needed
    if ($oldversion < 2025072909) {
        
        // Define table block_aiassistant_keys to be created
        $table = new xmldb_table('block_aiassistant_keys');

        // Adding fields to table block_aiassistant_keys
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('fireworks_key_id', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('fireworks_api_key', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('display_name', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('created_time', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('last_used', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('is_active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');

        // Adding keys to table block_aiassistant_keys
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid_key', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        // Adding indexes to table block_aiassistant_keys
        $table->add_index('userid_active', XMLDB_INDEX_NOTUNIQUE, ['userid', 'is_active']);
        $table->add_index('fireworks_key_id', XMLDB_INDEX_UNIQUE, ['fireworks_key_id']);

        // Conditionally launch create table for block_aiassistant_keys
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Aiassistant savepoint reached
        upgrade_block_savepoint(true, 2025072909, 'aiassistant');
    }

    return true;
}
