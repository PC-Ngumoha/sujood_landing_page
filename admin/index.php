<?php
declare(strict_types=1);
session_start();

// --- Config: change these before deploying! ---
const ADMIN_USER = 'admin';
// Generate a hash for your real password by running this once from a terminal:
//   php -r "echo password_hash('your-password-here', PASSWORD_DEFAULT), PHP_EOL;"
// then paste the result below. The default here is the hash for "changeme123" —
// do not leave it as-is.
const ADMIN_PASS_HASH = '$2y$12$zrUudEDjTAqnKp2hCsSgke1tLRkwT841Nkx92yrkJ7zaVX.BXazKG';

const DATA_DIR = __DIR__ . '/../data';
const SUB_CSV = DATA_DIR . '/subscribers.csv';
const REV_CSV = DATA_DIR . '/reviews.csv';
const REQ_CSV = DATA_DIR . '/requests.csv';
const EV_CSV  = DATA_DIR . '/events.csv';
const SOC_CSV = DATA_DIR . '/socials.csv';

const PER_PAGE = 12;
const ALLOWED_VIEWS = ['overview', 'subscribers', 'reviews', 'requests', 'events', 'socials'];

// ---------- helpers ----------

function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function csvHeaderFor(string $type): array {
    return match ($type) {
        'subscribers' => ['email', 'submitted_at'],
        'reviews'     => ['rating', 'name', 'review', 'submitted_at'],
        'requests'    => ['sender_name', 'sender_email', 'recipient_name', 'recipient_email', 'gift_message', 'submitted_at'],
        'events'      => ['name', 'day', 'month', 'location', 'time', 'tag'],
        'socials'     => ['platform', 'url'],
        default       => [],
    };
}

function csvPathFor(string $type): string {
    return match ($type) {
        'subscribers' => SUB_CSV,
        'reviews'     => REV_CSV,
        'requests'    => REQ_CSV,
        'events'      => EV_CSV,
        'socials'     => SOC_CSV,
        default       => '',
    };
}

/** Reads a CSV into rows; each row keeps its original 0-based line index (minus header) so deletes stay accurate. */
function readRows(string $csvPath, array $keys): array {
    $out = [];
    if (!file_exists($csvPath) || !($h = fopen($csvPath, 'r'))) {
        return $out;
    }
    $line = -1;
    fgetcsv($h, null, ',', '"', '"');
    while (($r = fgetcsv($h, null, ',', '"', '"')) !== false) {
        $line++;
        if (empty($r[0])) {
            continue;
        }
        $row = ['__line' => $line];
        foreach ($keys as $i => $k) {
            $row[$k] = $r[$i] ?? '';
        }
        $out[] = $row;
    }
    fclose($h);
    return $out;
}

