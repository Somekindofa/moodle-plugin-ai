<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_craftpilot\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use context_system;
use mod_craftpilot\credential_service;

/**
 * External API for getting user credentials
 * 
 * @package    mod_craftpilot
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_user_credentials extends external_api {

    /**
     * Describes the parameters for get_user_credentials
     */
    public static function get_user_credentials_parameters() {
        return new external_function_parameters([
            'provider' => new external_value(PARAM_TEXT, 'AI provider Fireworks', VALUE_DEFAULT, 'fireworks')
        ]);
    }

    /**
     * Get global API credentials for the specified provider
     */
    public static function get_user_credentials($provider = 'fireworks') {
        global $USER;

        // Validate parameters
        $params = self::validate_parameters(self::get_user_credentials_parameters(), [
            'provider' => $provider
        ]);

        // Validate context and require login
        $context = context_system::instance();
        self::validate_context($context);
        require_login();

        self::log_debug("Getting global API key for user {$USER->id} with provider {$params['provider']}");

        try {
            // Get the global Fireworks API key using credential service
            $api_key = credential_service::get_fireworks_api_key();
            
            if (empty($api_key)) {
                self::log_debug('Fireworks API key not configured in settings');
                return self::error_response('Fireworks API key not configured. Please check plugin settings.');
            }

            self::log_debug("Fireworks API key found, length: " . strlen($api_key));

            return [
                'success' => true,
                'api_key' => $api_key,
                'display_name' => 'Global Fireworks API Key',
                'message' => 'Using global Fireworks API key'
            ];

        } catch (\Exception $e) {
            self::log_error('Failed to get API credentials', $e, [
                'user_id' => $USER->id,
                'provider' => $params['provider']
            ]);
            
            return self::error_response('Failed to get credentials: ' . $e->getMessage());
        }
    }

    /**
     * Create error response structure
     */
    private static function error_response(string $message): array {
        return [
            'success' => false,
            'api_key' => '',
            'display_name' => '',
            'message' => $message
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
     * Describes the return value for get_user_credentials
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
