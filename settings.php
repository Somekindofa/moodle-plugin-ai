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
}