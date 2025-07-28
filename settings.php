<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    // Create the settings page
    $settings = new admin_settingpage('blocksettingaiassistant', 
                                     get_string('pluginname', 'block_aiassistant'));

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

    // Register the settings page
    $ADMIN->add('blocksettings', $settings);
}
