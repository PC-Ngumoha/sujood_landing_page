<?php
declare(strict_types=1);
session_start();

if (empty($_SESSION['is_admin'])) {
    http_response_code(403);
    exit('Unauthorized');
}

$csvPath = __DIR__ . '/../../data/subscribers.csv';
if (!file_exists($csvPath)) {
    http_response_code(404);
    exit('No subscribers yet.');
}

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="subscribers.csv"');
header('Content-Length: ' . filesize($csvPath));
readfile($csvPath);
