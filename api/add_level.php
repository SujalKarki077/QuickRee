<?php
// ============================================
//  Add Level — Teacher Dashboard
// ============================================

include "db.php";

$class   = isset($_POST['class']) ? $_POST['class'] : '';
$subject = $_POST['subject'];
$topic   = $_POST['topic'];
$level   = $_POST['level_no'];
$content = $_POST['content'];

// Check if 'class' column exists in levels table
$checkCol = $conn->query("SHOW COLUMNS FROM levels LIKE 'class'");
if ($checkCol && $checkCol->num_rows > 0) {
    // Table has class column — insert with it
    $sql = "INSERT INTO levels (class, subject, topic, level_no, content)
            VALUES ('$class','$subject','$topic','$level','$content')";
} else {
    // Table doesn't have class column — insert without it
    $sql = "INSERT INTO levels (subject, topic, level_no, content)
            VALUES ('$subject','$topic','$level','$content')";
}

if ($conn->query($sql)) {
    echo "Level Added";
} else {
    echo "Error: " . $conn->error;
}
?>