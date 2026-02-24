<?php
// ============================================
//  Database Updates for Hackathon Features
//  Run this once: http://localhost/AI_Classroom/api/setup_updates.php
// ============================================

include "db.php";

$queries = [];

// 0a. Add 'option_d' column to mcqs table if missing (for 4-option MCQs)
$check = $conn->query("SHOW COLUMNS FROM mcqs LIKE 'option_d'");
if ($check->num_rows === 0) {
    $conn->query("ALTER TABLE mcqs ADD COLUMN option_d VARCHAR(255) DEFAULT NULL AFTER option_c");
    $queries[] = "✅ Added 'option_d' column to mcqs table";
} else {
    $queries[] = "⏭️ 'option_d' column already exists in mcqs";
}

// 0b. Add 'option_d' column to questions table if missing (for 4-option MCQs)
$check = $conn->query("SHOW COLUMNS FROM questions LIKE 'option_d'");
if ($check && $check->num_rows === 0) {
    $conn->query("ALTER TABLE questions ADD COLUMN option_d VARCHAR(255) DEFAULT NULL AFTER option_c");
    $queries[] = "✅ Added 'option_d' column to questions table";
} else {
    $queries[] = "⏭️ 'option_d' column already exists in questions (or table doesn't exist)";
}

// 1. Add 'explanation' column to mcqs table (for AI hints on wrong answers)
$check = $conn->query("SHOW COLUMNS FROM mcqs LIKE 'explanation'");
if ($check->num_rows === 0) {
    $conn->query("ALTER TABLE mcqs ADD COLUMN explanation TEXT DEFAULT NULL AFTER correct_option");
    $queries[] = "✅ Added 'explanation' column to mcqs table";
} else {
    $queries[] = "⏭️ 'explanation' column already exists in mcqs";
}

// 2. Add 'class' column to levels table if missing
$check = $conn->query("SHOW COLUMNS FROM levels LIKE 'class'");
if ($check->num_rows === 0) {
    $conn->query("ALTER TABLE levels ADD COLUMN class VARCHAR(10) DEFAULT '8' AFTER id");
    $queries[] = "✅ Added 'class' column to levels table";
} else {
    $queries[] = "⏭️ 'class' column already exists in levels";
}

// 3. Add 'level_id' column to mcqs table if missing (to link MCQs to specific levels)
$check = $conn->query("SHOW COLUMNS FROM mcqs LIKE 'level_id'");
if ($check->num_rows === 0) {
    $conn->query("ALTER TABLE mcqs ADD COLUMN level_id INT DEFAULT NULL AFTER subject");
    $queries[] = "✅ Added 'level_id' column to mcqs table";
} else {
    $queries[] = "⏭️ 'level_id' column already exists in mcqs";
}

// 4. Create 'students' table for login system
$check = $conn->query("SHOW TABLES LIKE 'students'");
if ($check->num_rows === 0) {
    $conn->query("CREATE TABLE students (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        grade VARCHAR(10) NOT NULL DEFAULT '8',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $queries[] = "✅ Created 'students' table";
} else {
    $queries[] = "⏭️ 'students' table already exists";
}

// 5. Add 'student_id' column to student_progress table
$check = $conn->query("SHOW COLUMNS FROM student_progress LIKE 'student_id'");
if ($check && $check->num_rows === 0) {
    $conn->query("ALTER TABLE student_progress ADD COLUMN student_id INT DEFAULT NULL AFTER id");
    $queries[] = "✅ Added 'student_id' column to student_progress table";
} else {
    $queries[] = "⏭️ 'student_id' column already exists in student_progress (or table missing)";
}

// 6. Create 'mcqs' table if missing
$check = $conn->query("SHOW TABLES LIKE 'mcqs'");
if ($check->num_rows === 0) {
    $conn->query("CREATE TABLE mcqs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        level_id INT NOT NULL,
        question TEXT NOT NULL,
        option_a VARCHAR(255),
        option_b VARCHAR(255),
        option_c VARCHAR(255),
        option_d VARCHAR(255),
        correct_answer CHAR(1) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $queries[] = "✅ Created 'mcqs' table";
} else {
    $queries[] = "⏭️ 'mcqs' table already exists";
}

echo "<h2>Database Updates Complete</h2>";
echo "<ul>";
foreach ($queries as $q) {
    echo "<li>$q</li>";
}
echo "</ul>";
echo "<p><a href='../public/index.html'>← Go to Student Page</a> | <a href='../public/teacher.html'>Go to Teacher Page →</a></p>";
?>
