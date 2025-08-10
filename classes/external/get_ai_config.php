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
 */
class get_ai_config extends external_api {

    /**
     * Describes the parameters for get_ai_config
     * @return external_function_parameters
     */
    public static function get_ai_config_parameters() {
        return new external_function_parameters([
            // No parameters needed
        ]);
    }

    /**
     * Get AI configuration for frontend
     * @return array
     */
    public static function get_ai_config() {
        // Validate context
        $context = context_system::instance();
        self::validate_context($context);
        
        // Check if user is logged in
        require_login();

        try {
            // Get Claude models configuration with fallback strings
            $claude_models = [
                [
                    'key' => 'claude-opus-4-1-20250805',
                    'name' => 'Claude Opus 4.1'
                ],
                [
                    'key' => 'claude-sonnet-4-20250514', 
                    'name' => 'Claude Sonnet 4'
                ],
                [
                    'key' => 'claude-3-5-haiku-latest',
                    'name' => 'Claude Haiku'
                ]
            ];

            $default_claude_model = get_config('block_aiassistant', 'claude_default_model');
            if (empty($default_claude_model)) {
                $default_claude_model = 'claude-sonnet-4-20250514';
            }

            // Ensure default_claude_model is never null
            if ($default_claude_model === null || $default_claude_model === false) {
                $default_claude_model = 'claude-sonnet-4-20250514';
            }

            error_log("DEBUG: Default claude model: " . $default_claude_model);

            // Check if Claude API is configured
            $claude_api_key = get_config('block_aiassistant', 'claude_api_key');
            $claude_available = !empty($claude_api_key);
            error_log("DEBUG: Claude available: " . ($claude_available ? 'true' : 'false'));

            // Check if Fireworks is configured
            $fireworks_account_id = get_config('block_aiassistant', 'fireworks_account_id');
            $fireworks_service_account_id = get_config('block_aiassistant', 'fireworks_service_account_id');
            $fireworks_available = !empty($fireworks_account_id) && !empty($fireworks_service_account_id);
            error_log("DEBUG: Fireworks available: " . ($fireworks_available ? 'true' : 'false'));

            $result = [
                'success' => true,
                'claude_models' => $claude_models,
                'default_claude_model' => (string)$default_claude_model, // Ensure it's a string
                'claude_available' => (bool)$claude_available,
                'fireworks_available' => (bool)$fireworks_available,
                'message' => 'Configuration retrieved successfully'
            ];

            error_log("DEBUG: Returning AI config: " . json_encode($result));
            return $result;

        } catch (\Exception $e) {
            error_log("DEBUG: Exception in get_ai_config: " . $e->getMessage());
            return [
                'success' => false,
                'claude_models' => [],
                'default_claude_model' => '',
                'claude_available' => false,
                'fireworks_available' => false,
                'message' => 'Failed to get configuration: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Describes the return value for get_ai_config
     * @return external_single_structure
     */
    public static function get_ai_config_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the operation was successful'),
            'claude_models' => new external_multiple_structure(
                new external_single_structure([
                    'key' => new external_value(PARAM_TEXT, 'Model key'),
                    'name' => new external_value(PARAM_TEXT, 'Human readable model name')
                ]),
                'Available Claude models'
            ),
            'default_claude_model' => new external_value(PARAM_TEXT, 'Default Claude model key'),
            'claude_available' => new external_value(PARAM_BOOL, 'Whether Claude API is configured'),
            'fireworks_available' => new external_value(PARAM_BOOL, 'Whether Fireworks is configured'),
            'message' => new external_value(PARAM_TEXT, 'Status message')
        ]);
    }
}
