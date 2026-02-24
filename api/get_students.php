<?php
// ============================================
//  Get All Students (for Teacher Dashboard)
//  Returns students with progress stats
// ============================================

include "db.php";
header('Content-Type: application/json');

$students = [];
$grade = isset($_GET['grade']) ? $conn->real_escape_string($_GET['grade']) : '';

$sql = "
    SELECT 
        s.id, s.username, s.email, s.grade, s.created_at,
        COALESCE(SUM(sp.xp), 0) as total_xp,
        COUNT(DISTINCT CASE WHEN sp.completed = 1 THEN sp.level_id END) as levels_completed
    FROM students s
    LEFT JOIN student_progress sp ON sp.student_id = s.id
";

if ($grade !== '') {
    $sql .= " WHERE s.grade = '$grade'";
}

$sql .= " GROUP BY s.id ORDER BY total_xp DESC";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $students[] = [
            'id'               => (int)$row['id'],
            'username'         => $row['username'],
            'email'            => $row['email'],
            'grade'            => $row['grade'],
            'total_xp'         => (int)$row['total_xp'],
            'levels_completed' => (int)$row['levels_completed'],
            'created_at'       => $row['created_at']
        ];
    }
}

echo json_encode($students);
?>
