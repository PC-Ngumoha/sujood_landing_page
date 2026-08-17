<?php
declare(strict_types=1);

header('Content-Type: application/json');

// The CSV lives OUTSIDE public_html (one directory up), so it's never
// reachable by a direct URL like yoursite.com/data/subscribers.csv —
// unlike files inside public_html, this path simply isn't served by the
// web server at all. Adjust this path if your host's folder layout differs.
$csvPath = __DIR__ . '/../data/subscribers.csv';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

// --- Ensure the data directory & CSV (with header) exist ---
$dataDir = dirname($csvPath);
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}
if (!file_exists($csvPath)) {
    file_put_contents($csvPath, '');
    $fp = fopen($csvPath, 'a');
    fputcsv($fp, ['email', 'submitted_at']);
    fclose($fp);
}

// --- Parse the JSON body ---
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}

// // --- Honeypot: a hidden field real visitors never fill in, bots often do ---
// if (!empty($input['website'])) {
//     echo json_encode(['success' => true]); // pretend success, drop silently
//     exit;
// }

// --- Validate email ---
$email = strtolower(trim((string)($input['email'] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Please provide a valid email address.']);
    exit;
}

// --- Skip duplicates (also doubles as light anti-spam) ---
$existing = [];
if (($handle = fopen($csvPath, 'r')) !== false) {
    fgetcsv($handle); // skip header row
    while (($row = fgetcsv($handle)) !== false) {
        if (isset($row[0])) {
            $existing[] = strtolower($row[0]);
        }
    }
    fclose($handle);
}

if (!in_array($email, $existing, true)) {
    $fp = fopen($csvPath, 'a');
    if ($fp === false || !flock($fp, LOCK_EX)) {
        http_response_code(500);
        echo json_encode(['error' => 'Could not save your email. Please try again.']);
        exit;
    }
    fputcsv($fp, [sanitizeForCsv($email), date('c')]);
    flock($fp, LOCK_UN);
    fclose($fp);
}

echo json_encode(['success' => true]);

// Stops a value like "=cmd|calc" from being read as a spreadsheet formula
// if the CSV is later opened in Excel/Sheets (formula/CSV injection guard).
function sanitizeForCsv(string $value): string
{
    return preg_match('/^[=+\-@]/', $value) ? "'" . $value : $value;
}
