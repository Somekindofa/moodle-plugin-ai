<?php
// This file is part of Moodle - http://moodle.org/

class credential_service {
    public function generate_user_api_key($user_id) {
    $url = "https://api.fireworks.ai/v1/accounts/{$this->account_id}/apiKeys";
    $payload = ["apiKey" => ["displayName" => "moodle-user-{$user_id}"]];
    // Make HTTP request, store returned key in database
    // Return the key to your block
    }
}   // Return the key to your block