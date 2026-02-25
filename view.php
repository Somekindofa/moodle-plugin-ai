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

    // Load AMD chat interface module.
    // Pass the proxy URL so the JS never hard-codes an internal address.
    $proxyurl = (new moodle_url('/mod/craftpilot/chat_proxy.php'))->out(false);
    $PAGE->requires->js_call_amd('mod_craftpilot/chat_interface', 'init', [
        $cm->id,
        $course->id,
        $instance->id,
        $proxyurl,
    ]);

    // Render chat interface via Mustache template.
    $templatectx = [
        'cmid'       => $cm->id,
        'courseid'   => $course->id,
        'instanceid' => $instance->id,
    ];
    echo $OUTPUT->render_from_template('mod_craftpilot/chat_interface', $templatectx);
}

echo $OUTPUT->footer();
