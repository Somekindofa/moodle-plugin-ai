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
        $api_token = get_config('block_aiassistant', 'fireworks_api_token');
        
        error_log("DEBUG: API token configured: " . (empty($api_token) ? 'NO' : 'YES'));

        // Create API key under your existing service account
        $signin_cmd = "timeout 30 sh -c 'HOME=/tmp {$firectl_path} signin \"{$this->account_id}\"'";
        error_log("DEBUG: Running signin command: " . $signin_cmd);

        $signin_result = shell_exec($signin_cmd . ' 2>&1');
        error_log("DEBUG: Signin result: " . var_export($signin_result, true));

        if (strpos($signin_result, 'error') !== false || strpos($signin_result, 'Failed') !== false) {
            throw new \Exception("Failed to signin: " . $signin_result);
        }

        $create_key_cmd = "timeout 30 sh -c 'HOME=/tmp {$firectl_path} create api-key --service-account {$this->service_account_id}'";
        error_log("DEBUG: Running create key command: " . $create_key_cmd);
        
        $key_result = shell_exec($create_key_cmd . ' 2>&1');
        error_log("DEBUG: Create key result: " . var_export($key_result, true));
        
        if (strpos($key_result, 'error') !== false || strpos($key_result, 'Failed') !== false) {
            throw new \Exception("Failed to create API key: " . $key_result);
        }
        
        $api_key = trim($key_result);
        error_log("DEBUG: Extracted API key length: " . strlen($api_key));
        
        return [
            'key' => $api_key,
            'keyId' => "user-{$user_id}-key",
            'displayName' => "moodle-user-{$user_id}"
        ];
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