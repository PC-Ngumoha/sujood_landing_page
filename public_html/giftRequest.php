<?php
declare(strict_types=1);

header('Content-Type: application/json');

$requestCsvFile = __DIR__ . '/../data/requests.csv';

if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

// --- Ensure data directory and CSV file exist ---
$datadir = dirname($requestCsvFile);
if (!is_dir($datadir)) {
    mkdir($datadir, 0755, true);
}
if (!file_exists($requestCsvFile)) {
    file_put_contents($requestCsvFile, '');
    $fp = fopen($requestCsvFile, 'w'); // Open the file
    fputcsv($fp, ['sender_name', 'sender_email', 'recipient_name', 'recipient_email', 'gift_message', 'submitted_at']);
    fclose($fp); // close the file
}

// --- Parsing the JSON received ---
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}

// --- Validate required fields ---
$required_fields = ['sender_name', 'sender_email'];
$errors = [];

foreach ($required_fields as $field) {
    if (empty($input[$field])) {
        $errors[] = "Missing required field: $field";
    }
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['error' => 'Validation failed.', 'details' => $errors]);
    exit;
}

// --- Validate email formats ---
if (!filter_var($input['sender_email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid sender email format.']);
    exit;
}

if (!empty($input['recipient_email'])) {
    if (!filter_var($input['recipient_email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid recipient email format.']);
    exit;
}
}

// --- Prepare data for CSV ---
$csv_data = [
    $input['sender_name'],
    $input['sender_email'],
    $input['recipient_name'],
    $input['recipient_email'],
    $input['gift_message'],
    date('c')
];

// --- Write to CSV file ---
$fp = fopen($requestCsvFile, 'a');
if ($fp) {
    fputcsv($fp, $csv_data);
    fclose($fp);
    
    http_response_code(201);
    echo json_encode(['success' => true, 'message' => 'Gift request submitted successfully.']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to write to file.']);
}
exit;

