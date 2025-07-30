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
        
        // HTTP request
        $response = $this->make_api_request($url, $payload);

        // Store in database
        $this->store_user_api_key($user_id, $response);
        
        // Return the key
        return $response['key'];
    }

    private function make_api_request($url, $payload) {
        $api_token = get_config('block_aiassistant', 'fireworks_api_token');

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$api_token}",
                "Content-Type: application/json"
            ],
        ]);

        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            throw new \Exception("cURL Error: " . $err);
        }

        $decoded_response = json_decode($response, true);

        if (isset($decoded_response['code']) && $decoded_response['code'] !== 0) {
            throw new \Exception("Fireworks API error (code {$decoded_response['code']}): " . 
                                ($decoded_response['message'] ?? 'Unknown error'));
        }

        if (!isset($decoded_response['key']) || !isset($decoded_response['keyId'])) {
            throw new \Exception("Invalid API response: missing required fields");
        }
        
        if ($http_code !== 200) {
            throw new \Exception("API request failed with HTTP code {$http_code}: " . $response);
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON response: " . json_last_error_msg());
        }

        return $decoded_response;
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