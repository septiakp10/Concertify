<?php
$host = 'localhost';
$user = 'root';
$pass = 'basdat2024';
$dbname = 'tiket';

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>