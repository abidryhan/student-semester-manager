<?php
// ssm/views/signup.php
require_once '../config.php';
require_once '../models/user_model.php';

// Redirect if user is already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: student_dashboard.php");
    }
    exit();
}

$userModel = new UserModel($conn);
$errors = []; // Array to hold validation errors
$email_value = ''; // To repopulate form on error
$role_value = 'student'; // Default role

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? ''; // Don't trim password
    $role = $_POST['role'] ?? 'student'; // Default to student if not set

    $email_value = $email; // Keep email for repopulation
    $role_value = $role;   // Keep role for repopulation

    // --- Server-Side Validation ---
    // 1. Email Validation
    if (empty($email)) {
        $errors['email'] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format.";
    } elseif ($userModel->checkEmailExists($email)) {
        $errors['email'] = "Email address already exists.";
    }

    // 2. Password Validation
    if (empty($password)) {
        $errors['password'] = "Password is required.";
    } elseif (strlen($password) < 8) {
        $errors['password'] = "Password must be at least 8 characters long.";
    }
    // Add password confirmation check here if desired

    // 3. Role Validation
    if ($role !== 'admin' && $role !== 'student') {
        $errors['role'] = "Invalid role selected.";
    }

    // --- Process Signup if No Errors ---
    if (empty($errors)) {
        if ($userModel->createUser($email, $password, $role)) {
            // Signup successful, redirect to login page
            header("Location: login.php?signup=success");
            exit();
        } else {
            // Database error during creation
            $errors['general'] = "Registration failed due to a server error. Please try again later.";
        }
    }
    // If errors exist, the script continues and displays the form with errors
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - SSM</title>
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

    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-6">Create Your SSM Account</h2>

        <?php if (isset($errors['general'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($errors['general']); ?></span>
            </div>
        <?php endif; ?>

        <form action="signup.php" method="POST" onsubmit="return validateSignupForm();" novalidate aria-label="Signup form" autocomplete="on">
            <div class="mb-4">
                <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email:</label>
                <input type="email" id="email" name="email" required aria-required="true" aria-label="Email address"
                       value="<?php echo htmlspecialchars($email_value); ?>"
                       class="shadow appearance-none border <?php echo isset($errors['email']) ? 'border-red-500' : 'border-gray-300'; ?> rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                       placeholder="Enter your email" autocomplete="username">
                <?php if (isset($errors['email'])): ?>
                    <p class="text-red-500 text-xs italic mt-1"><?php echo htmlspecialchars($errors['email']); ?></p>
                <?php endif; ?>
            </div>

            <div class="mb-4">
                <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Password:</label>
                <input type="password" id="password" name="password" required aria-required="true" aria-label="Password"
                       class="shadow appearance-none border <?php echo isset($errors['password']) ? 'border-red-500' : 'border-gray-300'; ?> rounded w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                       placeholder="Minimum 8 characters" autocomplete="new-password">
                 <?php if (isset($errors['password'])): ?>
                    <p class="text-red-500 text-xs italic mt-1"><?php echo htmlspecialchars($errors['password']); ?></p>
                <?php endif; ?>
                 </div>

             <div class="mb-6">
                <label for="role" class="block text-gray-700 text-sm font-bold mb-2">Role:</label>
                 <select name="role" id="role" required aria-required="true" aria-label="Select role" class="shadow border <?php echo isset($errors['role']) ? 'border-red-500' : 'border-gray-300'; ?> rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline bg-white focus:ring-2 focus:ring-green-500">
                     <option value="student" <?php echo ($role_value == 'student') ? 'selected' : ''; ?>>Student</option>
                     <option value="admin" <?php echo ($role_value == 'admin') ? 'selected' : ''; ?>>Admin</option>
                 </select>
                 <?php if (isset($errors['role'])): ?>
                    <p class="text-red-500 text-xs italic mt-1"><?php echo htmlspecialchars($errors['role']); ?></p>
                <?php endif; ?>
            </div>


            <div class="flex items-center justify-between">
                <button type="submit"
                        class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-150 ease-in-out">
                    Sign Up
                </button>
            </div>
        </form>

        <div class="text-center mt-6">
             <p class="text-sm text-gray-600">
                Already have an account?
                <a href="login.php" class="font-bold text-blue-500 hover:text-blue-800 hover:underline">
                    Log In
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
