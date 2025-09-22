<?php
// This file is part of Moodle - http://moodle.org/

namespace block_aiassistant\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;
use context_system;

/**
 * External API for managing conversations
 * 
 * @package    block_aiassistant
 * @copyright  2025 Your Organization
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manage_conversations extends external_api {

    /**
     * Describes the parameters for manage_conversations
     */
    public static function manage_conversations_parameters() {
        return new external_function_parameters([
            'action' => new external_value(PARAM_TEXT, 'Action to perform (create, list, update, delete)'),
            'conversation_id' => new external_value(PARAM_TEXT, 'Conversation ID', VALUE_DEFAULT, ''),
            'title' => new external_value(PARAM_TEXT, 'Conversation title', VALUE_DEFAULT, ''),
            'metadata' => new external_value(PARAM_TEXT, 'Conversation metadata (JSON)', VALUE_DEFAULT, '')
        ]);
    }

    /**
     * Manage conversations (CRUD operations)
     */
    public static function manage_conversations($action, $conversation_id = '', $title = '', $metadata = '') {
        global $USER, $DB;

        // Validate parameters
        $params = self::validate_parameters(self::manage_conversations_parameters(), [
            'action' => $action,
            'conversation_id' => $conversation_id,
            'title' => $title,
            'metadata' => $metadata
        ]);

        // Validate context and require login
        $context = context_system::instance();
        self::validate_context($context);
        require_login();

        self::log_debug("Managing conversation for user {$USER->id} with action {$params['action']}");

        try {
            // Check if database table exists
            if (!$DB->get_manager()->table_exists('block_aiassistant_conversations')) {
                return self::error_response('Database table block_aiassistant_conversations does not exist');
            }

            switch ($params['action']) {
                case 'create':
                    return self::create_conversation($USER->id, $params['conversation_id'], $params['title'], $params['metadata']);
                    
                case 'list':
                    return self::list_conversations($USER->id);
                    
                case 'update':
                    return self::update_conversation($USER->id, $params['conversation_id'], $params['title'], $params['metadata']);
                    
                case 'delete':
                    return self::delete_conversation($USER->id, $params['conversation_id']);
                    
                default:
                    return self::error_response('Invalid action specified');
            }

        } catch (\Exception $e) {
            self::log_error('Failed to manage conversation', $e, [
                'user_id' => $USER->id,
                'action' => $params['action'],
                'conversation_id' => $params['conversation_id']
            ]);
            
            return self::error_response('Failed to manage conversation: ' . $e->getMessage());
        }
    }

    /**
     * Create a new conversation
     */
    private static function create_conversation(int $user_id, string $conversation_id, string $title, string $metadata = ''): array {
        global $DB;

        self::log_debug("Creating conversation for user {$user_id} with ID {$conversation_id}");

        if (empty($conversation_id)) {
            return self::error_response('Conversation ID is required');
        }

        if (empty($title)) {
            return self::error_response('Conversation title is required');
        }

        // Check if conversation already exists
        $existing = $DB->get_record('block_aiassistant_conversations', [
            'conversation_id' => $conversation_id,
            'is_active' => 1
        ]);

        if ($existing) {
            return self::error_response('Conversation with this ID already exists');
        }

        try {
            $record = new \stdClass();
            $record->conversation_id = $conversation_id;
            $record->userid = $user_id;
            $record->title = $title;
            $record->created_time = time();
            $record->last_updated = time();
            $record->is_active = 1;
            $record->metadata = $metadata;

            $id = $DB->insert_record('block_aiassistant_conversations', $record);

            self::log_debug("Created conversation with database ID {$id} for user {$user_id}");

            return [
                'success' => true,
                'message' => 'Conversation created successfully',
                'conversation_id' => $conversation_id,
                'database_id' => $id
            ];

        } catch (\Exception $e) {
            self::log_error('Failed to create conversation', $e, [
                'user_id' => $user_id,
                'conversation_id' => $conversation_id
            ]);
            return self::error_response('Failed to save conversation to database');
        }
    }

    /**
     * List conversations for a user
     */
    private static function list_conversations(int $user_id): array {
        global $DB;

        self::log_debug("Listing conversations for user {$user_id}");

        try {
            $conversations = $DB->get_records('block_aiassistant_conversations', [
                'userid' => $user_id,
                'is_active' => 1
            ], 'last_updated DESC');

            $conversation_list = [];
            foreach ($conversations as $conversation) {
                $conversation_list[] = [
                    'id' => $conversation->id,
                    'conversation_id' => $conversation->conversation_id,
                    'title' => $conversation->title,
                    'created_time' => $conversation->created_time,
                    'last_updated' => $conversation->last_updated,
                    'metadata' => $conversation->metadata
                ];
            }

            self::log_debug("Found " . count($conversation_list) . " conversations for user {$user_id}");

            return [
                'success' => true,
                'message' => 'Conversations retrieved successfully',
                'conversations' => $conversation_list
            ];

        } catch (\Exception $e) {
            self::log_error('Failed to list conversations', $e, ['user_id' => $user_id]);
            return self::error_response('Failed to retrieve conversations from database');
        }
    }

    /**
     * Update an existing conversation
     */
    private static function update_conversation(int $user_id, string $conversation_id, string $title, string $metadata = ''): array {
        global $DB;

        self::log_debug("Updating conversation {$conversation_id} for user {$user_id}");

        if (empty($conversation_id)) {
            return self::error_response('Conversation ID is required');
        }

        try {
            $conversation = $DB->get_record('block_aiassistant_conversations', [
                'conversation_id' => $conversation_id,
                'userid' => $user_id,
                'is_active' => 1
            ]);

            if (!$conversation) {
                return self::error_response('Conversation not found or access denied');
            }

            // Update fields if provided
            if (!empty($title)) {
                $conversation->title = $title;
            }
            if (!empty($metadata)) {
                $conversation->metadata = $metadata;
            }
            $conversation->last_updated = time();

            $DB->update_record('block_aiassistant_conversations', $conversation);

            self::log_debug("Updated conversation {$conversation_id} for user {$user_id}");

            return [
                'success' => true,
                'message' => 'Conversation updated successfully',
                'conversation_id' => $conversation_id
            ];

        } catch (\Exception $e) {
            self::log_error('Failed to update conversation', $e, [
                'user_id' => $user_id,
                'conversation_id' => $conversation_id
            ]);
            return self::error_response('Failed to update conversation in database');
        }
    }

    /**
     * Delete (soft delete) a conversation
     */
    private static function delete_conversation(int $user_id, string $conversation_id): array {
        global $DB;

        self::log_debug("Deleting conversation {$conversation_id} for user {$user_id}");

        if (empty($conversation_id)) {
            return self::error_response('Conversation ID is required');
        }

        try {
            $conversation = $DB->get_record('block_aiassistant_conversations', [
                'conversation_id' => $conversation_id,
                'userid' => $user_id,
                'is_active' => 1
            ]);

            if (!$conversation) {
                return self::error_response('Conversation not found or access denied');
            }

            // Soft delete by setting is_active to 0
            $conversation->is_active = 0;
            $conversation->last_updated = time();

            $DB->update_record('block_aiassistant_conversations', $conversation);

            self::log_debug("Deleted conversation {$conversation_id} for user {$user_id}");

            return [
                'success' => true,
                'message' => 'Conversation deleted successfully',
                'conversation_id' => $conversation_id
            ];

        } catch (\Exception $e) {
            self::log_error('Failed to delete conversation', $e, [
                'user_id' => $user_id,
                'conversation_id' => $conversation_id
            ]);
            return self::error_response('Failed to delete conversation from database');
        }
    }

    /**
     * Create error response structure
     */
    private static function error_response(string $message): array {
        return [
            'success' => false,
            'message' => $message,
            'conversations' => []
        ];
    }

    /**
     * Log debug message
     */
    private static function log_debug(string $message): void {
        error_log("AI Assistant Debug: {$message}");
    }

    /**
     * Log error message
     */
    private static function log_error(string $message, \Exception $e, array $context = []): void {
        $context_str = empty($context) ? '' : ' Context: ' . json_encode($context);
        error_log("AI Assistant Error: {$message} - {$e->getMessage()}{$context_str}");
    }

    /**
     * Describes the return value for manage_conversations
     */
    public static function manage_conversations_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the operation was successful'),
            'message' => new external_value(PARAM_TEXT, 'Status message'),
            'conversation_id' => new external_value(PARAM_TEXT, 'Conversation ID', VALUE_OPTIONAL),
            'database_id' => new external_value(PARAM_INT, 'Database record ID', VALUE_OPTIONAL),
            'conversations' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Database record ID'),
                    'conversation_id' => new external_value(PARAM_TEXT, 'Conversation ID'),
                    'title' => new external_value(PARAM_TEXT, 'Conversation title'),
                    'created_time' => new external_value(PARAM_INT, 'Creation timestamp'),
                    'last_updated' => new external_value(PARAM_INT, 'Last update timestamp'),
                    'metadata' => new external_value(PARAM_TEXT, 'Conversation metadata')
                ]),
                'List of conversations',
                VALUE_OPTIONAL
            )
        ]);
    }
}