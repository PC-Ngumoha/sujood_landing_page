<?php
declare(strict_types=1);

header('Content-Type: application/json');

$reviewCsvPath = __DIR__ . '/../data/reviews.csv';

if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

// --- Ensure data directory and CSV file exist ---
$datadir = dirname($reviewCsvPath);
if (!is_dir($datadir)) {
    mkdir($datadir, 0755, true);
}
if (!file_exists($reviewCsvPath)) {
    file_put_contents($reviewCsvPath, '');
    $fp = fopen($reviewCsvPath, 'w'); // Open the file
    fputcsv($fp, ['rating', 'name', 'review', 'submitted_at']);
    fclose($fp); // close the file
}

// --- Parsing the JSON received ---
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}


// --- Validate input and save to CSV file ---
$rating = (int)($input['rating'] ?? 0);
$name = trim((string)($input['name'] ?? ''));
$review = trim((string)($input['review'] ?? ''));

if ($rating < 1 || $rating > 5) {
    http_response_code(400);
    echo json_encode(['error' => 'Rating must be between 1 and 5']);
    exit;
}

if (empty($name)) {
    http_response_code(400);
    echo json_encode(['error' => 'Name is required']);
    exit;
}

if (empty($review)) {
    http_response_code(400);
    echo json_encode(['error' => 'Review is required']);
    exit;
}

// --- Save to CSV ---
$fp = fopen($reviewCsvPath, 'a');
if ($fp === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save review']);
    exit;
}

fputcsv($fp, [$rating, $name, $review, date('c')]);
fclose($fp);

http_response_code(200);
echo json_encode(['success' => 'Review saved successfully']);
exit;
