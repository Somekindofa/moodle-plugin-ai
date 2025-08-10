<?php
defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    // Fireworks.ai Configuration Section
    $settings->add(new admin_setting_heading(
        'block_aiassistant/fireworks_heading',
        get_string('fireworks_heading', 'block_aiassistant'),
        get_string('fireworks_heading_desc', 'block_aiassistant')
    ));

    $settings->add(new admin_setting_configtext(
        'block_aiassistant/fireworks_api_key',
        get_string('fireworks_api_key', 'block_aiassistant'),
        get_string('fireworks_api_key_desc', 'block_aiassistant'),
        '',
        PARAM_TEXT
    ));

    // Keep the old settings for backward compatibility but mark them as deprecated
    $settings->add(new admin_setting_configtext(
        'block_aiassistant/fireworks_account_id',
        get_string('fireworks_account_id', 'block_aiassistant') . ' (Legacy - not used)',
        get_string('fireworks_account_id_desc', 'block_aiassistant') . ' This setting is no longer used with the new direct API approach.',
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'block_aiassistant/fireworks_api_token',
        get_string('fireworks_api_token', 'block_aiassistant') . ' (Legacy - not used)',
        get_string('fireworks_api_token_desc', 'block_aiassistant') . ' This setting is no longer used with the new direct API approach.',
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'block_aiassistant/fireworks_service_account_id',
        get_string('fireworks_service_account_id', 'block_aiassistant') . ' (Legacy - not used)',
        get_string('fireworks_service_account_id_desc', 'block_aiassistant') . ' This setting is no longer used with the new direct API approach.',
        '',
        PARAM_TEXT
    ));

    // Claude API Configuration Section
    $settings->add(new admin_setting_heading(
        'block_aiassistant/claude_heading',
        get_string('claude_heading', 'block_aiassistant'),
        get_string('claude_heading_desc', 'block_aiassistant')
    ));

    $settings->add(new admin_setting_configtext(
        'block_aiassistant/claude_api_key',
        get_string('claude_api_key', 'block_aiassistant'),
        get_string('claude_api_key_desc', 'block_aiassistant'),
        '',
        PARAM_TEXT
    ));

    $claude_models = [
        'claude-opus-4-1-20250805' => get_string('claude_opus_4_1', 'block_aiassistant'),
        'claude-sonnet-4-20250514' => get_string('claude_sonnet_4', 'block_aiassistant'),
        'claude-3-5-haiku-latest' => get_string('claude_haiku', 'block_aiassistant')
    ];

    $settings->add(new admin_setting_configselect(
        'block_aiassistant/claude_default_model',
        get_string('claude_default_model', 'block_aiassistant'),
        get_string('claude_default_model_desc', 'block_aiassistant'),
        'claude-sonnet-4-20250514',
        $claude_models
    ));
}