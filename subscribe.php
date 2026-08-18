<?php
declare(strict_types=1);

header('Content-Type: application/json');

require __DIR__ . '/config.php';

// The CSV lives in the data/ folder beside this file. A .htaccess file in
// that folder denies direct web access on Apache/LiteSpeed hosts, so the
// data can never be fetched via a URL like yoursite.com/data/subscribers.csv.
$csvPath = __DIR__ . '/data/subscribers.csv';

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
    fputcsv($fp, ['email', 'submitted_at'], ',', '"', '"');
    fclose($fp);
}

// --- Parse the JSON body ---
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}

// --- Honeypot: a hidden field real visitors never fill in, bots often do ---
if (!empty($input['website'])) {
    echo json_encode(['success' => true]); // pretend success, drop silently
    exit;
}

// --- Validate email ---
$email = strtolower(trim((string)($input['email'] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Please provide a valid email address.']);
    exit;
}

// --- Rate limit: max one new sign-up per visitor every 60s ---
if (rate_limited('subscribe', 60)) {
    http_response_code(429);
    echo json_encode(['error' => 'Please wait a moment before subscribing again.']);
    exit;
}

// --- Skip duplicates (also doubles as light anti-spam) ---
$existing = [];
if (($handle = fopen($csvPath, 'r')) !== false) {
    fgetcsv($handle, null, ',', '"', '"'); // skip header row
    while (($row = fgetcsv($handle, null, ',', '"', '"')) !== false) {
        if (isset($row[0])) {
            $existing[] = strtolower($row[0]);
        }
    }
    fclose($handle);
}

$added = false;
if (!in_array($email, $existing, true)) {
    if (!csv_append_row($csvPath, [sanitizeForCsv($email), date('c')])) {
        http_response_code(500);
        echo json_encode(['error' => 'Could not save your email. Please try again.']);
        exit;
    }
    $added = true;
}

if ($added) {
    // 1. Confirmation back to the subscriber.
    $confirmParts = [
        'title'    => "You're on the list",
        'subtitle' => 'Welcome to the Between Sujood &amp; Strategy circle',
        'content'  => '<p style="margin:0 0 14px;">Salaam ' . e_html($email) . ',</p>'
            . '<p style="margin:0 0 14px;">Thank you for joining the mailing list. New event dates, excerpts and news about the book land in your inbox first &mdash; sent occasionally, never spam.</p>'
            . '<p style="margin:0 0 14px;">You will be the first to hear about launches, live conversations and everything happening between sujood and strategy.</p>'
            . '<p style="margin:0 0 14px;">JazakAllah khair for being part of the story.</p>'
            . '<p style="margin:0;">Warm salaams,<br>Rahmah Aderinoye</p>',
        'note' => 'You joined the mailing list from the Between Sujood &amp; Strategy website.',
    ];
    send_email(
        $email,
        "You're on the list — Between Sujood &amp; Strategy",
        email_brand($confirmParts),
        email_plain_build($confirmParts)
    );

    // 2. Alert to the site owner so the list can be tracked/exported.
    $notifyParts = [
        'title'    => 'New subscriber',
        'subtitle' => 'Someone new just joined the mailing list',
        'details'  => [
            'Email' => $email,
            'Time'  => date('Y-m-d H:i:s'),
        ],
        'content'  => '<p style="margin:0;">Open the admin panel on the site to view, search and export the full subscriber list.</p>',
        'note'     => 'Automatic alert from the book website.',
    ];
    send_email(
        SITE_ADMIN_EMAIL,
        'New subscriber — Between Sujood &amp; Strategy',
        email_brand($notifyParts),
        email_plain_build($notifyParts)
    );
}

echo json_encode(['success' => true]);

// Stops a value like "=cmd|calc" from being read as a spreadsheet formula
// if the CSV is later opened in Excel/Sheets (formula/CSV injection guard).
function sanitizeForCsv(string $value): string
{
    return preg_match('/^[=+\-@]/', $value) ? "'" . $value : $value;
}