/** Rewrites a CSV file with the given rows. */
function writeRows(string $csvPath, array $keys, array $rows): bool {
    $fp = fopen($csvPath, 'w');
    if ($fp === false) {
        return false;
    }
    if (flock($fp, LOCK_EX)) {
        fputcsv($fp, $keys, ',', '"', '"');
        foreach ($rows as $row) {
            $vals = [];
            foreach ($keys as $k) {
                $vals[] = $row[$k] ?? '';
            }
            fputcsv($fp, $vals, ',', '"', '"');
        }
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    return true;
}

function parseDateRough(?string $raw): ?DateTimeImmutable {
    if (empty($raw)) {
        return null;
    }
    try {
        return new DateTimeImmutable($raw);
    } catch (Exception) {
        return null;
    }
}

const MONTHS = ['Jan' => 1, 'Feb' => 2, 'Mar' => 3, 'Apr' => 4, 'May' => 5, 'Jun' => 6, 'Jul' => 7, 'Aug' => 8, 'Sep' => 9, 'Oct' => 10, 'Nov' => 11, 'Dec' => 12];

function sortEvents(array $rows): array {
    usort($rows, function ($a, $b) {
        $am = MONTHS[$a['month']] ?? 99;
        $bm = MONTHS[$b['month']] ?? 99;
        if ($am === $bm) {
            $ad = is_numeric($a['day']) ? (int)$a['day'] : 99;
            $bd = is_numeric($b['day']) ? (int)$b['day'] : 99;
            return $ad <=> $bd;
        }
        return $am <=> $bm;
    });
    return $rows;
}

function fmtDate(?string $raw): string {
    $d = parseDateRough($raw);
    return $d ? $d->format('M j, Y · g:i a') : '—';
}

function icon(string $name, int $size = 16): string {
    $icons = [
        'overview'    => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'subscribers' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 6L22 7"/>',
        'reviews'     => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'requests'    => '<path d="M21 12v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-8"/><path d="m3 7 4-4 17 4v2a2 2 0 0 1-2 2h-.5"/><path d="M7 7h4v4H7z" opacity=".55"/>',
        'events'      => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'socials'     => '<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/>',
        'mail'        => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 6L22 7"/>',
        'chat'        => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'gift'        => '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13"/><path d="M12 8s-1 0-5.5-3.5H12S13 8 12 8Zm0 0s1 0 5.5-3.5H12S11 8 12 8Z"/>',
        'calendar'    => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'link'        => '<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/>',
        'logout'      => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
        'download'    => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/>',
        'plus'        => '<path d="M12 5v14M5 12h14"/>',
        'trash'       => '<path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M10 11v6M14 11v6"/>',
        'pencil'      => '<path d="M17 3a2.8 2.8 0 0 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>',
        'x'           => '<path d="M18 6 6 18M6 6l12 12"/>',
        'alert'       => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/>',
        'check'       => '<path d="M20 6 9 17l-5-5"/>',
        'chev-l'      => '<path d="m15 18-6-6 6-6"/>',
        'chev-r'      => '<path d="m9 18 6-6-6-6"/>',
    ];
    $body = $icons[$name] ?? $icons['check'];
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $body . '</svg>';
}

function navLink(string $target, string $iconName, string $label, int $count, string $current): void {
    $cls = 'class="nav-link' . ($current === $target ? ' active' : '') . '"';
    $qs = $target === 'overview' ? 'index.php' : 'index.php?view=' . $target;
    echo "<a href=\"$qs\" $cls><span class=\"nav-emoji\">" . icon($iconName, 16) . "</span><span class=\"nav-label\">$label</span><span class=\"nav-count\">$count</span></a>";
}

// ---------- login gate ----------

$loginError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
    if (
        hash_equals(ADMIN_USER, (string)$_POST['username']) &&
        password_verify((string)$_POST['password'], ADMIN_PASS_HASH)
    ) {
        session_regenerate_id(true);
        $_SESSION['is_admin'] = true;
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    } else {
        $loginError = 'Incorrect username or password.';
    }
}

if (empty($_SESSION['is_admin'])) {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin login</title>
  <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
  <div class="login-wrap">
    <form class="login-card" method="post">
      <div class="login-head">
        <h1>Admin login</h1>
        <p>Between Sujood &amp; Strategy</p>
      </div>
      <div class="login-body">
        <?php if ($loginError): ?>
          <p class="login-error"><?= e($loginError) ?></p>
        <?php endif; ?>
        <label for="username">Username</label>
        <input id="username" type="text" name="username" autocomplete="username" required>
        <label for="password">Password</label>
        <input id="password" type="password" name="password" autocomplete="current-password" required>
        <button type="submit">Log in</button>
      </div>
    </form>
  </div>
</body>
</html>
    <?php
    exit;
}

// ---------- logged in: CSRF + actions ----------

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

$flash = $_SESSION['flash'] ?? '';
$_SESSION['flash'] = '';

