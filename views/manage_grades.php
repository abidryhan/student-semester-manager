<?php
// ssm/views/manage_grades.php
require_once '../config.php';
require_once '../models/course_model.php';

// --- Security & Setup ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
$admin_id = $_SESSION['user_id'];
$error_message = '';
$success_message = '';

// --- Get Task ID and Validate Ownership ---
if (!isset($_GET['task_id']) || !filter_var($_GET['task_id'], FILTER_VALIDATE_INT)) {
    header("Location: admin_dashboard.php?status=invalid_task"); // Or redirect to manage_tasks
    exit();
}
$task_id = (int)$_GET['task_id'];

// Fetch task details and verify admin ownership
$task = getTaskDetailsForGrading($conn, $task_id, $admin_id);

if (!$task) {
    // Task not found or admin doesn't manage the course
    header("Location: admin_dashboard.php?status=task_not_found_or_unauthorized");
    exit();
}
$course_id = $task['course_id']; // Get course_id from task details
$max_score = $task['max_score'] ?? 0; // Default to 0 if null

// --- Handle Form Submission (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verify task_id from POST matches GET to prevent tampering (optional but good)
    if (isset($_POST['task_id']) && (int)$_POST['task_id'] === $task_id) {
        $scores = $_POST['scores'] ?? [];
        $grades_saved = 0;
        $grades_failed = 0;

        foreach ($scores as $student_id => $score_input) {
            $student_id = (int)$student_id; // Sanitize student ID
            $score_value = null; // Default to null

            // Validate score: allow empty string (for NULL), must be numeric, within range
            if ($score_input !== '' && is_numeric($score_input)) {
                 $score_float = (float)$score_input;
                 // Check range using fetched max_score
                 if ($score_float >= 0 && $score_float <= $max_score) {
                     $score_value = $score_float;
                 } else {
                     $error_message .= "Invalid score entered for student ID {$student_id} (must be between 0 and {$max_score}). Score not saved. ";
                     $grades_failed++;
                     continue; // Skip saving this invalid score
                 }
            } elseif ($score_input !== '') {
                // Non-empty, but not numeric
                $error_message .= "Invalid score format for student ID {$student_id}. Score not saved. ";
                $grades_failed++;
                continue; // Skip saving this invalid score
            }
            // If $score_input was '', $score_value remains null (clears/sets grade to NULL)

            // Save valid score (or null)
            if (saveOrUpdateGrade($conn, $student_id, $task_id, $score_value)) {
                $grades_saved++;
            } else {
                $grades_failed++;
                $error_message .= "Failed to save grade for student ID {$student_id}. ";
            }
        }

        if ($grades_failed == 0 && $grades_saved > 0) {
            $success_message = "Grades saved successfully for {$grades_saved} student(s).";
        } elseif ($grades_failed > 0 && $grades_saved > 0) {
            $error_message = "{$grades_saved} grade(s) saved, but {$grades_failed} failed. " . $error_message;
        } elseif ($grades_failed > 0 && $grades_saved == 0) {
             $error_message = "Failed to save {$grades_failed} grade(s). " . $error_message;
        } else {
             $success_message = "No changes detected or submitted."; // Or perhaps no students case
        }

    } else {
        $error_message = "Task ID mismatch. Please try again.";
    }
    // Stay on the page after POST to show messages/re-edit
}

// --- Fetch Students and Existing Grades for Display ---
// Pass task_id to fetch relevant grades along with student list
$students = getStudentsByCourseAndFetchGrades($conn, $course_id, $task_id);

$page_title = "Enter Grades for: " . htmlspecialchars($task['task_name']);
$course = getCourseById($conn, $course_id); // Get course name for context

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
        /* Custom style for score inputs */
        input[type=number]::-webkit-inner-spin-button,
        input[type=number]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        input[type=number] {
            -moz-appearance: textfield; /* Firefox */
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">

    <header class="ssm-header">
        <div class="ssm-logo">SSM</div>
        <div class="ssm-title">Student Semester Manager</div>
    </header>

    <main class="container mx-auto p-4 md:p-6">
        <div class="ssm-card max-w-4xl mx-auto !p-6 md:!p-8">
            <h1 class="text-2xl font-bold text-gray-800 mb-2"><?php echo $page_title; ?></h1>
            <p class="text-gray-600 mb-1">Course: <?php echo htmlspecialchars($course['course_name'] ?? 'N/A'); ?></p>
            <p class="text-gray-600 mb-6 font-semibold">Maximum Score: <?php echo htmlspecialchars(number_format($max_score, 2)); ?></p>

            <?php if (!empty($error_message)): ?>
                <div class="mb-4 p-3 rounded bg-red-100 border border-red-400 text-red-700 text-sm" role="alert">
                    <?php echo $error_message; /* Already contains htmlspecialchars if needed */ ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($success_message)): ?>
                 <div class="mb-4 p-3 rounded bg-green-100 border border-green-400 text-green-700 text-sm" role="alert">
                    <?php echo htmlspecialchars($success_message); ?>
                 </div>
            <?php endif; ?>

            <?php if (empty($students)): ?>
                 <p class="text-center text-gray-500 my-6">No students are enrolled in this course yet.</p>
            <?php else: ?>
                <form action="manage_grades.php?task_id=<?php echo $task_id; ?>" method="POST">
                    <input type="hidden" name="task_id" value="<?php echo $task_id; ?>">
                    <div class="overflow-x-auto shadow-md rounded-lg">
                        <table class="min-w-full bg-white border border-gray-200">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="px-4 py-3 border-b border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Student Email</th>
                                    <th class="px-4 py-3 border-b border-gray-200 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider w-40">Score (/ <?php echo htmlspecialchars(number_format($max_score, 2)); ?>)</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-700 text-sm">
                                <?php foreach ($students as $student): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="border-b border-gray-200 px-4 py-2">
                                            <?php echo htmlspecialchars($student['email']); ?>
                                        </td>
                                        <td class="border-b border-gray-200 px-4 py-2">
                                            <input type="number" step="any"
                                                   name="scores[<?php echo $student['user_id']; ?>]"
                                                   value="<?php echo ($student['score'] !== null) ? htmlspecialchars(number_format($student['score'], 2)) : ''; ?>"
                                                   min="0"
                                                   max="<?php echo htmlspecialchars($max_score); ?>"
                                                   class="shadow-sm appearance-none border rounded w-full py-1 px-2 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                                                   placeholder="Enter score">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-6 text-right">
                        <button type="submit"
                                class="ssm-btn-primary !bg-green-600 hover:!bg-green-700 !py-2 !px-5">
                            <i class="fas fa-save mr-1"></i> Save All Grades
                        </button>
                    </div>
                </form>
            <?php endif; ?>

             <div class="mt-8 pt-4 border-t">
                 <a href="manage_tasks.php?course_id=<?php echo $course_id; ?>" class="text-blue-600 hover:underline text-sm">
                     &larr; Back to Manage Tasks for <?php echo htmlspecialchars($course['course_name'] ?? 'Course'); ?>
                 </a>
             </div>

        </div> </main>

    <footer class="ssm-footer">
        &copy; <?php echo date("Y"); ?> SSM Project. All rights reserved.
    </footer>

</body>
</html>
