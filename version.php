<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'mod_craftpilot';
$plugin->version = 2026022700; // Add cm_index table + event observer for course ingestion
$plugin->requires = 2022041900; // Moodle 4.1
$plugin->maturity = MATURITY_STABLE;
$plugin->release = '0.5.0'; // Course content ingestion into ChromaDB
