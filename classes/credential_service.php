<?php
// This file is part of Moodle - http://moodle.org/
namespace block_aiassistant;

defined('MOODLE_INTERNAL') || die();

class credential_service {
    private $account_id;
    
    public function __construct($account_id) {
        if (empty($account_id)) {
            throw new \Exception('Fireworks account ID not configured');
        }
        $this->account_id = $account_id;
    }

    public function get_user_credentials($user_id) {
        global $DB;
        
        // Check if user already has an active API key
        $existing_key = $DB->get_record('block_aiassistant_keys', [
            'userid' => $user_id,
            'is_active' => 1
        ]);

        if ($existing_key) {
            return [
                'api_key' => $existing_key->fireworks_api_key,
                'display_name' => $existing_key->display_name,
                'exists' => true
            ];
        }

        // No existing key, create new one
        $api_key = $this->generate_user_api_key($user_id);
        
        return [
            'api_key' => $api_key,
            'display_name' => "moodle-user-{$user_id}",
            'exists' => false
        ];
    }

    public function generate_user_api_key($user_id) {
        $response = $this->create_api_key_for_user($user_id);
        $this->store_user_api_key($user_id, $response);
        return $response['key'];
    }
    
    private function create_api_key_for_user($user_id) {
        $firectl_path = '/usr/local/bin/firectl';
        $api_token = get_config('block_aiassistant', 'fireworks_api_token');
        $service_account_id = get_config('block_aiassistant', 'fireworks_account_id');
        
        // Create API key under your existing service account
        $create_key_cmd = "HOME=/tmp FIREWORKS_API_KEY={$api_token} {$firectl_path} create api-key --service-account \"{$service_account_id}\"";
        $key_result = shell_exec($create_key_cmd . ' 2>&1');
        
        error_log("API key creation output: " . $key_result);
        
        if (strpos($key_result, 'error') !== false) {
            throw new \Exception("Failed to create API key: " . $key_result);
        }
        
        $api_key = trim($key_result);
        
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