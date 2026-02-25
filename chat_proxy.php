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
 * Server-side streaming proxy for the RAG backend.
 *
 * The RAG backend runs on 127.0.0.1:8000 (server-local) and cannot be
 * reached directly from a browser. This script:
 *   1. Validates the Moodle session.
 *   2. Forwards the POST body to the backend via cURL.
 *   3. Streams the newline-delimited JSON response back to the browser.
 *
 * @package   mod_craftpilot
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

// Must be logged in.
require_login();

// Only accept POST requests.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Read and validate the incoming JSON body.
$rawbody = file_get_contents('php://input');
$data    = json_decode($rawbody, true);

if (!is_array($data) || empty($data['conversation_thread_id'])) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing or invalid request body']);
    exit;
}

// RAG backend endpoint (server-local — not reachable from browser directly).
$backend_url = 'http://127.0.0.1:8000/api/chat';

// Stream headers — tell Nginx/Apache not to buffer.
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-cache, no-store');
header('X-Accel-Buffering: no');  // Nginx: disable proxy buffering
header('X-Content-Type-Options: nosniff');

// Flush any existing output buffers so streaming works.
while (ob_get_level()) {
    ob_end_flush();
}
ob_implicit_flush(true);
flush();

// Forward to the RAG backend via cURL with streaming write callback.
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $backend_url,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($data),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_TIMEOUT        => 120,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_RETURNTRANSFER => false,  // Must be false for the write callback to work.
    CURLOPT_WRITEFUNCTION  => function($curl_handle, $chunk) {
        echo $chunk;
        flush();
        return strlen($chunk);
    },
]);

$ok       = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlerr  = curl_error($ch);
curl_close($ch);

if (!$ok || $httpcode === 0) {
    // Backend unreachable — emit an error event the JS can parse.
    echo "\n" . json_encode([
        'event'   => 'error',
        'message' => 'AI backend unreachable' . ($curlerr ? ': ' . $curlerr : ''),
    ]) . "\n";
    flush();
}
