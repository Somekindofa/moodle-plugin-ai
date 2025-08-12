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
use block_aiassistant\credential_service;

/**
 * External API for getting user credentials
 * 
 * @package    block_aiassistant
 * @copyright  2025 Your Organization
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_user_credentials extends external_api {

    /**
     * Describes the parameters for get_user_credentials
     */
    public static function get_user_credentials_parameters() {
        return new external_function_parameters([
            'provider' => new external_value(PARAM_TEXT, 'AI provider (fireworks or claude)', VALUE_DEFAULT, 'fireworks')
        ]);
    }

    /**
     * Get or create user credentials
     */
    public static function get_user_credentials($provider = 'fireworks') {
        global $USER, $DB;

        // Validate parameters
        $params = self::validate_parameters(self::get_user_credentials_parameters(), [
            'provider' => $provider
        ]);

        // Validate context and require login
        $context = context_system::instance();
        self::validate_context($context);
        require_login();

        self::log_debug("Getting credentials for user {$USER->id} with provider {$params['provider']}");

        try {
            // Check if database table exists
            if (!$DB->get_manager()->table_exists('block_aiassistant_keys')) {
                return self::error_response('Database table block_aiassistant_keys does not exist');
            }

            if ($params['provider'] === 'claude') {
                return self::get_claude_credentials($USER->id);
            } else {
                return self::get_fireworks_credentials($USER->id);
            }

        } catch (\Exception $e) {
            self::log_error('Failed to get user credentials', $e, [
                'user_id' => $USER->id,
                'provider' => $params['provider']
            ]);
            
            return self::error_response('Failed to get credentials: ' . $e->getMessage());
        }
    }

    /**
     * Get Claude API credentials using credential service
     */
    private static function get_claude_credentials(int $user_id): array {
        self::log_debug("Getting Claude credentials for user {$user_id}");
        
        try {
            $credential_service = self::create_credential_service();
            $claude_key = $credential_service->get_claude_api_key($user_id);

            self::log_debug("Successfully retrieved Claude key for user {$user_id}");

            return [
                'success' => true,
                'api_key' => $claude_key,
                'display_name' => "claude-user-{$user_id}",
                'message' => 'Claude API key retrieved successfully'
            ];

        } catch (\Exception $e) {
            self::log_error('Failed to get Claude credentials', $e, ['user_id' => $user_id]);
            return self::error_response('Failed to get Claude credentials: ' . $e->getMessage());
        }
    }

    /**
     * Get Fireworks API credentials (using master key approach)
     */
    private static function get_fireworks_credentials(int $user_id): array {
        self::log_debug("Getting Fireworks credentials for user {$user_id} (master key approach)");

        // Get the master Fireworks API key from plugin settings
        $fireworks_api_key = get_config('block_aiassistant', 'fireworks_api_key');
        
        if (empty($fireworks_api_key)) {
            self::log_debug('Fireworks master API key not configured');
            return self::error_response('Fireworks API key not configured. Please check plugin settings.');
        }

        self::log_debug("Fireworks master API key found, length: " . strlen($fireworks_api_key));

        return [
            'success' => true,
            'api_key' => $fireworks_api_key,
            'display_name' => "fireworks-user-{$user_id}",
            'message' => 'Using master Fireworks API key'
        ];
    }

    /**
     * Create credential service instance
     */
    private static function create_credential_service(): credential_service {
        // Use dummy Fireworks values for Claude-only operation
        $fireworks_account_id = get_config('block_aiassistant', 'fireworks_account_id') ?: 'dummy';
        $fireworks_service_account_id = get_config('block_aiassistant', 'fireworks_service_account_id') ?: 'dummy';
        
        return new credential_service($fireworks_account_id, $fireworks_service_account_id);
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
