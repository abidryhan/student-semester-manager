<?php
// ssm/views/login.php
// UPDATED for Phase 6: Uses UserModel and password_verify via authenticateUser

require_once '../config.php';
require_once '../models/user_model.php'; // Now includes UserModel class

// Redirect if user is already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: student_dashboard.php");
    }
    exit();
}


$userModel = new UserModel($conn); // Instantiate the model
$error_message = '';
$success_message = ''; // For signup success message

// Check for signup success message
if (isset($_GET['signup']) && $_GET['signup'] === 'success') {
    $success_message = "Signup successful! Please log in.";
}

// --- Handle Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['email']) && isset($_POST['password'])) {
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        if (empty($email) || empty($password)) {
            $error_message = "Please enter both email and password.";
        } else {
            // *** Use the new authenticateUser method ***
            $user_data = $userModel->authenticateUser($email, $password);

            if ($user_data) {
                // Authentication successful!
                $_SESSION['user_id'] = $user_data['user_id'];
                $_SESSION['role'] = $user_data['role'];
                $_SESSION['email'] = $user_data['email'];

                // Redirect based on role
                if ($user_data['role'] === 'admin') {
                    header("Location: admin_dashboard.php");
                    exit();
                } elseif ($user_data['role'] === 'student') {
                    header("Location: student_dashboard.php");
                    exit();
                } else {
                    $error_message = "Authentication successful, but user role is undefined.";
                    session_unset();
                    session_destroy();
                }
            } else {
                // Authentication failed (invalid email or password)
                $error_message = "Invalid email or password.";
            }
        }
    } else {
        $error_message = "Form data is incomplete.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SSM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <link rel="stylesheet" href="../public/ssm-theme.css" />
</head>
<body>
    <header class="ssm-header">
        <div class="ssm-logo" aria-label="SSM Logo" role="img">SSM <span class="sr-only">Student Semester Manager Logo</span></div>
        <div class="ssm-title">Student Semester Manager</div>
    </header>
    <main class="flex flex-col items-center min-h-[60vh]">
        <div class="ssm-card w-full max-w-sm mt-4">

    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-sm">
        <h1 class="text-2xl font-bold text-center text-gray-800 mb-6">Login to SSM</h1>

        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($success_message); ?></span>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" onsubmit="return validateLoginForm();" novalidate aria-label="Login form" autocomplete="username">
             <div class="mb-4">
                <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email:</label>
                <input type="email" id="email" name="email" required aria-required="true" aria-label="Email address"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                       placeholder="Enter your email" autocomplete="username">
            </div>

            <div class="mb-6">
                <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password:</label>
                <input type="password" id="password" name="password" required aria-required="true" aria-label="Password"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                       placeholder="Enter your password" autocomplete="current-password">
            </div>

            <div class="flex items-center justify-between">
                <button type="submit"
                        class="ssm-btn-primary w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-150 ease-in-out"
                        aria-label="Login to Student Semester Manager">
                    Login
                </button>
            </div>
        </form>

        <div class="text-center mt-6">
            <p class="text-sm text-gray-600">
                Don't have an account?
                <a href="signup.php" class="font-bold text-green-500 hover:text-green-700 hover:underline">
                    Sign Up
                </a>
            </p>
        </div>
         <div class="text-center mt-2">
            <a href="../index.php" class="text-sm text-gray-500 hover:text-gray-700 hover:underline">
                 &larr; Back to Home
             </a>
         </div>

    </div>

    <script src="../public/scripts.js"></script>
</body>
</html>
