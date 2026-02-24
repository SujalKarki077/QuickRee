<?php
// ============================================
//  Delete Level — Teacher Dashboard
// ============================================

error_reporting(0);
include "db.php";
header('Content-Type: application/json');

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid level ID']);
    exit;
}

// Delete related MCQs first (table may not exist)
$check = @$conn->query("SHOW TABLES LIKE 'mcqs'");
if ($check && $check->num_rows > 0) {
    @$conn->query("DELETE FROM mcqs WHERE level_id='$id'");
}

// Delete related progress
@$conn->query("DELETE FROM student_progress WHERE level_id='$id'");

// Delete the level
if ($conn->query("DELETE FROM levels WHERE id='$id'")) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $conn->error]);
}
?>
