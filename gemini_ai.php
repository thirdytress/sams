<?php
// gemini_ai.php - Google Gemini helper for COR analysis and schedule suggestions.

if (!defined('GEMINI_API_KEY')) {
    define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');
}

if (!defined('GEMINI_MODEL')) {
    define('GEMINI_MODEL', getenv('GEMINI_MODEL') ?: 'gemini-2.0-flash');
}

if (!defined('GEMINI_FALLBACK_ON_QUOTA')) {
    $fallbackOnQuota = getenv('GEMINI_FALLBACK_ON_QUOTA');
    define('GEMINI_FALLBACK_ON_QUOTA', $fallbackOnQuota === false ? false : strtolower((string) $fallbackOnQuota) === 'true');
}

function gemini_get_api_key(): string {
    $apiKey = trim((string) GEMINI_API_KEY);
    if ($apiKey !== '') {
        return $apiKey;
    }

    $configFile = __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'gemini.php';
    if (is_file($configFile)) {
        $config = include $configFile;
        if (is_array($config) && !empty($config['api_key'])) {
            return trim((string) $config['api_key']);
        }
    }

    return '';
}

function gemini_normalize_path(string $path): string {
    $path = trim($path);
    if ($path === '') {
        return '';
    }

    if (preg_match('/^[A-Z]:\\\\/i', $path) || str_starts_with($path, '/') || str_starts_with($path, '\\')) {
        return $path;
    }

    return __DIR__ . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
}

function gemini_file_to_part(string $filePath): ?array {
    $resolvedPath = gemini_normalize_path($filePath);
    if ($resolvedPath === '' || !is_file($resolvedPath) || !is_readable($resolvedPath)) {
        return null;
    }

    $mimeType = function_exists('mime_content_type') ? (string) mime_content_type($resolvedPath) : '';
    if ($mimeType === '') {
        $extension = strtolower(pathinfo($resolvedPath, PATHINFO_EXTENSION));
        $mimeType = match ($extension) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => 'application/octet-stream',
        };
    }

    $binary = file_get_contents($resolvedPath);
    if ($binary === false) {
        return null;
    }

    return [
        'inline_data' => [
            'mime_type' => $mimeType,
            'data' => base64_encode($binary),
        ],
    ];
}

function gemini_extract_retry_seconds(string $message): int {
    if (preg_match('/retry\s+in\s+([0-9]+(?:\.[0-9]+)?)s/i', $message, $matches)) {
        return max(1, (int) ceil((float) $matches[1]));
    }

    return 2;
}

function gemini_quota_fallback_data(array $studentContext = []): array {
    $studentName = trim((string) ($studentContext['full_name'] ?? 'Student'));
    $studentId = trim((string) ($studentContext['student_id'] ?? ''));
    $course = trim((string) ($studentContext['course'] ?? ''));
    $yearLevel = trim((string) ($studentContext['year_level'] ?? ''));

    return [
        'full_name' => $studentName,
        'student_id' => $studentId,
        'course' => $course,
        'year_level' => $yearLevel,
        'work_location' => 'Campus Office',
        'conflicts' => ['Generated with fallback due to temporary Gemini quota limit.'],
        'available_windows' => [
            'Monday 1:00PM-5:00PM',
            'Wednesday 8:00AM-12:00PM',
            'Friday 1:00PM-5:00PM',
        ],
        'recommended_work_schedule' => 'Monday: 1:00PM-5:00PM | Wednesday: 8:00AM-12:00PM | Friday: 1:00PM-5:00PM',
        'recommended_hours_per_week' => 12,
        'notes' => 'Fallback schedule only. Replace with live Gemini output once quota becomes available.',
    ];
}

