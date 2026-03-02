<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Event observer registration for mod_craftpilot.
 *
 * Registers observers for core Moodle course module events so that the
 * ChromaDB vector store is kept in sync with course content automatically.
 *
 * internal => false  means the callback fires AFTER the DB transaction that
 * triggered the event has been committed.  This ensures we read the final
 * state of the module content, not an in-flight version.
 *
 * @package   mod_craftpilot
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\course_module_created',
        'callback'  => 'mod_craftpilot\observer::course_module_created',
        'internal'  => false,
        'priority'  => 200,
    ],
    [
        'eventname' => '\core\event\course_module_updated',
        'callback'  => 'mod_craftpilot\observer::course_module_updated',
        'internal'  => false,
        'priority'  => 200,
    ],
    [
        'eventname' => '\core\event\course_module_deleted',
        'callback'  => 'mod_craftpilot\observer::course_module_deleted',
        'internal'  => false,
        'priority'  => 200,
    ],
    [
        'eventname' => '\core\event\course_deleted',
        'callback'  => 'mod_craftpilot\observer::course_deleted',
        'internal'  => false,
        'priority'  => 200,
    ],
];
