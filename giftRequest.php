<?php
declare(strict_types=1);

header('Content-Type: application/json');

require __DIR__ . '/config.php';

$requestCsvFile = __DIR__ . '/data/requests.csv';

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
    fputcsv($fp, ['sender_name', 'sender_email', 'recipient_name', 'recipient_email', 'gift_message', 'submitted_at'], ',', '"', '"');
    fclose($fp); // close the file
}

// --- Parsing the JSON received ---
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}

// --- Honeypot: a hidden field real visitors never fill in, bots often do ---
if (!empty($input['website'])) {
    http_response_code(201);
    echo json_encode(['success' => true, 'message' => 'Gift request submitted successfully.']);
    exit;
}

// --- Rate limit: max one gift request per visitor every 120s ---
if (rate_limited('gift', 120)) {
    http_response_code(429);
    echo json_encode(['error' => 'Please wait a moment before submitting another request.']);
    exit;
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
if (csv_append_row($requestCsvFile, $csv_data)) {

    // --- Build a readable summary of the request ---
    $isSurprise = empty($input['recipient_name']) && empty($input['recipient_email']);
    $recipientValue = $isSurprise
        ? 'Surprise a stranger (recipient chosen for them)'
        : trim((string)($input['recipient_name'] ?? ''))
            . (trim((string)($input['recipient_email'] ?? '')) !== '' ? ' — ' . trim((string)$input['recipient_email']) : '');
    $giftMessage = trim((string)($input['gift_message'] ?? ''));

    // 1. Confirmation back to the person who sent the gift.
    $confirmParts = [
        'title'    => 'Gift It Forward — request received',
        'subtitle' => 'Thank you for gifting the book forward',
        'details'  => [
            'Recipient' => $recipientValue,
            'Your note' => $giftMessage !== '' ? $giftMessage : '—',
        ],
        'content'  => '<p style="margin:0 0 14px;">Salaam ' . e_html((string)$input['sender_name']) . ',</p>'
            . '<p style="margin:0 0 14px;">We have received your gift request and a real person is now preparing it. If the recipient&rsquo;s details are needed to complete the send, we may be in touch by email.</p>'
            . '<p style="margin:0 0 14px;">A gift shared in intention is a gift multiplied &mdash; may Allah accept it from you.</p>'
            . '<p style="margin:0;">Warm salaams,<br>Rahmah Aderinoye &amp; the Between Sujood team</p>',
        'note' => 'You submitted this via the Gift It Forward form on the book website.',
    ];
    send_email(
        (string)$input['sender_email'],
        'Gift It Forward — request received',
        email_brand($confirmParts),
        email_plain_build($confirmParts)
    );

    // 2. Alert to the site owner with the full details so it can be fulfilled.
    $notifyParts = [
        'title'    => 'New Gift It Forward request',
        'subtitle' => 'Someone just asked to gift the book',
        'details'  => [
            'From'      => (string)$input['sender_name'] . ' <' . (string)$input['sender_email'] . '>',
            'Recipient' => $recipientValue,
            'Message'   => $giftMessage !== '' ? $giftMessage : '—',
            'Time'      => date('Y-m-d H:i:s'),
        ],
        'content'  => '<p style="margin:0;">Open the admin panel on the site to view and export the full list of gift requests.</p>',
        'note'     => 'Automatic alert from the book website.',
    ];
    send_email(
        SITE_ADMIN_EMAIL,
        'New Gift It Forward request',
        email_brand($notifyParts),
        email_plain_build($notifyParts)
    );

    http_response_code(201);
    echo json_encode(['success' => true, 'message' => 'Gift request submitted successfully.']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to write to file.']);
}
exit;

