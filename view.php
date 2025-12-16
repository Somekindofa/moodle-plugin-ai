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
 * Activity view page for the mod_craftpilot plugin.

 * @package   mod_craftpilot
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot.'/mod/craftpilot/lib.php');

$id = optional_param('id', 0, PARAM_INT); // Course module ID.
$a  = optional_param('a', 0, PARAM_INT);  // CraftPilot instance ID.

if ($id) {
    [$course, $cm] = get_course_and_cm_from_cmid($id, 'craftpilot');
    $instance = $DB->get_record('craftpilot', ['id' => $cm->instance], '*', MUST_EXIST);
} else {
    $instance = $DB->get_record('craftpilot', ['id' => $a], '*', MUST_EXIST);
    [$course, $cm] = get_course_and_cm_from_instance($instance->id, 'craftpilot');
}

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/craftpilot:view', $context);

// Log view event (for completion tracking).
$event = \mod_craftpilot\event\course_module_viewed::create([
    'objectid' => $instance->id,
    'context' => $context,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('craftpilot', $instance);
$event->add_record_snapshot('course_modules', $cm);
$event->trigger();

// Mark activity as viewed (completion).
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// Setup page.
$PAGE->set_url('/mod/craftpilot/view.php', ['id' => $cm->id]);
$PAGE->set_title($course->shortname.': '.$instance->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_context($context);
$PAGE->set_activity_record($instance);

// Output header.
echo $OUTPUT->header();

// Display intro (if show description is enabled).
if (trim(strip_tags($instance->intro))) {
    echo $OUTPUT->box_start('mod_introbox');
    echo format_module_intro('craftpilot', $instance, $cm->id);
    echo $OUTPUT->box_end();
}

// Display main content.
echo $OUTPUT->box_start('generalbox aiassistant-content');
$content = file_rewrite_pluginfile_urls(
    $instance->content,
    'pluginfile.php',
    $context->id,
    'mod_craftpilot',
    'content',
    0
);
echo format_text($content, $instance->contentformat, [
    'noclean' => true,
    'overflowdiv' => true,
    'context' => $context
]);
echo $OUTPUT->box_end();

// Display AI prompt bar if enabled.
if ($instance->enable_promptbar) {
    // Load marked.js for markdown rendering.
    $PAGE->requires->js(new moodle_url('https://cdn.jsdelivr.net/npm/marked/lib/marked.umd.js'), true);
    
    // Load chat interface.
    $PAGE->requires->js_call_amd('mod_craftpilot/chat_interface', 'init', [
        $cm->id,
        $course->id,
        $instance->id
    ]);
    
    // Output chat interface HTML.
    echo '<div class="aiassistant-chat-wrapper">';
    echo '<div class="aiassistant-chat-container">';
    
    // Chat input area (fixed at bottom).
    echo '<div class="ai-input-area">';
    echo '<textarea id="user-input" placeholder="'.get_string('promptplaceholder', 'craftpilot').'" rows="2"></textarea>';
    echo '<button id="send-btn"><i class="fa fa-paper-plane"></i> '.get_string('send', 'craftpilot').'</button>';
    echo '</div>';
    
    // Message display area.
    echo '<div class="ai-messages-area" id="messages-area"></div>';
    
    // Documents sidepanel (hidden by default).
    echo '<div class="ai-sidepanel" id="documents-sidepanel" style="display: none;">';
    echo '<div class="sidepanel-header">'.get_string('retrieveddocs', 'craftpilot').'</div>';
    echo '<div id="documents-list"></div>';
    echo '<div id="video-player-container"></div>';
    echo '</div>';
    
    echo '</div>'; // .aiassistant-chat-container
    echo '</div>'; // .aiassistant-chat-wrapper
    
    // Add CSS for chat interface.
    echo '<style>
    .aiassistant-chat-wrapper {
        margin-top: 2rem;
        position: relative;
    }
    .aiassistant-chat-container {
        display: flex;
        flex-direction: column;
        max-width: 100%;
        margin: 0 auto;
    }
    .ai-input-area {
        display: flex;
        gap: 10px;
        padding: 15px;
        background: #f9f9f9;
        border: 1px solid #ddd;
        border-radius: 8px;
        position: fixed;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        width: 90%;
        max-width: 800px;
        z-index: 1000;
        box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
    }
    .ai-input-area textarea {
        flex: 1;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        resize: none;
        font-size: 14px;
    }
    .ai-input-area button {
        padding: 10px 20px;
        background: #0066cc;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        white-space: nowrap;
    }
    .ai-input-area button:hover {
        background: #0052a3;
    }
    .ai-messages-area {
        padding: 20px;
        margin-bottom: 120px;
        min-height: 200px;
    }
    .ai-message {
        margin-bottom: 20px;
        padding: 15px;
        border-radius: 8px;
        max-width: 80%;
    }
    .ai-message.user {
        background: #e3f2fd;
        margin-left: auto;
        text-align: right;
    }
    .ai-message.ai {
        background: #f5f5f5;
        margin-right: auto;
    }
    .ai-sidepanel {
        position: fixed;
        right: 0;
        top: 100px;
        width: 300px;
        height: calc(100vh - 200px);
        background: white;
        border-left: 1px solid #ddd;
        padding: 15px;
        overflow-y: auto;
        z-index: 999;
    }
    .sidepanel-header {
        font-weight: bold;
        margin-bottom: 15px;
        font-size: 16px;
    }
    </style>';
}

echo $OUTPUT->footer();
