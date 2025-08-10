<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$functions = [
    'block_aiassistant_get_user_credentials' => [
        'classname'   => 'block_aiassistant\external\get_user_credentials',
        'methodname'  => 'get_user_credentials',
        'description' => 'Get user credentials for AI API access',
        'type'        => 'read',
        'ajax'        => true,
        'loginrequired' => true,
    ],
    'block_aiassistant_get_ai_config' => [
        'classname'   => 'block_aiassistant\external\get_ai_config',
        'methodname'  => 'get_ai_config',
        'description' => 'Get AI configuration for frontend',
        'type'        => 'read',
        'ajax'        => true,
        'loginrequired' => true,
    ],
    'block_aiassistant_send_chat_message' => [
        'classname'   => 'block_aiassistant_external',
        'methodname'  => 'send_chat_message',
        'description' => 'Send chat message to AI and get response',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
    ],
];
