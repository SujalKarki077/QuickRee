<?php
// ============================================
//  Smart MCQ Generator — Content-Based AI
//  No API key required!
//  
//  This engine analyzes lesson content and 
//  generates MCQs using NLP-like techniques:
//  - Sentence extraction & ranking
//  - Key term identification
//  - Distractor generation
//  - Multiple question templates
// ============================================

error_reporting(0);
header('Content-Type: application/json');
include 'db.php';

// --- Read Input ---
$input    = json_decode(file_get_contents('php://input'), true);
$class    = $input['class']    ?? '';
$subject  = $input['subject']  ?? '';
$level_id = $input['level_id'] ?? '';

if (!$class || !$subject) {
    echo json_encode(['success' => false, 'error' => 'Class or Subject missing']);
    exit;
}

// --- Fetch lesson content from the database ---
if ($level_id) {
    // Specific level requested
    $stmt = $conn->prepare("SELECT * FROM levels WHERE id = ?");
    $stmt->bind_param("i", $level_id);
} else {
    // Fallback: pick random level
    $stmt = $conn->prepare("SELECT * FROM levels WHERE class = ? AND subject = ? ORDER BY RAND() LIMIT 1");
    $stmt->bind_param("ss", $class, $subject);
}
$stmt->execute();
$result = $stmt->get_result();
$lesson = $result->fetch_assoc();
$stmt->close();

if (!$lesson) {
    echo json_encode([
        'success' => false,
        'error'   => "No lessons found for Class $class $subject. Add a level first!"
    ]);
    exit;
}

$topic   = $lesson['topic'];
$content = $lesson['content'];

// ============================================
//  AI ENGINE: Gemini API + Local Fallback
// ============================================