// types that may be added via the admin UI (socials are edit-only)
$addSpecs = [
    'reviews' => ['rating', 'name', 'review'],
    'events'  => ['name', 'day', 'month', 'location', 'time', 'tag'],
];
// types that may be edited via the modal dialog
$editSpecs = [
    'events'  => ['name', 'day', 'month', 'location', 'time', 'tag'],
    'socials' => ['platform', 'url'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Invalid session token. Go back and try again.');
    }

    $action = (string)$_POST['action'];
    $type   = in_array((string)($_POST['type'] ?? ''), ['subscribers', 'reviews', 'requests', 'events', 'socials'], true) ? (string)$_POST['type'] : '';
    $path   = csvPathFor($type);
    $keys   = csvHeaderFor($type);

    if ($action === 'add' && isset($addSpecs[$type]) && $path !== '') {
        $row = [];
        foreach ($keys as $k) {
            $row[$k] = trim((string)($_POST['field_' . $k] ?? ''));
        }

        $ok = true;
        if ($type === 'reviews') {
            $row['rating'] = (string)(int)$row['rating'];
            $ok = (int)$row['rating'] >= 1 && (int)$row['rating'] <= 5 && $row['name'] !== '' && $row['review'] !== '';
            $row['submitted_at'] = date('c');
        } elseif ($type === 'events') {
            $ok = $row['name'] !== '' && $row['day'] !== '' && $row['month'] !== '';
        }

        if ($ok) {
            $final = [];
            foreach ($keys as $k) {
                $final[$k] = $row[$k] ?? '';
            }
            $rows = readRows($path, $keys);
            $rows[] = array_merge($final, ['__line' => -1]);
            writeRows($path, $keys, $rows);
            $_SESSION['flash'] = 'Entry added.';
        } else {
            $_SESSION['flash'] = 'Could not add — please fill in the required fields.';
        }
    }

    if ($action === 'edit' && isset($editSpecs[$type]) && $path !== '') {
        $line   = (int)($_POST['line'] ?? -1);
        $rows   = readRows($path, $keys);
        $found  = false;
        foreach ($rows as &$row) {
            if ($row['__line'] === $line) {
                foreach ($editSpecs[$type] as $k) {
                    $row[$k] = trim((string)($_POST['field_' . $k] ?? ''));
                }
                $found = true;
                break;
            }
        }
        unset($row);
        if ($found && writeRows($path, $keys, $rows)) {
            $_SESSION['flash'] = 'Changes saved.';
        } else {
            $_SESSION['flash'] = 'Could not save changes.';
        }
    }

    if ($action === 'delete' && $type !== '' && $path !== '') {
        $target = (int)($_POST['line'] ?? -1);
        $rows = readRows($path, $keys);
        $rows = array_values(array_filter($rows, fn($r) => $r['__line'] !== $target));
        writeRows($path, $keys, $rows);
        $_SESSION['flash'] = 'Entry deleted.';
    }

    if ($action === 'clear' && $type !== '' && $path !== '') {
        writeRows($path, $keys, []);
        $_SESSION['flash'] = ucfirst($type) . ' cleared — all entries removed.';
    }

    $qs = http_build_query(array_filter([
        'view'   => $_POST['return_view'] ?? 'overview',
        'q'      => trim((string)($_POST['return_q'] ?? '')),
        'rating' => ($_POST['return_rating'] ?? '') !== '' ? (string)$_POST['return_rating'] : '',
    ]));
    header('Location: index.php' . ($qs !== '' ? '?' . $qs : ''));
    exit;
}

// ---------- gather data ----------

$subRows = readRows(SUB_CSV, csvHeaderFor('subscribers'));
$revRows = readRows(REV_CSV, csvHeaderFor('reviews'));
$reqRows = readRows(REQ_CSV, csvHeaderFor('requests'));
$evRows  = readRows(EV_CSV, csvHeaderFor('events'));
$socRows = readRows(SOC_CSV, csvHeaderFor('socials'));

$subCount = count($subRows);
$revCount = count($revRows);
$reqCount = count($reqRows);
$evCount  = count($evRows);
$socCount = count($socRows);

$now = new DateTimeImmutable();
$todayStart = $now->setTime(0, 0);
$weekStart = $now->modify('monday this week')->setTime(0, 0);

$subToday = count(array_filter($subRows, fn($r) => ($d = parseDateRough($r['submitted_at'])) && $d >= $todayStart));
$subWeek  = count(array_filter($subRows, fn($r) => ($d = parseDateRough($r['submitted_at'])) && $d >= $weekStart));
$reqToday = count(array_filter($reqRows, fn($r) => ($d = parseDateRough($r['submitted_at'])) && $d >= $todayStart));
$reqWeek  = count(array_filter($reqRows, fn($r) => ($d = parseDateRough($r['submitted_at'])) && $d >= $weekStart));
$revToday = count(array_filter($revRows, fn($r) => ($d = parseDateRough($r['submitted_at'])) && $d >= $todayStart));
$revWeek  = count(array_filter($revRows, fn($r) => ($d = parseDateRough($r['submitted_at'])) && $d >= $weekStart));
$avgRating = $revCount > 0
    ? round(array_sum(array_map(fn($r) => (float)$r['rating'], $revRows)) / $revCount, 1)
    : 0.0;

