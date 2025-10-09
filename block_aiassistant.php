<?php
// This file is part of Moodle - http://moodle.org/
    

defined('MOODLE_INTERNAL') || die();

class block_aiassistant extends block_base {
    /**
     * Set block to have configuration settings
     */
    public function has_config() {
        return true;
    }
    
    public function init() {
        $this->title = get_string('pluginname', 'block_aiassistant');
    }
    
    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }
        
        $this->content = new stdClass;
        $this->content->text = $this->get_chat_interface(); 
        $this->content->footer = '';
        
        return $this->content;
    }
    
    private function get_chat_interface() {
        global $CFG, $PAGE;
        $PAGE->requires->js_call_amd('block_aiassistant/chat_interface', 'init');
        
        $html = "
        <script src=\"https://cdn.jsdelivr.net/npm/marked/lib/marked.umd.js\"></script>
        <link rel=\"stylesheet\" href=\"https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css\">
        <div class=\"ai-chat-container\" id=\"ai-chat-container\">
            <!-- Input area at the top -->
            <div class=\"ai-input-area\">
                <div class=\"ai-provider-selection\">
                    <select id=\"ai-provider-select\">
                        <option value=\"fireworks\">Fireworks.ai</option>
                    </select>
                </div>
                
                <textarea id=\"ai-chat-input\" placeholder=\"Enter your query here...\" rows=\"1\"></textarea>
                <button id=\"ai-chat-send\" type=\"button\">Send</button>
            </div>
            
            <!-- Toggle button area between input and content -->
            <div class=\"ai-toggle-area\">
                <button id=\"ai-conversations-toggle\" class=\"ai-conversations-toggle\" type=\"button\" title=\"Toggle Conversations\">
                    <i class=\"fa-solid fa-table-columns\"></i>
                </button>
            </div>
            
            <!-- Main content area with motto and results -->
            <div class=\"ai-content-area\" id=\"ai-content-area\">
                <div class=\"ai-motto\" id=\"ai-motto\">Query. Retrieve. Learn.</div>
                
                <!-- Hidden by default, shown when there are results -->
                <div class=\"ai-results-area\" id=\"ai-results-area\" style=\"display: none;\">
                    <div class=\"ai-response-section\" id=\"ai-response-section\">
                        <div class=\"ai-chat-messages\" id=\"ai-chat-messages\"></div>
                    </div>
                    <div class=\"ai-documents-section\" id=\"ai-documents-section\">
                        <h4>Retrieved Documents</h4>
                        <div class=\"ai-documents-list\" id=\"ai-documents-list\">
                            <p>No documents retrieved yet.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Collapsible conversations panel -->
            <div class=\"ai-conversations-panel\" id=\"ai-conversations-panel\">
                <div class=\"ai-conversations-header\">
                    <h4>Previous Conversations</h4>
                    <button id=\"ai-new-conversation-btn\" class=\"ai-new-conversation-btn\" type=\"button\" title=\"New Conversation\">+</button>
                </div>
                <div class=\"ai-conversation-list\" id=\"ai-conversation-list\">
                    <!-- Conversations will be created dynamically via JavaScript -->
                </div>
            </div>
        </div>
        
        <style>
            /* Main container */
            .ai-chat-container {
                position: relative;
                width: 100%;
                max-width: 900px;
                min-height: 500px;
                border: 1px solid #8a96ffff;
                border-radius: 8px;
                overflow: hidden;
                margin: 0 auto;
                display: flex;
                flex-direction: column;
                background: #ffffff;
            }
            
            /* Content area with motto and results */
            .ai-content-area {
                flex: 1;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                padding: 40px 20px 20px 20px;
                overflow-y: auto;
                min-height: 400px;
            }
            
            /* Motto text */
            .ai-motto {
                font-size: 2.5em;
                font-weight: 300;
                color: rgba(0, 0, 0, 0.15);
                text-align: center;
                letter-spacing: 2px;
                user-select: none;
            }
            
            /* Results area (hidden by default) */
            .ai-results-area {
                width: 100%;
                height: 100%;
                display: flex;
                gap: 20px;
            }
            
            /* Response section (LLM-generated text) */
            .ai-response-section {
                flex: 2;
                display: flex;
                flex-direction: column;
                overflow: hidden;
            }
            
            /* Documents section (retrieved links) */
            .ai-documents-section {
                flex: 1;
                background: #f8f9fa;
                border: 1px solid #dee2e6;
                border-radius: 4px;
                padding: 15px;
                overflow-y: auto;
                max-width: 300px;
            }
            
            .ai-documents-section h4 {
                margin: 0 0 10px 0;
                font-size: 14px;
                font-weight: bold;
                color: #333;
            }
            
            .ai-documents-list {
                font-size: 12px;
            }
            
            .ai-documents-list ul {
                list-style: none;
                padding: 0;
                margin: 0;
            }
            
            .ai-documents-list li {
                padding: 8px;
                margin-bottom: 8px;
                background: #ffffff;
                border: 1px solid #e3e6ea;
                border-radius: 4px;
                word-wrap: break-word;
                overflow-wrap: break-word;
                line-height: 1.3;
                color: #495057;
            }
            
            /* Chat messages */
            .ai-chat-messages {
                flex: 1;
                overflow-y: auto;
                padding: 15px;
                background: #f9f9f9;
                border: 1px solid #dee2e6;
                border-radius: 4px;
            }
            
            .ai-message, .user-message {
                margin-bottom: 15px;
                padding: 10px;
                border-radius: 6px;
                line-height: 1.5;
            }
            
            .ai-message {
                background: #e3f2fd;
            }
            
            .user-message {
                background: #f3e5f5;
                text-align: right;
            }
            
            /* Markdown rendering styles */
            .ai-message .response-text h1, .ai-message .response-text h2, .ai-message .response-text h3 {
                margin: 0.5em 0;
                font-weight: bold;
            }
            .ai-message .response-text h1 { font-size: 1.3em; }
            .ai-message .response-text h2 { font-size: 1.2em; }
            .ai-message .response-text h3 { font-size: 1.1em; }
            .ai-message .response-text p {
                margin: 0.5em 0;
            }
            .ai-message .response-text code {
                background: #f0f0f0;
                padding: 2px 4px;
                border-radius: 3px;
                font-family: monospace;
                font-size: 0.9em;
            }
            .ai-message .response-text pre {
                background: #f0f0f0;
                padding: 10px;
                border-radius: 5px;
                overflow-x: auto;
                margin: 0.5em 0;
            }
            .ai-message .response-text pre code {
                background: none;
                padding: 0;
            }
            .ai-message .response-text ul, .ai-message .response-text ol {
                margin: 0.5em 0;
                padding-left: 20px;
            }
            .ai-message .response-text blockquote {
                border-left: 3px solid #ddd;
                margin: 0.5em 0;
                padding-left: 10px;
                color: #666;
            }
            .ai-message .response-text strong {
                font-weight: bold;
            }
            .ai-message .response-text em {
                font-style: italic;
            }
            
            /* Input area - now at the top */
            .ai-input-area {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 15px;
                background: #f8f9fa;
                border-bottom: 1px solid #dee2e6;
            }
            
            /* Toggle button area between input and content */
            .ai-toggle-area {
                padding: 10px 15px;
                background: #ffffff;
                border-bottom: 1px solid #e9ecef;
                display: flex;
                align-items: center;
            }
            
            .ai-conversations-toggle {
                background: #007cba;
                color: white;
                border: none;
                border-radius: 4px;
                width: 36px;
                height: 36px;
                font-size: 16px;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: background-color 0.2s ease;
                flex-shrink: 0;
            }
            
            .ai-conversations-toggle:hover {
                background: #005a87;
            }
            
            .ai-conversations-toggle.active {
                background: #005a87;
            }
            
            .ai-provider-selection {
                flex-shrink: 0;
            }
            
            .ai-provider-selection select {
                padding: 8px 12px;
                border: 1px solid #ced4da;
                border-radius: 4px;
                font-size: 13px;
                background: white;
                cursor: pointer;
            }
            
            #ai-chat-input {
                flex: 1;
                border: 1px solid #ced4da;
                border-radius: 4px;
                padding: 10px 12px;
                font-size: 14px;
                resize: vertical;
                min-height: 36px;
                max-height: 120px;
                font-family: inherit;
            }
            
            #ai-chat-send {
                padding: 10px 20px;
                background: #007cba;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
                font-weight: 500;
                transition: background-color 0.2s ease;
                flex-shrink: 0;
            }
            
            #ai-chat-send:hover {
                background: #005a87;
            }
            
            /* Conversations panel (collapsible, slides down from toggle area) */
            .ai-conversations-panel {
                position: absolute;
                top: 130px;
                left: 0;
                right: 0;
                max-height: 300px;
                background: #ffffff;
                border-bottom: 2px solid #8a96ffff;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                transform: translateY(-100%);
                transition: transform 0.3s ease;
                display: flex;
                flex-direction: column;
                z-index: 10;
            }
            
            .ai-conversations-panel.open {
                transform: translateY(0);
            }
            
            .ai-conversations-header {
                padding: 12px 15px;
                background: #e9ecef;
                border-bottom: 1px solid #dee2e6;
                display: flex;
                justify-content: space-between;
                align-items: center;
                flex-shrink: 0;
            }
            
            .ai-conversations-header h4 {
                margin: 0;
                font-size: 14px;
                font-weight: bold;
                color: #333;
            }
            
            .ai-new-conversation-btn {
                background: #007cba;
                color: white;
                border: none;
                border-radius: 50%;
                width: 28px;
                height: 28px;
                font-size: 18px;
                font-weight: bold;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                line-height: 1;
                transition: background-color 0.2s ease;
            }
            
            .ai-new-conversation-btn:hover {
                background: #005a87;
            }
            
            .ai-conversation-list {
                flex: 1;
                padding: 10px;
                overflow-y: auto;
                background: #f8f9fa;
            }
            
            .ai-conversation-item {
                padding: 10px 12px;
                margin-bottom: 6px;
                background: #ffffff;
                border: 1px solid #e3e6ea;
                border-radius: 4px;
                cursor: pointer;
                transition: background-color 0.2s ease, border-color 0.2s ease;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 8px;
            }
            
            .ai-conversation-item:hover {
                background-color: #e7f1ff;
                border-color: #b6d7ff;
            }
            
            .ai-conversation-item:active,
            .ai-conversation-item.active {
                background-color: #cce7ff;
                border-color: #80bdff;
            }
            
            .ai-conversation-title {
                font-size: 13px;
                line-height: 1.3;
                color: #495057;
                display: block;
                word-wrap: break-word;
                overflow-wrap: break-word;
                flex: 1;
            }
            
            .ai-conversation-delete-btn {
                background: #dc3545;
                color: white;
                border: none;
                border-radius: 3px;
                width: 20px;
                height: 20px;
                font-size: 14px;
                font-weight: bold;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                line-height: 1;
                transition: background-color 0.2s ease;
                flex-shrink: 0;
                padding: 0;
            }
            
            .ai-conversation-delete-btn:hover {
                background: #c82333;
            }

            .ai-provider-disabled {
                opacity: 0.6;
                pointer-events: none;
            }
        </style>";
        
        return $html;
    }
    
    /**
     * Defines the page formats where this block can be displayed.
     *
     * Available formats include:
     * - 'all' => true: Display on all page types
     * - 'site' => true: Site front page
     * - 'course' => true: Course pages
     * - 'course-category' => true: Course category pages
     * - 'my' => true: Dashboard/My Moodle page
     * - 'user' => true: User profile pages
     * - 'mod' => true: Activity/module pages
     * - 'tag' => true: Tag pages
     * - 'admin' => true: Admin pages
     * - 'blog' => true: Blog pages
     * - 'calendar' => true: Calendar pages
     *
     * @return array Array of applicable formats with boolean values
     */
    public function applicable_formats() {
        return array('all' => true); # if we only want it to be shown on page types "course" and "dashboard" then use: return array('course' => true, 'my' => true);
    }
    
    /**
     * Determines whether multiple instances of this block can exist on a single page.
     *
     * This method controls the block instance multiplicity behavior. When returning false,
     * only one instance of this block type can be added to any given page, preventing
     * duplicate block placements.
     *
     * @return bool False to allow only one instance per page, true to allow multiple instances
     */
    public function instance_allow_multiple() {
        return false;
    }
}