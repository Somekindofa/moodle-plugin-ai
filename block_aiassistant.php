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
        <div class=\"ai-chat-container\" id=\"ai-chat-container\">
            <div class=\"ai-conversation-panel\" id=\"ai-conversation-panel\">
                <div class=\"ai-conversation-header\">
                    <h4>Conversations</h4>
                </div>
                <div class=\"ai-conversation-list\" id=\"ai-conversation-list\">
                    <div class=\"ai-conversation-item\" data-conversation-id=\"1\">
                        <span class=\"ai-conversation-title\">GBL - Analysing hand movement for pipe entrance</span>
                    </div>
                    <div class=\"ai-conversation-item\" data-conversation-id=\"2\">
                        <span class=\"ai-conversation-title\">WW - Polishing wood</span>
                    </div>
                </div>
            </div>
            
            <div class=\"ai-chat-main\">
                <div class=\"ai-provider-selection\">
                    <div class=\"ai-provider-controls\">
                        <label for=\"ai-provider-select\">AI Provider:</label>

                        <select id=\"ai-provider-select\">
                            <option value=\"fireworks\">Fireworks.ai</option>
                        </select>
                    </div>
                </div>

                <div class=\"ai-chat-messages\" id=\"ai-chat-messages\">
                    <div class=\"ai-message\">
                        <strong>AI Assistant:</strong> Hello! How can I help you today?
                    </div>
                </div>

                <div class=\"ai-chat-input\">
                    <textarea id=\"ai-chat-input\" placeholder=\"Type your message here...\" rows=\"3\"></textarea>
                    <button id=\"ai-chat-send\" type=\"button\">Send</button>
                </div>
            </div>

            <div class=\"ai-sidepanel\" id=\"ai-sidepanel\">
                <div class=\"ai-sidepanel-header\">
                    <h4>Retrieved Documents</h4>
                </div>

                <div class=\"ai-sidepanel-content\" id=\"ai-sidepanel-content\">
                    <p>No documents retrieved yet.</p>
                </div>
            </div>
        </div>
        
        <style>
            .ai-sidepanel {
                width: 15%;
                min-width: 150px;
                background: #f8f9fa;
                border-left: 1px solid #ddd;
                display: flex;
                flex-direction: column;
            }
            
            .ai-sidepanel-header {
                padding: 8px 10px;
                background: #e9ecef;
                border-bottom: 1px solid #ddd;
                flex-shrink: 0;
            }
            
            .ai-sidepanel-header h4 {
                margin: 0;
                font-size: 12px;
                font-weight: bold;
                color: #333;
            }
            
            .ai-sidepanel-content {
                flex: 1;
                padding: 5px;
                overflow-y: auto;
            }
            
            .ai-document-list {
                list-style: none;
                padding: 0;
                margin: 0;
            }
            
            .ai-document-list li {
                padding: 6px;
                margin-bottom: 4px;
                background: #ffffff;
                border: 1px solid #e3e6ea;
                border-radius: 4px;
                font-size: 10px;
                word-wrap: break-word;
                overflow-wrap: break-word;
                line-height: 1.2;
                color: #495057;
            }
            
            .ai-chat-container {
                position: relative;
                width: 500px;
                min-width: 300px;
                max-width: 900px;
                border: 1px solid #ddd;
                border-radius: 8px;
                overflow: hidden;
                margin: 0 auto;
                display: flex;
            }
            
            .ai-conversation-panel {
                width: 15%;
                min-width: 150px;
                background: #f8f9fa;
                border-right: 1px solid #ddd;
                display: flex;
                flex-direction: column;
            }
            
            .ai-conversation-header {
                padding: 8px 10px;
                background: #e9ecef;
                border-bottom: 1px solid #ddd;
                flex-shrink: 0;
            }
            
            .ai-conversation-header h4 {
                margin: 0;
                font-size: 12px;
                font-weight: bold;
                color: #333;
            }
            
            .ai-conversation-list {
                flex: 1;
                padding: 5px;
                overflow-y: auto;
            }
            
            .ai-conversation-item {
                padding: 8px 6px;
                margin-bottom: 4px;
                background: #ffffff;
                border: 1px solid #e3e6ea;
                border-radius: 4px;
                cursor: pointer;
                transition: background-color 0.2s ease, border-color 0.2s ease;
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
                font-size: 10px;
                line-height: 1.2;
                color: #495057;
                display: block;
                word-wrap: break-word;
                overflow-wrap: break-word;
            }
            
            .ai-chat-main {
                flex: 1;
                display: flex;
                flex-direction: column;
                position: relative;
            }

            .ai-provider-selection {
                background: #f0f0f0;
                padding: 10px;
                border-bottom: 1px solid #ddd;
            }

            .ai-provider-controls {
                display: flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
            }

            .ai-provider-controls label {
                font-weight: bold;
                font-size: 12px;
                color: #333;
            }

            .ai-provider-controls select {
                padding: 5px 8px;
                border: 1px solid #ccc;
                border-radius: 4px;
                font-size: 12px;
                background: white;
            }
            
            .ai-chat-messages {
                height: 200px;
                min-height: 150px;
                max-height: 600px;
                overflow-y: auto;
                padding: 10px;
                background: #f9f9f9;
                border-bottom: 1px solid #ddd;
            }
            
            .ai-resize-handle {
                position: absolute;
                bottom: 0;
                right: 0;
                width: 20px;
                height: 20px;
                cursor: se-resize;
                background: linear-gradient(135deg, transparent 0%, transparent 30%, #999 30%, #999 40%, transparent 40%, transparent 50%, #999 50%, #999 60%, transparent 60%, transparent 70%, #999 70%, #999 80%, transparent 80%);
                border-top-left-radius: 4px;
                opacity: 0.7;
                transition: opacity 0.2s ease;
            }
            
            .ai-resize-handle:hover {
                opacity: 1;
            }
            
            .ai-chat-container.resizing {
                user-select: none;
            }
            
            .ai-message, .user-message {
                margin-bottom: 10px;
                padding: 8px;
                border-radius: 4px;
            }
            
            .ai-message .response-text h1, .ai-message .response-text h2, .ai-message .response-text h3 {
                margin: 0.5em 0;
                font-weight: bold;
            }
            .ai-message .response-text h1 { font-size: 1.2em; }
            .ai-message .response-text h2 { font-size: 1.1em; }
            .ai-message .response-text h3 { font-size: 1.05em; }
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
            .ai-message {
                background: #e3f2fd;
            }
            
            .user-message {
                background: #f3e5f5;
                text-align: right;
            }
            
            .ai-chat-input {
                padding: 10px;
                display: flex;
                gap: 5px;
            }
            
            .ai-chat-input textarea {
                flex: 1;
                border: 1px solid #ccc;
                border-radius: 4px;
                padding: 8px;
                resize: vertical;
            }
            
            .ai-chat-input button {
                padding: 8px 16px;
                background: #007cba;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
            }
            
            .ai-chat-input button:hover {
                background: #005a87;
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