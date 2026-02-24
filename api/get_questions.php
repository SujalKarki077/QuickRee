<?php
// ============================================
//  Get Questions for a Level
//  Fetches from both 'questions' and 'mcqs' tables
// ============================================

include "db.php";
header('Content-Type: application/json');

$level_id = $_GET['level_id'] ?? 0;

$allQuestions = [];

// 1. Try to get from 'questions' table (manually added)
$result = @$conn->query("SELECT *, '' as explanation FROM questions WHERE level_id='$level_id'");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $allQuestions[] = $row;
    }
}

// 2. Get from 'mcqs' table (AI-generated, linked by level_id)
$result = @$conn->query("SELECT id, question, option_a, option_b, option_c, option_d, correct_option, explanation FROM mcqs WHERE level_id='$level_id'");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Map correct_option to 'correct' field for consistency
        $row['correct'] = $row['correct_option'];
        $allQuestions[] = $row;
    }
}

// 3. Fallback: if no questions found by level_id, try matching by class/subject of this level
if (empty($allQuestions)) {
    $levelResult = @$conn->query("SELECT class, subject FROM levels WHERE id='$level_id'");
    if ($levelResult && $level = $levelResult->fetch_assoc()) {
        $cls = $conn->real_escape_string($level['class']);
        $sub = $conn->real_escape_string($level['subject']);
        $result = @$conn->query("SELECT id, question, option_a, option_b, option_c, option_d, correct_option, explanation FROM mcqs WHERE class='$cls' AND subject='$sub' ORDER BY RAND() LIMIT 5");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $row['correct'] = $row['correct_option'];
                $allQuestions[] = $row;
            }
        }
    }
}

// Shuffle and limit to 5 questions
shuffle($allQuestions);
$allQuestions = array_slice($allQuestions, 0, 5);

echo json_encode($allQuestions);
?>