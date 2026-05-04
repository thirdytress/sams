<?php
// admin/logout.php - clear admin session and go back to login
session_start();

unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_role'], $_SESSION['department']);

header('Location: ../login.php');
exit;
