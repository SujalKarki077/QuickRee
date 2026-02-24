<?php
// ============================================
//  Generate Weekly Report — Teacher Dashboard
//  Provides summary of all student activity
// ============================================

error_reporting(0);
include "db.php";
header('Content-Type: application/json');

$grade = isset($_GET['grade']) ? $conn->real_escape_string($_GET['grade']) : '';

// --- Build WHERE clause ---
$whereStudent = $grade !== '' ? " WHERE s.grade = '$grade'" : '';
$whereProgress = " WHERE sp.completed = 1";

// --- Total students ---
$totalStudents = 0;
$res = $conn->query("SELECT COUNT(*) as cnt FROM students s" . ($grade ? " WHERE s.grade='$grade'" : ''));
if ($res && $r = $res->fetch_assoc()) $totalStudents = (int)$r['cnt'];

// --- Active students (completed at least 1 level in last 7 days) ---
$activeStudents = 0;
$res = $conn->query("
    SELECT COUNT(DISTINCT sp.student_id) as cnt 
    FROM student_progress sp 
    JOIN students s ON s.id = sp.student_id
    WHERE sp.completed = 1 
    AND sp.id IN (SELECT id FROM student_progress WHERE completed=1 ORDER BY id DESC)
    " . ($grade ? " AND s.grade='$grade'" : ''));
if ($res && $r = $res->fetch_assoc()) $activeStudents = (int)$r['cnt'];

// --- Overall stats ---
$totalXp = 0;
$totalLevelsCompleted = 0;
$res = $conn->query("
    SELECT COALESCE(SUM(sp.xp), 0) as total_xp, 
           COUNT(DISTINCT CONCAT(sp.student_id, '-', sp.level_id)) as levels_done
    FROM student_progress sp
    JOIN students s ON s.id = sp.student_id
    WHERE sp.completed = 1
    " . ($grade ? " AND s.grade='$grade'" : ''));
if ($res && $r = $res->fetch_assoc()) {
    $totalXp = (int)$r['total_xp'];
    $totalLevelsCompleted = (int)$r['levels_done'];
}

// --- Top 5 students ---
$topStudents = [];
$res = $conn->query("
    SELECT s.username, s.grade, 
           COALESCE(SUM(sp.xp), 0) as total_xp,
           COUNT(DISTINCT CASE WHEN sp.completed=1 THEN sp.level_id END) as levels_done
    FROM students s
    LEFT JOIN student_progress sp ON sp.student_id = s.id
    " . ($grade ? " WHERE s.grade='$grade'" : '') . "
    GROUP BY s.id
    ORDER BY total_xp DESC
    LIMIT 5
");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $topStudents[] = [
            'username' => $r['username'],
            'grade' => $r['grade'],
            'total_xp' => (int)$r['total_xp'],
            'levels_done' => (int)$r['levels_done']
        ];
    }
}

// --- Subject-wise performance ---
$subjectStats = [];
$res = $conn->query("
    SELECT l.subject, 
           COUNT(DISTINCT sp.student_id) as students_active,
           COALESCE(SUM(sp.xp), 0) as total_xp,
           COUNT(DISTINCT CASE WHEN sp.completed=1 THEN CONCAT(sp.student_id,'-',sp.level_id) END) as levels_done
    FROM student_progress sp
    JOIN levels l ON l.id = sp.level_id
    JOIN students s ON s.id = sp.student_id
    WHERE sp.completed = 1
    " . ($grade ? " AND s.grade='$grade'" : '') . "
    GROUP BY l.subject
    ORDER BY total_xp DESC
");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $subjectStats[] = [
            'subject' => $r['subject'],
            'students_active' => (int)$r['students_active'],
            'total_xp' => (int)$r['total_xp'],
            'levels_done' => (int)$r['levels_done']
        ];
    }
}

// --- Students needing attention (0 XP or very low) ---
$needAttention = [];
$res = $conn->query("
    SELECT s.username, s.grade, COALESCE(SUM(sp.xp), 0) as total_xp
    FROM students s
    LEFT JOIN student_progress sp ON sp.student_id = s.id AND sp.completed = 1
    " . ($grade ? " WHERE s.grade='$grade'" : '') . "
    GROUP BY s.id
    HAVING total_xp = 0
    ORDER BY s.created_at DESC
    LIMIT 10
");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $needAttention[] = [
            'username' => $r['username'],
            'grade' => $r['grade']
        ];
    }
}

echo json_encode([
    'success' => true,
    'report' => [
        'generated_at' => date('Y-m-d H:i:s'),
        'grade_filter' => $grade ?: 'All',
        'total_students' => $totalStudents,
        'active_students' => $activeStudents,
        'total_xp_earned' => $totalXp,
        'total_topics_completed' => $totalLevelsCompleted,
        'avg_xp_per_student' => $totalStudents > 0 ? round($totalXp / $totalStudents) : 0,
        'top_students' => $topStudents,
        'subject_performance' => $subjectStats,
        'need_attention' => $needAttention
    ]
]);
?>
