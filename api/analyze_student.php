<?php
// ============================================
//  Smart Student Progress Analyzer
//  No API key required!
//  
//  Analyzes student data using rule-based logic:
//  - Performance scoring per subject
//  - XP efficiency analysis
//  - Completion rate tracking
//  - Personalized recommendations
// ============================================

include "db.php";
header('Content-Type: application/json');
error_reporting(0);

$input = json_decode(file_get_contents('php://input'), true);
$studentId = (int)($input['student_id'] ?? 0);

if (!$studentId) {
    echo json_encode(['success' => false, 'error' => 'Student ID required']);
    exit;
}

// --- Fetch student info ---
$stmt = $conn->prepare("SELECT id, username, email, grade, created_at FROM students WHERE id = ?");
$stmt->bind_param("i", $studentId);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    echo json_encode(['success' => false, 'error' => 'Student not found']);
    exit;
}

// --- Fetch detailed progress ---
$result = $conn->query("
    SELECT 
        sp.level_id, sp.xp, sp.completed,
        l.topic, l.level_no, l.subject, l.class
    FROM student_progress sp
    LEFT JOIN levels l ON sp.level_id = l.id
    WHERE sp.student_id = $studentId
    ORDER BY sp.id ASC
");

$totalXp = 0;
$levelsCompleted = 0;
$subjects = [];
$levelDetails = [];
$xpScores = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $xp = (int)$row['xp'];
        $totalXp += $xp;
        if ($row['completed']) $levelsCompleted++;
        $subj = $row['subject'] ?? 'Unknown';
        if (!isset($subjects[$subj])) $subjects[$subj] = ['xp' => 0, 'levels' => 0, 'scores' => []];
        $subjects[$subj]['xp'] += $xp;
        $subjects[$subj]['levels']++;
        $subjects[$subj]['scores'][] = $xp;
        $xpScores[] = $xp;
        $levelDetails[] = [
            'topic' => $row['topic'] ?? 'Unknown',
            'level' => $row['level_no'] ?? '?',
            'subject' => $subj,
            'xp' => $xp,
            'completed' => (bool)$row['completed']
        ];
    }
}

// --- Count total available levels for this grade ---
$totalLevels = 0;
$levelsResult = $conn->query("SELECT COUNT(*) as cnt FROM levels WHERE class = '{$student['grade']}'");
if ($levelsResult && $r = $levelsResult->fetch_assoc()) {
    $totalLevels = (int)$r['cnt'];
}