function gemini_analyze_cor(string $filePath, array $studentContext = []): array {
    $apiKey = gemini_get_api_key();
    if ($apiKey === '') {
        return [
            'ok' => false,
            'success' => false,
            'error' => 'Gemini API key is not configured. Set GEMINI_API_KEY in your environment.',
        ];
    }

    $filePart = gemini_file_to_part($filePath);
    if ($filePart === null) {
        return [
            'ok' => false,
            'success' => false,
            'error' => 'COR file could not be read.',
        ];
    }

    $studentName = trim((string) ($studentContext['full_name'] ?? 'Student'));
    $studentId = trim((string) ($studentContext['student_id'] ?? ''));
    $course = trim((string) ($studentContext['course'] ?? ''));
    $yearLevel = trim((string) ($studentContext['year_level'] ?? ''));
    $workLocation = trim((string) ($studentContext['work_location'] ?? ''));
    $skills = trim((string) ($studentContext['skills'] ?? ''));

    $prompt = [
        [
            'role' => 'user',
            'parts' => [
                [
                    'text' =>
                        "Analyze this COR or class schedule document for {$studentName}. " .
                        "Return ONLY valid JSON with these keys: full_name, student_id, course, year_level, work_location, " .
                        "conflicts, available_windows, recommended_work_schedule, recommended_hours_per_week, notes. " .
                        "Use this schedule format for recommended_work_schedule: Monday: 8:00AM-12:00PM | Tuesday: 1:00PM-5:00PM. " .
                        "Keep recommendations inside the allowed work window of Monday to Saturday, 8:00AM to 5:00PM. " .
                        "Strict rules: minimum duty block is 2 hours, no maximum duty hours limit, and always exclude lunch break 12:00PM-1:00PM. " .
                        "If class is 7:00AM-10:00AM, recommended duty should start at 10:00AM and continue up to 5:00PM while skipping 12:00PM-1:00PM lunch. " .
                        "Avoid class conflicts and make the schedule practical for a student assistant. " .
                        "Here is known context: student_id={$studentId}; course={$course}; year_level={$yearLevel}; work_location={$workLocation}; skills={$skills}."
                ],
                $filePart,
            ],
        ],
    ];

    $payload = json_encode([
        'contents' => $prompt,
        'generationConfig' => [
            'temperature' => 0.2,
        ],
    ]);

    $url = 'https://generativelanguage.googleapis.com/v1/models/' . rawurlencode(GEMINI_MODEL) . ':generateContent?key=' . rawurlencode($apiKey);

    $response = null;
    $curlError = '';
    $statusCode = 0;
    $decoded = null;
    $maxAttempts = 2;

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 120,
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $response === null) {
            break;
        }

        $decoded = json_decode($response, true);
        $errorMessage = is_array($decoded) ? (string) ($decoded['error']['message'] ?? '') : '';

        if ($statusCode === 429 && $attempt < $maxAttempts) {
            $retrySeconds = gemini_extract_retry_seconds($errorMessage);
            sleep(min(5, $retrySeconds));
            continue;
        }

        break;
    }

    if ($response === false || $response === null) {
        return [
            'ok' => false,
            'success' => false,
            'error' => 'Gemini request failed: ' . ($curlError !== '' ? $curlError : 'unknown error'),
        ];
    }

    if (!is_array($decoded)) {
        return [
            'ok' => false,
            'success' => false,
            'error' => 'Gemini returned invalid JSON. Status: ' . $statusCode . '. Response: ' . substr($response, 0, 500),
        ];
    }

    if ($statusCode < 200 || $statusCode >= 300) {
        $errorMsg = (string) ($decoded['error']['message'] ?? 'Unknown error');
        $isQuotaError = $statusCode === 429
            || stripos($errorMsg, 'quota') !== false
            || stripos($errorMsg, 'free_tier') !== false
            || stripos($errorMsg, 'limit: 0') !== false;

        if ($statusCode === 429 && GEMINI_FALLBACK_ON_QUOTA) {
            $isFreeTierHardLimit = stripos($errorMsg, 'free_tier') !== false || stripos($errorMsg, 'limit: 0') !== false;
            if ($isFreeTierHardLimit) {
                return [
                    'ok' => true,
                    'success' => true,
                    'used_fallback' => true,
                    'warning' => 'Gemini quota temporarily unavailable. Returned fallback schedule.',
                    'data' => gemini_quota_fallback_data($studentContext),
                ];
            }
        }

        return [
            'ok' => false,
            'success' => false,
            'code' => $isQuotaError ? 'quota_exceeded' : 'api_error',
            'retry_after_seconds' => $isQuotaError ? gemini_extract_retry_seconds($errorMsg) : null,
            'error' => 'Gemini API error (status ' . $statusCode . '): ' . $errorMsg,
        ];
    }

    if (empty($decoded['candidates'][0]['content']['parts'][0]['text'])) {
        return [
            'ok' => false,
            'success' => false,
            'error' => 'Gemini returned no text content. Response structure: ' . json_encode($decoded, JSON_UNESCAPED_SLASHES),
        ];
    }

    $text = (string) $decoded['candidates'][0]['content']['parts'][0]['text'];
    $analysis = json_decode($text, true);
    if (!is_array($analysis)) {
        return [
            'ok' => false,
            'success' => false,
            'error' => 'Gemini response was not valid JSON: ' . substr($text, 0, 300),
        ];
    }

    return [
        'ok' => true,
        'success' => true,
        'data' => $analysis,
    ];
}
