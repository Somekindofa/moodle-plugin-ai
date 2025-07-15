<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Hook to inject AI Assistant into all pages
 */
function local_aiassistant_before_footer() {
    global $PAGE, $OUTPUT, $COURSE, $USER;
    
    // Don't show on login page or during installation
    if ($PAGE->pagelayout == 'login' || during_initial_install()) {
        return;
    }
    
    // Get current course context
    $context = context_course::instance($COURSE->id);
    $course_title = $COURSE->fullname;
    $current_page = $PAGE->title;
    
    // Check API status
    $api_status = local_aiassistant_check_api_status();
    
    // Prepare data for JavaScript
    $assistant_data = [
        'courseTitle' => $course_title,
        'currentPage' => $current_page,
        'apiStatus' => $api_status,
        'userId' => $USER->id,
        'courseId' => $COURSE->id,
        'contextId' => $context->id,
        'apiEndpoint' => new moodle_url('/local/aiassistant/ajax.php'),
        'sesskey' => sesskey()
    ];
    
    // Include CSS and JavaScript
    $PAGE->requires->css('/local/aiassistant/styles/assistant.css');
    $PAGE->requires->js_call_amd('local_aiassistant/assistant', 'init', [$assistant_data]);
    
    // Output the HTML structure
    echo $OUTPUT->render_from_template('local_aiassistant/assistant_bubble', $assistant_data);
}

/**
 * Check RAG API status
 */
function local_aiassistant_check_api_status() {
    $api_url = get_config('local_aiassistant', 'api_url');
    
    if (empty($api_url)) {
        return 'offline';
    }
    
    // Simple health check
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url . '/health');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        // Check if we're on a course page
        global $COURSE;
        if ($COURSE->id > 1) { // Not site course
            return 'course-active';
        }
        return 'online';
    }
    
    return 'offline';
}

/**
 * Extract page context for RAG system
 */
function local_aiassistant_get_page_context() {
    global $PAGE, $COURSE, $DB;
    
    $context = [
        'course_title' => $COURSE->fullname,
        'page_title' => $PAGE->title,
        'page_url' => $PAGE->url->out(),
        'course_id' => $COURSE->id
    ];
    
    // Add module-specific context if available
    if ($PAGE->cm) {
        $cm = $PAGE->cm;
        $context['module_name'] = $cm->name;
        $context['module_type'] = $cm->modname;
        
        // Get module content based on type
        switch ($cm->modname) {
            case 'page':
                $page = $DB->get_record('page', ['id' => $cm->instance]);
                if ($page) {
                    $context['content'] = strip_tags($page->content);
                }
                break;
            case 'resource':
                $resource = $DB->get_record('resource', ['id' => $cm->instance]);
                if ($resource) {
                    $context['resource_name'] = $resource->name;
                }
                break;
            case 'forum':
                $forum = $DB->get_record('forum', ['id' => $cm->instance]);
                if ($forum) {
                    $context['forum_intro'] = strip_tags($forum->intro);
                }
                break;
        }
    }
    
    return $context;
}
