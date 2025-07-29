<?php
// Temporary script to manually create the table
// Place this in your Moodle root directory and access via browser (as admin)

require_once('config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

echo "<h2>Manual Table Creation for block_aiassistant_keys</h2>";

$dbman = $DB->get_manager();

// Define table block_aiassistant_keys
$table = new xmldb_table('block_aiassistant_keys');

// Check if table already exists
if ($dbman->table_exists($table)) {
    echo "<p style='color: orange;'>Table block_aiassistant_keys already exists!</p>";
} else {
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

    try {
        // Create the table
        $dbman->create_table($table);
        echo "<p style='color: green;'>✓ Table block_aiassistant_keys created successfully!</p>";
        
        // Verify it was created
        if ($dbman->table_exists($table)) {
            echo "<p style='color: green;'>✓ Table exists and is accessible</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Error creating table: " . $e->getMessage() . "</p>";
    }
}

echo "<p><a href='" . $CFG->wwwroot . "/admin/index.php'>Back to admin</a></p>";
echo "<p>After table creation, you can delete this script.</p>";
