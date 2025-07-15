"use client"

import { useState, useRef, useEffect } from "react"
import { MessageCircle, GraduationCap, X, FileText, Download, Eye, Send, ChevronLeft } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Badge } from "@/components/ui/badge"
import { Card, CardContent } from "@/components/ui/card"
import { ScrollArea } from "@/components/ui/scroll-area"

interface Message {
  id: string
  type: "user" | "assistant"
  content: string
  timestamp: Date
  documentCount?: number
}

interface Document {
  id: string
  title: string
  relevance: number
  preview: string
  url: string
}

interface MoodleAIAssistantProps {
  courseTitle?: string
  currentPage?: string
  apiStatus?: "online" | "offline" | "course-active"
}

export default function MoodleAIAssistant({
  courseTitle = "Arts & Crafts Fundamentals",
  currentPage = "Pottery Techniques",
  apiStatus = "course-active",
}: MoodleAIAssistantProps) {
  const [isExpanded, setIsExpanded] = useState(false)
  const [isHovered, setIsHovered] = useState(false)
  const [messages, setMessages] = useState<Message[]>([
    {
      id: "1",
      type: "assistant",
      content:
        "Hello! I'm your Arts & Crafts AI Assistant. I can help you with course materials, techniques, and answer any questions about your current lesson.",
      timestamp: new Date(),
      documentCount: 0,
    },
  ])
  const [inputValue, setInputValue] = useState("")
  const [documents, setDocuments] = useState<Document[]>([])
  const [showDocuments, setShowDocuments] = useState(false)
  const [isLoading, setIsLoading] = useState(false)
  const messagesEndRef = useRef<HTMLDivElement>(null)

  const getBorderColor = () => {
    switch (apiStatus) {
      case "offline":
        return "#DC2626"
      case "course-active":
        return "#2563EB"
      case "online":
        return "#16A34A"
      default:
        return "#2563EB"
    }
  }

  const getStatusText = () => {
    switch (apiStatus) {
      case "offline":
        return "API Offline"
      case "course-active":
        return "Connected"
      case "online":
        return "Online"
      default:
        return "Connected"
    }
  }

  const contextualQuestions = [
    "How do I mix glazes for pottery?",
    "What are the best clay preparation techniques?",
    "Show me firing temperature guidelines",
  ]

  const mockDocuments: Document[] = [
    {
      id: "1",
      title: "Pottery Glazing Techniques",
      relevance: 95,
      preview: "Complete guide to ceramic glazing methods and color mixing...",
      url: "/documents/glazing-guide.pdf",
    },
    {
      id: "2",
      title: "Clay Preparation Methods",
      relevance: 87,
      preview: "Essential techniques for preparing clay for wheel throwing...",
      url: "/documents/clay-prep.pdf",
    },
    {
      id: "3",
      title: "Kiln Firing Schedules",
      relevance: 78,
      preview: "Temperature schedules and timing for different clay types...",
      url: "/documents/firing-schedules.pdf",
    },
  ]

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: "smooth" })
  }

  useEffect(() => {
    scrollToBottom()
  }, [messages])

  const handleSendMessage = async (message: string) => {
    if (!message.trim()) return

    const userMessage: Message = {
      id: Date.now().toString(),
      type: "user",
      content: message,
      timestamp: new Date(),
    }

    setMessages((prev) => [...prev, userMessage])
    setInputValue("")
    setIsLoading(true)

    // Simulate API call
    setTimeout(() => {
      const assistantMessage: Message = {
        id: (Date.now() + 1).toString(),
        type: "assistant",
        content: `Great question about "${message}"! Based on the current lesson materials, I can provide you with detailed information. Let me search through the relevant documents to give you the most accurate answer.`,
        timestamp: new Date(),
        documentCount: 3,
      }

      setMessages((prev) => [...prev, assistantMessage])
      setDocuments(mockDocuments)
      setShowDocuments(true)
      setIsLoading(false)
    }, 1500)
  }

  const handleQuestionClick = (question: string) => {
    handleSendMessage(question)
  }

  return (
    <>
      {/* Floating Bubble */}
      <div
        className="fixed z-50 transition-all duration-200 ease-in-out cursor-pointer"
        style={{
          bottom: "24px",
          right: "24px",
          transform: isHovered ? "scale(1.1)" : "scale(1)",
        }}
        onClick={() => setIsExpanded(true)}
        onMouseEnter={() => setIsHovered(true)}
        onMouseLeave={() => setIsHovered(false)}
      >
        <div
          className="w-14 h-14 rounded-full bg-white flex items-center justify-center shadow-lg relative group"
          style={{
            border: `2px solid ${getBorderColor()}`,
            boxShadow: "0 4px 12px rgba(0,0,0,0.15)",
          }}
        >
          <div className="relative">
            <MessageCircle size={24} style={{ color: getBorderColor() }} />
            <GraduationCap size={12} className="absolute -top-1 -right-1" style={{ color: getBorderColor() }} />
          </div>

          {/* Tooltip */}
          <div className="absolute bottom-full right-0 mb-2 px-3 py-1 bg-gray-900 text-white text-sm rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 whitespace-nowrap">
            Arts & Crafts AI Assistant
            <div className="absolute top-full right-4 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-gray-900"></div>
          </div>
        </div>
      </div>

      {/* Expanded Chat Interface */}
      {isExpanded && (
        <div className="fixed inset-0 z-50 flex items-end justify-end p-4">
          {/* Backdrop */}
          <div className="absolute inset-0 bg-black bg-opacity-20" onClick={() => setIsExpanded(false)} />

          <div className="flex gap-4 relative">
            {/* Document Panel */}
            {showDocuments && (
              <Card className="w-80 h-[600px] bg-white/95 backdrop-blur-md border-0 shadow-xl rounded-2xl overflow-hidden">
                <div className="p-4 border-b bg-white/50">
                  <div className="flex items-center justify-between">
                    <div>
                      <h3 className="font-semibold text-gray-900">Documents Found</h3>
                      <p className="text-sm text-gray-600">{documents.length} relevant sources</p>
                    </div>
                    <Button variant="ghost" size="sm" onClick={() => setShowDocuments(false)}>
                      <ChevronLeft size={16} />
                    </Button>
                  </div>
                </div>

                <ScrollArea className="flex-1 p-4">
                  <div className="space-y-3">
                    {documents.map((doc) => (
                      <Card key={doc.id} className="border border-gray-200 hover:shadow-md transition-shadow">
                        <CardContent className="p-4">
                          <div className="flex items-start gap-3">
                            <div className="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                              <FileText size={20} className="text-blue-600" />
                            </div>
                            <div className="flex-1 min-w-0">
                              <h4 className="font-medium text-sm text-gray-900 truncate">{doc.title}</h4>
                              <p className="text-xs text-gray-600 mt-1 line-clamp-2">{doc.preview}</p>
                              <div className="flex items-center justify-between mt-2">
                                <Badge variant="secondary" className="text-xs">
                                  {doc.relevance}% match
                                </Badge>
                                <div className="flex gap-1">
                                  <Button variant="ghost" size="sm" className="h-6 w-6 p-0">
                                    <Eye size={12} />
                                  </Button>
                                  <Button variant="ghost" size="sm" className="h-6 w-6 p-0">
                                    <Download size={12} />
                                  </Button>
                                </div>
                              </div>
                            </div>
                          </div>
                        </CardContent>
                      </Card>
                    ))}
                  </div>
                </ScrollArea>
              </Card>
            )}

            {/* Main Chat Panel */}
            <Card className="w-96 h-[600px] bg-white/95 backdrop-blur-md border-0 shadow-xl rounded-2xl overflow-hidden flex flex-col">
              {/* Header */}
              <div className="p-4 border-b bg-white/50 flex items-center justify-between">
                <div className="flex-1">
                  <h2 className="font-semibold text-gray-900">AI Assistant</h2>
                  <p className="text-sm text-gray-600 truncate">
                    {courseTitle} • {currentPage}
                  </p>
                </div>
                <div className="flex items-center gap-2">
                  <div className="flex items-center gap-1">
                    <div className="w-2 h-2 rounded-full" style={{ backgroundColor: getBorderColor() }} />
                    <span className="text-xs text-gray-600">{getStatusText()}</span>
                  </div>
                  <Button variant="ghost" size="sm" onClick={() => setIsExpanded(false)}>
                    <X size={16} />
                  </Button>
                </div>
              </div>

              {/* Messages */}
              <ScrollArea className="flex-1 p-4">
                <div className="space-y-4">
                  {messages.map((message) => (
                    <div
                      key={message.id}
                      className={`flex ${message.type === "user" ? "justify-end" : "justify-start"}`}
                    >
                      <div
                        className={`max-w-[80%] rounded-2xl px-4 py-2 ${
                          message.type === "user" ? "text-white" : "bg-gray-100 text-gray-900"
                        }`}
                        style={{
                          backgroundColor: message.type === "user" ? getBorderColor() : undefined,
                        }}
                      >
                        <p className="text-sm">{message.content}</p>
                        {message.documentCount && message.documentCount > 0 && (
                          <Badge variant="secondary" className="mt-2 text-xs">
                            {message.documentCount} documents found
                          </Badge>
                        )}
                      </div>
                    </div>
                  ))}
                  {isLoading && (
                    <div className="flex justify-start">
                      <div className="bg-gray-100 rounded-2xl px-4 py-2">
                        <div className="flex space-x-1">
                          <div className="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></div>
                          <div
                            className="w-2 h-2 bg-gray-400 rounded-full animate-bounce"
                            style={{ animationDelay: "0.1s" }}
                          ></div>
                          <div
                            className="w-2 h-2 bg-gray-400 rounded-full animate-bounce"
                            style={{ animationDelay: "0.2s" }}
                          ></div>
                        </div>
                      </div>
                    </div>
                  )}
                </div>
                <div ref={messagesEndRef} />
              </ScrollArea>

              {/* Contextual Questions */}
              <div className="p-4 border-t bg-white/50">
                <div className="flex flex-wrap gap-2 mb-3">
                  {contextualQuestions.map((question, index) => (
                    <Button
                      key={index}
                      variant="outline"
                      size="sm"
                      className="text-xs rounded-full border-gray-300 hover:bg-gray-50 bg-transparent"
                      onClick={() => handleQuestionClick(question)}
                    >
                      {question}
                    </Button>
                  ))}
                </div>

                {/* Input */}
                <div className="flex gap-2">
                  <Input
                    value={inputValue}
                    onChange={(e) => setInputValue(e.target.value)}
                    placeholder="Ask anything..."
                    className="flex-1 rounded-full border-gray-300"
                    onKeyPress={(e) => {
                      if (e.key === "Enter") {
                        handleSendMessage(inputValue)
                      }
                    }}
                  />
                  <Button
                    size="sm"
                    className="rounded-full w-10 h-10 p-0"
                    style={{ backgroundColor: getBorderColor() }}
                    onClick={() => handleSendMessage(inputValue)}
                    disabled={!inputValue.trim() || isLoading}
                  >
                    <Send size={16} />
                  </Button>
                </div>
              </div>
            </Card>
          </div>
        </div>
      )}
    </>
  )
}
