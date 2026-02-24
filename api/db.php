<?php
$conn = new mysqli("localhost", "root", "", "ai_school");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>