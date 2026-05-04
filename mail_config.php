<?php
// SMTP settings for PHPMailer OTP delivery.
// Update these values with your real mail server credentials.
//
// Gmail quick setup:
// 1) Turn ON 2-Step Verification in your Google account.
// 2) Generate an App Password (Google Account -> Security -> App passwords).
// 3) Use that App Password in MAIL_PASSWORD (not your normal Gmail password).
// 4) MAIL_HOST=smtp.gmail.com, MAIL_PORT=587, MAIL_ENCRYPTION=STARTTLS.

if (!defined('MAIL_HOST')) {
    define('MAIL_HOST', 'smtp.gmail.com');
}

if (!defined('MAIL_PORT')) {
    define('MAIL_PORT', 587);
}

if (!defined('MAIL_ENCRYPTION')) {
    define('MAIL_ENCRYPTION', PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS);
}

if (!defined('MAIL_USERNAME')) {
    define('MAIL_USERNAME', 'jgarvia9@gmail.com');
}

if (!defined('MAIL_PASSWORD')) {
    define('MAIL_PASSWORD', 'wbbc kkhb yeco pnza');
}

if (!defined('MAIL_FROM_EMAIL')) {
    define('MAIL_FROM_EMAIL', 'jgarvia9@gmail.com');
}

if (!defined('MAIL_FROM_NAME')) {
    define('MAIL_FROM_NAME', 'NU SAMS');
}

if (!defined('MAIL_LOGO_URL')) {
    define('MAIL_LOGO_URL', '');
}
