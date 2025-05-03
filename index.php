<?php
// ssm/index.php

// Include the configuration file to potentially start session and define BASE_URL.
// The database connection $conn is also established but not used on this page.
require_once 'config.php';

// --- TEMPORARY DATABASE TEST (REMOVED) ---
// The code block previously here for testing DB connection has been removed.
// --- END OF TEMPORARY DATABASE TEST ---

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - Student Semester Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <link rel="stylesheet" href="public/ssm-theme.css" />
</head>
<body>
    <header class="ssm-header">
        <div class="ssm-logo" aria-label="SSM Logo" role="img">SSM <span class="sr-only">Student Semester Manager Logo</span></div>
        <div class="ssm-title">Student Semester Manager</div>
    </header>
    <main class="flex items-center justify-center min-h-[60vh]">
        <div class="ssm-card w-full max-w-md text-center">
            <h1 class="text-2xl font-extrabold text-gray-800 mb-3 tracking-tight">Welcome to SSM!</h1>
            <p class="text-gray-600 mb-4">Your central hub for managing courses, grades, and tasks efficiently.</p>
            <p class="text-gray-500 mb-8 text-sm">SSM Version 0.1 &mdash; Elevating your academic journey.</p>
            <a href="views/login.php" class="ssm-btn-primary w-full block mb-2">Proceed to Login</a>
        </div>
    </main>
    <footer class="ssm-footer">
        &copy; <?php echo date("Y"); ?> SSM Project. All rights reserved.
    </footer>
</body>
</html>