<?php
declare(strict_types=1);
session_start();

// --- Config: change these before deploying! ---
const ADMIN_USER = 'admin';
// Generate a hash for your real password by running this once from a terminal:
//   php -r "echo password_hash('your-password-here', PASSWORD_DEFAULT), PHP_EOL;"
// then paste the result below. The default here is the hash for "changeme123" —
// do not leave it as-is.
const ADMIN_PASS_HASH = '$2y$10$4jdd86LbffxbZFbCS8PxiOzOXyh1H54P/WbFKTALILhiKKvDgtNrK';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
    if (
        hash_equals(ADMIN_USER, $_POST['username']) &&
        password_verify($_POST['password'], ADMIN_PASS_HASH)
    ) {
        session_regenerate_id(true); // prevent session fixation
        $_SESSION['is_admin'] = true;
    } else {
        $error = 'Incorrect username or password.';
    }
}

// --- Not logged in: show the login form ---
if (empty($_SESSION['is_admin'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title>Admin login</title>
      <link rel="stylesheet" href="/admin/admin.css">
    </head>
    <body>
      <form class="login-card" method="post">
        <header>
          <h1>Admin login</h1>
        </header>
        <?php if ($error): ?>
          <p class="error"><?= htmlspecialchars($error, ENT_QUOTES) ?></p>
        <?php endif; ?>
        <div class="login-fields">
          <label>
          Username
          <input type="text" name="username" autocomplete="username" required>
        </label>
        <label>
          Password
          <input type="password" name="password" autocomplete="current-password" required>
        </label>
        <button type="submit">Log in</button>
        </div>
      </form>
    </body>
    </html>
    <?php
    exit;
}

// --- Logged in: read subscribers from subscriber CSV file ---
$subscriberCsvPath = __DIR__ . '/../../data/subscribers.csv';
$subscribers = [];
if (file_exists($subscriberCsvPath) && ($handle = fopen($subscriberCsvPath, 'r')) !== false) {
    fgetcsv($handle, null, ",", '"', ''); // skip header
    while (($row = fgetcsv($handle,  null, ",", '"', '')) !== false) {
        if (!empty($row[0])) {
            $subscribers[] = ['email' => $row[0], 'date' => $row[1] ?? ''];
        }
    }
    fclose($handle);
}
$subscribers = array_reverse($subscribers);
$subscriberCount = count($subscribers);

$reviewCsvPath = __DIR__ . '/../../data/reviews.csv';
$reviews = [];
if (file_exists($reviewCsvPath) && ($handle = fopen($reviewCsvPath, 'r')) !== false) {
  fgetcsv($handle, null, ",", '"', ''); // Skip header
  while(($row = fgetcsv($handle, null, ",", '"', '')) !== false) {
    if (!empty($row[0])) {
      
      $reviews[] = ['rating' => $row[0], 'name' => $row[1], 'review' => $row[2], 'date' => $row[3]];
    }
  }
  fclose($handle);
}
$reviews = array_reverse($reviews);
$reviewCount = count($reviews);
?>
<!-- Rendering dashboard to display all info received from csv files -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Subscribers</title>
  <link rel="stylesheet" href="/admin/admin.css">
  <style>
    
  </style>
</head>
<body>
  <main>
    <header>
      <div>
        <h1>📧 Subscribers</h1>
        <p class="count"><?= $subscriberCount ?> subscriber<?= $subscriberCount === 1 ? '' : 's' ?></p>
      </div>
      <div class="actions">
        <a href="download.php" class="download-btn">📥 Download CSV</a>
        <a href="logout.php" class="logout-link">🚪 Log out</a>
      </div>
    </header>
    <table>
      <thead><tr><th>#</th><th>Email</th><th>Submitted</th></tr></thead>
      <tbody>
        <?php if ($subscriberCount === 0): ?>
          <tr><td colspan="3" class="empty">No subscribers yet.</td></tr>
        <?php else: foreach ($subscribers as $i => $s): ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td><?= htmlspecialchars($s['email'], ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($s['date'], ENT_QUOTES) ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </main>
  <hr />
  <main>
    <header>
      <div>
        <h1>💬 Reviews</h1>
        <p class="count"><?= $reviewCount ?> review<?= $reviewCount === 1 ? '' : 's' ?></p>
      </div>
      <!-- <div class="actions">
        <a href="download.php" class="download-btn">📥 Download CSV</a>
        <a href="logout.php" class="logout-link">🚪 Log out</a>
      </div> -->
    </header>
    <table>
      <thead><tr><th>#</th><th>Rating</th><th>Full Name</th><th colspan="2">Review</th><th>Submitted</th></tr></thead>
      <tbody>
        <?php if ($reviewCount === 0): ?>
          <tr><td colspan="5" class="empty">No reviews yet.</td></tr>
        <?php else: foreach ($reviews as $i => $r): ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td><?= htmlspecialchars($r['rating'], ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($r['name'], ENT_QUOTES) ?></td>
            <td colspan="2"><?= htmlspecialchars($r['review'], ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($r['date'], ENT_QUOTES) ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </main>
</body>
</html>
