<?php
// This file is part of Moodle - http://moodle.org/
namespace block_aiassistant;

defined('MOODLE_INTERNAL') || die();

class credential_service {
    private $account_id, $service_account_id;
    
    public function __construct($account_id, $service_account_id) {
        // Allow dummy values for Claude-only usage
        if ((empty($account_id) || $account_id === 'dummy') && (empty($service_account_id) || $service_account_id === 'dummy')) {
            error_log('DEBUG: Using dummy Fireworks credentials for Claude-only operation');
            $this->account_id = $account_id ?: 'dummy';
            $this->service_account_id = $service_account_id ?: 'dummy';
            return;
        }
        
        if (empty($account_id) || empty($service_account_id)) {
            throw new \Exception('Please enter your Fireworks.ai credentials first.');
        }
        $this->account_id = $account_id;
        $this->service_account_id = $service_account_id;
    }

    public function generate_user_api_key($user_id) {
        $response = $this->create_api_key_for_user($user_id);
        $this->store_user_api_key($user_id, $response);
        return $response['key'];
    }

    /**
     * Get or create Claude API key for user
     * @param int $user_id The user ID
     * @return string The Claude API key
     */
    public function get_claude_api_key($user_id) {
        // For Claude, we use the admin's API key for all users
        // This is different from Fireworks which creates individual keys
        $claude_api_key = get_config('block_aiassistant', 'claude_api_key');
        
        if (empty($claude_api_key)) {
            throw new \Exception('Claude API key not configured');
        }

        // Store the fact that this user is using Claude API
        $this->store_claude_usage($user_id, $claude_api_key);
        
        return $claude_api_key;
    }

    /**
     * Store Claude API usage for user
     * @param int $user_id
     * @param string $claude_api_key
     */
    private function store_claude_usage($user_id, $claude_api_key) {
        global $DB;
        
        try {
            // Check if user already has a record
            $existing_record = $DB->get_record('block_aiassistant_keys', [
                'userid' => $user_id,
                'is_active' => 1
            ]);

            if ($existing_record) {
                // Update existing record with Claude API key
                $existing_record->claude_api_key = $claude_api_key;
                $existing_record->last_used = time();
                $DB->update_record('block_aiassistant_keys', $existing_record);
            } else {
                // Create new record for Claude usage
                $record = new \stdClass();
                $record->userid = $user_id;
                $record->fireworks_key_id = ''; // Empty for Claude-only usage
                $record->fireworks_api_key = ''; // Empty for Claude-only usage
                $record->claude_api_key = $claude_api_key;
                $record->display_name = "claude-user-{$user_id}";
                $record->created_time = time();
                $record->last_used = time();
                $record->is_active = 1;
                $DB->insert_record('block_aiassistant_keys', $record);
            }
        } catch (\Exception $e) {
            error_log('Database error storing Claude usage: ' . $e->getMessage());
            throw $e;
        }
    }
    
    private function create_api_key_for_user($user_id) {
        error_log("DEBUG: Starting create_api_key_for_user for user " . $user_id);

        $firectl_path = '/usr/local/bin/firectl';

        $create_key_cmd = "HOME=/tmp {$firectl_path} create api-key --key-name='moodle-user-{$user_id}' --service-account={$this->service_account_id}";
        error_log("DEBUG: Running create key command: " . $create_key_cmd);
        
        $key_result = shell_exec($create_key_cmd . ' 2>&1');
        error_log("DEBUG: Create key result: " . var_export($key_result, true));
        
        if (strpos($key_result, 'error') !== false || strpos($key_result, 'Failed') !== false) {
            throw new \Exception("Failed to create API key: " . $key_result);
        }
        
        // Parse the output to extract individual values
        $parsed_data = $this->parse_firectl_output($key_result);
        error_log("DEBUG: Parsed API key length: " . strlen($parsed_data['key']));
        
        return $parsed_data;
    }

    private function parse_firectl_output($output) {
        $lines = explode("\n", trim($output));
        $result = [
            'key' => '',
            'keyId' => '',
            'displayName' => ''
        ];
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (strpos($line, 'Key Id:') === 0) {
                $result['keyId'] = trim(str_replace('Key Id:', '', $line));
            } elseif (strpos($line, 'Display Name:') === 0) {
                $result['displayName'] = trim(str_replace('Display Name:', '', $line));
            } elseif (strpos($line, 'Key:') === 0) {
                $result['key'] = trim(str_replace('Key:', '', $line));
            }
        }
        
        // Validate we got all required fields
        if (empty($result['key']) || empty($result['keyId'])) {
            throw new \Exception("Failed to parse firectl output: " . $output);
        }
        
        return $result;
    }

    public function store_user_api_key($user_id, $fireworks_response) {
        global $DB;
        
        try {
            $record = new \stdClass();
            $record->userid = $user_id;
            $record->fireworks_key_id = $fireworks_response['keyId'];
            $record->fireworks_api_key = $fireworks_response['key'];
            $record->display_name = $fireworks_response['displayName'];
            $record->created_time = time();
            $record->last_used = null; // Not used yet
            $record->is_active = 1;
            return $DB->insert_record('block_aiassistant_keys', $record);
        } catch (\Exception $e) {
            error_log('Database error: ' . $e->getMessage());
            throw $e;
        }
        
    }
}