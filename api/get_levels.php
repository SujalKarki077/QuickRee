<?php
include "db.php";

$class   = isset($_GET['class'])   ? $conn->real_escape_string($_GET['class'])   : '';
$subject = isset($_GET['subject']) ? $conn->real_escape_string($_GET['subject']) : '';

$sql = "SELECT * FROM levels WHERE 1=1";

if ($class !== '') {
    $sql .= " AND class='$class'";
}
if ($subject !== '') {
    $sql .= " AND subject='$subject'";
}

$sql .= " ORDER BY level_no ASC";

$result = $conn->query($sql);

$levels = [];
while ($row = $result->fetch_assoc()) {
    $levels[] = $row;
}

echo json_encode($levels);
?>