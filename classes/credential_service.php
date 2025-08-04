<?php
// This file is part of Moodle - http://moodle.org/
namespace block_aiassistant;

defined('MOODLE_INTERNAL') || die();

class credential_service {
    private $account_id, $service_account_id;
    
    public function __construct($account_id, $service_account_id) {
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