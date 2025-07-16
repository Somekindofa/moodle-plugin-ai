<?php
defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => \core\hook\output\before_http_headers::class,
        'callback' => 'local_aiassistant_before_headers',
    ],
    [
        'hook' => \core\hook\output\before_footer_html_generation::class,
        'callback' => 'local_aiassistant_before_footer',
    ],
];