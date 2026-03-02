<?php
/**
 * CraftPilot — Bulk ingestion CLI script.
 *
 * Iterates all existing course modules of supported types (page, label, resource)
 * and calls the Python backend's /api/ingest-course-module endpoint for each one.
 *
 * Run as:
 *   sudo -u apache php /var/www/html/public/mod/craftpilot/cli/bulk_ingest.php
 *   sudo -u apache php /var/www/html/public/mod/craftpilot/cli/bulk_ingest.php --course=83
 *   sudo -u apache php /var/www/html/public/mod/craftpilot/cli/bulk_ingest.php --dry-run
 *
 * Options:
 *   --course=ID   Only ingest modules from the given course ID.
 *   --dry-run     Extract and print payload info but do NOT call the backend.
 *   --help        Show this help message.
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../lib/clilib.php');

$options = [
    'course'  => false,
    'dry-run' => false,
    'help'    => false,
];
[$options, $unrecognized] = cli_get_params($options, ['h' => 'help']);

if ($options['help']) {
    echo <<<EOT
CraftPilot bulk ingestion script.

Iterates all supported course modules (page, label, resource) and sends them
to the Python backend for chunking and embedding.

Options:
  --course=ID   Restrict to a single course (Moodle course ID).
  --dry-run     Show what would be ingested without calling the backend.
  --help        Show this help.

EOT;
    exit(0);
}

require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/mod/craftpilot/classes/course_content_extractor.php');
require_once($CFG->dirroot . '/mod/craftpilot/classes/backend_client.php');

$supported_types = ['page', 'label', 'resource'];
$dry_run         = (bool) $options['dry-run'];
$filter_course   = $options['course'] ? (int) $options['course'] : null;

// ─── Fetch all course modules of supported types ───────────────────────────

$placeholders = implode(',', array_fill(0, count($supported_types), '?'));
$params       = $supported_types;

$sql = "
    SELECT cm.id AS cmid, cm.course AS course_id, m.name AS modname
    FROM {course_modules} cm
    JOIN {modules} m ON m.id = cm.module
    WHERE m.name IN ({$placeholders})
";

if ($filter_course !== null) {
    $sql   .= ' AND cm.course = ?';
    $params[] = $filter_course;
}

$sql .= ' ORDER BY cm.course, cm.id';

$rows = $DB->get_records_sql($sql, $params);

if (empty($rows)) {
    cli_writeln("No modules found. Exiting.");
    exit(0);
}

$total     = count($rows);
$ingested  = 0;
$skipped   = 0;
$errors    = 0;
$current   = 0;

$extractor = new \mod_craftpilot\course_content_extractor();
$client    = $dry_run ? null : new \mod_craftpilot\backend_client();

cli_writeln("Found {$total} modules to process." . ($dry_run ? ' (DRY RUN — backend will not be called)' : ''));
cli_writeln(str_repeat('-', 60));

foreach ($rows as $row) {
    $current++;
    $cmid      = (int) $row->cmid;
    $course_id = (int) $row->course_id;
    $modname   = $row->modname;

    $prefix = "[{$current}/{$total}] course={$course_id} cmid={$cmid} type={$modname}";

    try {
        $payload = $extractor->extract_module($cmid, $modname, $course_id);
    } catch (Throwable $e) {
        cli_writeln("{$prefix}  ERROR (extract): " . $e->getMessage());
        $errors++;
        continue;
    }

    if (empty($payload)) {
        cli_writeln("{$prefix}  SKIP (empty content)");
        $skipped++;
        continue;
    }

    $content_len = strlen($payload['content_html'] ?? $payload['content_raw_b64'] ?? '');
    $name        = $payload['module_name'] ?? '(unnamed)';

    if ($dry_run) {
        cli_writeln("{$prefix}  DRY-RUN  name=\"{$name}\"  content_bytes={$content_len}");
        $ingested++;
        continue;
    }

    try {
        $client->ingest_module($course_id, $cmid, $modname, $payload);

        // Update the cm_index table so the observer skips unchanged content later.
        $hash = md5($payload['content_html'] ?? $payload['content_raw_b64'] ?? '');
        $now  = time();
        $existing = $DB->get_record('craftpilot_cm_index', ['cmid' => $cmid]);
        if ($existing) {
            $existing->content_hash = $hash;
            $existing->last_indexed = $now;
            $DB->update_record('craftpilot_cm_index', $existing);
        } else {
            $rec               = new stdClass();
            $rec->cmid         = $cmid;
            $rec->course_id    = $course_id;
            $rec->content_hash = $hash;
            $rec->last_indexed = $now;
            $DB->insert_record('craftpilot_cm_index', $rec);
        }

        cli_writeln("{$prefix}  OK  name=\"{$name}\"  content_bytes={$content_len}");
        $ingested++;

    } catch (Throwable $e) {
        cli_writeln("{$prefix}  ERROR (backend): " . $e->getMessage());
        $errors++;
    }
}

cli_writeln(str_repeat('-', 60));
cli_writeln("Done. ingested={$ingested}  skipped={$skipped}  errors={$errors}");
