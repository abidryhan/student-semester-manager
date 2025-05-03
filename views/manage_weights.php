<?php
// ssm/views/manage_weights.php
require_once '../config.php';
require_once '../models/course_model.php'; // Contains weight functions

// --- Security & Setup ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
$admin_id = $_SESSION['user_id'];
$error_message = '';
$success_message = '';

// --- Get Course ID and Validate Ownership ---
if (!isset($_GET['course_id']) || !filter_var($_GET['course_id'], FILTER_VALIDATE_INT)) {
    header("Location: admin_dashboard.php?status=invalid_course");
    exit();
}
$course_id = (int)$_GET['course_id'];
$course = getCourseById($conn, $course_id);

if (!$course || $course['managed_by'] !== $admin_id) {
    header("Location: admin_dashboard.php?status=not_found");
    exit();
}

// --- Handle Actions ---
$action = $_GET['action'] ?? 'view'; // Default action
$weight_id = isset($_GET['weight_id']) ? (int)$_GET['weight_id'] : null;

// DELETE Action
if ($action === 'delete' && $weight_id) {
    $delete_result = deleteWeight($conn, $weight_id, $course_id); // Pass course_id for check
    if ($delete_result === true) {
        header("Location: manage_weights.php?course_id=$course_id&status=weight_deleted");
        exit();
    } else {
        // deleteWeight returns an error message string on failure
        $error_message = $delete_result; // Already safe string or true
    }
    // Stay on page to show error
    $action = 'view';
}

// POST Request (Add Weight)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_weight'])) {
    $container_type = trim($_POST['container_type'] ?? '');
    // *** UPDATED: Get percentage from number input ***
    $percentage_input = trim($_POST['percentage'] ?? '');
    $percentage = null; // Default to null

    // Basic Validation
    if (empty($container_type)) {
        $error_message = "Category Name is required.";
    } elseif ($percentage_input === '') {
        $error_message = "Percentage is required.";
    } elseif (!is_numeric($percentage_input)) {
        $error_message = "Percentage must be a number.";
    } else {
        $percentage_float = floatval($percentage_input); // Convert to float for comparison
        // Validate range (e.g., must be > 0 and <= 100)
        if ($percentage_float <= 0 || $percentage_float > 100) {
             $error_message = "Percentage must be between 0 (exclusive) and 100 (inclusive).";
        } else {
            $percentage = $percentage_float; // Use the validated float value

             // *** ADDED: Check if total percentage exceeds 100 BEFORE adding ***
             $current_weights = getWeightsByCourse($conn, $course_id);
             $current_total = 0;
             foreach ($current_weights as $w) {
                 $current_total += $w['percentage'];
             }

             if (($current_total + $percentage) > 100.01) { // Use a small tolerance for floating point math
                 $error_message = "Cannot add weight: Total percentage would exceed 100% (Current Total: {$current_total}%).";
             } else {
                 // Proceed with adding
                 // Note: addWeight expects int, consider if float percentage needed in DB/model later
                 if (addWeight($conn, $course_id, $container_type, (int)round($percentage))) { // Round to int for now
                     header("Location: manage_weights.php?course_id=$course_id&status=weight_saved");
                     exit();
                 } else {
                     $error_message = "Error saving weight category. This category name might already exist or another database error occurred.";
                 }
             }
             // *** END ADDED CHECK ***
        }
    }
    // If errors, script continues to display form with error message
}

// --- Prepare Data for Display ---
$weights = getWeightsByCourse($conn, $course_id); // Fetch weights for the table

// Handle feedback messages from redirects
if (isset($_GET['status'])) {
     switch ($_GET['status']) {
        case 'weight_deleted': $success_message = "Weight category deleted successfully."; break;
        case 'weight_saved': $success_message = "Weight category saved successfully."; break;
     }
}

