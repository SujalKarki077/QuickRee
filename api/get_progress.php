<?php
// ============================================
//  Get Student Progress
//  Supports both student_id (logged in) and name (legacy)
// ============================================

include "db.php";
header('Content-Type: application/json');
error_reporting(0);

$studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$name = isset($_GET['name']) ? $conn->real_escape_string($_GET['name']) : '';

if (!$studentId && $name === '') {
    echo json_encode(['completed_levels' => [], 'total_xp' => 0, 'levels_completed' => 0]);
    exit;
}

$completedLevels = [];
$totalXp = 0;

// Query by student_id if available, otherwise by name
if ($studentId) {
    $result = @$conn->query(
        "SELECT level_id, SUM(xp) as total_xp 
         FROM student_progress 
         WHERE student_id=$studentId AND completed=1 
         GROUP BY level_id"
    );
} else {
    $result = @$conn->query(
        "SELECT level_id, SUM(xp) as total_xp 
         FROM student_progress 
         WHERE student_name='$name' AND completed=1 
         GROUP BY level_id"
    );
}

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $completedLevels[] = (int)$row['level_id'];
        $totalXp += (int)$row['total_xp'];
    }
}

echo json_encode([
    'completed_levels' => $completedLevels,
    'total_xp' => $totalXp,
    'levels_completed' => count($completedLevels)
]);
?>
