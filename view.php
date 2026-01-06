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
require_once($CFG->dirroot . '/mod/craftpilot/lib.php');

$id = optional_param('id', 0, PARAM_INT); // Course module ID.
$a = optional_param('a', 0, PARAM_INT);  // CraftPilot instance ID.

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
$PAGE->set_title($course->shortname . ': ' . $instance->name);
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
    echo '<div class="ai-chat-wrapper" id="ai-chat-wrapper">';

    // Left unified sidebar panel (Conversations + Documents)
    echo '<div class="ai-sidebar-panel" id="ai-sidebar-panel">';
    echo '<div class="ai-sidebar-section conversations-section">';
    echo '<div class="sidebar-header">Conversations</div>';
    echo '<div id="conversations-list" class="conversations-list"></div>';
    echo '</div>';
    echo '<div class="ai-sidebar-divider"></div>';
    echo '<div class="ai-sidebar-section documents-section">';
    echo '<div class="sidebar-header">Retrieved Documents</div>';
    echo '<div id="documents-list" class="documents-list"></div>';
    echo '<div id="video-player-container" class="video-player-container"></div>';
    echo '</div>';
    echo '</div>';

    // Chat interface container (hidden by default, slides up from bottom)
    echo '<div id="ai-chat-interface" class="ai-chat-interface">';

    // Conversation header with name
    echo '<div class="ai-chat-header">';
    // Sidebar toggle handle
    echo '<button id="ai-sidebar-toggle" class="ai-sidebar-toggle" title="Toggle sidebar" aria-label="Toggle sidebar">';
    echo '<i class="fa-solid fa-file"></i>';
    echo '</button>';
    echo '<span id="current-conversation-title" class="conversation-title">Chat</span>';
    echo '</div>';

    // Messages container (scrollable)
    echo '<div class="ai-messages-wrapper">';
    echo '<div class="ai-messages-area" id="messages-area"></div>';
    echo '</div>';

    // Chat input area
    echo '<div class="ai-input-container">';
    echo '<div class="ai-input-area">';
    echo '<textarea id="user-input" placeholder="' . get_string('promptplaceholder', 'craftpilot') . '" rows="2"></textarea>';
    echo '<button id="send-btn" class="send-button"><i class="fa fa-paper-plane"></i></button>';
    echo '</div>';
    echo '</div>';

    echo '</div>'; // .ai-chat-interface

    // Hammer bubble button (appears in footer)
    echo '<button id="ai-chat-toggle" class="ai-chat-bubble" title="Open CraftPilot Chat">';
    echo '</button>';

    echo '</div>'; // .ai-chat-wrapper

    // Add comprehensive CSS for chat interface.
    echo '<style>
    /* Chat Wrapper - Main Container */
    .ai-chat-wrapper {
        position: fixed;
        bottom: 0;
        left: 285px;
        right: 80px;
        height: 100vh;
        display: flex;
        gap: 5px;
        padding: 10px;
        background: transparent;
        z-index: 1050;
        pointer-events: none;
    }

    .ai-chat-wrapper:not(.expanded) {
        gap: 0;
    }
    
    /* Sidebar Panel (Left) */
    .ai-sidebar-panel {
        flex: 0 0 0;
        width: 0;
        min-width: 0;
        height: 100%;
        background: white;
        border: 1px solid #ddd;
        border-radius: 8px;
        display: flex;
        flex-direction: column;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        pointer-events: none;
        opacity: 0;
        transform: translateX(-12px);
        transition: all 0.25s ease-out;
    }
    
    .ai-sidebar-panel.expanded {
        flex: 0 0 280px;
        width: 280px;
        min-width: 280px;
        opacity: 1;
        pointer-events: auto;
        transform: translateX(0);
    }

    .ai-sidebar-toggle {
        position: relative;
        align-self: center;
        width: 24px;
        height: 56px;
        margin-left: -4px;
        margin-right: -4px;
        border: none;
        background: #0066cc;
        color: white;
        cursor: pointer;
        border-radius: 0 28px 28px 0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        pointer-events: auto;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.2s ease, transform 0.2s ease;
    }

    .ai-sidebar-toggle:hover {
        background: #0052a3;
        transform: translateX(2px);
    }

    .ai-sidebar-toggle-arrow {
        font-size: 14px;
        line-height: 1;
    }
    
    /* Sidebar Sections */
    .ai-sidebar-section {
        flex: 1;
        display: flex;
        flex-direction: column;
        min-height: 0;
        overflow: hidden;
    }
    
    .ai-sidebar-divider {
        height: 1px;
        background: #e0e0e0;
        flex-shrink: 0;
    }
    
    .sidebar-header {
        padding: 12px 15px;
        font-weight: 600;
        font-size: 13px;
        background: #f5f5f5;
        border-bottom: 1px solid #ddd;
        color: #666;
        flex-shrink: 0;
    }
    
    .conversations-list,
    .documents-list {
        flex: 1;
        overflow-y: auto;
        padding: 8px;
    }

    .conversation-item,
    .document-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 10px;
        margin-bottom: 6px;
        background: #fafafa;
        border: 1px solid #e0e0e0;
        border-radius: 4px;
        cursor: pointer;
        font-size: 13px;
        color: #333;
        transition: all 0.2s ease;
        pointer-events: auto;
    }

    .conversation-item:hover,
    .document-item:hover {
        background: #e8f4ff;
        border-color: #0066cc;
        color: #0066cc;
    }

    .conversation-item.active {
        background: #0066cc;
        border-color: #0066cc;
        color: white;
    }

    .conversation-meta {
        display: flex;
        flex-direction: column;
        gap: 2px;
        flex: 1;
        min-width: 0;
    }

    .conversation-delete {
        border: none;
        background: transparent;
        color: #999;
        cursor: pointer;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        flex-shrink: 0;
    }

    .conversation-delete:hover {
        background: rgba(0,0,0,0.05);
        color: #c0392b;
    }
    
    /* Chat Interface Container */
    .ai-chat-interface {
        flex: 1;
        background: white;
        border: 1px solid #ddd;
        border-radius: 8px;
        z-index: 1050;
        display: flex;
        flex-direction: column;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        pointer-events: auto;
        transform: translateY(100%);
        transition: transform 0.4s ease-out;
    }
    
    .ai-chat-interface.expanded {
        transform: translateY(0);
    }
    
    /* Chat Header */
    .ai-chat-header {
        padding: 15px 20px;
        background: #f5f5f5;
        border-bottom: 1px solid #ddd;
        font-weight: 600;
        font-size: 16px;
        color: #333;
        flex-shrink: 0;
        border-radius: 8px 8px 0 0;
    }
    
    .conversation-title {
        display: inline-block;
    }
    
    /* Messages Wrapper and Area */
    .ai-messages-wrapper {
        flex: 1;
        overflow-y: auto;
        padding: 15px 20px;
        background: #fafafa;
    }
    
    .ai-messages-area {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    
    .ai-message {
        display: flex;
        margin-bottom: 0;
        padding: 12px 15px;
        border-radius: 12px;
        max-width: 75%;
        word-wrap: break-word;
        font-size: 14px;
        line-height: 1.5;
    }
    
    .ai-message.user-message {
        background: #f5f5f5;
        margin-left: auto;
        margin-right: 0;
        color: #333;
        border: 1px solid #e0e0e0;
    }
    
    .ai-message.assistant-message {
        background: #e8e8e8;
        margin-left: 0;
        margin-right: auto;
        color: #333;
    }
    
    .ai-message strong {
        display: none;
    }
    
    /* Input Container */
    .ai-input-container {
        display: flex;
        padding: 15px;
        background: white;
        border-top: 1px solid #ddd;
        flex-shrink: 0;
        border-radius: 0 0 8px 8px;
        pointer-events: auto;
    }
    
    /* Input Area */
    .ai-input-area {
        display: flex;
        gap: 8px;
        flex: 1;
    }
    
    .ai-input-area textarea {
        flex: 1;
        padding: 10px 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        resize: none;
        font-size: 14px;
        font-family: inherit;
        max-height: 100px;
        pointer-events: auto;
    }
    
    .ai-input-area textarea:focus {
        outline: none;
        border-color: #0066cc;
        box-shadow: 0 0 0 3px rgba(0,102,204,0.1);
    }
    
    .send-button {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: #0066cc;
        color: white;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        transition: all 0.2s ease;
        flex-shrink: 0;
        pointer-events: auto;
    }
    
    .send-button:hover:not(:disabled) {
        background: #0052a3;
    }
    
    .send-button:disabled {
        background: #ccc;
        cursor: not-allowed;
    }
    
    /* Hammer Bubble */
    .ai-chat-bubble {
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: #0066cc;
        color: white;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.25);
        pointer-events: auto;
        z-index: 1100;
        transition: all 0.4s ease-out;
    }
    
    /* Hammer icon using pseudo-element */
    .ai-chat-bubble::before {
        content: "🔨";
        font-size: 24px;
        line-height: 1;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .ai-chat-bubble:hover {
        background: #0052a3;
        transform: scale(1.1);
    }
    
    /* Video Player Container */
    .video-player-container {
        padding: 8px;
    }
    
    .video-player-container iframe {
        max-width: 100%;
        border-radius: 4px;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .ai-chat-wrapper {
            left: 0;
            right: 60px;
            gap: 0;
            padding: 0;
        }
        
        .ai-sidebar-panel {
            position: absolute;
            width: 260px;
            height: 100%;
            border-radius: 0;
            left: 0;
            top: 0;
        }
        
        .ai-chat-interface {
            width: 100%;
            border-radius: 0;
            height: 100%;
        }
        
        .ai-message {
            max-width: 85%;
        }
        
        .ai-chat-bubble {
            bottom: 15px;
            right: 15px;
            width: 45px;
            height: 45px;
        }
    }
    </style>';
}

echo $OUTPUT->footer();
