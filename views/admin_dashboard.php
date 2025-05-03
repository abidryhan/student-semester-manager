<?php
// ssm/views/admin_dashboard.php
require_once '../config.php'; // Connects to DB, starts session
require_once '../models/course_model.php'; // We need course functions

// --- Security Check ---
// Ensure user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php"); // Redirect to login if not authorized
    exit();
}

$admin_id = $_SESSION['user_id']; // Get the logged-in admin's ID
$feedback_message = ''; // For displaying messages after actions (e.g., delete)
$error_message = ''; // For displaying error messages

// --- Handle Final Grade Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_final_grades') {
    if (isset($_POST['course_id']) && filter_var($_POST['course_id'], FILTER_VALIDATE_INT)) {
        $course_id = (int)$_POST['course_id'];

        // Optional: Verify admin manages this course
        $course_details = getCourseById($conn, $course_id);
        if (!$course_details || $course_details['managed_by'] !== $admin_id) {
            $error_message = "Error: Course not found or you don't have permission.";
        } else {
            // Check if grades already submitted
            if (checkIfFinalGradesSubmittedForCourse($conn, $course_id)) {
                $error_message = "Error: Final grades have already been submitted for this course.";
            } else {
                $student_ids = getStudentIdsByCourse($conn, $course_id);
                if (empty($student_ids)) {
                    $error_message = "No students enrolled in this course to submit grades for.";
                } else {
                    $conn->begin_transaction(); // Start transaction
                    $all_success = true;
                    foreach ($student_ids as $student_id) {
                        $percentage = calculateFinalCoursePercentage($conn, $student_id, $course_id);
                        if ($percentage === null) {
                            // Handle error case - maybe skip student or fail transaction
                            error_log("Error calculating percentage for student $student_id in course $course_id.");
                            // For now, let's treat it as 0% -> F grade
                             $percentage = 0.0;
                             // Alternatively, you could fail the whole process:
                             // $all_success = false;
                             // $error_message = "Error calculating grade for one or more students.";
                             // break;
                         }

                        $final_grade_data = calculateFinalGrade($percentage);
                        $letter_grade = $final_grade_data['letter'];
                        $gpa = $final_grade_data['gpa'];

                        if (!updateFinalGradeForStudent($conn, $student_id, $course_id, $letter_grade, $gpa)) {
                            error_log("Failed to update final grade for student $student_id in course $course_id.");
                            $all_success = false;
                            $error_message = "Error submitting grades. Please try again.";
                            break; // Exit loop on first failure
                        }
                    }

                    if ($all_success) {
                        $conn->commit(); // Commit transaction
                        // Store feedback in session for display after redirect
                        $_SESSION['feedback_message'] = "Final grades submitted successfully for " . htmlspecialchars($course_details['course_name']) . ".";
                        header("Location: admin_dashboard.php"); // Redirect to avoid resubmission
                        exit();
                    } else {
                        $conn->rollback(); // Rollback transaction
                        // Error message already set
                    }
                }
            }
        }
    } else {
        $error_message = "Invalid request.";
    }
}

// --- Handle Feedback/Error Messages (from redirect or current process) ---
if (isset($_SESSION['feedback_message'])) {
    $feedback_message = $_SESSION['feedback_message'];
    unset($_SESSION['feedback_message']); // Clear message after displaying
}
// Continue with existing feedback/error handling from GET params...
if (isset($_GET['status'])) {
    switch ($_GET['status']) {
        // Success messages
        case 'deleted':
            $feedback_message = "Course deleted successfully.";
            break;
        case 'saved':
            $feedback_message = "Course saved successfully.";
            break;
        case 'task_deleted': // Added for task feedback consistency
             $feedback_message = "Task deleted successfully.";
             break;
        case 'task_saved': // Added for task feedback consistency
             $feedback_message = "Task saved successfully.";
             break;
         case 'weight_deleted': // Placeholder for future weight feedback
             $feedback_message = "Weight category deleted successfully.";
             break;
         case 'weight_saved': // Placeholder for future weight feedback
             $feedback_message = "Weight category saved successfully.";
             break;

        // Error messages
        case 'delete_error':
             $error_message = "Error deleting course. It might have dependencies or a database error occurred.";
            break;
        case 'save_error':
             $error_message = "Error saving course. Please check the details and try again.";
            break;
        case 'task_delete_error': // Added for task feedback consistency
             $error_message = "Error deleting task.";
             break;
        case 'task_save_error': // Added for task feedback consistency
             $error_message = "Error saving task.";
             break;
         case 'weight_delete_error': // Placeholder
             $error_message = "Error deleting weight category.";
             break;
         case 'weight_save_error': // Placeholder
             $error_message = "Error saving weight category.";
             break;
        case 'not_found':
             $error_message = "Error: The requested resource was not found or you don't have permission to manage it.";
            break;
         case 'invalid_course':
             $error_message = "Error: Invalid course specified.";
             break;
         // Add other specific error statuses as needed
    }
}

