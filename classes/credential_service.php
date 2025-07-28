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
    
    public function generate_user_api_key($user_id) {
        $url = "https://api.fireworks.ai/v1/accounts/{$this->account_id}/apiKeys";
        $payload = ["apiKey" => ["displayName" => "moodle-user-{$user_id}"]];
        
        # HTTP request
        $response = $this->make_api_request($url, $payload);
          // Store in database
        $this->store_user_api_key($user_id, $response);
        
        // Return the key
        return $response['key'];
    }

    private function make_api_request($url, $payload) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . get_config('block_aiassistant', 'fireworks_api_token')
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
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
            $record->is_active = 1;
            return $DB->insert_record('block_aiassistant_keys', $record);
        } catch (\Exception $e) {
            error_log('Database error: ' . $e->getMessage());
            throw $e;
        }
        
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

}