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
 * Settings for the mod_aiassistant plugin.
 *
 * @package   mod_aiassistant
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('modsettingaiassistant', get_string('pluginname', 'mod_aiassistant'));
    $ADMIN->add('modsettings', $settings);

    $settings->add(new admin_setting_heading(
        'mod_aiassistant/fireworks_heading',
        get_string('fireworks_heading', 'mod_aiassistant'),
        get_string('fireworks_heading_desc', 'mod_aiassistant')
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'mod_aiassistant/fireworks_api_key',
        get_string('fireworks_api_key', 'mod_aiassistant'),
        get_string('fireworks_api_key_desc', 'mod_aiassistant'),
        ''
    ));
}