try {
    $mcqs = [];
    $source = 'Local AI Engine';

    // --- Try Gemini API first ---
    $config = include 'config.php';
    $apiKey = $config['gemini_api_key'] ?? '';

    if ($apiKey && strlen($apiKey) > 10) {
        $geminiResult = generateWithGemini($apiKey, $topic, $content, $subject, $class);
        if ($geminiResult && is_array($geminiResult) && count($geminiResult) > 0) {
            $mcqs = $geminiResult;
            $source = 'Gemini AI';
        }
    }

    // --- Fallback to local engine ---
    if (empty($mcqs)) {
        $localMcq = generateMCQ($topic, $content, $subject, $class);
        if ($localMcq) {
            $mcqs = [$localMcq];
            $source = 'Local AI Engine';
        }
    }

    if (empty($mcqs)) {
        echo json_encode(['success' => false, 'error' => 'Could not generate questions from this content. Try adding more detailed lesson content.']);
        exit;
    }

    // --- Save all MCQs to Database ---
    $levelId = $lesson['id'];
    $saved = 0;

    foreach ($mcqs as $mcq) {
        if (!isset($mcq['question']) || !isset($mcq['correct_option'])) continue;

        if (!isset($mcq['explanation']) || empty($mcq['explanation'])) {
            $mcq['explanation'] = 'The correct answer is related to: ' . $topic;
        }

        $stmt = $conn->prepare(
            "INSERT INTO mcqs (class, subject, level_id, question, option_a, option_b, option_c, option_d, correct_option, explanation)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("ssisssssss",
            $class, $subject, $levelId,
            $mcq['question'],
            $mcq['option_a'], $mcq['option_b'],
            $mcq['option_c'], $mcq['option_d'],
            $mcq['correct_option'], $mcq['explanation']
        );
        $stmt->execute();
        $stmt->close();
        $saved++;
    }

    // Return the first MCQ for display + count
    $first = $mcqs[0];
    echo json_encode([
        'success'        => true,
        'source'         => $source,
        'from_topic'     => $topic,
        'questions_saved' => $saved
    ] + $first);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// ============================================
//  GEMINI API MCQ GENERATION
// ============================================

function generateWithGemini($apiKey, $topic, $content, $subject, $class) {
    $prompt = "Generate 5 high-quality multiple choice questions (MCQs) based on the topic below.

Strict Requirements:
1. DO NOT ask questions that directly repeat or paraphrase the content. No simple recall or definition questions.
2. NEVER ask vague association questions like 'Which is most closely related to X?' or 'Which is associated with X?' — these are lazy and test nothing.
3. Questions must test application, reasoning, or critical thinking — students should THINK, not just remember.
4. At least 3 questions must be scenario-based (e.g. 'A student observes...', 'In a village...', 'If a farmer...').
5. Avoid obvious or trivial questions.
6. Avoid repeating similar question patterns.
7. Each question must have 4 plausible options (A-D).
8. Incorrect options must be realistic and conceptually related (strong distractors).
9. Only one correct answer per question.
10. Do not use \"All of the above\" or \"None of the above.\"
11. Avoid copying lines from the content directly.
12. Vary cognitive level: 1 conceptual, 3 application-based, 1 higher-order thinking.

Target Grade: Class $class
Subject: $subject
Topic: $topic

Content:
$content

Return ONLY a valid JSON array with 5 objects. Each object must have these exact keys:
- \"question\": the question text
- \"option_a\": option A text
- \"option_b\": option B text
- \"option_c\": option C text
- \"option_d\": option D text
- \"correct_option\": the letter of correct answer (A, B, C, or D)
- \"explanation\": clear educational explanation of the answer

Return ONLY the JSON array, no markdown, no extra text.";

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . urlencode($apiKey);

    $payload = json_encode([
        'contents' => [
            ['parts' => [['text' => $prompt]]]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 4096
        ]
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

    if ($httpCode !== 200 || !$response) return null;

    $data = json_decode($response, true);
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

    if (empty($text)) return null;

    // Clean markdown code fences if present
    $text = preg_replace('/^```json\s*/i', '', trim($text));
    $text = preg_replace('/```\s*$/', '', trim($text));
    $text = trim($text);

    $questions = json_decode($text, true);

    if (!is_array($questions) || count($questions) === 0) return null;

    // Normalize the questions
    $normalized = [];
    foreach ($questions as $q) {
        if (!isset($q['question']) || !isset($q['correct_option'])) continue;

        $correct = strtoupper(trim($q['correct_option']));
        // Handle cases where correct_option might be "A" or "a" or "Option A"
        if (strlen($correct) > 1) {
            $correct = substr($correct, 0, 1);
        }

        $normalized[] = [
            'question'       => $q['question'],
            'option_a'       => $q['option_a'] ?? '',
            'option_b'       => $q['option_b'] ?? '',
            'option_c'       => $q['option_c'] ?? '',
            'option_d'       => $q['option_d'] ?? '',
            'correct_option' => $correct,
            'explanation'    => $q['explanation'] ?? ''
        ];
    }

    return count($normalized) > 0 ? $normalized : null;
}

// ============================================
//  CORE AI FUNCTIONS
// ============================================

function generateMCQ($topic, $content, $subject, $class) {
    // Step 1: Extract meaningful sentences
    $sentences = extractSentences($content);
    if (empty($sentences)) return null;

    // Step 2: Rank sentences by information density
    $rankedSentences = rankSentences($sentences);
    if (empty($rankedSentences)) return null;

    // Step 3: Pick the best sentence and extract a key fact
    shuffle($rankedSentences);
    $bestSentence = $rankedSentences[0];

    // Step 4: Extract key terms from the sentence
    $keyTerms = extractKeyTerms($bestSentence, $subject);

    // Step 5: Choose a question pattern and generate
    $mcq = buildQuestion($bestSentence, $keyTerms, $topic, $subject, $content);

    // Store the source sentence as explanation for AI hints
    if ($mcq) {
        $mcq['explanation'] = '💡 Hint: ' . $bestSentence;
    }

    return $mcq;
}

/**
 * Extract clean sentences from content
 */
function extractSentences($content) {
    // Clean the content
    $content = strip_tags($content);
    $content = preg_replace('/\s+/', ' ', $content);
    $content = trim($content);

    // Split by sentence-ending punctuation
    $raw = preg_split('/(?<=[.!?])\s+/', $content);

    $sentences = [];
    foreach ($raw as $s) {
        $s = trim($s);
        // Only keep sentences with enough substance (at least 6 words)
        $wordCount = str_word_count($s);
        if ($wordCount >= 6 && $wordCount <= 40) {
            $sentences[] = $s;
        }
    }

    return $sentences;
}

/**
 * Rank sentences by information density
 * (sentences with numbers, key terms, definitions are ranked higher)
 */
function rankSentences($sentences) {
    $scored = [];
    foreach ($sentences as $s) {
        $score = 0;
        // Contains numbers (facts/data)
        if (preg_match('/\d+/', $s)) $score += 3;
        // Contains "is", "are", "was" (definitions)
        if (preg_match('/\b(is|are|was|were|means|called|known as|defined as|refers to)\b/i', $s)) $score += 4;
        // Contains comparison words
        if (preg_match('/\b(larger|smaller|faster|slower|more|less|greater|highest|lowest|most|least)\b/i', $s)) $score += 2;
        // Contains cause-effect
        if (preg_match('/\b(because|therefore|causes|results in|leads to|due to|produces)\b/i', $s)) $score += 3;
        // Contains proper nouns (capitalized words mid-sentence)
        if (preg_match('/\b[A-Z][a-z]{2,}\b/', substr($s, 1))) $score += 1;
        // Longer sentences carry slightly more info
        $score += min(str_word_count($s) / 10, 2);

        if ($score >= 2) {
            $scored[] = ['sentence' => $s, 'score' => $score];
        }
    }

    // Sort by score descending
    usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

    return array_map(fn($item) => $item['sentence'], array_slice($scored, 0, 5));
}

/**
 * Extract key terms (nouns, important words) from a sentence
 */
function extractKeyTerms($sentence, $subject) {
    // Common stop words to ignore
    $stopWords = ['the','a','an','is','are','was','were','be','been','being',
        'have','has','had','do','does','did','will','would','could','should',
        'may','might','shall','can','need','dare','ought','used','to','of',
        'in','for','on','with','at','by','from','as','into','through','during',
        'before','after','above','below','between','out','off','over','under',
        'again','further','then','once','here','there','when','where','why',
        'how','all','both','each','few','more','most','other','some','such',
        'no','nor','not','only','own','same','so','than','too','very','just',
        'but','and','or','if','while','that','this','these','those','it','its',
        'they','them','their','we','our','you','your','he','she','him','her',
        'which','what','who','whom','whose','also','about','up','down','any',
        'every','much','many'];

    $words = preg_split('/[\s,;:()]+/', strtolower($sentence));
    $terms = [];

    foreach ($words as $w) {
        $w = preg_replace('/[^a-z0-9-]/', '', $w);
        if (strlen($w) > 3 && !in_array($w, $stopWords)) {
            $terms[] = $w;
        }
    }

    return array_unique($terms);
}

/**
 * Build a question using various patterns
 */
function buildQuestion($sentence, $keyTerms, $topic, $subject, $fullContent) {
    if (empty($keyTerms)) return null;

    // Choose a random question pattern
    $patterns = ['fill_blank', 'true_statement', 'what_question', 'which_question'];
    shuffle($patterns);

    foreach ($patterns as $pattern) {
        $mcq = null;
        switch ($pattern) {
            case 'fill_blank':
                $mcq = generateFillBlank($sentence, $keyTerms, $topic, $fullContent);
                break;
            case 'true_statement':
                $mcq = generateTrueStatement($sentence, $keyTerms, $topic);
                break;
            case 'what_question':
                $mcq = generateWhatQuestion($sentence, $keyTerms, $topic, $fullContent);
                break;
            case 'which_question':
                $mcq = generateWhichQuestion($sentence, $keyTerms, $topic, $fullContent);
                break;
        }
        if ($mcq) return $mcq;
    }

    // Fallback: simple topic question
    return generateTopicFallback($sentence, $keyTerms, $topic);
}

/**
 * Pattern 1: Fill in the blank
 * "_____ is the process of making food in plants" → Answer: Photosynthesis
 */
function generateFillBlank($sentence, $keyTerms, $topic, $fullContent) {
    // Find a key term to blank out
    $targetTerm = null;
    $originalWord = null;

    foreach ($keyTerms as $term) {
        // Find the original casing in the sentence
        if (preg_match('/\b(' . preg_quote($term, '/') . ')\b/i', $sentence, $m)) {
            $targetTerm = $term;
            $originalWord = $m[1];
            break;
        }
    }

    if (!$targetTerm || !$originalWord) return null;

    // Create the blanked sentence
    $blanked = preg_replace('/\b' . preg_quote($originalWord, '/') . '\b/', '_____', $sentence, 1);
    $question = "Fill in the blank: \"$blanked\"";

    // Generate distractors
    $distractors = generateDistractors($targetTerm, $keyTerms, $fullContent);
    if (count($distractors) < 3) return null;

    return buildMCQArray($question, ucfirst($targetTerm), $distractors);
}

/**
 * Pattern 2: Which statement is true about [topic]?
 */
function generateTrueStatement($sentence, $keyTerms, $topic) {
    $question = "Which of the following statements about " . $topic . " is correct?";

    // Correct answer is the real sentence (shortened if needed)
    $correct = shortenSentence($sentence);

    // Generate false statements by modifying the real one
    $falseStatements = generateFalseStatements($sentence, $keyTerms);
    if (count($falseStatements) < 3) return null;

    return buildMCQArray($question, $correct, $falseStatements);
}

/**
 * Pattern 3: What is/does/causes [key concept]?
 */
function generateWhatQuestion($sentence, $keyTerms, $topic, $fullContent) {
    // Look for "X is Y" pattern
    if (preg_match('/\b(\w[\w\s]{2,30}?)\s+(?:is|are|was|were)\s+(.{10,})/i', $sentence, $m)) {
        $subjectPart = trim($m[1]);
        $definitionPart = trim($m[2]);

        // Remove trailing period
        $definitionPart = rtrim($definitionPart, '.');

        $question = "What is $subjectPart?";
        $correct  = ucfirst($definitionPart);

        $distractors = generateConceptDistractors($definitionPart, $fullContent);
        if (count($distractors) < 3) return null;

        return buildMCQArray($question, $correct, $distractors);
    }

    return null;
}

/**
 * Pattern 4: Which of the following is related to [topic]?
 */
function generateWhichQuestion($sentence, $keyTerms, $topic, $fullContent) {
    if (count($keyTerms) < 2) return null;

    $correctTerm = $keyTerms[array_rand($keyTerms)];
    $question = "Which of the following is most closely related to $topic?";

    $distractors = generateDistractors($correctTerm, $keyTerms, $fullContent, true);
    if (count($distractors) < 3) return null;

    return buildMCQArray($question, ucfirst($correctTerm), $distractors);
}

/**
 * Fallback: Simple topic-based question
 */
function generateTopicFallback($sentence, $keyTerms, $topic) {
    $correct = shortenSentence($sentence);
    $question = "Which of the following best describes $topic?";

    $falseOptions = [
        "A mathematical formula used in algebra",
        "A type of chemical compound found in rocks",
        "A literary technique used in poetry",
        "A historical event from ancient civilization",
        "A musical instrument from the Renaissance period",
        "A geographical feature found in deserts",
        "A programming language for web development",
        "A method of transportation in modern cities"
    ];
    shuffle($falseOptions);

    return buildMCQArray($question, $correct, array_slice($falseOptions, 0, 3));
}

// ============================================
//  HELPER FUNCTIONS
// ============================================

/**
 * Generate distractor words (wrong answers) for a key term
 */
function generateDistractors($correctTerm, $keyTerms, $fullContent, $unrelated = false) {
    $distractors = [];

    // Related terms from content (but not the answer)
    $allTerms = extractKeyTerms($fullContent, '');
    $otherTerms = array_filter($allTerms, fn($t) => $t !== $correctTerm && strlen($t) > 3);
    $otherTerms = array_values($otherTerms);
    shuffle($otherTerms);

    foreach ($otherTerms as $t) {
        if (count($distractors) >= 3) break;
        $distractors[] = ucfirst($t);
    }

    // If still not enough, add generic distractors for Science/Math/English
    $genericDistractors = [
        'Velocity', 'Momentum', 'Friction', 'Gravity', 'Electron',
        'Molecule', 'Photon', 'Neutron', 'Proton', 'Nucleus',
        'Equation', 'Variable', 'Function', 'Polygon', 'Fraction',
        'Adjective', 'Pronoun', 'Metaphor', 'Alliteration', 'Syntax',
        'Osmosis', 'Mitosis', 'Erosion', 'Condensation', 'Diffusion',
        'Amplitude', 'Frequency', 'Wavelength', 'Refraction', 'Absorption',
        'Ecosystem', 'Organism', 'Chromosome', 'Catalyst', 'Compound'
    ];
    shuffle($genericDistractors);

    foreach ($genericDistractors as $d) {
        if (count($distractors) >= 3) break;
        if (strtolower($d) !== strtolower($correctTerm) && !in_array($d, $distractors)) {
            $distractors[] = $d;
        }
    }

    return array_slice($distractors, 0, 3);
}

/**
 * Generate concept-level distractors (for definition questions)
 */
function generateConceptDistractors($correctDef, $fullContent) {
    $distractors = [];

    // Get other sentences from content as plausible wrong answers
    $sentences = extractSentences($fullContent);
    shuffle($sentences);

    foreach ($sentences as $s) {
        if (count($distractors) >= 3) break;
        $shortened = shortenSentence($s);
        // Don't use the same sentence as distractor
        if (similar_text(strtolower($shortened), strtolower($correctDef)) < strlen($correctDef) * 0.5) {
            $distractors[] = $shortened;
        }
    }

    // Fill remaining with generic wrong definitions
    $genericDefs = [
        "The process of converting light into sound energy",
        "A chemical reaction that produces carbon dioxide only",
        "The movement of objects in a straight line without force",
        "A method of measuring electrical resistance in circuits",
        "The study of ancient languages and their origins",
        "A mathematical operation used to find square roots"
    ];
    shuffle($genericDefs);

    foreach ($genericDefs as $d) {
        if (count($distractors) >= 3) break;
        $distractors[] = $d;
    }

    return array_slice($distractors, 0, 3);
}

/**
 * Generate false statements by modifying a true sentence
 */
function generateFalseStatements($trueSentence, $keyTerms) {
    $falseStatements = [];

    // Strategy 1: Negate the sentence
    $negated = preg_replace('/\b(is|are|was|were)\b/i', '$1 not', $trueSentence, 1);
    if ($negated !== $trueSentence) {
        $falseStatements[] = shortenSentence($negated);
    }

    // Strategy 2: Replace a key term with another
    if (count($keyTerms) >= 2) {
        $term1 = $keyTerms[0];
        $swapWords = ['opposite', 'reverse', 'absence', 'lack'];
        $replacement = $swapWords[array_rand($swapWords)] . ' of ' . $term1;
        $modified = preg_replace('/\b' . preg_quote($keyTerms[1], '/') . '\b/i', $replacement, $trueSentence, 1);
        if ($modified !== $trueSentence) {
            $falseStatements[] = shortenSentence($modified);
        }
    }

    // Strategy 3: Add generic false statements
    $genericFalse = [
        "It has no practical applications in daily life",
        "It was discovered in the 21st century only",
        "It only applies to objects in outer space",
        "It contradicts all known scientific principles",
        "It is only theoretical and has never been observed",
        "It requires extremely high temperatures to occur"
    ];
    shuffle($genericFalse);

    foreach ($genericFalse as $f) {
        if (count($falseStatements) >= 3) break;
        $falseStatements[] = $f;
    }

    return array_slice($falseStatements, 0, 3);
}

/**
 * Shorten a sentence to a reasonable length for an MCQ option
 */
function shortenSentence($sentence) {
    $sentence = rtrim(trim($sentence), '.');
    $words = explode(' ', $sentence);
    if (count($words) > 15) {
        $sentence = implode(' ', array_slice($words, 0, 15)) . '...';
    }
    return $sentence;
}

/**
 * Build the final MCQ array with randomized option positions
 */
function buildMCQArray($question, $correctAnswer, $distractors) {
    // Combine correct + distractors
    $options = array_merge([$correctAnswer], array_slice($distractors, 0, 3));

    // Shuffle and track the correct position
    $indexed = [];
    foreach ($options as $i => $opt) {
        $indexed[] = ['text' => $opt, 'isCorrect' => ($i === 0)];
    }
    shuffle($indexed);

    $letters = ['A', 'B', 'C', 'D'];
    $correctLetter = 'A';
    $result = ['question' => $question];

    foreach ($indexed as $i => $item) {
        $key = 'option_' . strtolower($letters[$i]);
        $result[$key] = $item['text'];
        if ($item['isCorrect']) {
            $correctLetter = $letters[$i];
        }
    }

    $result['correct_option'] = $correctLetter;
    return $result;
}
?>