$page_title = "Manage Weights for " . htmlspecialchars($course['course_name']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - SSM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../public/ssm-theme.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        /* Improve number input appearance */
        input[type=number]::-webkit-inner-spin-button,
        input[type=number]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        input[type=number] {
            -moz-appearance: textfield;
        }
     </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <header class="ssm-header">
        <div class="ssm-logo">SSM</div>
        <div class="ssm-title">Student Semester Manager</div>
    </header>

    <main class="container mx-auto p-4 md:p-6">
        <div class="ssm-card max-w-3xl mx-auto !p-6 md:!p-8">
            <h1 class="text-2xl font-bold text-gray-800 mb-6"><?php echo $page_title; ?></h1>

            <?php if (!empty($error_message)): ?> <div class="mb-4 p-3 rounded bg-red-100 border border-red-400 text-red-700 text-sm" role="alert"><?php echo htmlspecialchars($error_message); ?></div> <?php endif; ?>
            <?php if (!empty($success_message)): ?> <div class="mb-4 p-3 rounded bg-green-100 border border-green-400 text-green-700 text-sm" role="alert"><?php echo htmlspecialchars($success_message); ?></div> <?php endif; ?>

            <div class="mb-8 p-6 border rounded-lg bg-gray-50 shadow-sm">
                <h2 class="text-xl font-semibold mb-4 text-gray-700">Add New Weight Category</h2>
                <form action="manage_weights.php?course_id=<?php echo $course_id; ?>" method="POST" class="space-y-4">
                    <input type="hidden" name="add_weight" value="1">
                    <div>
                        <label for="container_type" class="block text-gray-700 text-sm font-bold mb-2">Category Name:</label>
                        <input type="text" id="container_type" name="container_type" required maxlength="50" class="shadow-sm appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="e.g., Quizzes, Midterm Exam">
                    </div>
                    <div>
                         <label for="percentage" class="block text-gray-700 text-sm font-bold mb-2">Percentage (%):</label>
                         <input type="number" id="percentage" name="percentage" required min="0.01" max="100" step="any" class="shadow-sm appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="e.g., 15 or 12.5">
                         </div>
                     <div>
                         <button type="submit" class="ssm-btn-primary !bg-blue-600 hover:!bg-blue-700 !py-2 !px-5 !rounded-md text-sm"><i class="fas fa-plus mr-1"></i> Add Weight Category</button>
                     </div>
                </form>
            </div>

            <h2 class="text-xl font-semibold text-gray-700 mb-4">Existing Weight Categories</h2>
             <div class="overflow-x-auto shadow-md rounded-lg">
                <table class="min-w-full bg-white border border-gray-200">
                     <thead class="bg-gray-100"><tr><th class="px-4 py-3 border-b border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider w-2/5">Category Name</th> <th class="px-4 py-3 border-b border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider w-1/5">Percentage</th> <th class="px-4 py-3 border-b border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider w-2/5">Actions</th> </tr></thead>
                    <tbody class="text-gray-700 text-sm">
                         <?php if (!empty($weights)): ?>
                            <?php $total_percentage = 0; foreach ($weights as $weight) { $total_percentage += $weight['percentage']; ?>
                                 <tr class="hover:bg-gray-50"><td class="border-b border-gray-200 px-4 py-2"><?php echo htmlspecialchars($weight['container_type']); ?></td> <td class="border-b border-gray-200 px-4 py-2"><?php echo htmlspecialchars(number_format($weight['percentage'], 2)); // Show decimals ?>%</td> <td class="border-b border-gray-200 px-4 py-2"><a href="manage_weights.php?course_id=<?php echo $course_id; ?>&action=delete&weight_id=<?php echo $weight['weight_id']; ?>" class="inline-block bg-red-500 hover:bg-red-600 text-white font-semibold py-1 px-2 rounded-md text-xs transition duration-150" title="Delete Weight" onclick="return confirm('Are you sure? Deleting might fail if tasks are assigned.');"><i class="fas fa-trash-alt"></i> Delete</a></td></tr>
                            <?php } ?>
                             <tr class="bg-gray-100 font-semibold text-sm"><td class="border-b border-gray-200 px-4 py-2 text-right font-bold">Total Allocated:</td><td class="border-b border-gray-200 px-4 py-2 font-bold <?php echo ($total_percentage > 100) ? 'text-red-600' : (($total_percentage == 100) ? 'text-green-600' : ''); ?>"><?php echo number_format($total_percentage, 2); ?>%</td><td class="border-b border-gray-200 px-4 py-2"><?php if ($total_percentage > 100): ?><span class="text-red-600 text-xs italic">Exceeds 100%! Adjust weights.</span><?php elseif ($total_percentage < 100 && $total_percentage > 0): ?><span class="text-yellow-600 text-xs italic">Does not sum to 100%.</span><?php elseif ($total_percentage == 0 && count($weights) > 0): ?><span class="text-yellow-600 text-xs italic">Total is 0%.</span><?php endif; ?></td></tr>
                        <?php else: ?><tr><td colspan="3" class="border-b border-gray-200 px-4 py-3 text-center text-gray-500 italic">No weight categories defined yet.</td></tr><?php endif; ?>
                    </tbody></table></div>

             <div class="mt-8 pt-4 border-t">
                 <a href="admin_dashboard.php" class="text-blue-600 hover:underline text-sm">&larr; Back to Dashboard</a>
             </div>
        </div> </main>
     <footer class="ssm-footer"> &copy; <?php echo date("Y"); ?> SSM Project. All rights reserved. </footer>
</body>
</html>
