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
        global $PAGE;
        
        // Generate a unique ID for this block instance
        $unique_id = 'aiassistant_' . uniqid();
        
        // Include CSS (keep this as external file for easier editing)
        $PAGE->requires->css('/blocks/aiassistant/assets/chat.css');
        
        // Call the JavaScript module and pass the unique ID
        $PAGE->requires->js_call_amd('block_aiassistant/chat', 'init', [$unique_id]);
        
        // Load just the HTML template
        $html_template = $this->load_file('assets/chat.html');
        
        // Replace placeholders in the template
        $html = str_replace('{UNIQUE_ID}', $unique_id, $html_template);
        
        return $html;
    }
    
    /**
     * Load content from a file within the block directory
     */
    private function load_file($relative_path) {
        global $CFG;
        
        $file_path = $CFG->dirroot . '/blocks/aiassistant/' . $relative_path;
        
        if (file_exists($file_path)) {
            return file_get_contents($file_path);
        } else {
            error_log("AI Assistant: Could not load file: {$file_path}");
            return "<!-- File not found: {$relative_path} -->";
        }
    }
    
    /**
     * Defines the page formats where this block can be displayed.
     */
    public function applicable_formats() {
        return array('all' => true);
    }
    
    /**
     * Determines whether multiple instances of this block can exist on a single page.
     */
    public function instance_allow_multiple() {
        return false;
    }
}