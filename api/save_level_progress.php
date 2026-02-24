<?php
// ============================================
//  Save Level Progress
//  Supports both student_id (logged in) and student_name (legacy)
// ============================================

include "db.php";

$name       = $_POST['name'] ?? '';
$studentId  = $_POST['student_id'] ?? null;
$level      = $_POST['level_id'] ?? '';
$xp         = $_POST['xp'] ?? 0;

if (!$level) {
    echo "Missing level_id";
    exit;
}

// Use student_id if available, otherwise fall back to name
if ($studentId) {
    $stmt = $conn->prepare("INSERT INTO student_progress (student_id, student_name, level_id, xp, completed) VALUES (?, ?, ?, ?, 1)");
    $stmt->bind_param("isii", $studentId, $name, $level, $xp);
    $stmt->execute();
    $stmt->close();
} else {
    $sql = "INSERT INTO student_progress (student_name, level_id, xp, completed)
            VALUES ('$name','$level','$xp',1)";
    $conn->query($sql);
}

echo "Progress Saved";
?>