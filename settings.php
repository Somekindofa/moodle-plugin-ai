<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_aiassistant', get_string('pluginname', 'local_aiassistant'));
    
    $settings->add(new admin_setting_configtext(
        'local_aiassistant/api_url',
        get_string('api_url', 'local_aiassistant'),
        get_string('api_url_desc', 'local_aiassistant'),
        'http://localhost:7860',
        PARAM_URL
    ));
    
    $settings->add(new admin_setting_configpasswordunmask(
        'local_aiassistant/api_key',
        get_string('api_key', 'local_aiassistant'),
        get_string('api_key_desc', 'local_aiassistant'),
        ''
    ));
    
    $settings->add(new admin_setting_configcheckbox(
        'local_aiassistant/enabled',
        get_string('enabled', 'local_aiassistant'),
        get_string('enabled_desc', 'local_aiassistant'),
        1
    ));
    
    $ADMIN->add('localplugins', $settings);
}
