<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['is_admin'])) {
    http_response_code(403);
    exit('Unauthorized');
}

$types = [
    'subscribers' => ['path' => __DIR__ . '/../data/subscribers.csv', 'name' => 'subscribers'],
    'reviews'     => ['path' => __DIR__ . '/../data/reviews.csv',     'name' => 'reviews'],
    'requests'    => ['path' => __DIR__ . '/../data/requests.csv',    'name' => 'gift-requests'],
    'events'      => ['path' => __DIR__ . '/../data/events.csv',      'name' => 'events'],
    'socials'     => ['path' => __DIR__ . '/../data/socials.csv',     'name' => 'socials'],
];

$type = (string)($_GET['type'] ?? 'subscribers');
if (!isset($types[$type])) {
    $type = 'subscribers';
}

$csvPath = $types[$type]['path'];
if (!file_exists($csvPath)) {
    http_response_code(404);
    exit('Nothing to export yet.');
}

$filename = $types[$type]['name'] . '-' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($csvPath));
readfile($csvPath);
exit;