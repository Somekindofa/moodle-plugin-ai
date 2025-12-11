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
 * Activity creation/editing form for the mod_aiassistant plugin.
 *
 * @package   mod_aiassistant
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/course/moodleform_mod.php');

/**
 * Form for creating/editing AI Assistant activity instances.
 */
class mod_aiassistant_mod_form extends moodleform_mod {

    /**
     * Define the form elements.
     */
    public function definition() {
        global $CFG;

        $mform = $this->_form;

        // General section.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Activity name (required).
        $mform->addElement('text', 'name', get_string('name'), ['size' => '48']);
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Description/intro (standard Moodle field).
        $this->standard_intro_elements();

        // Content section.
        $mform->addElement('header', 'contentsection', get_string('contentheader', 'aiassistant'));

        // Page content editor (HTML editor).
        $mform->addElement(
            'editor',
            'content',
            get_string('content', 'aiassistant'),
            ['rows' => 20],
            [
                'maxfiles' => EDITOR_UNLIMITED_FILES,
                'noclean' => true,
                'context' => $this->context,
                'subdirs' => true
            ]
        );
        $mform->setType('content', PARAM_RAW);
        $mform->addRule('content', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('content', 'content', 'aiassistant');

        // AI prompt bar section.
        $mform->addElement('header', 'promptbarsection', get_string('promptbarsection', 'aiassistant'));

        // Enable prompt bar checkbox.
        $mform->addElement(
            'advcheckbox',
            'enable_promptbar',
            get_string('enablepromptbar', 'aiassistant'),
            get_string('enablepromptbar_desc', 'aiassistant')
        );
        $mform->setDefault('enable_promptbar', 1);
        $mform->addHelpButton('enable_promptbar', 'enablepromptbar', 'aiassistant');

        // Standard Moodle course module settings.
        $this->standard_coursemodule_elements();

        // Buttons.
        $this->add_action_buttons();
    }

    /**
     * Preprocess data before displaying the form.
     *
     * @param array $default_values Default values for the form
     */
    public function data_preprocessing(&$default_values) {
        // Prepare content editor defaults safely (handles new and existing instances).
        $draftitemid = file_get_submitted_draft_itemid('content');

        $existingcontent = '';
        if (!empty($default_values['content'])) {
            // Existing records store raw HTML in content.
            $existingcontent = is_array($default_values['content']) ? ($default_values['content']['text'] ?? '') : $default_values['content'];
        }

        $default_values['content'] = [
            'text' => file_prepare_draft_area(
                $draftitemid,
                $this->context->id,
                'mod_aiassistant',
                'content',
                0,
                ['subdirs' => true],
                $existingcontent
            ),
            'format' => $default_values['contentformat'] ?? FORMAT_HTML,
            'itemid' => $draftitemid,
        ];
    }
}
