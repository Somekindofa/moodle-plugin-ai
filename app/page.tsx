import MoodleAIAssistant from "@/components/moodle-ai-assistant"

export default function Home() {
  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100">
      {/* Mock Moodle Page Content */}
      <div className="container mx-auto p-8">
        <header className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900 mb-2">Arts & Crafts Fundamentals</h1>
          <p className="text-gray-600">Module 3: Pottery Techniques</p>
        </header>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          <div className="lg:col-span-2">
            <div className="bg-white rounded-lg shadow-sm p-6 mb-6">
              <h2 className="text-xl font-semibold mb-4">Lesson: Glazing Fundamentals</h2>
              <p className="text-gray-700 mb-4">
                In this lesson, we'll explore the art of ceramic glazing. Glazing is one of the most important aspects
                of pottery, as it not only provides a protective coating but also adds color, texture, and visual appeal
                to your ceramic pieces.
              </p>
              <p className="text-gray-700 mb-4">
                We'll cover different types of glazes, mixing techniques, application methods, and firing
                considerations. Understanding these fundamentals will help you create beautiful and durable ceramic
                pieces.
              </p>
              <div className="bg-blue-50 border-l-4 border-blue-400 p-4 mb-4">
                <p className="text-blue-800">
                  <strong>Pro Tip:</strong> Always test your glaze combinations on small test tiles before applying to
                  your finished pieces.
                </p>
              </div>
            </div>

            <div className="bg-white rounded-lg shadow-sm p-6">
              <h3 className="text-lg font-semibold mb-4">Required Materials</h3>
              <ul className="list-disc list-inside space-y-2 text-gray-700">
                <li>Ceramic glazes (various colors)</li>
                <li>Brushes for glaze application</li>
                <li>Test tiles</li>
                <li>Kiln stilts and furniture</li>
                <li>Safety equipment (gloves, mask)</li>
              </ul>
            </div>
          </div>

          <div className="space-y-6">
            <div className="bg-white rounded-lg shadow-sm p-6">
              <h3 className="text-lg font-semibold mb-4">Course Progress</h3>
              <div className="space-y-3">
                <div className="flex justify-between items-center">
                  <span className="text-sm text-gray-600">Module 1: Clay Basics</span>
                  <span className="text-sm font-medium text-green-600">Complete</span>
                </div>
                <div className="flex justify-between items-center">
                  <span className="text-sm text-gray-600">Module 2: Wheel Throwing</span>
                  <span className="text-sm font-medium text-green-600">Complete</span>
                </div>
                <div className="flex justify-between items-center">
                  <span className="text-sm text-gray-600">Module 3: Pottery Techniques</span>
                  <span className="text-sm font-medium text-blue-600">In Progress</span>
                </div>
              </div>
            </div>

            <div className="bg-white rounded-lg shadow-sm p-6">
              <h3 className="text-lg font-semibold mb-4">Upcoming Assignments</h3>
              <div className="space-y-3">
                <div className="border-l-4 border-yellow-400 pl-3">
                  <p className="text-sm font-medium">Glazed Bowl Project</p>
                  <p className="text-xs text-gray-600">Due: Next Friday</p>
                </div>
                <div className="border-l-4 border-gray-300 pl-3">
                  <p className="text-sm font-medium">Technique Reflection</p>
                  <p className="text-xs text-gray-600">Due: In 2 weeks</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* AI Assistant Component */}
      <MoodleAIAssistant
        courseTitle="Arts & Crafts Fundamentals"
        currentPage="Pottery Techniques"
        apiStatus="course-active"
      />
    </div>
  )
}
