<?php
include "db.php";

$result = $conn->query("SELECT * FROM lessons ORDER BY id DESC LIMIT 1");
$row = $result->fetch_assoc();

echo json_encode($row);
?>