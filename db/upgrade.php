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

    // Debug output
    error_log("AI Assistant upgrade called: oldversion = $oldversion");

    // Add upgrade steps here as needed
    if ($oldversion < 2025072910) {
        
        error_log("AI Assistant: Creating table block_aiassistant_keys");
        
        // Define table block_aiassistant_keys to be created
        $table = new xmldb_table('block_aiassistant_keys');

        // Don't check if table exists - just try to create it
        // The XMLDB system handles this properly
        
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

        // Create table 
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
            error_log("AI Assistant: Table created successfully");
        } else {
            error_log("AI Assistant: Table already exists");
        }

        // Aiassistant savepoint reached
        upgrade_block_savepoint(true, 2025072910, 'aiassistant');
        error_log("AI Assistant: Upgrade completed successfully");
    }

    // Add upgrade for Claude API key storage
    if ($oldversion < 2025080704) {
        error_log("AI Assistant: Adding Claude API key field");
        
        $table = new xmldb_table('block_aiassistant_keys');
        
        // Add Claude API key field
        $field = new xmldb_field('claude_api_key', XMLDB_TYPE_TEXT, null, null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            error_log("AI Assistant: Claude API key field added successfully");
        }
        
        // Aiassistant savepoint reached
        upgrade_block_savepoint(true, 2025080704, 'aiassistant');
        error_log("AI Assistant: Claude upgrade completed successfully");
    }

    // Add upgrade for conversations table
    if ($oldversion < 2025092201) {
        error_log("AI Assistant: Creating conversations table");
        
        // Define table block_aiassistant_conv to be created
        $table = new xmldb_table('block_aiassistant_conv');

        // Adding fields to table block_aiassistant_conv
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('conversation_id', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('created_time', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('last_updated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('is_active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('metadata', XMLDB_TYPE_TEXT, null, null, null, null, null);

        // Adding keys to table block_aiassistant_conv
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid_key', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        // Adding indexes to table block_aiassistant_conv
        $table->add_index('conversation_id_unique', XMLDB_INDEX_UNIQUE, ['conversation_id']);
        $table->add_index('userid_active', XMLDB_INDEX_NOTUNIQUE, ['userid', 'is_active']);
        $table->add_index('userid_last_updated', XMLDB_INDEX_NOTUNIQUE, ['userid', 'last_updated']);

        // Create table if it doesn't exist
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
            error_log("AI Assistant: Conversations table created successfully");
        } else {
            error_log("AI Assistant: Conversations table already exists");
        }

        // Aiassistant savepoint reached
        upgrade_block_savepoint(true, 2025092201, 'aiassistant');
        error_log("AI Assistant: Conversations table upgrade completed successfully");
    }

    // Add upgrade for messages table
    if ($oldversion < 2025092304) {
        error_log("AI Assistant: Creating messages table");
        
        // Define table block_aiassistant_messages to be created
        $table = new xmldb_table('block_aiassistant_messages');

        // Adding fields to table block_aiassistant_messages
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('conversation_id', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('message_type', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('content', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('created_time', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sequence_number', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('metadata', XMLDB_TYPE_TEXT, null, null, null, null, null);

        // Adding keys to table block_aiassistant_messages
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('conversation_fk', XMLDB_KEY_FOREIGN, ['conversation_id'], 'block_aiassistant_conv', ['conversation_id']);

        // Adding indexes to table block_aiassistant_messages
        $table->add_index('conv_sequence', XMLDB_INDEX_NOTUNIQUE, ['conversation_id', 'sequence_number']);
        $table->add_index('conv_time', XMLDB_INDEX_NOTUNIQUE, ['conversation_id', 'created_time']);
        $table->add_index('message_type_idx', XMLDB_INDEX_NOTUNIQUE, ['message_type']);
        $table->add_index('created_time_idx', XMLDB_INDEX_NOTUNIQUE, ['created_time']);

        // Create table if it doesn't exist
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
            error_log("AI Assistant: Messages table created successfully");
        } else {
            error_log("AI Assistant: Messages table already exists");
        }

        // Aiassistant savepoint reached
        upgrade_block_savepoint(true, 2025092304, 'aiassistant');
        error_log("AI Assistant: Messages table upgrade completed successfully");
    }

    return true;
}
