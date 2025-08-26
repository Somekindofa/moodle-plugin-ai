<?php
// This file is part of Moodle - http://moodle.org/

namespace block_aiassistant;

defined('MOODLE_INTERNAL') || die();

/**
 * Service class for managing AI service credentials (Fireworks and Claude)
 * 
 * @package    block_aiassistant
 * @copyright  2025 Your Organization
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class credential_service {
    
    /** @var string Dummy value for testing/Claude-only mode */
    private const DUMMY_VALUE = 'dummy';
    
    /** @var string Path to firectl binary */
    private const FIRECTL_PATH = '/usr/local/bin/firectl';
    
    /** @var string Fireworks account ID */
    private $account_id;
    
    /** @var string Fireworks service account ID */
    private $service_account_id;
    
    /** @var bool Whether we're in dummy/Claude-only mode */
    private $is_dummy_mode = false;

    /**
     * Constructor
     * 
     * @param string|null $account_id Fireworks account ID
     * @param string|null $service_account_id Fireworks service account ID
     * @throws \Exception If required credentials are missing
     */
    public function __construct(?string $account_id, ?string $service_account_id) {
        $this->is_dummy_mode = $this->is_dummy_credentials($account_id, $service_account_id);
        
        if ($this->is_dummy_mode) {
            $this->account_id = $account_id ?: self::DUMMY_VALUE;
            $this->service_account_id = $service_account_id ?: self::DUMMY_VALUE;
            $this->log_debug('Using dummy Fireworks credentials for Claude-only operation');
            return;
        }
        
        if (empty($account_id) || empty($service_account_id)) {
            throw new \Exception('Fireworks.ai credentials are required. Please configure them in the plugin settings.');
        }
        
        $this->account_id = $account_id;
        $this->service_account_id = $service_account_id;
    }

    /**
     * Generate and store a new Fireworks API key for a user
     * 
     * @param int $user_id The user ID
     * @return string The generated API key
     * @throws \Exception If key generation fails
     */
    public function generate_fireworks_api_key(int $user_id): string {
        if ($this->is_dummy_mode) {
            throw new \Exception('Cannot generate Fireworks API keys in dummy mode');
        }
        
        $key_data = $this->create_fireworks_api_key($user_id);
        $this->store_fireworks_api_key($user_id, $key_data);
        
        return $key_data['key'];
    }

    /**
     * Get Claude API key for user (uses shared admin key)
     * 
     * @param int $user_id The user ID
     * @return string The Claude API key
     * @throws \Exception If Claude API key is not configured
     */
    public function get_claude_api_key(int $user_id): string {
        $claude_api_key = get_config('block_aiassistant', 'claude_api_key');
        
        if (empty($claude_api_key)) {
            throw new \Exception('Claude API key is not configured. Please set it in the plugin settings.');
        }

        $this->store_claude_api_usage($user_id, $claude_api_key);
        
        return $claude_api_key;
    }

    /**
     * Get existing user credentials from database
     * 
     * @param int $user_id The user ID
     * @return \stdClass|null The user's credential record or null if not found
     */
    public function get_user_credentials(int $user_id): ?\stdClass {
        global $DB;
        
        try {
            return $DB->get_record('block_aiassistant_keys', [
                'userid' => $user_id,
                'is_active' => 1
            ]);
        } catch (\Exception $e) {
            $this->log_error('Failed to retrieve user credentials', $e, ['user_id' => $user_id]);
            return null;
        }
    }

    /**
     * Check if credentials are dummy values
     */
    private function is_dummy_credentials(?string $account_id, ?string $service_account_id): bool {
        $is_account_dummy = empty($account_id) || $account_id === self::DUMMY_VALUE;
        $is_service_dummy = empty($service_account_id) || $service_account_id === self::DUMMY_VALUE;
        
        return $is_account_dummy && $is_service_dummy;
    }

    /**
     * Create a new Fireworks API key using firectl
     * 
     * @param int $user_id The user ID
     * @return array Array containing key, keyId, and displayName
     * @throws \Exception If key creation fails
     */
    private function create_fireworks_api_key(int $user_id): array {
        $this->log_debug("Creating Fireworks API key for user {$user_id}");

        $key_name = "moodle-user-{$user_id}";
        $command = sprintf(
            "HOME=/tmp %s create api-key --key-name='%s' --service-account=%s 2>&1",
            self::FIRECTL_PATH,
            $key_name,
            $this->service_account_id
        );
        
        $this->log_debug("Executing firectl command", ['command' => $command]);
        
        $output = shell_exec($command);
        
        if (empty($output) || $this->is_firectl_error($output)) {
            throw new \Exception("Failed to create Fireworks API key: {$output}");
        }
        
        $key_data = $this->parse_firectl_output($output);
        $this->log_debug("Successfully created Fireworks API key", ['key_id' => $key_data['keyId']]);
        
        return $key_data;
    }

    /**
     * Parse firectl command output
     * 
     * @param string $output The command output
     * @return array Parsed key data
     * @throws \Exception If parsing fails
     */
    private function parse_firectl_output(string $output): array {
        $lines = array_filter(array_map('trim', explode("\n", trim($output))));
        $result = ['key' => '', 'keyId' => '', 'displayName' => ''];
        
        foreach ($lines as $line) {
            if (strpos($line, 'Key Id:') === 0) {
                $result['keyId'] = trim(str_replace('Key Id:', '', $line));
            } elseif (strpos($line, 'Display Name:') === 0) {
                $result['displayName'] = trim(str_replace('Display Name:', '', $line));
            } elseif (strpos($line, 'Key:') === 0) {
                $result['key'] = trim(str_replace('Key:', '', $line));
            }
        }
        
        if (empty($result['key']) || empty($result['keyId'])) {
            throw new \Exception("Failed to parse firectl output. Output: {$output}");
        }
        
        return $result;
    }

    /**
     * Store Fireworks API key in database
     * 
     * @param int $user_id The user ID
     * @param array $key_data The key data from firectl
     * @throws \Exception If database operation fails
     */
    private function store_fireworks_api_key(int $user_id, array $key_data): void {
        global $DB;
        
        try {
            $record = new \stdClass();
            $record->userid = $user_id;
            $record->fireworks_key_id = $key_data['keyId'];
            $record->fireworks_api_key = $key_data['key'];
            $record->claude_api_key = '';
            $record->display_name = $key_data['displayName'];
            $record->created_time = time();
            $record->last_used = null;
            $record->is_active = 1;
            
            $DB->insert_record('block_aiassistant_keys', $record);
            $this->log_debug("Stored Fireworks API key for user {$user_id}");
            
        } catch (\Exception $e) {
            $this->log_error('Failed to store Fireworks API key', $e, ['user_id' => $user_id]);
            throw new \Exception('Failed to save API key to database');
        }
    }

    /**
     * Store Claude API usage record in database
     * 
     * @param int $user_id The user ID
     * @param string $claude_api_key The Claude API key
     * @throws \Exception If database operation fails
     */
    private function store_claude_api_usage(int $user_id, string $claude_api_key): void {
        global $DB;
        
        try {
            $existing_record = $this->get_user_credentials($user_id);

            if ($existing_record) {
                $existing_record->claude_api_key = $claude_api_key;
                $existing_record->last_used = time();
                $DB->update_record('block_aiassistant_keys', $existing_record);
            } else {
                $record = new \stdClass();
                $record->userid = $user_id;
                $record->fireworks_key_id = '';
                $record->fireworks_api_key = '';
                $record->claude_api_key = $claude_api_key;
                $record->display_name = "claude-user-{$user_id}";
                $record->created_time = time();
                $record->last_used = time();
                $record->is_active = 1;
                
                $DB->insert_record('block_aiassistant_keys', $record);
            }
            
            $this->log_debug("Stored Claude API usage for user {$user_id}");
            
        } catch (\Exception $e) {
            $this->log_error('Failed to store Claude API usage', $e, ['user_id' => $user_id]);
            throw new \Exception('Failed to save Claude API usage to database');
        }
    }

    /**
     * Check if firectl output indicates an error
     */
    private function is_firectl_error(string $output): bool {
        $error_indicators = ['error', 'Error', 'ERROR', 'Failed', 'failed', 'FAILED'];
        
        foreach ($error_indicators as $indicator) {
            if (strpos($output, $indicator) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Log debug message
     */
    private function log_debug(string $message, array $context = []): void {
        $context_str = empty($context) ? '' : ' ' . json_encode($context);
        error_log("AI Assistant Debug: {$message}{$context_str}");
    }

    /**
     * Log error message
     */
    private function log_error(string $message, \Exception $e, array $context = []): void {
        $context_str = empty($context) ? '' : ' Context: ' . json_encode($context);
        error_log("AI Assistant Error: {$message} - {$e->getMessage()}{$context_str}");
    }

    // Legacy method - kept for backward compatibility
    public function generate_user_api_key($user_id) {
        return $this->generate_fireworks_api_key($user_id);
    }

    // Legacy method - kept for backward compatibility  
    public function store_user_api_key($user_id, $fireworks_response) {
        $this->store_fireworks_api_key($user_id, $fireworks_response);
    }
}