<?php
include "db.php";

$class = $_POST['class'];
$subject = $_POST['subject'];
$topic = $_POST['topic'];
$content = $_POST['content'];

$sql = "INSERT INTO lessons (class, subject, topic, content)
        VALUES ('$class', '$subject', '$topic', '$content')";

$conn->query($sql);
echo "Lesson Added";
?>