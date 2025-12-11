<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * External functions and services for the mod_aiassistant plugin.
 *
 * @package   mod_aiassistant
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_aiassistant_get_user_credentials' => [
        'classname'   => 'mod_aiassistant\external\get_user_credentials',
        'methodname'  => 'get_user_credentials',
        'description' => 'Get user Fireworks API credentials',
        'type'        => 'read',
        'ajax'        => true,
        'loginrequired' => true,
    ],
    'mod_aiassistant_manage_conversations' => [
        'classname'   => 'mod_aiassistant\external\manage_conversations',
        'methodname'  => 'manage_conversations',
        'description' => 'Manage conversations for module instance',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
    ],
    'mod_aiassistant_manage_messages' => [
        'classname'   => 'mod_aiassistant\external\manage_messages',
        'methodname'  => 'manage_messages',
        'description' => 'Manage conversation messages (save, load)',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
    ],
];
