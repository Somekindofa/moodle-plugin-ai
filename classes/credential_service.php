<?php
// This file is part of Moodle - http://moodle.org/

class credential_service {
    private $account_id;
    
    public function __construct($account_id) {
        $this->account_id = $account_id;
    }
    
    public function generate_user_api_key($user_id) {
        $url = "https://api.fireworks.ai/v1/accounts/{$this->account_id}/apiKeys";
        $payload = ["apiKey" => ["displayName" => "moodle-user-{$user_id}"]];
        
        # HTTP request
        $response = $this->make_api_request($url, $payload);
        
        // Store in database
        $this->store_user_api_key($user_id, $response['key']);
        
        // Return the key
        return $response['api_key'];
    }

    private function make_api_request($url, $payload) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer YOUR_API_TOKEN_HERE'
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }

    public function store_user_api_key($user_id, $fireworks_response) {
        global $DB;
        
        $record = new stdClass();
        $record->userid = $user_id;
        $record->fireworks_key_id = $fireworks_response['keyId'];
        $record->fireworks_api_key = $fireworks_response['key'];
        $record->display_name = $fireworks_response['displayName'];
        $record->created_time = time();
        $record->is_active = 1;
        
        return $DB->insert_record('block_aiassistant_keys', $record);
    }

}