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
 * External API for getting AI configuration
 * 
 * @package    block_aiassistant
 * @copyright  2025 Your Organization
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_ai_config extends external_api {

    /**
     * Describes the parameters for get_ai_config
     */
    public static function get_ai_config_parameters() {
        return new external_function_parameters([]);
    }

    /**
     * Get AI configuration for frontend
     */
    public static function get_ai_config() {
        // Validate context
        $context = context_system::instance();
        self::validate_context($context);
        require_login();

        try {
            $config = [
                'success' => true,
                'fireworks_available' => self::is_fireworks_configured(),
                'message' => 'Configuration retrieved successfully'
            ];

            self::log_debug('AI configuration retrieved', $config);
            return $config;

        } catch (\Exception $e) {
            self::log_error('Failed to get AI configuration', $e);
            
            return [
                'success' => false,
                'fireworks_available' => false,
                'message' => 'Failed to get configuration: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Check if Fireworks API is configured
     */
    private static function is_fireworks_configured(): bool {
        $account_id = get_config('block_aiassistant', 'fireworks_account_id');
        $service_account_id = get_config('block_aiassistant', 'fireworks_service_account_id');
        $api_key = get_config('block_aiassistant', 'fireworks_api_key');
        
        // Either individual keys OR master API key approach
        return (!empty($account_id) && !empty($service_account_id)) || !empty($api_key);
    }

    /**
     * Log debug message
     */
    private static function log_debug(string $message, array $context = []): void {
        $context_str = empty($context) ? '' : ' ' . json_encode($context);
        error_log("AI Assistant Debug: {$message}{$context_str}");
    }

    /**
     * Log error message
     */
    private static function log_error(string $message, \Exception $e): void {
        error_log("AI Assistant Error: {$message} - {$e->getMessage()}");
    }

    /**
     * Describes the return value for get_ai_config
     */
    public static function get_ai_config_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the operation was successful'),
            'fireworks_available' => new external_value(PARAM_BOOL, 'Whether Fireworks is configured'),
            'message' => new external_value(PARAM_TEXT, 'Status message')
        ]);
    }
}
