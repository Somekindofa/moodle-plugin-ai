<?php
// This file is part of Moodle - http://moodle.org/

namespace block_aiassistant\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use context_system;

/**
 * External API for getting user credentials
 */
class get_user_credentials extends external_api {

    /**
     * Describes the parameters for get_user_credentials
     * @return external_function_parameters
     */
    public static function get_user_credentials_parameters() {
        return new external_function_parameters([
            'provider' => new external_value(PARAM_TEXT, 'AI provider (fireworks or claude)', VALUE_DEFAULT, 'fireworks')
        ]);
    }

    /**
     * Get or create user credentials
     * @param string $provider AI provider to get credentials for
     * @return array
     */
    public static function get_user_credentials($provider = 'fireworks') {
        global $USER, $DB;
        error_log("DEBUG: Starting get_user_credentials for user " . $USER->id . " with provider " . $provider);

        // Validate context
        $context = context_system::instance();
        self::validate_context($context);
        
        // Check if user is logged in
        require_login();

        try {
            // Debug: Check if table exists
            error_log("DEBUG: Checking if table exists");
            if (!$DB->get_manager()->table_exists('block_aiassistant_keys')) {
                error_log("DEBUG: Table does not exist");
                return [
                    'success' => false,
                    'api_key' => '',
                    'display_name' => '',
                    'message' => 'Table block_aiassistant_keys does not exist in database'
                ];
            }

            if ($provider === 'claude') {
                return self::get_claude_credentials($USER->id);
            } else {
                return self::get_fireworks_credentials($USER->id);
            }

        } catch (\Exception $e) {
            error_log("DEBUG: Exception caught: " . $e->getMessage());
            error_log("DEBUG: Exception trace: " . $e->getTraceAsString());
            return [
                'success' => false,
                'api_key' => '',
                'display_name' => '',
                'message' => 'Failed to get credentials: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get Claude API credentials
     * @param int $user_id
     * @return array
     */
    private static function get_claude_credentials($user_id) {
        error_log("DEBUG: Getting Claude credentials for user " . $user_id);
        
        // Check if Claude API is configured
        $claude_api_key = get_config('block_aiassistant', 'claude_api_key');
        if (empty($claude_api_key)) {
            error_log("DEBUG: Claude API key not configured");
            return [
                'success' => false,
                'api_key' => '',
                'display_name' => '',
                'message' => 'Claude API key not configured. Please check plugin settings.'
            ];
        }

        error_log("DEBUG: Claude API key found, length: " . strlen($claude_api_key));

        try {
            // For Claude, we don't need Fireworks config, use dummy values if needed
            $fireworks_account_id = get_config('block_aiassistant', 'fireworks_account_id') ?: 'dummy';
            $fireworks_service_account_id = get_config('block_aiassistant', 'fireworks_service_account_id') ?: 'dummy';

            $credential_service = new \block_aiassistant\credential_service($fireworks_account_id, $fireworks_service_account_id);
            $claude_key = $credential_service->get_claude_api_key($user_id);

            error_log("DEBUG: Successfully got Claude key for user " . $user_id);

            return [
                'success' => true,
                'api_key' => $claude_key,
                'display_name' => "claude-user-{$user_id}",
                'message' => 'Claude API key retrieved'
            ];

        } catch (\Exception $e) {
            error_log("DEBUG: Exception in get_claude_credentials: " . $e->getMessage());
            return [
                'success' => false,
                'api_key' => '',
                'display_name' => '',
                'message' => 'Failed to get Claude credentials: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get Fireworks API credentials (using master key approach)
     * @param int $user_id
     * @return array
     */
    private static function get_fireworks_credentials($user_id) {
        error_log("DEBUG: Getting Fireworks credentials for user " . $user_id . " (master key approach)");

        // Get the master Fireworks API key from plugin settings
        $fireworks_api_key = get_config('block_aiassistant', 'fireworks_api_key');
        
        if (empty($fireworks_api_key)) {
            error_log("DEBUG: Fireworks master API key not configured");
            return [
                'success' => false,
                'api_key' => '',
                'display_name' => '',
                'message' => 'Fireworks API key not configured. Please check plugin settings.'
            ];
        }

        error_log("DEBUG: Fireworks master API key found, length: " . strlen($fireworks_api_key));

        return [
            'success' => true,
            'api_key' => $fireworks_api_key,
            'display_name' => "fireworks-user-{$user_id}",
            'message' => 'Using master Fireworks API key'
        ];
    }

    /**
     * Describes the return value for get_user_credentials
     * @return external_single_structure
     */
    public static function get_user_credentials_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the operation was successful'),
            'api_key' => new external_value(PARAM_TEXT, 'The API key'),
            'display_name' => new external_value(PARAM_TEXT, 'Display name for the key'),
            'message' => new external_value(PARAM_TEXT, 'Status message')
        ]);
    }
}
