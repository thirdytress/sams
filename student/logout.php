<?php
// student/logout.php – destroy session then go back to home page
session_start();

// Clear all session data
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

session_destroy();

// Redirect to main landing page
header('Location: ../index.php');
exit;
