<?php
$mysqli = new mysqli('127.0.0.1', 'root', '', 'sams_db');
$res = $mysqli->query('SHOW COLUMNS FROM attendance');
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
