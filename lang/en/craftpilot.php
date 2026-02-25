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
 * Language strings for the mod_craftpilot plugin.

 * @package   mod_craftpilot
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'CraftPilot';
$string['modulename'] = 'CraftPilot';
$string['modulename_help'] = 'The CraftPilot module allows you to create content pages with an optional AI chat interface powered by Fireworks.ai. Students can ask questions about the course content and receive AI-generated responses with retrieved documents.';
$string['modulenameplural'] = 'CraftPilots';
$string['craftpilot:addinstance'] = 'Add a new CraftPilot activity';
$string['craftpilot:view'] = 'View CraftPilot activity';

// Form strings
$string['contentheader'] = 'Page Content';
$string['content'] = 'Content';
$string['content_help'] = 'Enter the main content for this page. This will be displayed above the AI chat interface if enabled.';
$string['promptbarsection'] = 'AI Chat Settings';
$string['enablepromptbar'] = 'Enable AI prompt bar';
$string['enablepromptbar_desc'] = 'Show the AI chat interface at the bottom of the page';
$string['enablepromptbar_help'] = 'When enabled, students will see an AI chat interface at the bottom of the page where they can ask questions about the course content.';

// Chat interface strings
$string['promptplaceholder'] = 'Ask a question about this content...';
$string['send'] = 'Send';
$string['openchat'] = 'Open CraftPilot chat';
$string['closechat'] = 'Close chat';
$string['retrieveddocs'] = 'Retrieved Documents';
$string['sources'] = 'Sources';
$string['newconversation'] = 'New conversation';
$string['conversations'] = 'Conversations';

// Fireworks.ai Configuration
$string['fireworks_heading'] = 'Fireworks.ai Configuration';
$string['fireworks_heading_desc'] = 'Configure Fireworks.ai API settings for CraftPilot functionality';
$string['fireworks_api_key'] = 'Fireworks Master API Key';
$string['fireworks_api_key_desc'] = 'Your master Fireworks.ai API key for direct API access (used for all users)';
