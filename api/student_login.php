<?php
// ============================================
//  Student Login
// ============================================

include "db.php";
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';

if (!$username || !$password) {
    echo json_encode(['success' => false, 'error' => 'Username and password are required.']);
    exit;
}

// Find student by username
$stmt = $conn->prepare("SELECT id, username, email, password_hash, grade FROM students WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'No account found with this username.']);
    $stmt->close();
    exit;
}

$student = $result->fetch_assoc();
$stmt->close();

// Verify password
if (!password_verify($password, $student['password_hash'])) {
    echo json_encode(['success' => false, 'error' => 'Incorrect password.']);
    exit;
}

// Get progress summary
$totalXp = 0;
$levelsCompleted = 0;
$progressResult = @$conn->query(
    "SELECT COUNT(DISTINCT level_id) as levels_done, SUM(xp) as total_xp 
     FROM student_progress 
     WHERE student_id='{$student['id']}' AND completed=1"
);
if ($progressResult && $row = $progressResult->fetch_assoc()) {
    $totalXp = (int)($row['total_xp'] ?? 0);
    $levelsCompleted = (int)($row['levels_done'] ?? 0);
}

echo json_encode([
    'success' => true,
    'student' => [
        'id'       => (int)$student['id'],
        'username' => $student['username'],
        'email'    => $student['email'],
        'grade'    => $student['grade']
    ],
    'progress' => [
        'total_xp'         => $totalXp,
        'levels_completed' => $levelsCompleted
    ]
]);
?>
