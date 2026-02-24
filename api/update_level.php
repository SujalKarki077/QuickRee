<?php
// ============================================
//  Update Level — Teacher Dashboard
// ============================================

include "db.php";
header('Content-Type: application/json');

$id      = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$topic   = isset($_POST['topic']) ? $conn->real_escape_string($_POST['topic']) : '';
$content = isset($_POST['content']) ? $conn->real_escape_string($_POST['content']) : '';

if ($id <= 0 || $topic === '') {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

if ($conn->query("UPDATE levels SET topic='$topic', content='$content' WHERE id='$id'")) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $conn->error]);
}
?>