// --- Get class average for comparison ---
$classAvgXp = 0;
$classStudentCount = 0;
$avgResult = $conn->query("
    SELECT s.id, COALESCE(SUM(sp.xp), 0) as student_xp
    FROM students s
    LEFT JOIN student_progress sp ON sp.student_id = s.id AND sp.completed = 1
    WHERE s.grade = '{$student['grade']}'
    GROUP BY s.id
");
if ($avgResult) {
    $allXps = [];
    while ($r = $avgResult->fetch_assoc()) {
        $allXps[] = (int)$r['student_xp'];
    }
    $classStudentCount = count($allXps);
    $classAvgXp = $classStudentCount > 0 ? round(array_sum($allXps) / $classStudentCount) : 0;
}

// ============================================
//  ANALYSIS ENGINE
// ============================================

$analysis = [];
$completionRate = $totalLevels > 0 ? round(($levelsCompleted / $totalLevels) * 100) : 0;
$avgXpPerLevel = $levelsCompleted > 0 ? round($totalXp / $levelsCompleted) : 0;
// Max XP per level = 50 (first try correct on 5 questions)
$maxXpPerLevel = 250;
$efficiency = $maxXpPerLevel > 0 && $levelsCompleted > 0 ? round(($avgXpPerLevel / $maxXpPerLevel) * 100) : 0;

// --- 1. Overall Performance ---
$overall = "**📊 Overall Performance**\n";
if ($levelsCompleted === 0) {
    $overall .= "{$student['username']} hasn't completed any levels yet. They joined on " . date('M j, Y', strtotime($student['created_at'])) . " and are in Class {$student['grade']}. Encourage them to start with an easier subject to build confidence.\n";
} else {
    $performanceLabel = $efficiency >= 80 ? "excellent" : ($efficiency >= 60 ? "good" : ($efficiency >= 40 ? "average" : "developing"));
    $overall .= "{$student['username']} has completed **{$levelsCompleted} out of {$totalLevels} levels** ({$completionRate}% completion) with **{$totalXp} XP** total. ";
    $overall .= "Their performance is **{$performanceLabel}** with an average of {$avgXpPerLevel} XP per level";
    if ($classStudentCount > 1) {
        $comparison = $totalXp > $classAvgXp ? "above" : ($totalXp < $classAvgXp ? "below" : "at");
        $overall .= ". They are **{$comparison} the class average** ({$classAvgXp} XP)";
    }
    $overall .= ".\n";
}
$analysis[] = $overall;

// --- 2. Strengths ---
$strengths = "**💪 Strengths**\n";
if (count($subjects) === 0) {
    $strengths .= "No data yet — needs to complete levels to identify strengths.\n";
} else {
    // Sort subjects by average XP (best first)
    $subjectAvgs = [];
    foreach ($subjects as $subj => $data) {
        $subjectAvgs[$subj] = $data['levels'] > 0 ? round($data['xp'] / $data['levels']) : 0;
    }
    arsort($subjectAvgs);
    
    $bestSubject = array_key_first($subjectAvgs);
    $bestAvg = $subjectAvgs[$bestSubject];
    
    if ($bestAvg >= 200) {
        $strengths .= "Excels in **{$bestSubject}** with an impressive average of {$bestAvg} XP per level — showing strong mastery. ";
    } elseif ($bestAvg >= 100) {
        $strengths .= "Shows good understanding in **{$bestSubject}** with {$bestAvg} XP per level average. ";
    } else {
        $strengths .= "Best performance is in **{$bestSubject}** ({$bestAvg} XP avg). ";
    }
    
    // Check for consistency (low variance = consistent)
    if (count($xpScores) >= 3) {
        $mean = array_sum($xpScores) / count($xpScores);
        $variance = 0;
        foreach ($xpScores as $s) $variance += pow($s - $mean, 2);
        $variance /= count($xpScores);
        $stdDev = sqrt($variance);
        if ($stdDev < $mean * 0.25) {
            $strengths .= "Shows **consistent performance** across levels — a sign of steady learning habits. ";
        }
    }
    
    // High scores on specific levels
    $highScoreLevels = array_filter($levelDetails, fn($l) => $l['xp'] >= 200);
    if (count($highScoreLevels) > 0) {
        $topLevel = array_reduce($highScoreLevels, fn($carry, $l) => (!$carry || $l['xp'] > $carry['xp']) ? $l : $carry);
        $strengths .= "Scored highest on **{$topLevel['topic']}** ({$topLevel['xp']} XP). ";
    }
    $strengths .= "\n";
}
$analysis[] = $strengths;

// --- 3. Areas for Improvement ---
$improvements = "**📈 Areas for Improvement**\n";
if (count($subjects) === 0) {
    $improvements .= "Complete some levels first to identify improvement areas.\n";
} else {
    $subjectAvgs = [];
    foreach ($subjects as $subj => $data) {
        $subjectAvgs[$subj] = $data['levels'] > 0 ? round($data['xp'] / $data['levels']) : 0;
    }
    asort($subjectAvgs);
    
    $weakestSubject = array_key_first($subjectAvgs);
    $weakestAvg = $subjectAvgs[$weakestSubject];
    
    if (count($subjects) > 1) {
        $improvements .= "**{$weakestSubject}** needs the most attention with only {$weakestAvg} XP per level average. ";
    }
    
    // Low score levels
    $lowScoreLevels = array_filter($levelDetails, fn($l) => $l['xp'] < 100 && $l['xp'] > 0);
    if (count($lowScoreLevels) > 0) {
        $lowTopics = array_unique(array_column($lowScoreLevels, 'topic'));
        $lowList = implode(', ', array_slice($lowTopics, 0, 3));
        $improvements .= "Struggled with: **{$lowList}** — may need to revisit these topics. ";
    }
    
    if ($completionRate < 50 && $totalLevels > 0) {
        $remaining = $totalLevels - $levelsCompleted;
        $improvements .= "Still has **{$remaining} levels** remaining ({$completionRate}% completion) — encourage more regular practice. ";
    }
    
    if ($efficiency < 50 && $levelsCompleted > 0) {
        $improvements .= "Quiz accuracy is low ({$efficiency}% of max XP) — may benefit from re-reading lesson content before attempting quizzes. ";
    }
    $improvements .= "\n";
}
$analysis[] = $improvements;

// --- 4. Recommendations ---
$recommendations = "**🎯 Recommendations for Teacher**\n";
if ($levelsCompleted === 0) {
    $recommendations .= "• Start with a 1-on-1 session to introduce the platform and help them complete their first level.\n";
    $recommendations .= "• Assign an easy topic in their strongest subject to build early confidence.\n";
    $recommendations .= "• Check in after a few days to ensure they're engaging with the material.\n";
} else {
    if ($efficiency >= 80) {
        $recommendations .= "• This student is performing excellently — consider giving them **advanced challenges** or letting them help peers.\n";
    } elseif ($efficiency >= 50) {
        $recommendations .= "• Solid performance overall. Focus on the weaker subjects to bring them up to the same level.\n";
    } else {
        $recommendations .= "• Consider **targeted review sessions** on the topics they struggled with most.\n";
        $recommendations .= "• Encourage them to read the lesson content carefully before attempting quizzes.\n";
    }
    
    if (count($subjects) > 1) {
        asort($subjectAvgs);
        $weakest = array_key_first($subjectAvgs);
        $recommendations .= "• Prioritize extra practice in **{$weakest}** — this is currently their weakest area.\n";
    }
    
    if ($completionRate < 30) {
        $recommendations .= "• Low completion rate — set small daily goals (e.g., complete 1 level per day).\n";
    }
    
    if ($totalXp > $classAvgXp && $classStudentCount > 1) {
        $recommendations .= "• Above class average — could be a peer tutor for classmates who are struggling.\n";
    }
}
$analysis[] = $recommendations;

// --- Combine analysis ---
$fullAnalysis = implode("\n", $analysis);

echo json_encode([
    'success'  => true,
    'student'  => $student['username'],
    'analysis' => $fullAnalysis,
    'stats'    => [
        'total_xp'         => $totalXp,
        'levels_completed' => $levelsCompleted,
        'total_levels'     => $totalLevels,
        'subjects'         => $subjects,
        'avg_xp_per_level' => $avgXpPerLevel,
        'efficiency'       => $efficiency,
        'class_avg_xp'     => $classAvgXp,
        'completion_rate'  => $completionRate
    ]
]);
?>
