<?php
// ============================================
//  Auto-Generate Lesson Content
//  Method 1: Gemini AI (if API key available)
//  Method 2: Smart Local Template (no API key)
// ============================================

error_reporting(0);
header('Content-Type: application/json');

$input   = json_decode(file_get_contents('php://input'), true);
$topic   = trim($input['topic']   ?? '');
$class   = trim($input['class']   ?? '');
$subject = trim($input['subject'] ?? '');

if (!$topic) {
    echo json_encode(['success' => false, 'error' => 'Please enter a topic name first.']);
    exit;
}

// --- Try Gemini API first ---
$config = @include 'config.php';
$apiKey = $config['gemini_api_key'] ?? '';
$content = '';
$source = 'Local Template';

if ($apiKey && strlen($apiKey) > 10) {
    $content = tryGemini($apiKey, $topic, $class, $subject);
    if ($content) $source = 'Gemini AI';
}

// --- Fallback: Local Smart Template ---
if (empty($content)) {
    $content = generateLocalContent($topic, $class, $subject);
    $source = 'Smart Template';
}

echo json_encode([
    'success' => true,
    'content' => $content,
    'source' => $source,
    'word_count' => str_word_count($content)
]);

// ============================================
//  GEMINI API METHOD
// ============================================
function tryGemini($apiKey, $topic, $class, $subject) {
    $prompt = "You are an expert teacher creating lesson content for Class $class students studying $subject.

Generate a well-structured, educational lesson on the topic: \"$topic\"

Requirements:
1. Write in simple, clear language appropriate for Class $class students.
2. Content should be 200-350 words.
3. Use this exact formatting:
   - Use # for main headings
   - Use **bold** for key terms and important words
   - Use - for bullet point lists
   - Use 1. 2. 3. for numbered/step lists
   - Use blank lines between sections
4. Include these sections:
   - # What is [Topic]? — A clear definition/introduction
   - # Key Concepts — Main facts, processes, or principles (use bullet points)
   - # How It Works / Process — Step-by-step explanation if applicable
   - # Real-Life Examples — 2-3 practical, relatable examples
   - # Important Points to Remember — Summary of key takeaways
5. Include enough factual detail to generate good MCQ questions.
6. Bold all key terms, definitions, names, and important facts.
7. Do NOT use markdown code blocks. Just plain text with # and ** formatting.

Return ONLY the lesson content text. No extra commentary.";

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . urlencode($apiKey);

    $payload = json_encode([
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => ['temperature' => 0.7, 'maxOutputTokens' => 2048]
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) return '';

    $data = json_decode($response, true);
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $text = preg_replace('/^```[a-z]*\s*/i', '', trim($text));
    $text = preg_replace('/```\s*$/', '', trim($text));

    return trim($text);
}

// ============================================
//  LOCAL SMART TEMPLATE GENERATOR
//  No API key needed!
// ============================================
function generateLocalContent($topic, $class, $subject) {
    $topicLower = strtolower($topic);
    $topicTitle = ucwords($topic);

    // --- Subject-specific context ---
    $subjectContext = getSubjectContext($subject);
    $gradeNote = getGradeNote($class);

    // --- Build structured content ---
    $content = "# What is $topicTitle?\n\n";
    $content .= "**$topicTitle** is an important topic in **$subject** for Class **$class** students. ";
    $content .= "It helps us understand " . $subjectContext['understanding'] . ".\n\n";

    $content .= "# Key Concepts\n\n";
    $content .= "Here are the main points about **$topicTitle**:\n\n";
    $content .= "- **Definition**: $topicTitle refers to " . generateDefinitionHint($topicLower, $subject) . "\n";
    $content .= "- **Importance**: Understanding $topicTitle is essential because it helps explain " . $subjectContext['importance'] . "\n";
    $content .= "- **Category**: This topic falls under the area of " . $subjectContext['category'] . "\n";
    $content .= "- **Key Terms**: Students should learn the important terms and definitions related to **$topicTitle**\n\n";

    $content .= "# How It Works\n\n";
    $content .= "To understand **$topicTitle**, follow these steps:\n\n";
    $content .= "1. Start by learning the **basic definition** and meaning of $topicTitle\n";
    $content .= "2. Identify the **key components** or parts involved\n";
    $content .= "3. Understand the **process or mechanism** — how it happens or works\n";
    $content .= "4. Learn about the **factors** that affect or influence $topicTitle\n";
    $content .= "5. Study the **results or outcomes** that come from it\n\n";

    $content .= "# Real-Life Examples\n\n";
    $examples = getExamples($subject);
    $content .= "**$topicTitle** can be observed in daily life:\n\n";
    $content .= "- " . $examples[0] . " — this connects to how **$topicTitle** works in practice\n";
    $content .= "- " . $examples[1] . " — another real-world application of this concept\n";
    $content .= "- " . $examples[2] . " — shows why understanding **$topicTitle** matters\n\n";

    $content .= "# Important Points to Remember\n\n";
    $content .= "- **$topicTitle** is a key topic in **$subject** for Class $class\n";
    $content .= "- Always remember the **definition** and **key terms**\n";
    $content .= "- Understand the **process** step by step\n";
    $content .= "- Connect the topic to **real-life examples** for better understanding\n";
    $content .= "- Practice questions to test your knowledge of **$topicTitle**";

    return $content;
}

function getSubjectContext($subject) {
    $contexts = [
        'Science' => [
            'understanding' => 'how the natural world works around us',
            'importance' => 'natural phenomena and scientific processes',
            'category' => '**natural sciences** and scientific inquiry'
        ],
        'Math' => [
            'understanding' => 'numbers, patterns, and logical problem-solving',
            'importance' => 'mathematical reasoning and calculations',
            'category' => '**mathematics** and quantitative analysis'
        ],
        'English' => [
            'understanding' => 'language, communication, and literature',
            'importance' => 'reading, writing, and effective communication',
            'category' => '**language arts** and English literature'
        ],
        'Social' => [
            'understanding' => 'society, history, geography, and civic life',
            'importance' => 'our world, its history, and how societies function',
            'category' => '**social studies** including history, geography, and civics'
        ],
        'Computer' => [
            'understanding' => 'technology, computing, and digital systems',
            'importance' => 'how computers and technology work in the modern world',
            'category' => '**computer science** and information technology'
        ],
        'Nepali' => [
            'understanding' => 'the Nepali language, literature, and grammar',
            'importance' => 'proper use of Nepali language in reading and writing',
            'category' => '**Nepali language** and literary studies'
        ],
        'Health' => [
            'understanding' => 'human health, hygiene, and well-being',
            'importance' => 'maintaining good health and preventing diseases',
            'category' => '**health education** and personal well-being'
        ]
    ];

    return $contexts[$subject] ?? [
        'understanding' => 'important academic concepts',
        'importance' => 'key principles in this field',
        'category' => '**academic studies**'
    ];
}

function getGradeNote($class) {
    if ($class <= 6) return 'Use simple language and relatable examples.';
    if ($class <= 8) return 'Include moderate detail with clear explanations.';
    return 'Include detailed concepts and critical thinking points.';
}

function generateDefinitionHint($topic, $subject) {
    $hints = [
        'Science' => 'a fundamental concept in science that explains a natural process or phenomenon.',
        'Math' => 'a mathematical concept used for solving problems and understanding patterns.',
        'English' => 'an important concept in English language and literature.',
        'Social' => 'a key concept in social studies that helps us understand society and the world.',
        'Computer' => 'a computing concept related to how technology and digital systems work.',
        'Nepali' => 'an important aspect of Nepali language, grammar, or literature.',
        'Health' => 'a health-related concept important for personal well-being and safety.'
    ];

    return $hints[$subject] ?? 'an important concept in this subject area.';
}

function getExamples($subject) {
    $examples = [
        'Science' => [
            'Observe nature and the environment around you',
            'Look at how things work in your kitchen or home',
            'Watch how weather and seasons change'
        ],
        'Math' => [
            'Calculate prices while shopping at a store',
            'Measure distances and areas in your home',
            'Notice patterns in numbers and shapes around you'
        ],
        'English' => [
            'Read newspapers, stories, and books regularly',
            'Practice writing letters, essays, and paragraphs',
            'Listen to English conversations and media'
        ],
        'Social' => [
            'Visit historical places and monuments in your area',
            'Learn about different cultures and communities',
            'Follow current events in newspapers and news'
        ],
        'Computer' => [
            'Use a computer or phone to complete tasks',
            'Notice how apps and websites work on your devices',
            'Think about how data is stored and shared online'
        ],
        'Nepali' => [
            'Read Nepali newspapers, poems, and stories',
            'Practice writing essays and formal letters in Nepali',
            'Listen to Nepali conversations and media'
        ],
        'Health' => [
            'Practice good hygiene like handwashing and bathing',
            'Observe healthy eating habits in your daily meals',
            'Notice how exercise and rest affect your energy'
        ]
    ];

    return $examples[$subject] ?? [
        'Look for connections to daily activities',
        'Discuss with friends and family about this topic',
        'Find examples in books and around your environment'
    ];
}
?>
