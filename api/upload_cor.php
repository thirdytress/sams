<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

if (!isset($_FILES['cor_file']) || $_FILES['cor_file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No valid COR PDF file was uploaded.']);
    exit;
}

$file = $_FILES['cor_file'];
$originalName = basename($file['name']);
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

if ($extension !== 'pdf') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Uploaded file must be a PDF document.']);
    exit;
}

$maxSize = 5 * 1024 * 1024; // 5MB
if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File size exceeds 5MB limit.']);
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if ($mimeType !== 'application/pdf') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File is not a valid PDF document.']);
    exit;
}

$tempRoot = __DIR__ . '/../uploads/registration_tmp';
if (!is_dir($tempRoot)) {
    mkdir($tempRoot, 0777, true);
}

$sessionFolder = session_id() ?: uniqid('reg_', true);
$tempFolder = $tempRoot . '/' . $sessionFolder;
if (!is_dir($tempFolder)) {
    mkdir($tempFolder, 0777, true);
}

$safeName = 'class_schedule_' . uniqid('', true) . '.pdf';
$targetPath = $tempFolder . '/' . $safeName;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save uploaded COR file.']);
    exit;
}

// Store in registration session so Step 4 (register2.php) has it already
if (!isset($_SESSION['sams_registration'])) {
    $_SESSION['sams_registration'] = [];
}
if (!isset($_SESSION['sams_registration']['step4'])) {
    $_SESSION['sams_registration']['step4'] = [
        'temp_folder' => $tempFolder,
        'files' => [],
        'agree_terms' => false,
        'agree_privacy' => false,
    ];
}
$_SESSION['sams_registration']['step4']['temp_folder'] = $tempFolder;
$_SESSION['sams_registration']['step4']['files']['class_schedule'] = [
    'original_name' => $originalName,
    'stored_name' => $safeName,
    'stored_path' => $targetPath,
    'mime_type' => 'application/pdf',
    'size' => (int) $file['size'],
    'uploaded_at' => date('Y-m-d H:i:s'),
];

echo json_encode([
    'success' => true,
    'message' => 'COR file uploaded and saved successfully.',
    'file' => [
        'original_name' => $originalName,
        'stored_name' => $safeName,
        'size' => (int) $file['size'],
    ]
]);
