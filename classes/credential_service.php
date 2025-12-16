<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_aiassistant;

defined('MOODLE_INTERNAL') || die();

/**
 * Service class for managing AI service credentials (Global API keys)
 * 
 * @package    mod_aiassistant
 * @copyright  2025 Your Organization
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class credential_service {
    /**
     * Get the global Fireworks API key from plugin settings
     * 
     * @return string|null The Fireworks API key or null if not configured
     */
    public static function get_fireworks_api_key(): ?string {
        $api_key = get_config('mod_aiassistant', 'fireworks_api_key');
        
        if (!empty($api_key)) {
            self::log_debug("Fireworks API key found, length: " . strlen($api_key));
            return $api_key;
        }
        
        self::log_debug("Fireworks API key not configured");
        return null;
    }

    /**
     * Check if Fireworks API key is configured
     * 
     * @return bool True if configured, false otherwise
     */
    public static function is_fireworks_configured(): bool {
        return !empty(self::get_fireworks_api_key());
    }

    /**
     * Get available AI providers configuration
     * 
     * @return array Array of available providers with their configuration
     */
    public static function get_available_providers(): array {
        $providers = [];
        
        // Check Fireworks configuration
        if (self::is_fireworks_configured()) {
            $providers['fireworks'] = [
                'name' => 'Fireworks AI',
                'configured' => true,
                'models' => [
                    'llama-v3p1-70b-instruct',
                    'llama-v3p1-8b-instruct'
                ]
            ];
        } else {
            $providers['fireworks'] = [
                'name' => 'Fireworks AI',
                'configured' => false,
                'error' => 'API key not configured'
            ];
        }
        
        return $providers;
    }

    /**
     * Log debug message
     * 
     * @param string $message The message to log
     * @param array $context Additional context data
     */
    private static function log_debug(string $message, array $context = []): void {
        $context_str = empty($context) ? '' : ' ' . json_encode($context);
        error_log("AI Assistant Debug: {$message}{$context_str}");
    }
}