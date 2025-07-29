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
            // No parameters needed - we get user from session
        ]);
    }

    /**
     * Get or create user credentials
     * @return array
     */
    public static function get_user_credentials() {
        global $USER, $DB;

        // Validate context
        $context = context_system::instance();
        self::validate_context($context);
        
        // Check if user is logged in
        require_login();

        try {
            // Debug: Check if table exists
            if (!$DB->get_manager()->table_exists('block_aiassistant_keys')) {
                return [
                    'success' => false,
                    'api_key' => '',
                    'display_name' => '',
                    'message' => 'Table block_aiassistant_keys does not exist in database'
                ];
            }

            // Check if user already has an active API key
            $existing_key = $DB->get_record('block_aiassistant_keys', [
                'userid' => $USER->id,
                'is_active' => 1
            ]);

            if ($existing_key) {
                return [
                    'success' => true,
                    'api_key' => $existing_key->fireworks_api_key,
                    'display_name' => $existing_key->display_name,
                    'message' => 'Using existing API key'
                ];
            }

            // No existing key, create new one
            $fireworks_account_id = get_config('block_aiassistant', 'fireworks_account_id');
            
            // Debug: Check if config value exists
            debugging('Fireworks Account ID retrieved: ' . var_export($fireworks_account_id, true), DEBUG_DEVELOPER);
            error_log('DEBUG: Fireworks Account ID: ' . var_export($fireworks_account_id, true));
            
            if (empty($fireworks_account_id)) {
                return [
                    'success' => false,
                    'api_key' => '',
                    'display_name' => '',
                    'message' => 'Fireworks Account ID not configured. Please check plugin settings.'
                ];
            }
            
            $credential_service = new \block_aiassistant\credential_service($fireworks_account_id);
            $api_key = $credential_service->generate_user_api_key($USER->id);

            return [
                'success' => true,
                'api_key' => $api_key,
                'display_name' => "moodle-user-{$USER->id}",
                'message' => 'Created new API key'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'api_key' => '',
                'display_name' => '',
                'message' => 'Failed to get credentials: ' . $e->getMessage()
            ];
        }
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
