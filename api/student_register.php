<?php
// ============================================
//  Student Registration
// ============================================

include "db.php";
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$username = trim($input['username'] ?? '');
$email    = trim($input['email'] ?? '');
$password = $input['password'] ?? '';
$grade    = trim($input['grade'] ?? '');

// Validate
if (!$username || !$email || !$password || !$grade) {
    echo json_encode(['success' => false, 'error' => 'All fields are required.']);
    exit;
}

if (strlen($password) < 4) {
    echo json_encode(['success' => false, 'error' => 'Password must be at least 4 characters.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid email address.']);
    exit;
}

// Check if email already exists
$stmt = $conn->prepare("SELECT id FROM students WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    echo json_encode(['success' => false, 'error' => 'An account with this email already exists.']);
    $stmt->close();
    exit;
}
$stmt->close();

// Hash password and insert
$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO students (username, email, password_hash, grade) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $username, $email, $passwordHash, $grade);

if ($stmt->execute()) {
    $studentId = $stmt->insert_id;
    echo json_encode([
        'success'  => true,
        'student'  => [
            'id'       => $studentId,
            'username' => $username,
            'email'    => $email,
            'grade'    => $grade
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Registration failed. Try again.']);
}
$stmt->close();
?>
