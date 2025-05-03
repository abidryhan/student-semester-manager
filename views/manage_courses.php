<?php
// ssm/views/manage_courses.php
require_once '../config.php';
require_once '../models/course_model.php';

// --- Security Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$error_message = '';
$success_message = ''; // Although we redirect on success, useful if staying on page

// --- Determine Action and Course ID ---
$action = $_GET['action'] ?? 'add'; // Default to 'add'
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : null;

// --- Handle DELETE Action ---
if ($action === 'delete' && $course_id) {
    // Optional: Add another check here to ensure the admin owns this course before deleting
    $course_to_delete = getCourseById($conn, $course_id);
    if ($course_to_delete && $course_to_delete['managed_by'] === $admin_id) {
         if (deleteCourse($conn, $course_id)) {
            header("Location: admin_dashboard.php?status=deleted");
            exit();
        } else {
            // Redirect back with error if delete fails
             header("Location: admin_dashboard.php?status=delete_error");
             exit();
        }
    } else {
         // Course not found or not managed by this admin
        header("Location: admin_dashboard.php?status=not_found");
        exit();
    }
}

// --- Define valid options for dropdowns (Matches ENUMs in DB) ---
$valid_days_options = ['Thursday/Saturday', 'Sunday/Tuesday', 'Monday/Wednesday'];
$valid_time_slots = ['8:00-9:20', '9:30-10:50', '11:00-12:20', '12:30-1:50', '2:00-3:20', '3:30-4:50', '5:00-6:20'];

// --- Initialize Form Variables ---
$course_name = '';
$semester = '';
$section = '';
$days_option = ''; // Initialize new field
$time_slot = '';   // Initialize new field
$page_title = 'Add New Course';
$form_action = 'manage_courses.php?action=add'; // Default form action
$submit_button_text = 'Add Course';

// --- Handle EDIT Action (Fetch data for prefilling form) ---
if ($action === 'edit' && $course_id) {
    $course = getCourseById($conn, $course_id);

    // Security: Check if course exists and belongs to the logged-in admin
    if ($course && $course['managed_by'] === $admin_id) {
        $course_name = $course['course_name'];
        $semester = $course['semester'];
        $section = $course['section'];
        $days_option = $course['days_option']; // Fetch existing days
        $time_slot = $course['time_slot'];     // Fetch existing time slot
        $page_title = 'Edit Course';
        $form_action = 'manage_courses.php?action=edit&course_id=' . $course_id; // Update form action for edit
        $submit_button_text = 'Update Course';
    } else {
        // Course not found or doesn't belong to this admin, redirect with error
         header("Location: admin_dashboard.php?status=not_found");
         exit();
    }
}

// --- Handle Form Submission (POST for ADD/EDIT) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve and sanitize/validate form data
    $course_name = trim($_POST['course_name'] ?? '');
    $semester = trim($_POST['semester'] ?? '');
    $section = trim($_POST['section'] ?? '');
    $days_option_input = trim($_POST['days_option'] ?? ''); // Get input
    $time_slot_input = trim($_POST['time_slot'] ?? '');     // Get input

    // Basic Validation
    if (empty($course_name) || empty($semester) || empty($section)) {
        $error_message = "All fields (Course Name, Semester, Section) are required.";
    // Validation for new fields - ensure they are not empty and are valid
    } elseif (empty($days_option_input) || !in_array($days_option_input, $valid_days_options)) {
        $error_message = "Please select a valid day combination.";
    } elseif (empty($time_slot_input) || !in_array($time_slot_input, $valid_time_slots)) {
        $error_message = "Please select a valid time slot.";
    } else {
        // Assign validated inputs (no need to check for NULL anymore)
        $days_option = $days_option_input;
        $time_slot = $time_slot_input;

        $success = false;
        if ($action === 'add') {
            $success = addCourse($conn, $course_name, $semester, $section, $admin_id, $days_option, $time_slot);
        } elseif ($action === 'edit' && $course_id) {
            $success = updateCourse($conn, $course_id, $course_name, $semester, $section, $days_option, $time_slot);
        }

        if ($success) {
            $status = ($action === 'add') ? 'added' : 'updated';
            header("Location: admin_dashboard.php?status=" . $status);
            exit();
        } else {
            $error_message = "Failed to " . ($action === 'add' ? 'add' : 'update') . " course. Please try again.";
            // Form repopulates with error message below
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - SSM</title>
    <script src="https://cdn.tailwindcss.com"></script>
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">

    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <h1 class="text-2xl font-bold text-center text-gray-800 mb-6"><?php echo htmlspecialchars($page_title); ?></h1>

        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>

         <form action="<?php echo htmlspecialchars($form_action); ?>" method="POST">
            <div class="mb-4">
                <label for="course_name" class="block text-gray-700 text-sm font-bold mb-2">Course Name:</label>
                <input type="text" id="course_name" name="course_name" required
                       value="<?php echo htmlspecialchars($course_name); ?>"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                       placeholder="e.g., Introduction to Programming">
            </div>

            <div class="mb-4">
                <label for="semester" class="block text-gray-700 text-sm font-bold mb-2">Semester:</label>
                <input type="text" id="semester" name="semester" required
                       value="<?php echo htmlspecialchars($semester); ?>"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                       placeholder="e.g., Fall 2025">
            </div>

            <div class="mb-6">
                <label for="section" class="block text-gray-700 text-sm font-bold mb-2">Section:</label>
                <input type="text" id="section" name="section" required
                       value="<?php echo htmlspecialchars($section); ?>"
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                       placeholder="e.g., A">
            </div>

            <!-- NEW: Days Option Dropdown -->
            <div class="mb-4">
                <label for="days_option" class="block text-gray-700 text-sm font-bold mb-2">Days:</label>
                <select id="days_option" name="days_option"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500 focus:ring-1 focus:ring-blue-500 ssm-select" >
                    <option value="" <?php echo empty($days_option) ? 'selected' : ''; ?>>Select Day Combination</option>
                    <?php foreach ($valid_days_options as $option): ?>
                        <option value="<?php echo htmlspecialchars($option); ?>"
                                <?php echo ($days_option === $option) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- NEW: Time Slot Dropdown -->
            <div class="mb-6">
                <label for="time_slot" class="block text-gray-700 text-sm font-bold mb-2">Time Slot:</label>
                <select id="time_slot" name="time_slot"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500 focus:ring-1 focus:ring-blue-500 ssm-select">
                    <option value="" <?php echo empty($time_slot) ? 'selected' : ''; ?>>Select Time Slot</option>
                    <?php foreach ($valid_time_slots as $slot): ?>
                        <option value="<?php echo htmlspecialchars($slot); ?>"
                                <?php echo ($time_slot === $slot) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($slot); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex items-center justify-between">
                <button type="submit"
                        class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-150 ease-in-out">
                    <i class="fas <?php echo ($action === 'edit') ? 'fa-save' : 'fa-plus'; ?> mr-1"></i> <?php echo htmlspecialchars($submit_button_text); ?>
                </button>
            </div>
        </form>

        <div class="text-center mt-6">
            <a href="admin_dashboard.php" class="inline-block align-baseline font-bold text-sm text-blue-500 hover:text-blue-800 hover:underline">
                &larr; Back to Dashboard
            </a>
        </div>

    </div>

</body>
</html>