// ---------- view + search/filter/pagination ----------

$view = $_GET['view'] ?? 'overview';
if (!in_array($view, ALLOWED_VIEWS, true)) {
    $view = 'overview';
}

$q      = trim((string)($_GET['q'] ?? ''));
$rating = (string)($_GET['rating'] ?? '');
if ($rating !== '' && !preg_match('/^[1-5]$/', $rating)) {
    $rating = '';
}
$page = max(1, (int)($_GET['page'] ?? 1));

$qsBase = 'view=' . $view . ($q !== '' ? '&q=' . urlencode($q) : '') . ($rating !== '' ? '&rating=' . $rating : '');

$items = [];
switch ($view) {
    case 'subscribers':
        $items = $subRows;
        if ($q !== '') {
            $items = array_values(array_filter($items, fn($r) => stripos($r['email'], $q) !== false));
        }
        break;

    case 'reviews':
        $items = $revRows;
        if ($q !== '') {
            $items = array_values(array_filter($items, fn($r) => stripos($r['name'], $q) !== false || stripos($r['review'], $q) !== false));
        }
        if ($rating !== '') {
            $items = array_values(array_filter($items, fn($r) => (string)(int)$r['rating'] === $rating));
        }
        $items = array_reverse($items);
        break;

    case 'requests':
        $items = $reqRows;
        if ($q !== '') {
            $items = array_values(array_filter(
                $items,
                fn($r) => stripos($r['sender_name'], $q) !== false
                    || stripos($r['sender_email'], $q) !== false
                    || stripos($r['recipient_name'], $q) !== false
                    || stripos($r['recipient_email'], $q) !== false
            ));
        }
        $items = array_reverse($items);
        break;

    case 'events':
        $items = $evRows;
        if ($q !== '') {
            $items = array_values(array_filter($items, fn($r) => stripos($r['name'], $q) !== false || stripos($r['location'], $q) !== false));
        }
        $items = sortEvents($items);
        break;

    case 'socials':
        $items = $socRows;
        if ($q !== '') {
            $items = array_values(array_filter($items, fn($r) => stripos($r['platform'], $q) !== false || stripos($r['url'], $q) !== false));
        }
        break;
}

