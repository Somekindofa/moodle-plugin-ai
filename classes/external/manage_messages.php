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
 * External API for managing conversation messages
 * 
 * @package    block_aiassistant
 * @copyright  2025 Your Organization
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manage_messages extends external_api {

    /**
     * Describes the parameters for manage_messages
     */
    public static function manage_messages_parameters() {
        return new external_function_parameters([
            'action' => new external_value(PARAM_TEXT, 'Action to perform (save, load)'),
            'conversation_id' => new external_value(PARAM_TEXT, 'Conversation ID'),
            'message_type' => new external_value(PARAM_TEXT, 'Message type (user or ai)', VALUE_DEFAULT, ''),
            'content' => new external_value(PARAM_RAW, 'Message content', VALUE_DEFAULT, ''),
            'metadata' => new external_value(PARAM_TEXT, 'Message metadata (JSON)', VALUE_DEFAULT, '')
        ]);
    }

    /**
     * Manage messages (save and load operations)
     */
    public static function manage_messages($action, $conversation_id, $message_type = '', $content = '', $metadata = '') {
        global $USER, $DB;

        // Debug logging
        error_log("AI Assistant: manage_messages called with action: {$action}, conversation_id: {$conversation_id}, message_type: {$message_type}");

        // Validate parameters
        $params = self::validate_parameters(self::manage_messages_parameters(), [
            'action' => $action,
            'conversation_id' => $conversation_id,
            'message_type' => $message_type,
            'content' => $content,
            'metadata' => $metadata
        ]);

        // Validate context and require login
        $context = context_system::instance();
        self::validate_context($context);
        require_login();

        self::log_debug("Managing messages for user {$USER->id} with action {$params['action']}");

        try {
            // Check if database tables exist
            if (!$DB->get_manager()->table_exists('block_aiassistant_msg')) {
                return self::error_response('Database table block_aiassistant_msg does not exist');
            }

            if (!$DB->get_manager()->table_exists('block_aiassistant_conv')) {
                return self::error_response('Database table block_aiassistant_conv does not exist');
            }

            // Verify user owns the conversation
            if (!self::user_owns_conversation($params['conversation_id'], $USER->id)) {
                return self::error_response('Access denied: You do not own this conversation');
            }

            switch ($params['action']) {
                case 'save':
                    return self::save_message($params['conversation_id'], $params['message_type'], $params['content'], $params['metadata']);
                    
                case 'load':
                    return self::load_messages($params['conversation_id']);
                    
                default:
                    return self::error_response('Invalid action specified');
            }

        } catch (\Exception $e) {
            self::log_error('Failed to manage messages', $e, [
                'user_id' => $USER->id,
                'action' => $params['action'],
                'conversation_id' => $params['conversation_id']
            ]);
            
            return self::error_response('Failed to manage messages: ' . $e->getMessage());
        }
    }

    /**
     * Save a message to the database
     */
    private static function save_message(string $conversation_id, string $message_type, string $content, string $metadata = ''): array {
        global $DB;

        error_log("AI Assistant: save_message called - conversation_id: {$conversation_id}, message_type: {$message_type}, content_length: " . strlen($content));

        self::log_debug("Saving message for conversation {$conversation_id} with type {$message_type}");

        if (empty($conversation_id)) {
            return self::error_response('Conversation ID is required');
        }

        if (empty($message_type) || !in_array($message_type, ['user', 'ai'])) {
            return self::error_response('Valid message type (user or ai) is required');
        }

        if (empty($content)) {
            return self::error_response('Message content is required');
        }

        try {
            // Get next sequence number for this conversation
            $next_sequence = $DB->get_field_sql(
                'SELECT COALESCE(MAX(sequence_number), 0) + 1 FROM {block_aiassistant_msg} WHERE conversation_id = ?',
                [$conversation_id]
            );

            $record = new \stdClass();
            $record->conversation_id = $conversation_id;
            $record->message_type = $message_type;
            $record->content = $content;
            $record->created_time = time();
            $record->sequence_number = $next_sequence;
            $record->metadata = $metadata;

            $id = $DB->insert_record('block_aiassistant_msg', $record);

            // Update conversation's last_updated timestamp
            self::update_conversation_timestamp($conversation_id);

            self::log_debug("Saved message with database ID {$id} for conversation {$conversation_id}");

            return [
                'success' => true,
                'message' => 'Message saved successfully',
                'message_id' => $id,
                'sequence_number' => $next_sequence
            ];

        } catch (\Exception $e) {
            self::log_error('Failed to save message', $e, [
                'conversation_id' => $conversation_id,
                'message_type' => $message_type
            ]);
            return self::error_response('Failed to save message to database');
        }
    }

    /**
     * Load messages for a conversation
     */
    private static function load_messages(string $conversation_id): array {
        global $DB;

        self::log_debug("Loading messages for conversation {$conversation_id}");

        if (empty($conversation_id)) {
            return self::error_response('Conversation ID is required');
        }

        try {
            $messages = $DB->get_records('block_aiassistant_msg', [
                'conversation_id' => $conversation_id
            ], 'sequence_number ASC');

            $message_list = [];
            foreach ($messages as $message) {
                $message_list[] = [
                    'id' => $message->id,
                    'message_type' => $message->message_type,
                    'content' => $message->content,
                    'created_time' => $message->created_time,
                    'sequence_number' => $message->sequence_number,
                    'metadata' => $message->metadata
                ];
            }

            self::log_debug("Loaded " . count($message_list) . " messages for conversation {$conversation_id}");

            return [
                'success' => true,
                'message' => 'Messages loaded successfully',
                'messages' => $message_list
            ];

        } catch (\Exception $e) {
            self::log_error('Failed to load messages', $e, ['conversation_id' => $conversation_id]);
            return self::error_response('Failed to load messages from database');
        }
    }

    /**
     * Check if user owns the conversation
     */
    private static function user_owns_conversation(string $conversation_id, int $user_id): bool {
        global $DB;

        try {
            $conversation = $DB->get_record('block_aiassistant_conv', [
                'conversation_id' => $conversation_id,
                'userid' => $user_id,
                'is_active' => 1
            ]);

            return $conversation !== false;
        } catch (\Exception $e) {
            self::log_error('Failed to verify conversation ownership', $e, [
                'conversation_id' => $conversation_id,
                'user_id' => $user_id
            ]);
            return false;
        }
    }

    /**
     * Update conversation's last_updated timestamp
     */
    private static function update_conversation_timestamp(string $conversation_id): void {
        global $DB;

        try {
            $conversation = $DB->get_record('block_aiassistant_conv', ['conversation_id' => $conversation_id]);
            if ($conversation) {
                $conversation->last_updated = time();
                $DB->update_record('block_aiassistant_conv', $conversation);
            }
        } catch (\Exception $e) {
            self::log_error('Failed to update conversation timestamp', $e, ['conversation_id' => $conversation_id]);
            // Don't throw exception here as it's not critical
        }
    }

    /**
     * Create error response structure
     */
    private static function error_response(string $message): array {
        return [
            'success' => false,
            'message' => $message,
            'messages' => []
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
     * Describes the return value for manage_messages
     */
    public static function manage_messages_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the operation was successful'),
            'message' => new external_value(PARAM_TEXT, 'Status message'),
            'message_id' => new external_value(PARAM_INT, 'Database message ID', VALUE_OPTIONAL),
            'sequence_number' => new external_value(PARAM_INT, 'Message sequence number', VALUE_OPTIONAL),
            'messages' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Database message ID'),
                    'message_type' => new external_value(PARAM_TEXT, 'Message type (user or ai)'),
                    'content' => new external_value(PARAM_RAW, 'Message content'),
                    'created_time' => new external_value(PARAM_INT, 'Creation timestamp'),
                    'sequence_number' => new external_value(PARAM_INT, 'Message sequence number'),
                    'metadata' => new external_value(PARAM_TEXT, 'Message metadata')
                ]),
                'List of messages',
                VALUE_OPTIONAL
            )
        ]);
    }
}