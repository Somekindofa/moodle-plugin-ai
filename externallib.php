<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

class block_aiassistant_external extends external_api {
    
    /**
     * Returns description of method parameters for get_user_credentials
     */
    public static function get_user_credentials_parameters() {
        return new external_function_parameters([]);
    }
    
    /**
     * Get user credentials for AI API
     */
    public static function get_user_credentials() {
        global $USER;
        
        // Validate context and permissions
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('block/aiassistant:use', $context);
        
        // TODO: Implement actual credential retrieval logic
        // For now, return a mock response
        return [
            'success' => true,
            'api_key' => 'mock_api_key_' . $USER->id,
            'message' => 'Credentials retrieved successfully'
        ];
    }
    
    /**
     * Returns description of method return value for get_user_credentials
     */
    public static function get_user_credentials_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the operation was successful'),
            'api_key' => new external_value(PARAM_TEXT, 'API key for the user', VALUE_OPTIONAL),
            'message' => new external_value(PARAM_TEXT, 'Status message')
        ]);
    }
    
    /**
     * Returns description of method parameters for send_chat_message
     */
    public static function send_chat_message_parameters() {
        return new external_function_parameters([
            'message' => new external_value(PARAM_TEXT, 'User message'),
            'api_key' => new external_value(PARAM_TEXT, 'API key for authentication')
        ]);
    }
    
    /**
     * Send chat message to AI and get response
     */
    public static function send_chat_message($message, $api_key) {
        global $USER;
        
        // Validate parameters
        $params = self::validate_parameters(self::send_chat_message_parameters(), [
            'message' => $message,
            'api_key' => $api_key
        ]);
        
        // Validate context and permissions
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('block/aiassistant:use', $context);
        
        // TODO: Implement actual AI chat logic using the RAG system
        // For now, return a mock response
        return [
            'success' => true,
            'response' => 'This is a mock AI response to: ' . $params['message'],
            'message' => 'Chat message processed successfully'
        ];
    }
    
    /**
     * Returns description of method return value for send_chat_message
     */
    public static function send_chat_message_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the operation was successful'),
            'response' => new external_value(PARAM_TEXT, 'AI response', VALUE_OPTIONAL),
            'message' => new external_value(PARAM_TEXT, 'Status message')
        ]);
    }
}
