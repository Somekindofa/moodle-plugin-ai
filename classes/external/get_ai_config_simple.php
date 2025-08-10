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
 * External API for getting AI configuration - SIMPLE TEST VERSION
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

        // Simple test response
        return [
            'success' => true,
            'claude_models' => [
                [
                    'key' => 'claude-sonnet-4-20250514',
                    'name' => 'Claude Sonnet 4'
                ]
            ],
            'default_claude_model' => 'claude-sonnet-4-20250514',
            'claude_available' => false,
            'fireworks_available' => false,
            'message' => 'Simple test configuration'
        ];
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
