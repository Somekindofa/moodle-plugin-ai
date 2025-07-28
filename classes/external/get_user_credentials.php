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
            $credential_service = new \block_aiassistant\credential_service(get_config('block_aiassistant', 'fireworks_account_id'));
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
