<?php
session_start();

// Unset all session variables related to user
$_SESSION = array();

// If session cookie exists, destroy it
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

header("Location: index.php?msg=logged_out");
exit;
?>
