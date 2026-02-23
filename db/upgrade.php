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
 * Upgrade script for mod_craftpilot
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool result
 */
function xmldb_craftpilot_upgrade($oldversion) {
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
        upgrade_module_savepoint(true, 2025072910, 'craftpilot');
        error_log("AI Assistant: Upgrade completed successfully");
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
        upgrade_module_savepoint(true, 2025092201, 'craftpilot');
        error_log("AI Assistant: Conversations table upgrade completed successfully");
    }

    // Add upgrade for messages table
    if ($oldversion < 2025092304) {
        error_log("AI Assistant: Creating messages table");
        
        // Define table block_aiassistant_msg to be created
        $table = new xmldb_table('block_aiassistant_msg');

        // Adding fields to table block_aiassistant_msg
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('conversation_id', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('message_type', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('content', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('created_time', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sequence_number', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('metadata', XMLDB_TYPE_TEXT, null, null, null, null, null);

        // Adding keys to table block_aiassistant_msg
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('conversation_fk', XMLDB_KEY_FOREIGN, ['conversation_id'], 'block_aiassistant_conv', ['conversation_id']);

        // Adding indexes to table block_aiassistant_msg
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
        upgrade_module_savepoint(true, 2025092304, 'craftpilot');
        error_log("AI Assistant: Messages table upgrade completed successfully");
    }

    // Add upgrade for video annotation tables
    if ($oldversion < 2025101901) {
        error_log("AI Assistant: Creating video annotation tables");
        
        // Define table block_aiassistant_proj
        $table = new xmldb_table('block_aiassistant_proj');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('created_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('name', XMLDB_INDEX_UNIQUE, ['name']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
            error_log("AI Assistant: Projects table created successfully");
        }

        // Define table block_aiassistant_videos
        $table = new xmldb_table('block_aiassistant_videos');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('project_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('filename', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('original_filename', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('file_path', XMLDB_TYPE_CHAR, '512', null, XMLDB_NOTNULL, null, null);
        $table->add_field('duration', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null);
        $table->add_field('created_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('project_id', XMLDB_KEY_FOREIGN, ['project_id'], 'block_aiassistant_proj', ['id']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
            error_log("AI Assistant: Videos table created successfully");
        }

        // Define table block_aiassistant_annot
        $table = new xmldb_table('block_aiassistant_annot');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('video_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timestamp', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, null);
        $table->add_field('audio_file_path', XMLDB_TYPE_CHAR, '512', null, XMLDB_NOTNULL, null, null);
        $table->add_field('transcription', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('transcription_status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
        $table->add_field('extended_transcript', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('extended_transcript_status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
        $table->add_field('feedback', XMLDB_TYPE_INTEGER, '1', null, null, null, null);
        $table->add_field('feedback_choices', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('created_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('video_id', XMLDB_KEY_FOREIGN, ['video_id'], 'block_aiassistant_videos', ['id']);
        $table->add_index('timestamp', XMLDB_INDEX_NOTUNIQUE, ['timestamp']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
            error_log("AI Assistant: Annotations table created successfully");
        }

        // Aiassistant savepoint reached
        upgrade_module_savepoint(true, 2025101901, 'craftpilot');
        error_log("AI Assistant: Video annotation tables upgrade completed successfully");
    }

    // Add upgrade to rename tables that exceed 28 character limit
    if ($oldversion < 2025102001) {
        error_log("AI Assistant: Renaming tables to comply with 28 character limit");
        
        // Handle block_aiassistant_messages -> block_aiassistant_msg
        $oldtable = new xmldb_table('block_aiassistant_messages');
        $newtable = new xmldb_table('block_aiassistant_msg');
        if ($dbman->table_exists($oldtable) && !$dbman->table_exists($newtable)) {
            // Old table exists, new doesn't - rename it
            $dbman->rename_table($oldtable, 'block_aiassistant_msg');
            error_log("AI Assistant: Renamed block_aiassistant_messages to block_aiassistant_msg");
        } else if ($dbman->table_exists($newtable) && $dbman->table_exists($oldtable)) {
            // Both exist - drop the old one (new one was created by previous upgrade attempt)
            $dbman->drop_table($oldtable);
            error_log("AI Assistant: Dropped old table block_aiassistant_messages (new table already exists)");
        } else if (!$dbman->table_exists($oldtable) && !$dbman->table_exists($newtable)) {
            error_log("AI Assistant: Neither old nor new messages table exists, will be created by previous upgrade step");
        } else {
            error_log("AI Assistant: Messages table already correctly named as block_aiassistant_msg");
        }
        
        // Handle block_aiassistant_projects -> block_aiassistant_proj
        $oldtable = new xmldb_table('block_aiassistant_projects');
        $newtable = new xmldb_table('block_aiassistant_proj');
        if ($dbman->table_exists($oldtable) && !$dbman->table_exists($newtable)) {
            // Old table exists, new doesn't - rename it
            $dbman->rename_table($oldtable, 'block_aiassistant_proj');
            error_log("AI Assistant: Renamed block_aiassistant_projects to block_aiassistant_proj");
        } else if ($dbman->table_exists($newtable) && $dbman->table_exists($oldtable)) {
            // Both exist - drop the old one (new one was created by previous upgrade attempt)
            $dbman->drop_table($oldtable);
            error_log("AI Assistant: Dropped old table block_aiassistant_projects (new table already exists)");
        } else if (!$dbman->table_exists($oldtable) && !$dbman->table_exists($newtable)) {
            error_log("AI Assistant: Neither old nor new projects table exists, will be created by previous upgrade step");
        } else {
            error_log("AI Assistant: Projects table already correctly named as block_aiassistant_proj");
        }
        
        // Handle block_aiassistant_annotations -> block_aiassistant_annot
        $oldtable = new xmldb_table('block_aiassistant_annotations');
        $newtable = new xmldb_table('block_aiassistant_annot');
        if ($dbman->table_exists($oldtable) && !$dbman->table_exists($newtable)) {
            // Old table exists, new doesn't - rename it
            $dbman->rename_table($oldtable, 'block_aiassistant_annot');
            error_log("AI Assistant: Renamed block_aiassistant_annotations to block_aiassistant_annot");
        } else if ($dbman->table_exists($newtable) && $dbman->table_exists($oldtable)) {
            // Both exist - drop the old one (new one was created by previous upgrade attempt)
            $dbman->drop_table($oldtable);
            error_log("AI Assistant: Dropped old table block_aiassistant_annotations (new table already exists)");
        } else if (!$dbman->table_exists($oldtable) && !$dbman->table_exists($newtable)) {
            error_log("AI Assistant: Neither old nor new annotations table exists, will be created by previous upgrade step");
        } else {
            error_log("AI Assistant: Annotations table already correctly named as block_aiassistant_annot");
        }
        
        // Aiassistant savepoint reached
        upgrade_module_savepoint(true, 2025102001, 'craftpilot');
        error_log("AI Assistant: Table renaming upgrade completed successfully");
    }

    // Rename legacy mod_aiassistant tables and migrate config to mod_craftpilot
    if ($oldversion < 2025121601) {
        error_log("CraftPilot: Renaming legacy aiassistant tables and migrating config");

        $renames = [
            'aiassistant' => 'craftpilot',
            'aiassistant_keys' => 'craftpilot_keys',
            'aiassistant_conv' => 'craftpilot_conv',
            'aiassistant_msg' => 'craftpilot_msg',
            'aiassistant_proj' => 'craftpilot_proj',
            'aiassistant_videos' => 'craftpilot_videos',
            'aiassistant_annot' => 'craftpilot_annot'
        ];

        foreach ($renames as $oldname => $newname) {
            $oldtable = new xmldb_table($oldname);
            $newtable = new xmldb_table($newname);

            if ($dbman->table_exists($oldtable) && !$dbman->table_exists($newtable)) {
                $dbman->rename_table($oldtable, $newname);
                error_log("CraftPilot: Renamed {$oldname} to {$newname}");
            }
        }

        // Migrate mod_aiassistant config entries to mod_craftpilot
        $oldconfigs = $DB->get_records('config_plugins', ['plugin' => 'mod_aiassistant']);
        foreach ($oldconfigs as $config) {
            set_config($config->name, $config->value, 'mod_craftpilot');
            error_log("CraftPilot: Migrated config {$config->name} to mod_craftpilot");
        }

        upgrade_module_savepoint(true, 2025121601, 'craftpilot');
        error_log("CraftPilot: Legacy table/config migration complete");
    }

    return true;
}
