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
 * Library functions for the mod_craftpilot plugin.

 * @package   mod_craftpilot
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Supported features by this activity module.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know
 */
function craftpilot_supports($feature) {
    switch($feature) {
        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_RESOURCE;
        case FEATURE_GROUPS:
            return false;
        case FEATURE_GROUPINGS:
            return false;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_CONTENT;
        default:
            return null;
    }
}

/**
 * Add a new AI Assistant instance to the database.
 *
 * @param stdClass $data Form data with instance configuration
 * @param mod_craftpilot_mod_form $mform The form object (optional)
 * @return int New instance ID
 */
function craftpilot_add_instance($data, $mform = null) {
    global $DB;

    $data->timemodified = time();
    
    // Handle editor fields
    if (isset($data->content) && is_array($data->content)) {
        $data->contentformat = $data->content['format'];
        $data->content = $data->content['text'];
    }
    
    // Insert the instance
    $data->id = $DB->insert_record('craftpilot', $data);
    
    // Handle file uploads for content
    if ($mform) {
        $draftitemid = $data->content;
        $context = context_module::instance($data->coursemodule);
        $data->content = file_save_draft_area_files(
            $draftitemid,
            $context->id,
            'mod_craftpilot',
            'content',
            0,
            ['subdirs' => true],
            $data->content
        );
        $DB->update_record('craftpilot', $data);
    }
    
    // Update completion date event
    $completionexpected = (!empty($data->completionexpected)) ? $data->completionexpected : null;
    \core_completion\api::update_completion_date_event($data->coursemodule, 'craftpilot', $data->id, $completionexpected);
    
    return $data->id;
}

/**
 * Update an existing AI Assistant instance.
 *
 * @param stdClass $data Form data with instance configuration
 * @param mod_craftpilot_mod_form $mform The form object (optional)
 * @return bool True on success
 */
function craftpilot_update_instance($data, $mform = null) {
    global $DB;

    $data->timemodified = time();
    $data->id = $data->instance;
    
    // Handle editor fields
    if (isset($data->content) && is_array($data->content)) {
        $data->contentformat = $data->content['format'];
        $data->content = $data->content['text'];
    }
    
    // Update the instance
    $DB->update_record('craftpilot', $data);
    
    // Handle file uploads for content
    if ($mform) {
        $draftitemid = $data->content;
        $context = context_module::instance($data->coursemodule);
        $data->content = file_save_draft_area_files(
            $draftitemid,
            $context->id,
            'mod_craftpilot',
            'content',
            0,
            ['subdirs' => true],
            $data->content
        );
        $DB->update_record('craftpilot', $data);
    }
    
    // Update completion date event
    $completionexpected = (!empty($data->completionexpected)) ? $data->completionexpected : null;
    \core_completion\api::update_completion_date_event($data->coursemodule, 'craftpilot', $data->id, $completionexpected);
    
    return true;
}

/**
 * Delete an AI Assistant instance and all related data.
 *
 * @param int $id Instance ID to delete
 * @return bool True on success
 */
function craftpilot_delete_instance($id) {
    global $DB;

    if (!$instance = $DB->get_record('craftpilot', ['id' => $id])) {
        return false;
    }

    // Delete all conversations associated with this instance
    $conversations = $DB->get_records('craftpilot_conv', ['instanceid' => $id]);
    foreach ($conversations as $conversation) {
        // Delete all messages in this conversation
        $DB->delete_records('craftpilot_msg', ['conversation_id' => $conversation->conversation_id]);
    }
    
    // Delete the conversations
    $DB->delete_records('craftpilot_conv', ['instanceid' => $id]);
    
    // Delete the instance
    $DB->delete_records('craftpilot', ['id' => $id]);
    
    // Delete completion events
    $cm = get_coursemodule_from_instance('craftpilot', $id);
    if ($cm) {
        \core_completion\api::update_completion_date_event($cm->id, 'craftpilot', $id, null);
    }
    
    return true;
}

/**
 * Given a course_module object, this function returns extra information needed to print this activity.
 *
 * @param stdClass $coursemodule
 * @return cached_cm_info Info to customize main page display
 */
function craftpilot_get_coursemodule_info($coursemodule) {
    global $DB;

    if (!$instance = $DB->get_record('craftpilot', ['id' => $coursemodule->instance], 'id, name, intro, introformat')) {
        return null;
    }

    $info = new cached_cm_info();
    $info->name = $instance->name;

    if ($coursemodule->showdescription) {
        // Convert intro to html and add to coursepage.
        $info->content = format_module_intro('craftpilot', $instance, $coursemodule->id, false);
    }

    return $info;
}
