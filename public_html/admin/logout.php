<?php
declare(strict_types=1);
session_start();

// 1. Wipe the PHP memory array immediately
$_SESSION = array();

// 2. Delete the physical session file from the server
session_destroy();

// 3. Force browser to delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000, // Sets cookie expiration date to the past
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// 4. Redirect the user back to the index page
header("Location: index.php");
exit;
?>
