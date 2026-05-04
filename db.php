<?php
// db.php – central database connection for NU SAMS
// Adjust credentials to match your XAMPP/MySQL setup.

$DB_HOST = 'localhost';
$DB_NAME = 'sams_db';      // TODO: create this database in phpMyAdmin
$DB_USER = 'root';         // Default XAMPP user
$DB_PASS = '';             // Default XAMPP password (empty)

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($mysqli->connect_error) {
    die('Database connection failed: ' . $mysqli->connect_error);
}

// Optional: set character set
$mysqli->set_charset('utf8mb4');
