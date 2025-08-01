<?php
defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext(
        'block_aiassistant/fireworks_account_id',
        get_string('fireworks_account_id', 'block_aiassistant'),
        get_string('fireworks_account_id_desc', 'block_aiassistant'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'block_aiassistant/fireworks_api_token',
        get_string('fireworks_api_token', 'block_aiassistant'),
        get_string('fireworks_api_token_desc', 'block_aiassistant'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'block_aiassistant/fireworks_service_account_id',
        get_string('fireworks_service_account_id', 'block_aiassistant'),
        get_string('fireworks_service_account_id_desc', 'block_aiassistant'),
        '',
        PARAM_TEXT
    ));
}