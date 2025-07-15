// This file is part of Moodle - http://moodle.org/

var define = window.define // Declare the define variable

define(["jquery", "core/ajax", "core/notification"], ($, Ajax, Notification) => {
  var Assistant = {
    config: {},
    isExpanded: false,
    conversationHistory: [],

    init: function (config) {
      this.config = config
      this.bindEvents()
      this.loadSuggestions()
    },

    bindEvents: function () {
      var self = this

      // Bubble click
      $("#ai-bubble").on("click", () => {
        self.toggleChat()
      })

      // Close button
      $("#ai-close-btn").on("click", () => {
        self.closeChat()
      })

      // Send button
      $("#ai-send-btn").on("click", () => {
        self.sendMessage()
      })

      // Enter key in input
      $("#ai-input").on("keypress", (e) => {
        if (e.which === 13) {
          self.sendMessage()
        }
      })

      // Document panel toggle
      $("#ai-documents-close").on("click", () => {
        self.hideDocuments()
      })

      // Suggestion clicks
      $(document).on("click", ".ai-suggestion", function () {
        var question = $(this).text()
        $("#ai-input").val(question)
        self.sendMessage()
      })
    },

    toggleChat: function () {
      if (this.isExpanded) {
        this.closeChat()
      } else {
        this.openChat()
      }
    },

    openChat: function () {
      $("#ai-chat-panel").slideDown(300)
      this.isExpanded = true
      $("#ai-input").focus()
    },

    closeChat: function () {
      $("#ai-chat-panel").slideUp(300)
      this.isExpanded = false
      this.hideDocuments()
    },

    sendMessage: function () {
      var message = $("#ai-input").val().trim()
      if (!message) return

      this.addMessage("user", message)
      $("#ai-input").val("")
      this.showLoading()

      // Add to conversation history
      this.conversationHistory.push({
        type: "user",
        content: message,
        timestamp: Date.now(),
      })
      
      Ajax.call([
        {
          methodname: "local_aiassistant_send_message",
          args: {
            message: message,
            course_id: this.config.courseId,
            history: JSON.stringify(this.conversationHistory),
          },
        },
      ])[0]
        .done((response) => {
          this.hideLoading()
          if (response.success) {
            this.addMessage("assistant", response.message, response.documents)
            this.conversationHistory.push({
              type: "assistant",
              content: response.message,
              timestamp: Date.now(),
            })

            if (response.documents && response.documents.length > 0) {
              this.showDocuments(response.documents)
            }
          } else {
            this.addMessage("assistant", "Sorry, I encountered an error. Please try again.")
          }
        })
        .fail(() => {
          this.hideLoading()
          this.addMessage("assistant", "Sorry, I'm having trouble connecting. Please try again later.")
        })
    },

    addMessage: function (type, content, documents) {
      var messageClass = "ai-message-" + type
      var documentBadge = ""

      if (documents && documents.length > 0) {
        documentBadge = '<span class="ai-document-badge">' + documents.length + " documents found</span>"
      }

      var messageHtml =
        '<div class="ai-message ' +
        messageClass +
        '">' +
        '<div class="ai-message-content">' +
        content +
        documentBadge +
        "</div>" +
        "</div>"

      $("#ai-messages").append(messageHtml)
      this.scrollToBottom()
    },

    showLoading: function () {
      var loadingHtml =
        '<div class="ai-message ai-message-assistant ai-loading-message">' +
        '<div class="ai-loading">' +
        '<div class="ai-loading-dot"></div>' +
        '<div class="ai-loading-dot"></div>' +
        '<div class="ai-loading-dot"></div>' +
        "</div></div>"

      $("#ai-messages").append(loadingHtml)
      this.scrollToBottom()
    },

    hideLoading: () => {
      $(".ai-loading-message").remove()
    },

    showDocuments: (documents) => {
      var documentsHtml = ""
      documents.forEach((doc) => {
        documentsHtml +=
          '<div class="ai-document-item">' +
          '<div class="ai-document-title">' +
          doc.title +
          "</div>" +
          '<div class="ai-document-preview">' +
          doc.preview +
          "</div>" +
          '<div class="ai-document-meta">' +
          '<span class="ai-document-relevance">' +
          doc.relevance +
          "% match</span>" +
          '<div class="ai-document-actions">' +
          '<button class="ai-document-action" onclick="window.open(\'' +
          doc.url +
          "', '_blank')\" title=\"View\">" +
          '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
          '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>' +
          '<circle cx="12" cy="12" r="3"/></svg></button>' +
          '<button class="ai-document-action" onclick="window.open(\'' +
          doc.url +
          "', '_blank')\" title=\"Download\">" +
          '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
          '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>' +
          '<polyline points="7,10 12,15 17,10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></button>' +
          "</div></div></div>"
      })

      $("#ai-documents-list").html(documentsHtml)
      $("#ai-documents-panel").show()
    },

    hideDocuments: () => {
      $("#ai-documents-panel").hide()
    },

    loadSuggestions: function () {
      
      Ajax.call([
        {
          methodname: "local_aiassistant_get_suggestions",
          args: {
            course_id: this.config.courseId,
          },
        },
      ])[0].done((response) => {
        if (response.success && response.suggestions) {
          var suggestionsHtml = ""
          response.suggestions.forEach((suggestion) => {
            suggestionsHtml += '<button class="ai-suggestion">' + suggestion + "</button>"
          })
          $("#ai-suggestions").html(suggestionsHtml)
        }
      })
    },

    scrollToBottom: () => {
      var messages = $("#ai-messages")
      messages.scrollTop(messages[0].scrollHeight)
    },
  }

  return Assistant
})
