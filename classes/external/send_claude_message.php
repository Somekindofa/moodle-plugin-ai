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
 * 
 * @package    block_aiassistant
 * @copyright  2025 Your Organization
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_claude_message extends external_api {

    /** @var string Claude API endpoint */
    private const CLAUDE_API_URL = 'https://api.anthropic.com/v1/messages';
    
    /** @var string Claude API version */
    private const CLAUDE_API_VERSION = '2023-06-01';
    
    /** @var int Request timeout in seconds */
    private const REQUEST_TIMEOUT = 30;
    
    /** @var int Default max tokens */
    private const DEFAULT_MAX_TOKENS = 2000;

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
            // Get Claude API key using refactored credential service
            $api_key = self::get_claude_api_key($USER->id);
            
            // Send request to Claude API
            $response_data = self::send_claude_request($api_key, $params['message'], $params['model']);
            
            return [
                'success' => true,
                'content' => $response_data['content'][0]['text'],
                'model' => $response_data['model'] ?? $params['model']
            ];

        } catch (\Exception $e) {
            self::log_error('Claude API request failed', $e, [
                'user_id' => $USER->id,
                'model' => $params['model']
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get Claude API key for user
     * 
     * @param int $user_id The user ID
     * @return string The API key
     * @throws \Exception If API key cannot be retrieved
     */
    private static function get_claude_api_key(int $user_id): string {
        // Use dummy Fireworks values for Claude-only operation
        $fireworks_account_id = get_config('block_aiassistant', 'fireworks_account_id') ?: 'dummy';
        $fireworks_service_account_id = get_config('block_aiassistant', 'fireworks_service_account_id') ?: 'dummy';
        
        $credential_service = new credential_service($fireworks_account_id, $fireworks_service_account_id);
        
        return $credential_service->get_claude_api_key($user_id);
    }

    /**
     * Send request to Claude API
     * 
     * @param string $api_key The Claude API key
     * @param string $message The message to send
     * @param string $model The model to use
     * @return array The parsed response data
     * @throws \Exception If request fails
     */
    private static function send_claude_request(string $api_key, string $message, string $model): array {
        $headers = [
            'Content-Type: application/json',
            'x-api-key: ' . $api_key,
            'anthropic-version: ' . self::CLAUDE_API_VERSION
        ];

        $data = [
            'model' => $model,
            'max_tokens' => self::DEFAULT_MAX_TOKENS,
            'temperature' => 0.7,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $message
                ]
            ]
        ];

        // Initialize cURL
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => self::CLAUDE_API_URL,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::REQUEST_TIMEOUT,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            throw new \Exception('Connection error: ' . $curl_error);
        }

        $response_data = json_decode($response, true);

        if ($http_code !== 200) {
            $error_message = self::extract_error_message($response_data, $http_code);
            throw new \Exception($error_message);
        }

        if (!isset($response_data['content']) || !isset($response_data['content'][0]['text'])) {
            throw new \Exception('Unexpected response format from Claude API');
        }

        return $response_data;
    }

    /**
     * Extract error message from API response
     * 
     * @param array|null $response_data The decoded response
     * @param int $http_code The HTTP status code
     * @return string The error message
     */
    private static function extract_error_message(?array $response_data, int $http_code): string {
        if (isset($response_data['error']['message'])) {
            return $response_data['error']['message'];
        }
        
        if (isset($response_data['message'])) {
            return $response_data['message'];
        }
        
        return "API request failed with HTTP code {$http_code}";
    }

    /**
     * Log error with context
     */
    private static function log_error(string $message, \Exception $e, array $context = []): void {
        $context_str = empty($context) ? '' : ' Context: ' . json_encode($context);
        error_log("AI Assistant Claude API Error: {$message} - {$e->getMessage()}{$context_str}");
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