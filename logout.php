<?php
// ssm/logout.php

// We need to start the session in order to access and destroy it.
// Including config.php is an easy way to ensure session is started
// and potentially handle other cleanup if needed in the future.
require_once 'config.php'; // Assuming config.php is in the same root directory

// 1. Unset all session variables
$_SESSION = array();

// 2. Destroy the session cookie (optional but recommended)
// This will delete the session cookie, so the browser forgets the session ID.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, // Set expiry date in the past
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Finally, destroy the session data on the server
session_destroy();

// 4. Redirect the user to the login page
// Make sure the path is correct relative to where logout.php is located (the root ssm/ folder)
header("Location: views/login.php?status=logged_out");
exit(); // Ensure no further code is executed after redirect

?>