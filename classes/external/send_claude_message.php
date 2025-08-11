<?php
// This file is part of Moodle - http://moodle.org/

namespace block_aiassistant\external;

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use block_aiassistant\credential_service;
use context_system;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * External API for sending messages to Claude API
 */
class send_claude_message extends external_api {

    /**
     * Parameters for send_claude_message
     */
    public static function send_claude_message_parameters() {
        return new external_function_parameters([
            'message' => new external_value(PARAM_RAW, 'The message to send to Claude'),
            'model' => new external_value(PARAM_TEXT, 'The Claude model to use', VALUE_DEFAULT, 'claude-sonnet-4-20250514')
        ]);
    }

    /**
     * Send message to Claude API
     */
    public static function send_claude_message($message, $model) {
        global $USER;

        // Validate parameters
        $params = self::validate_parameters(self::send_claude_message_parameters(), [
            'message' => $message,
            'model' => $model
        ]);

        // Require login and proper context
        $context = context_system::instance();
        self::validate_context($context);
        require_login();

        try {
            // Get user's Claude API key - use dummy Fireworks values for Claude-only operation
            $fireworks_account_id = get_config('block_aiassistant', 'fireworks_account_id') ?: 'dummy';
            $fireworks_service_account_id = get_config('block_aiassistant', 'fireworks_service_account_id') ?: 'dummy';
            
            $credential_service = new credential_service($fireworks_account_id, $fireworks_service_account_id);
            
            try {
                $api_key = $credential_service->get_claude_api_key($USER->id);
            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'message' => 'Failed to get Claude API key: ' . $e->getMessage()
                ];
            }
            
            // Prepare Claude API request
            $url = 'https://api.anthropic.com/v1/messages';
            $headers = [
                'Content-Type: application/json',
                'x-api-key: ' . $api_key,
                'anthropic-version: 2023-06-01'
            ];

            $data = [
                'model' => $params['model'],
                'max_tokens' => 2000,
                'temperature' => 0.7,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $params['message']
                    ]
                ]
            ];

            // Initialize cURL
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2
            ]);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            if ($curl_error) {
                return [
                    'success' => false,
                    'message' => 'Connection error: ' . $curl_error
                ];
            }

            $response_data = json_decode($response, true);

            if ($http_code !== 200) {
                $error_message = 'API request failed';
                if (isset($response_data['error']['message'])) {
                    $error_message = $response_data['error']['message'];
                } elseif (isset($response_data['message'])) {
                    $error_message = $response_data['message'];
                }
                
                return [
                    'success' => false,
                    'message' => $error_message,
                    'http_code' => $http_code
                ];
            }

            if (!isset($response_data['content']) || !isset($response_data['content'][0]['text'])) {
                return [
                    'success' => false,
                    'message' => 'Unexpected response format from Claude API'
                ];
            }

            return [
                'success' => true,
                'content' => $response_data['content'][0]['text'],
                'model' => $response_data['model'] ?? $params['model']
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Return structure for send_claude_message
     */
    public static function send_claude_message_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the request was successful'),
            'content' => new external_value(PARAM_RAW, 'The response content from Claude', VALUE_OPTIONAL),
            'model' => new external_value(PARAM_TEXT, 'The model used', VALUE_OPTIONAL),
            'message' => new external_value(PARAM_TEXT, 'Error message if applicable', VALUE_OPTIONAL),
            'http_code' => new external_value(PARAM_INT, 'HTTP response code', VALUE_OPTIONAL)
        ]);
    }
}