// --- Fetch Courses Managed by this Admin ---
$courses = getCoursesByAdmin($conn, $admin_id); // Function from course_model.php

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - SSM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <link rel="stylesheet" href="../public/ssm-theme.css" />
    <style>
        /* Ensure table layout is fixed to prevent content overflow issues */
        .table-fixed { table-layout: fixed; }
    </style>
</head>
<body>
    <header class="ssm-header">
        <div class="ssm-logo" aria-label="SSM Logo" role="img">SSM <span class="sr-only">Student Semester Manager Logo</span></div>
        <div class="ssm-title">Student Semester Manager</div>
    </header>
    <main class="flex flex-col items-center min-h-[60vh]">
        <div class="ssm-card w-full max-w-4xl mt-4">
            <!-- Dashboard content starts here -->

    <div class="container mx-auto p-6">
        <div class="bg-white p-8 rounded-lg shadow-md">

            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
                <h1 class="text-2xl md:text-3xl font-bold text-gray-800 mb-2 sm:mb-0">Admin Dashboard</h1>
                 <a href="../logout.php" class="bg-red-500 hover:bg-red-600 text-white font-semibold py-2 px-4 rounded-lg transition duration-300 ease-in-out text-sm self-start sm:self-center">
                    <i class="fas fa-sign-out-alt mr-1"></i> Logout
                 </a>
            </div>

             <p class="text-gray-600 mb-6">Welcome, Admin! (<?php echo htmlspecialchars($_SESSION['email'] ?? 'Admin User'); ?>)</p>

            <?php if (!empty($feedback_message)): ?>
                <div class="mb-4 p-4 rounded bg-green-100 border border-green-400 text-green-700" role="alert">
                    <?php echo htmlspecialchars($feedback_message); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="mb-4 p-4 rounded bg-red-100 border border-red-400 text-red-700" role="alert">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <div class="mb-4">
                <a href="manage_courses.php?action=add"
                   class="inline-block bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg transition duration-300 ease-in-out">
                   <i class="fas fa-plus mr-1"></i> Add New Course
                </a>
            </div>

            <h2 class="text-2xl font-semibold text-gray-700 mb-4">Your Courses</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white rounded-lg shadow overflow-x-auto" aria-label="Your Courses Table">
                    <caption class="sr-only">List of managed courses</caption>
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="w-1/3 px-4 py-2 border border-gray-300 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Course Name</th>
                            <th class="w-1/4 px-4 py-2 border border-gray-300 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Semester</th>
                            <th class="px-4 py-2 border border-gray-300 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Section</th>
                            <th class="px-4 py-2 border border-gray-300 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Days</th>
                            <th class="px-4 py-2 border border-gray-300 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Time Slot</th>
                            <th class="px-4 py-2 border border-gray-300 text-left text-sm font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700">
                        <?php if (!empty($courses)): ?>
                            <?php foreach ($courses as $course): ?>
                                <?php
                                    // Check if final grades are already submitted
                                    $grades_submitted = checkIfFinalGradesSubmittedForCourse($conn, $course['course_id']);
                                    // If not submitted, check for ungraded tasks (only if needed for the button)
                                    $ungraded_count = 0;
                                    if (!$grades_submitted) {
                                        $ungraded_count = checkTotalUngradedTasksForCourse($conn, $course['course_id']);
                                        if ($ungraded_count < 0) { // Handle potential error from the function
                                             error_log("Error checking ungraded tasks for course ID: " . $course['course_id']);
                                             $ungraded_count = 0; // Default to 0 to avoid blocking UI unnecessarily on error
                                         }
                                    }
                                ?>
                                <tr class="hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500" tabindex="0">
                                    <td class="border border-gray-300 px-4 py-2 break-words"><?php echo htmlspecialchars($course['course_name']); ?></td>
                                    <td class="border border-gray-300 px-4 py-2 break-words"><?php echo htmlspecialchars($course['semester']); ?></td>
                                    <td class="border border-gray-300 px-4 py-2 break-words"><?php echo htmlspecialchars($course['section']); ?></td>
                                    <td class="border border-gray-300 px-4 py-2 break-words"><?php echo htmlspecialchars($course['days_option'] ?? 'N/A'); ?></td>
                                    <td class="border border-gray-300 px-4 py-2 break-words"><?php echo htmlspecialchars($course['time_slot'] ?? 'N/A'); ?></td>
                                    <td class="border border-gray-300 px-4 py-2">
                                        <div class="flex flex-wrap gap-2 justify-center sm:justify-start">
                                            <a href="manage_courses.php?action=edit&course_id=<?php echo $course['course_id']; ?>"
                                               class="inline-block bg-yellow-500 hover:bg-yellow-600 text-white font-semibold py-1 px-3 rounded-md text-xs transition duration-150 ease-in-out" title="Edit Course">
                                               <i class="fas fa-pencil-alt"></i> <span class="hidden lg:inline">Edit</span>
                                            </a>
                                            <a href="manage_courses.php?action=delete&course_id=<?php echo $course['course_id']; ?>"
                                               class="inline-block bg-red-500 hover:bg-red-600 text-white font-semibold py-1 px-3 rounded-md text-xs transition duration-150 ease-in-out" title="Delete Course"
                                               onclick="return confirm('Are you sure you want to delete this course?\n\nWARNING: This will also delete all related weights, tasks, grades, and notifications.\nThis action cannot be undone.');">
                                               <i class="fas fa-trash-alt"></i> <span class="hidden lg:inline">Delete</span>
                                            </a>
                                            <a href="manage_weights.php?course_id=<?php echo $course['course_id']; ?>"
                                               class="inline-block bg-indigo-500 hover:bg-indigo-600 text-white font-semibold py-1 px-3 rounded-md text-xs transition duration-150 ease-in-out" title="Manage Weights">
                                               <i class="fas fa-balance-scale"></i> <span class="hidden lg:inline">Weights</span>
                                            </a>
                                            <a href="manage_tasks.php?course_id=<?php echo $course['course_id']; ?>"
                                               class="inline-block bg-green-500 hover:bg-green-600 text-white font-semibold py-1 px-3 rounded-md text-xs transition duration-150 ease-in-out" title="Manage Tasks">
                                               <i class="fas fa-tasks"></i> <span class="hidden lg:inline">Tasks</span>
                                            </a>

                                            <!-- Submit Final Grades Button/Status -->
                                            <?php if ($grades_submitted): ?>
                                                <span class="inline-block bg-gray-400 text-white font-semibold py-1 px-3 rounded-md text-xs cursor-not-allowed" title="Final grades have been submitted">
                                                    <i class="fas fa-check-circle"></i> <span class="hidden lg:inline">Grades Submitted</span>
                                                </span>
                                            <?php else: ?>
                                                <form method="POST" action="admin_dashboard.php" class="inline-block submit-final-grades-form"
                                                      data-course-id="<?php echo $course['course_id']; ?>"
                                                      data-course-name="<?php echo htmlspecialchars($course['course_name']); ?>"
                                                      data-ungraded-count="<?php echo $ungraded_count; ?>">
                                                    <input type="hidden" name="action" value="submit_final_grades">
                                                    <input type="hidden" name="course_id" value="<?php echo $course['course_id']; ?>">
                                                    <button type="submit"
                                                            class="inline-block bg-teal-500 hover:bg-teal-600 text-white font-semibold py-1 px-3 rounded-md text-xs transition duration-150 ease-in-out submit-final-grades-btn"
                                                            title="Calculate and Submit Final Grades">
                                                        <i class="fas fa-graduation-cap"></i> <span class="hidden lg:inline">Submit Final Grades</span>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="border border-gray-300 px-4 py-4 text-center text-gray-500">You haven't added any courses yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
        </div>
    </main>
    <footer class="ssm-footer">
        &copy; <?php echo date("Y"); ?> SSM Project. All rights reserved.
    </footer>
    <script src="../public/scripts.js"></script> <!-- Ensure scripts.js is included -->
</body>
</html>
