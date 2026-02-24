<?php
include "db.php";

$name = $_POST['name'];
$lesson_id = $_POST['lesson_id'];
$score = $_POST['score'];

$sql = "INSERT INTO progress (student_name, lesson_id, score)
        VALUES ('$name', '$lesson_id', '$score')";

$conn->query($sql);
echo "Progress Saved";
?>