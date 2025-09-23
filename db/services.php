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
    'block_aiassistant_manage_conversations' => [
        'classname'   => 'block_aiassistant\external\manage_conversations',
        'methodname'  => 'manage_conversations',
        'description' => 'Manage user conversations (create, list, update, delete)',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
    ],
    'block_aiassistant_manage_messages' => [
        'classname'   => 'block_aiassistant\external\manage_messages',
        'methodname'  => 'manage_messages',
        'description' => 'Manage conversation messages (save, load)',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
    ],
];