$total = count($items);
$pages = max(1, (int)ceil($total / PER_PAGE));
$page  = min($page, $pages);
$pageItems = array_slice($items, ($page - 1) * PER_PAGE, PER_PAGE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin — Between Sujood &amp; Strategy</title>
  <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="admin-shell">

  <aside class="sidebar">
    <div class="sidebar-brand">
      <div class="brand-title">Between Sujood<br>&amp; Strategy</div>
      <div class="brand-sub">Content admin</div>
    </div>
    <nav class="sidebar-nav">
      <?php navLink('overview', 'overview', 'Overview', $subCount + $revCount + $reqCount, $view); ?>
      <?php navLink('subscribers', 'subscribers', 'Subscribers', $subCount, $view); ?>
      <?php navLink('reviews', 'reviews', 'Reviews', $revCount, $view); ?>
      <?php navLink('requests', 'requests', 'Gift requests', $reqCount, $view); ?>
      <?php navLink('events', 'events', 'Events', $evCount, $view); ?>
      <?php navLink('socials', 'socials', 'Social links', $socCount, $view); ?>
    </nav>
    <div class="sidebar-foot">
      <a class="view-site-link" href="../index.php" target="_blank" rel="noopener"><?= icon('link', 17) ?> <span>View public site</span></a>
      <a class="logout-link js-logout" href="logout.php"><?= icon('logout', 17) ?> <span>Log out</span></a>
    </div>
  </aside>

  <main class="content">

    <?php if ($flash): ?>
      <div class="flash"><?= icon('check', 15) ?> <?= e($flash) ?></div>
    <?php endif; ?>

    <?php if ($view === 'overview'): ?>

      <!-- ===== OVERVIEW ===== -->
      <div class="page-head">
        <div>
          <h1>Overview</h1>
          <p class="page-desc">What's coming in from the public site.</p>
        </div>
        <span class="tag-pill">Updated <?= $now->format('M j, Y · g:i a') ?></span>
      </div>

      <div class="stats-grid">
        <a class="stat-card" href="index.php?view=subscribers">
          <span class="stat-label">Subscribers</span>
          <span class="stat-value"><?= $subCount ?></span>
          <span class="stat-sub"><span class="pill"><?= $subToday ?> today</span><span class="pill"><?= $subWeek ?> this week</span></span>
        </a>
        <a class="stat-card green" href="index.php?view=reviews">
          <span class="stat-label">Reviews</span>
          <span class="stat-value"><?= $revCount ?></span>
          <span class="stat-sub"><span class="pill">Avg <?= number_format($avgRating, 1) ?> / 5</span><span class="pill"><?= $revToday ?> today</span></span>
        </a>
        <a class="stat-card gold" href="index.php?view=requests">
          <span class="stat-label">Gift requests</span>
          <span class="stat-value"><?= $reqCount ?></span>
          <span class="stat-sub"><span class="pill"><?= $reqToday ?> today</span><span class="pill"><?= $reqWeek ?> this week</span></span>
        </a>
        <a class="stat-card coral" href="index.php?view=events">
          <span class="stat-label">Events</span>
          <span class="stat-value"><?= $evCount ?></span>
          <span class="stat-sub"><span class="pill">Managed in CSV</span></span>
        </a>
        <a class="stat-card coral" href="index.php?view=socials">
          <span class="stat-label">Social links</span>
          <span class="stat-value"><?= $socCount ?></span>
          <span class="stat-sub"><span class="pill">Edit-only</span></span>
        </a>
        <div class="stat-card">
          <span class="stat-label">Total entries</span>
          <span class="stat-value"><?= $subCount + $revCount + $reqCount + $evCount + $socCount ?></span>
          <span class="stat-sub">
            <span class="pill"><?= icon('mail', 12) ?> <?= $subCount ?></span>
            <span class="pill"><?= icon('chat', 12) ?> <?= $revCount ?></span>
            <span class="pill"><?= icon('gift', 12) ?> <?= $reqCount ?></span>
            <span class="pill"><?= icon('calendar', 12) ?> <?= $evCount ?></span>
            <span class="pill"><?= icon('link', 12) ?> <?= $socCount ?></span>
          </span>
        </div>
      </div>

      <section class="panel">
        <div class="panel-head">
          <span class="panel-title">Recent subscribers</span>
          <a class="btn btn-ghost" href="index.php?view=subscribers">View all →</a>
        </div>
        <table class="recent-table">
          <thead><tr><th>Email</th><th>Submitted</th></tr></thead>
          <tbody>
            <?php if ($subCount === 0): ?>
              <tr><td colspan="2" class="empty">No subscribers yet.</td></tr>
            <?php else: foreach (array_slice($subRows, 0, 5) as $s): ?>
              <tr><td><?= e($s['email']) ?></td><td><?= e(fmtDate($s['submitted_at'])) ?></td></tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </section>

      <section class="panel">
        <div class="panel-head">
          <span class="panel-title">Recent reviews</span>
          <a class="btn btn-ghost" href="index.php?view=reviews">View all →</a>
        </div>
        <table class="recent-table">
          <thead><tr><th>Rating</th><th>Reviewer</th><th class="review-text">Review</th><th>Submitted</th></tr></thead>
          <tbody>
            <?php if ($revCount === 0): ?>
              <tr><td colspan="4" class="empty">No reviews yet.</td></tr>
            <?php else: foreach (array_slice(array_reverse($revRows), 0, 5) as $r): ?>
              <?php $rate = (int)$r['rating']; ?>
              <tr>
                <td><span class="rating-badge rating-<?= $rate ?>"><?= icon('star', 12) ?> <?= $rate ?></span></td>
                <td><?= e($r['name']) ?></td>
                <td class="review-text"><?= e($r['review']) ?></td>
                <td><?= e(fmtDate($r['submitted_at'])) ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </section>

      <section class="panel">
        <div class="panel-head">
          <span class="panel-title">Upcoming events</span>
          <a class="btn btn-ghost" href="index.php?view=events">Manage →</a>
        </div>
        <table class="recent-table">
          <thead><tr><th>Event</th><th>Date</th><th>Location</th><th>Tag</th></tr></thead>
          <tbody>
            <?php if ($evCount === 0): ?>
              <tr><td colspan="4" class="empty">No events yet.</td></tr>
            <?php else: foreach (sortEvents($evRows) as $ev): ?>
              <tr>
                <td><?= e($ev['name']) ?></td>
                <td><?= e($ev['day']) ?> <?= e($ev['month']) ?></td>
                <td><?= e($ev['location']) ?> <?= $ev['time'] !== '' ? '· ' . e($ev['time']) : '' ?></td>
                <td><span class="tag-pill"><?= e($ev['tag']) ?></span></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </section>

      <section class="panel">
        <div class="panel-head">
          <span class="panel-title">Social links</span>
          <a class="btn btn-ghost" href="index.php?view=socials">Manage →</a>
        </div>
        <table class="recent-table">
          <thead><tr><th>Platform</th><th>URL</th></tr></thead>
          <tbody>
            <?php if ($socCount === 0): ?>
              <tr><td colspan="2" class="empty">No social links yet.</td></tr>
            <?php else: foreach ($socRows as $sc): ?>
              <tr><td><?= e($sc['platform']) ?></td><td><?= e($sc['url']) ?></td></tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </section>

    <?php else: ?>

      <?php
        $title = match ($view) {
            'subscribers' => 'Subscribers',
            'reviews'     => 'Reviews',
            'requests'    => 'Gift requests',
            'events'      => 'Events',
            'socials'     => 'Social links',
            default       => '',
        };
      ?>
      <div class="page-head">
        <div>
          <h1><?= $title ?></h1>
          <p class="page-desc"><?= $total ?> record<?= $total === 1 ? '' : 's' ?> found</p>
        </div>
      </div>

      <?php if (isset($addSpecs[$view])): ?>
        <!-- ===== ADD FORM ===== -->
        <section class="panel">
          <div class="panel-head">
            <span class="panel-title">Add <?= strtolower($title) ?></span>
          </div>
          <form class="add-form" method="post" action="index.php">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="type" value="<?= e($view) ?>">
            <input type="hidden" name="return_view" value="<?= e($view) ?>">

            <?php if ($view === 'reviews'): ?>
              <select name="field_rating" required>
                <option value="">Rating…</option>
                <?php for ($i = 5; $i >= 1; $i--): ?>
                  <option value="<?= $i ?>">★ <?= $i ?></option>
                <?php endfor; ?>
              </select>
              <input type="text" name="field_name" class="af-input" placeholder="Reviewer name" required>
              <textarea name="field_review" class="af-input af-grow" placeholder="Review text" rows="2" required></textarea>
            <?php elseif ($view === 'events'): ?>
              <input type="text" name="field_name" class="af-input af-grow" placeholder="Event name" required>
              <input type="text" name="field_day" class="af-input af-day" placeholder="Day" required>
              <select name="field_month" required>
                <option value="">Month…</option>
                <?php foreach (array_keys(MONTHS) as $m): ?>
                  <option value="<?= $m ?>"><?= $m ?></option>
                <?php endforeach; ?>
              </select>
              <input type="text" name="field_location" class="af-input af-grow" placeholder="Location" required>
              <input type="text" name="field_time" class="af-input" placeholder="Time" >
              <select name="field_tag" required>
                <option value="">Tag…</option>
                <option>Online</option>
                <option>In person</option>
                <option>Hybrid</option>
              </select>
            <?php endif; ?>

            <button class="btn btn-primary" type="submit"><?= icon('plus', 15) ?> Add</button>
          </form>
        </section>
      <?php endif; ?>

      <div class="panel">
        <div class="toolbar">
          <form method="get" action="index.php" class="filter-form">
            <input type="hidden" name="view" value="<?= e($view) ?>">
            <div class="search-box">
              <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search…">
            </div>
            <?php if ($view === 'reviews'): ?>
              <select name="rating">
                <option value="">All ratings</option>
                <?php for ($i = 5; $i >= 1; $i--): ?>
                  <option value="<?= $i ?>" <?= $rating === (string)$i ? 'selected' : '' ?>>★ <?= $i ?> star<?= $i === 1 ? '' : 's' ?></option>
                <?php endfor; ?>
              </select>
            <?php endif; ?>
            <button class="btn btn-soft-wine" type="submit">Filter</button>
            <?php if ($q !== '' || $rating !== ''): ?>
              <a class="btn btn-ghost" href="index.php?view=<?= e($view) ?>">Clear</a>
            <?php endif; ?>
          </form>
          <a class="btn btn-primary" href="download.php?type=<?= e($view) ?>"><?= icon('download', 14) ?> Export CSV</a>
        </div>

        <table class="recent-table">
          <thead>
            <?php if ($view === 'subscribers'): ?>
              <tr><th>#</th><th>Email</th><th>Submitted</th><th></th></tr>
            <?php elseif ($view === 'reviews'): ?>
              <tr><th>#</th><th>Rating</th><th>Reviewer</th><th class="review-text">Review</th><th>Submitted</th><th></th></tr>
            <?php elseif ($view === 'requests'): ?>
              <tr><th>#</th><th>Type</th><th>From</th><th>To</th><th class="review-text">Message</th><th>Submitted</th><th></th></tr>
            <?php elseif ($view === 'events'): ?>
              <tr><th>#</th><th>Event</th><th>Date</th><th>Location</th><th>Time</th><th>Tag</th><th></th></tr>
            <?php else: ?>
              <tr><th>#</th><th>Platform</th><th>URL</th><th></th></tr>
            <?php endif; ?>
          </thead>
          <tbody>
            <?php if ($total === 0): ?>
              <tr><td colspan="10" class="empty">No records match.</td></tr>
            <?php else: foreach ($pageItems as $i => $r): ?>
              <?php $realIdx = ($page - 1) * PER_PAGE + $i + 1; ?>
              <tr>
                <td><?= $realIdx ?></td>
                <?php if ($view === 'subscribers'): ?>
                  <td><?= e($r['email']) ?></td>
                  <td><?= e(fmtDate($r['submitted_at'])) ?></td>
                <?php elseif ($view === 'reviews'): ?>
                  <?php $rate = (int)$r['rating']; ?>
                  <td><span class="rating-badge rating-<?= $rate ?>"><?= icon('star', 12) ?> <?= $rate ?></span></td>
                  <td><?= e($r['name']) ?></td>
                  <td class="review-text"><?= e($r['review']) ?></td>
                  <td><?= e(fmtDate($r['submitted_at'])) ?></td>
                <?php elseif ($view === 'requests'): ?>
                  <?php $isSurpriseGift = $r['recipient_name'] === '' && $r['recipient_email'] === ''; ?>
                  <td><?= $isSurpriseGift
                      ? '<span class="surprise-badge">' . icon('gift', 13) . ' Surprise a stranger</span>'
                      : '<span class="tag-pill type-known">Gift someone I know</span>' ?></td>
                  <td><?= e($r['sender_name']) ?> <span class="tag-pill"><?= e($r['sender_email']) ?></span></td>
                  <td><?= $isSurpriseGift
                      ? '<span class="surprise-badge">' . icon('gift', 13) . ' Surprise a stranger</span>'
                      : e($r['recipient_name']) . ($r['recipient_email'] !== '' ? ' <span class="tag-pill">' . e($r['recipient_email']) . '</span>' : '') ?></td>
                  <td class="review-text"><?= $r['gift_message'] !== '' ? e($r['gift_message']) : '—' ?></td>
                  <td><?= e(fmtDate($r['submitted_at'])) ?></td>
                <?php elseif ($view === 'events'): ?>
                  <td><?= e($r['name']) ?></td>
                  <td><?= e($r['day']) ?> <?= e($r['month']) ?></td>
                  <td><?= e($r['location']) ?></td>
                  <td><?= e($r['time']) ?></td>
                  <td><span class="tag-pill"><?= e($r['tag']) ?></span></td>
                <?php else: ?>
                  <td><?= e($r['platform']) ?></td>
                  <td class="url-cell"><a href="<?= e($r['url']) ?>" target="_blank" rel="noopener"><?= e($r['url']) ?></a></td>
                <?php endif; ?>
                <td class="row-actions">
                  <?php if ($view === 'events' || $view === 'socials'): ?>
                    <button class="icon-btn js-edit" type="button" title="Edit"
                      data-csrf="<?= e($csrf) ?>"
                      data-type="<?= e($view) ?>"
                      data-line="<?= (int)$r['__line'] ?>"
                      data-return_view="<?= e($view) ?>"
                      data-return_q="<?= e($q) ?>"
                      data-return_rating="<?= e($rating) ?>"
                      <?php foreach ($editSpecs[$view] as $fk): ?>
                        data-field_<?= $fk ?>="<?= e($r[$fk]) ?>"
                      <?php endforeach; ?>
                    ><?= icon('pencil', 14) ?></button>
                  <?php endif; ?>
                  <?php if ($view !== 'socials'): ?>
                    <form class="inline-form js-confirm" method="post" action="index.php"
                          data-title="Delete entry"
                          data-ok="Delete"
                          data-message="Delete this entry? This cannot be undone.">
                      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="type" value="<?= e($view) ?>">
                      <input type="hidden" name="line" value="<?= (int)$r['__line'] ?>">
                      <input type="hidden" name="return_view" value="<?= e($view) ?>">
                      <input type="hidden" name="return_q" value="<?= e($q) ?>">
                      <input type="hidden" name="return_rating" value="<?= e($rating) ?>">
                      <button class="icon-btn danger" type="submit" title="Delete entry"><?= icon('trash', 14) ?></button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>

        <div class="pagination">
          <span>
            Showing <?= $total === 0 ? '0' : (($page - 1) * PER_PAGE + 1) ?>–<?= min($page * PER_PAGE, $total) ?> of <?= $total ?> · Page <?= $page ?> / <?= $pages ?>
          </span>
          <div class="pager-btns">
            <?php
              if ($pages > 1) {
                  $start = max(1, $page - 3);
                  $end   = min($pages, $page + 3);
                  if ($start > 1) {
                      echo "<a href=\"index.php?$qsBase&page=1\">1</a>";
                      if ($start > 2) echo '<span class="pager-gap">…</span>';
                  }
                  for ($p = $start; $p <= $end; $p++) {
                      if ($p === $page) {
                          echo "<span class=\"now\">$p</span>";
                      } else {
                          echo "<a href=\"index.php?$qsBase&page=$p\">$p</a>";
                      }
                  }
                  if ($end < $pages) {
                      if ($end < $pages - 1) echo '<span class="pager-gap">…</span>';
                      echo "<a href=\"index.php?$qsBase&page=$pages\">$pages</a>";
                  }
              }
            ?>
          </div>
          <?php if ($view !== 'socials'): ?>
            <form class="inline-form js-confirm" method="post" action="index.php"
                  data-title="Clear <?= e($title) ?>"
                  data-ok="Clear all"
                  data-message="Clear all <?= e($title) ?> entries? This cannot be undone.">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="action" value="clear">
              <input type="hidden" name="type" value="<?= e($view) ?>">
              <input type="hidden" name="return_view" value="<?= e($view) ?>">
              <button class="btn btn-danger" type="submit"><?= icon('trash', 14) ?> Clear all</button>
            </form>
          <?php endif; ?>
        </div>
      </div>

    <?php endif; ?>

  </main>
</div>

<!-- ===== MODAL ===== -->
<div id="modal-backdrop" class="modal-backdrop" hidden>
  <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="modal-title">
    <button type="button" class="modal-close" id="modal-close" aria-label="Close"><?= icon('x', 18) ?></button>
    <h3 id="modal-title">Confirm</h3>
    <p id="modal-message"></p>
    <div id="modal-form-host"></div>
    <div class="modal-actions">
      <button type="button" class="btn btn-ghost" id="modal-cancel">Cancel</button>
      <button type="button" class="btn btn-primary" id="modal-ok">Confirm</button>
    </div>
  </div>
</div>

<script src="../assets/js/admin.js"></script>
</body>
</html>