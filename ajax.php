<?php
// This file is part of Moodle - http://moodle.org/

require_once('../../config.php');

require_login();
require_sesskey();

$action = required_param('action', PARAM_ALPHA);

header('Content-Type: application/json');

switch ($action) {
    case 'send_message':
        handle_send_message();
        break;
    case 'get_suggestions':
        handle_get_suggestions();
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}

function handle_send_message() {
    global $USER, $COURSE;
    
    $message = required_param('message', PARAM_TEXT);
    $course_id = required_param('course_id', PARAM_INT);
    $conversation_history = optional_param('history', '', PARAM_RAW);
    
    // Get page context
    $context = local_aiassistant_get_page_context();
    
    // Prepare data for RAG API
    $rag_data = [
        'query' => $message,
        'context' => $context,
        'user_id' => $USER->id,
        'course_id' => $course_id,
        'conversation_history' => json_decode($conversation_history, true) ?: []
    ];
    
    // Call RAG API
    $response = call_rag_api($rag_data);
    
    if ($response) {
        echo json_encode([
            'success' => true,
            'message' => $response['answer'],
            'documents' => $response['documents'] ?? [],
            'timestamp' => time()
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to get response from AI service'
        ]);
    }
}

function handle_get_suggestions() {
    global $COURSE;
    
    $course_id = required_param('course_id', PARAM_INT);
    $context = local_aiassistant_get_page_context();
    
    // Generate contextual suggestions based on current page
    $suggestions = generate_contextual_suggestions($context);
    
    echo json_encode([
        'success' => true,
        'suggestions' => $suggestions
    ]);
}

function call_rag_api($data) {
    $api_url = get_config('local_aiassistant', 'api_url');
    $api_key = get_config('local_aiassistant', 'api_key');
    
    if (empty($api_url)) {
        return false;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url . '/chat');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        return json_decode($response, true);
    }
    
    return false;
}

function generate_contextual_suggestions($context) {
    // Generate suggestions based on current context
    $suggestions = [];
    
    if (strpos(strtolower($context['course_title']), 'art') !== false || 
        strpos(strtolower($context['course_title']), 'craft') !== false) {
        $suggestions = [
            "How do I mix glazes for pottery?",
            "What are the best clay preparation techniques?",
            "Show me firing temperature guidelines"
        ];
    } else if (strpos(strtolower($context['course_title']), 'math') !== false) {
        $suggestions = [
            "Explain this concept step by step",
            "Show me practice problems",
            "What are the key formulas?"
        ];
    } else {
        $suggestions = [
            "Summarize this lesson",
            "What are the key points?",
            "Give me study tips"
        ];
    }
    
    return $suggestions;
}
