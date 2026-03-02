<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace mod_craftpilot;

defined('MOODLE_INTERNAL') || die();

/**
 * HTTP client for the CraftPilot Python backend.
 *
 * Sends course module content to the backend's ingestion endpoints and
 * issues deletion requests when modules or courses are removed.
 *
 * All public methods throw \RuntimeException on HTTP or curl error.
 * Callers (observer.php) are responsible for catching and logging.
 *
 * @package   mod_craftpilot
 */
class backend_client {

    /** Backend base URL — same host as the chat proxy. */
    private const BASE_URL = 'http://127.0.0.1:8000/api';

    /** cURL timeout in seconds for ingestion calls (large files may take longer). */
    private const TIMEOUT = 60;

    // ─────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────

    /**
     * POST /api/ingest-course-module
     *
     * @param int    $course_id  Moodle course ID
     * @param int    $cmid       Course module ID
     * @param string $modname    'page', 'label', or 'resource'
     * @param array  $payload    Output of course_content_extractor::extract_module()
     */
    public function ingest_module(int $course_id, int $cmid, string $modname, array $payload): void {
        $this->post('/ingest-course-module', $payload);
        error_log("CraftPilot backend_client: ingested course={$course_id} module={$cmid} type={$modname}");
    }

    /**
     * DELETE /api/delete-course-module
     *
     * @param int $course_id Moodle course ID
     * @param int $cmid      Course module ID
     */
    public function delete_module(int $course_id, int $cmid): void {
        $this->delete('/delete-course-module', [
            'course_id' => (string) $course_id,
            'module_id' => (string) $cmid,
        ]);
        error_log("CraftPilot backend_client: deleted course={$course_id} module={$cmid}");
    }

    /**
     * DELETE /api/delete-course
     *
     * @param int $course_id Moodle course ID
     */
    public function delete_course(int $course_id): void {
        $this->delete('/delete-course', ['course_id' => (string) $course_id]);
        error_log("CraftPilot backend_client: deleted course collection course={$course_id}");
    }

    // ─────────────────────────────────────────────────────────────
    // cURL helpers
    // ─────────────────────────────────────────────────────────────

    private function post(string $path, array $data): array {
        return $this->request('POST', $path, $data);
    }

    private function delete(string $path, array $data): array {
        return $this->request('DELETE', $path, $data);
    }

    private function request(string $method, string $path, array $data): array {
        $url  = self::BASE_URL . $path;
        $body = json_encode($data, JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($body),
            ],
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            throw new \RuntimeException("CraftPilot backend cURL error [{$method} {$path}]: {$curl_error}");
        }

        if ($http_code >= 400) {
            throw new \RuntimeException(
                "CraftPilot backend HTTP {$http_code} [{$method} {$path}]: " . substr($response, 0, 500)
            );
        }

        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : [];
    }
}
