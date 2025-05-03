<?php
// ssm/views/student_dashboard.php
require_once '../config.php';
require_once '../models/course_model.php';
require_once '../models/task_log_model.php'; // Include the personal task model

// --- Security Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$feedback_message = '';
$error_message = '';

// --- Instantiate Task Log Model ---
$taskLogModel = new TaskLogModel($conn); // Pass connection

// --- Handle Personal Task Actions ---
$personal_task_action = $_GET['action'] ?? 'view_personal';
$personal_task_id = isset($_GET['task_id']) ? (int)$_GET['task_id'] : null;

// DELETE Personal Task
if ($personal_task_action === 'delete_task' && $personal_task_id) {
    if ($taskLogModel->deleteTask($personal_task_id, $student_id)) {
        header("Location: student_dashboard.php?status=personal_deleted"); exit();
    } else { $error_message = "Error deleting personal task."; $personal_task_action = 'view_personal'; }
}
// POST: Add or Edit Personal Task
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $submitted_action = $_POST['personal_action_type'] ?? null;
    if ($submitted_action === 'add_task' || $submitted_action === 'edit_task') {
        $personal_task_name = trim($_POST['personal_task_name'] ?? '');
        $personal_status = $_POST['personal_status'] ?? 'pending';
        $posted_personal_task_id = isset($_POST['personal_task_id']) ? (int)$_POST['personal_task_id'] : null;

        if (empty($personal_task_name)) {
            $error_message = "Personal task name cannot be empty."; $personal_task_action = $submitted_action; $personal_task_id = $posted_personal_task_id;
        } else {
            $success = false;
            if ($submitted_action === 'add_task') {
                $success = $taskLogModel->addTask($student_id, $personal_task_name, $personal_status);
            } elseif ($submitted_action === 'edit_task' && $posted_personal_task_id) {
                $success = $taskLogModel->updateTask($posted_personal_task_id, $personal_task_name, $personal_status, $student_id);
                if (!$success && empty($error_message)){ $error_message = "Failed to update task."; }
            }
            if ($success) { header("Location: student_dashboard.php?status=personal_saved"); exit();
            } elseif (empty($error_message)) { $error_message = "Error saving personal task."; $personal_task_action = $submitted_action; $personal_task_id = $posted_personal_task_id; }
        }
    }
}

// --- Fetch Data for Display ---
$enrolled_courses = getEnrolledCourses($conn, $student_id);
// *** FIXED: Ensure $course_tasks is fetched for its dedicated section ***
$course_tasks = getUpcomingTasks($conn, $student_id); // Fetching ALL course tasks for this student
// *** END FIX ***
$personal_tasks = $taskLogModel->getTasksByStudent($student_id);
$notification_tasks = getTasksDueWithinWeek($conn, $student_id);
$notification_count = count($notification_tasks);

// --- Grade Calculation Data ---
$all_student_grades_data = getStudentGradesData($conn, $student_id);
$course_grade_summaries = [];
$all_tasks_with_grades = []; // For detailed grade table display

// Calculate summaries and prepare detailed list
if (!empty($all_student_grades_data)) {
    foreach ($all_student_grades_data as $course_id_grade => $course_data) {
        // Check if the student is actually enrolled in this course (data might exist if they unenrolled)
         $is_enrolled = false;
         foreach($enrolled_courses as $enrolled_c) { if ($enrolled_c['course_id'] == $course_id_grade) { $is_enrolled = true; break; } }
         if (!$is_enrolled) continue; // Skip grade calculation if not currently enrolled

        $course_grade_summaries[$course_id_grade] = calculateCourseGradeSummary($course_data);

        // Prepare flat list of tasks with grades for this course for the details table
        foreach($course_data['weights'] as $weight_id => $weight_info) {
            foreach($weight_info['tasks'] as $task) {
                 $task_percentage = ($task['max_score'] ?? 0) > 0 && ($task['score'] !== null) ? round(($task['score'] / $task['max_score']) * 100, 1) : null;
                 $all_tasks_with_grades[$course_id_grade][] = [
                    'task_name' => $task['task_name'], 'category' => $weight_info['container_type'],
                    'score' => $task['score'], 'max_score' => $task['max_score'], 'percentage' => $task_percentage ];
            }
        }
        // Sort tasks within the course
        if (isset($all_tasks_with_grades[$course_id_grade])) {
             usort($all_tasks_with_grades[$course_id_grade], function($a, $b) {
                $catCompare = strcmp($a['category'], $b['category']);
                if ($catCompare !== 0) return $catCompare; return strcmp($a['task_name'], $b['task_name']); });
        }
    }
}


// --- Prepare Personal Task Form Variables (for Edit or failed POST) ---
$personal_task_name_value = ''; $personal_status_value = 'pending';
$personal_form_title = 'Add Personal Task'; $personal_submit_text = 'Add Task'; $personal_form_action_type = 'add_task';
if ($personal_task_action === 'edit_task' && $personal_task_id && empty($error_message)) {
    $task_data = $taskLogModel->getTaskById($personal_task_id, $student_id);
    if ($task_data) {
        $personal_task_name_value = $task_data['task_name']; $personal_status_value = $task_data['status'];
        $personal_form_title = 'Edit Personal Task'; $personal_submit_text = 'Update Task'; $personal_form_action_type = 'edit_task';
    } else { $error_message = "Personal task to edit not found or permission denied."; $personal_task_action = 'view_personal'; }
} elseif (!empty($error_message) && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['personal_action_type'])) {
    $personal_task_name_value = $_POST['personal_task_name'] ?? ''; $personal_status_value = $_POST['personal_status'] ?? 'pending';
    if ($personal_task_action === 'edit_task'){ $personal_form_title = 'Edit Personal Task'; $personal_submit_text = 'Update Task'; }
    else { $personal_form_title = 'Add Personal Task'; $personal_submit_text = 'Add Task'; }
}

// Handle feedback messages
if (isset($_GET['status'])) {
    switch ($_GET['status']) {
        case 'enrolled': $feedback_message = "Successfully enrolled!"; break;
        case 'enroll_error': $error_message = "Enrollment failed."; break;
        case 'personal_deleted': $feedback_message = "Personal task deleted."; break;
        case 'personal_saved': $feedback_message = "Personal task saved."; break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - SSM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../public/ssm-theme.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        .table-fixed { table-layout: fixed; }
        .hidden { display: none; }
        .progress-bar-bg { background-color: #e5e7eb; /* gray-200 */ }
        .progress-bar { background-color: #3b82f6; /* blue-500 */ transition: width 0.5s ease-in-out; }
        /* Widths for Grades Detail Table */
        .w-task { width: 40%; } .w-category { width: 20%; } .w-score { width: 15%; } .w-max { width: 15%; } .w-pct { width: 10%; }
        /* Widths for Course Task Table */
        .course-task-name { width: 40%; } .course-task-course { width: 35%; } .course-task-due { width: 25%; }
         /* Widths for Personal Task Table */
        .task-name { width: 60%; } .task-status { width: 20%; } .task-actions { width: 20%; }
    </style>
</head>
<body class="font-sans">
    <header class="ssm-header">
        <div class="ssm-logo">SSM</div> <div class="ssm-title">Student Semester Manager</div>
    </header>
    <main class="container mx-auto p-4 md:p-6">
        <div class="ssm-card w-full max-w-6xl mx-auto !p-6">

            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 border-b pb-4">
                <h1 class="text-2xl md:text-3xl font-bold text-gray-800 mb-2 sm:mb-0">Student Dashboard</h1>
                <a href="../logout.php" class="ssm-btn-primary !bg-red-500 hover:!bg-red-600 !py-2 !px-4 !rounded-lg text-sm self-start sm:self-center"><i class="fas fa-sign-out-alt mr-1"></i> Logout</a>
            </div>
            <p class="text-gray-600 mb-6">Welcome, <?php echo htmlspecialchars($_SESSION['email'] ?? 'Student'); ?>!</p>

             <?php if (!empty($feedback_message)): ?><div class="mb-4 p-3 rounded bg-green-100 border border-green-400 text-green-700 text-sm" role="alert"><?php echo htmlspecialchars($feedback_message); ?></div><?php endif; ?>
             <?php if (!empty($error_message)): ?><div class="mb-4 p-3 rounded bg-red-100 border border-red-400 text-red-700 text-sm" role="alert"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>

            <div class="flex flex-wrap gap-4 items-center mb-6">
                 <a href="enroll_course.php" class="ssm-btn-primary !bg-green-500 hover:!bg-green-600 !py-2 !px-4 text-sm"><i class="fas fa-plus-circle mr-1"></i> Enroll in Course</a>
                <button onclick="toggleGrades()" class="ssm-btn-primary !bg-blue-500 hover:!bg-blue-600 !py-2 !px-4 text-sm"><i class="fas fa-calculator mr-1"></i> Show/Hide Grades</button>
                <button onclick="toggleNotifications()" class="ssm-btn-primary !bg-red-500 hover:!bg-red-600 !py-2 !px-4 text-sm inline-flex items-center relative"><i class="fas fa-bell mr-2"></i> Notifications <?php if ($notification_count > 0): ?><span id="notif-count" class="ml-2 bg-red-700 text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center absolute -top-1 -right-1"><?php echo $notification_count; ?></span><?php endif; ?></button>
                <!-- Add Routine Toggle Button (optional) -->
                <!-- <button onclick="toggleRoutine()" class="ssm-btn-primary !bg-purple-500 hover:!bg-purple-600 !py-2 !px-4 text-sm"><i class="fas fa-calendar-alt mr-1"></i> Show/Hide Routine</button> -->
            </div>

            <div id="notification-details" class="hidden mb-6 p-4 border rounded-lg bg-yellow-50 shadow max-w-lg">
                <h3 class="text-lg font-semibold mb-2 border-b pb-1 text-yellow-800">Upcoming Deadlines (Next 7 Days)</h3>
                <?php if ($notification_count > 0): ?>
                    <ul class="list-disc list-inside space-y-1 text-yellow-900 text-sm">
                        <?php foreach ($notification_tasks as $notif_task): ?><li> <?php echo htmlspecialchars($notif_task['task_name']); ?> (<?php echo htmlspecialchars($notif_task['course_name']); ?>) is due in <?php echo $notif_task['days_left']; ?> day(s) (on <?php echo date('M d', strtotime($notif_task['due_date'])); ?>).</li><?php endforeach; ?>
                    </ul>
                <?php else: ?><p class="text-gray-500 text-sm">No course tasks due within the next 7 days.</p><?php endif; ?>
            </div>

            <!-- NEW: Class Routine Section -->
            <div id="class-routine-section" class="mb-8 bg-teal-50 p-4 rounded-lg shadow-inner">
                <h2 class="text-xl font-semibold text-teal-800 mb-3 border-b border-teal-200 pb-2">Class Routine</h2>
                <div class="overflow-x-auto">
                    <?php if (empty($enrolled_courses)): ?>
                        <p class="text-center text-gray-500 py-4 italic">You are not enrolled in any courses.</p>
                    <?php else: ?>
                        <table class="w-full bg-white border border-gray-200 text-sm rounded-lg shadow">
                            <thead class="bg-teal-200 text-xs uppercase text-teal-800">
                                <tr>
                                    <th class="px-4 py-2 border text-left routine-course">Course (Section)</th>
                                    <th class="px-4 py-2 border text-left routine-days">Days</th>
                                    <th class="px-4 py-2 border text-left routine-time">Time Slot</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-700">
                                <?php foreach ($enrolled_courses as $course): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="border px-4 py-2 routine-course">
                                            <?php echo htmlspecialchars($course['course_name']) . ' (' . htmlspecialchars($course['section']) . ')'; ?>
                                        </td>
                                        <td class="border px-4 py-2 routine-days">
                                            <?php echo htmlspecialchars($course['days_option'] ?? 'N/A'); // Display N/A if data missing ?>
                                        </td>
                                        <td class="border px-4 py-2 routine-time">
                                            <?php echo htmlspecialchars($course['time_slot'] ?? 'N/A'); // Display N/A if data missing ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="space-y-8">

                 <div id="grades-section" class="hidden bg-indigo-50 p-4 rounded-lg shadow-inner">
                     <h2 class="text-xl font-semibold text-indigo-800 mb-3 border-b border-indigo-200 pb-2">Your Grades</h2>
                     <?php if (empty($enrolled_courses)): ?>
                          <p class="text-center text-gray-500 py-4 italic">You are not enrolled in any courses.</p>
                     <?php elseif (empty($all_student_grades_data)): ?>
                         <p class="text-center text-gray-500 py-4 italic">No grade information available yet for your courses.</p>
                     <?php else: ?>
                         <?php foreach ($enrolled_courses as $course):
                                $course_id = $course['course_id'];
                                // Ensure summary exists for this enrolled course, default if not
                                $summary = $course_grade_summaries[$course_id] ?? ['secured' => 0.0, 'attempted' => 0.0, 'details' => []];
                                $course_tasks_detail = $all_tasks_with_grades[$course_id] ?? [];
                                $progress_percentage = $summary['secured']; // Base progress on secured out of 100
                         ?>
                             <div class="mb-6 p-4 border border-indigo-100 rounded bg-white shadow-sm">
                                 <h3 class="text-lg font-semibold text-indigo-700 mb-2"><?php echo htmlspecialchars($course['course_name']); ?></h3>
                                 <div class="text-sm mb-3">
                                     <span class="font-semibold">Current Grade:</span> <span class="font-bold text-indigo-700"><?php echo number_format($summary['secured'], 2); ?>%</span>
                                     <span class="text-gray-500 ml-2">(Based on <?php echo number_format($summary['attempted'], 0); ?>% attempted weight)</span>
                                 </div>
                                 <div class="w-full progress-bar-bg rounded-full h-2.5 mb-4"><div class="progress-bar h-2.5 rounded-full" style="width: <?php echo max(0, min(100, $progress_percentage)); ?>%"></div></div>
                                 <h4 class="text-md font-semibold text-gray-600 mb-2">Grade Details:</h4>
                                 <div class="overflow-x-auto"><table class="w-full bg-white border border-gray-200 text-sm table-fixed">
                                     <thead class="bg-gray-100"><tr> <th class="w-task px-3 py-2 border text-left font-semibold text-gray-600 uppercase">Task</th> <th class="w-category px-3 py-2 border text-left font-semibold text-gray-600 uppercase">Category</th> <th class="w-score px-3 py-2 border text-left font-semibold text-gray-600 uppercase">Score</th> <th class="w-max px-3 py-2 border text-left font-semibold text-gray-600 uppercase">Max</th> <th class="w-pct px-3 py-2 border text-left font-semibold text-gray-600 uppercase">%</th> </tr></thead>
                                     <tbody class="text-gray-700">
                                         <?php if (!empty($course_tasks_detail)): ?>
                                             <?php foreach ($course_tasks_detail as $detail): ?><tr class="hover:bg-gray-50">
                                                 <td class="border px-3 py-1 break-words"><?php echo htmlspecialchars($detail['task_name']); ?></td>
                                                 <td class="border px-3 py-1 break-words"><?php echo htmlspecialchars($detail['category'] ?? 'N/A'); ?></td>
                                                 <td class="border px-3 py-1"><?php echo ($detail['score'] !== null) ? number_format($detail['score'], 2) : '<em class="text-gray-400">N/A</em>'; ?></td>
                                                 <td class="border px-3 py-1"><?php echo ($detail['max_score'] !== null) ? number_format($detail['max_score'], 2) : '<em class="text-gray-400">N/A</em>'; ?></td>
                                                 <td class="border px-3 py-1 <?php echo ($detail['percentage'] !== null && $detail['percentage'] < 60) ? 'text-red-600' : ''; ?>"><?php echo ($detail['percentage'] !== null) ? number_format($detail['percentage'], 1) . '%' : '<em class="text-gray-400">--</em>'; ?></td></tr>
                                             <?php endforeach; ?>
                                         <?php else: ?><tr><td colspan="5" class="border px-4 py-3 text-center text-gray-500 italic">No tasks or grades recorded for this course yet.</td></tr><?php endif; ?>
                                     </tbody></table></div></div>
                         <?php endforeach; ?>
                     <?php endif; ?>
                 </div> 
                 <div class="bg-gray-50 p-4 rounded-lg shadow-inner">
                     <h2 class="text-xl font-semibold text-gray-700 mb-3 border-b pb-2">Enrolled Courses</h2>
                     <div class="overflow-x-auto">
                         <?php if (empty($enrolled_courses)):
                             ?>
                             <p class="text-center text-gray-500 py-4 italic">You are not enrolled in any courses. <a href="enroll_course.php" class="text-blue-500 hover:underline">Enroll here</a>.</p>
                         <?php else:
                             ?>
                             <table class="min-w-full bg-white rounded-lg shadow" aria-label="Enrolled Courses Table">
                                 <thead class="bg-gray-200 text-xs uppercase text-gray-600">
                                     <tr>
                                         <th class="px-4 py-2 border text-left">Course Name</th>
                                         <th class="px-4 py-2 border text-left">Semester</th>
                                         <th class="px-4 py-2 border text-left">Section</th>
                                         <th class="px-4 py-2 border text-left">Final Grade</th>
                                         <th class="px-4 py-2 border text-left">GPA</th>
                                     </tr>
                                 </thead>
                                 <tbody class="text-gray-700 text-sm">
                                     <?php foreach ($enrolled_courses as $course):
                                         ?>
                                         <tr class="hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500" tabindex="0">
                                             <td class="border border-gray-300 px-4 py-2 break-words"><?php echo htmlspecialchars($course['course_name']); ?></td>
                                             <td class="border border-gray-300 px-4 py-2 break-words"><?php echo htmlspecialchars($course['semester']); ?></td>
                                             <td class="border border-gray-300 px-4 py-2 break-words"><?php echo htmlspecialchars($course['section']); ?></td>
                                             <td class="border border-gray-300 px-4 py-2 text-center">
                                                 <?php if (!empty($course['final_grade_submitted'])):
                                                     ?>
                                                     <span class="font-semibold"><?php echo htmlspecialchars($course['final_letter_grade'] ?? '-'); ?></span>
                                                 <?php else:
                                                     ?>
                                                     <span class="text-gray-500 italic">Pending</span>
                                                 <?php endif; ?>
                                             </td>
                                             <td class="border border-gray-300 px-4 py-2 text-center">
                                                 <?php if (!empty($course['final_grade_submitted']) && isset($course['final_gpa'])):
                                                     ?>
                                                     <span class="font-semibold"><?php echo number_format((float)$course['final_gpa'], 1); ?></span>
                                                 <?php else:
                                                     ?>
                                                     <span class="text-gray-500">-</span>
                                                 <?php endif; ?>
                                             </td>
                                         </tr>
                                     <?php endforeach; ?>
                                 </tbody>
                             </table>
                         <?php endif; ?>
                     </div>
                 </div>

                <div class="bg-gray-50 p-4 rounded-lg shadow-inner">
                    <h2 class="text-xl font-semibold text-gray-700 mb-3 border-b pb-2">Course Task List</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full bg-white border border-gray-200 table-fixed rounded-lg shadow">
                             <thead class="bg-gray-200 text-xs uppercase text-gray-600"><tr><th class="course-task-name px-4 py-2 border text-left">Task Name</th> <th class="course-task-course px-4 py-2 border text-left">Course</th> <th class="course-task-due px-4 py-2 border text-left">Due Date</th> </tr></thead>
                            <tbody class="text-gray-700 text-sm">
                                <?php if (!empty($course_tasks)): ?>
                                    <?php $today = strtotime(date('Y-m-d')); ?>
                                    <?php foreach ($course_tasks as $task): ?>
                                        <?php $dueDateTimestamp = strtotime($task['due_date']); $is_overdue = $dueDateTimestamp < $today; ?>
                                        <tr class="hover:bg-gray-50 <?php echo $is_overdue ? 'opacity-70' : ''; ?>">
                                            <td class="border px-4 py-2 break-words <?php echo $is_overdue ? 'line-through text-gray-500' : ''; ?>"><?php echo htmlspecialchars($task['task_name']); ?></td>
                                            <td class="border px-4 py-2 break-words <?php echo $is_overdue ? 'line-through text-gray-500' : ''; ?>"><?php echo htmlspecialchars($task['course_name']); ?></td>
                                            <td class="border px-4 py-2 <?php echo $is_overdue ? 'line-through text-gray-500' : ''; ?>"><?php echo htmlspecialchars(date('M d, Y', $dueDateTimestamp)); ?></td></tr>
                                    <?php endforeach; ?>
                                <?php else: ?><tr><td colspan="3" class="border px-4 py-3 text-center text-gray-500 italic">No course tasks found for your enrolled courses.</td></tr><?php endif; ?>
                            </tbody></table></div></div>

                 <div class="bg-gray-50 p-4 rounded-lg shadow-inner">
                    <h2 class="text-xl font-semibold text-gray-700 mb-3 border-b pb-2">Personal Task Log</h2>
                     <?php if ($personal_task_action === 'add_task' || $personal_task_action === 'edit_task'): ?>
                        <div class="mb-6 p-4 border rounded-lg bg-white">
                            <h3 class="text-lg font-semibold mb-3"><?php echo $personal_form_title; ?></h3>
                            <form action="student_dashboard.php" method="POST" class="space-y-3">
                                <input type="hidden" name="personal_action_type" value="<?php echo $personal_form_action_type; ?>">
                                <?php if ($personal_task_action === 'edit_task' && $personal_task_id): ?> <input type="hidden" name="personal_task_id" value="<?php echo $personal_task_id; ?>"> <?php endif; ?>
                                <div><label for="personal_task_name" class="block text-gray-700 text-sm font-bold mb-1">Task Name:</label><input type="text" id="personal_task_name" name="personal_task_name" required value="<?php echo htmlspecialchars($personal_task_name_value); ?>" class="shadow-sm appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500"></div>
                                <div><label for="personal_status" class="block text-gray-700 text-sm font-bold mb-1">Status:</label><select name="personal_status" id="personal_status" required class="shadow-sm border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white appearance-none"><option value="pending" <?php echo ($personal_status_value == 'pending') ? 'selected' : ''; ?>>Pending</option><option value="working" <?php echo ($personal_status_value == 'working') ? 'selected' : ''; ?>>Working</option><option value="done" <?php echo ($personal_status_value == 'done') ? 'selected' : ''; ?>>Done</option></select></div>
                                <div class="flex items-center gap-4 pt-2"><button type="submit" class="ssm-btn-primary !bg-green-600 hover:!bg-green-700 !py-2 !px-4 text-sm"><i class="fas <?php echo ($personal_task_action === 'edit_task') ? 'fa-save' : 'fa-plus'; ?> mr-1"></i> <?php echo $personal_submit_text; ?></button><a href="student_dashboard.php" class="text-gray-600 hover:underline text-sm">Cancel</a></div>
                            </form></div>
                     <?php endif; ?>
                     <?php if ($personal_task_action === 'view_personal'): ?>
                         <div class="mb-4"><a href="student_dashboard.php?action=add_task" class="ssm-btn-primary !bg-blue-500 hover:!bg-blue-600 !py-2 !px-3 text-sm"><i class="fas fa-plus mr-1"></i> Add Personal Task</a></div>
                         <div class="overflow-x-auto shadow-md rounded-lg">
                            <table class="w-full bg-white border border-gray-200 table-fixed">
                                <thead class="bg-gray-100 text-xs uppercase text-gray-600"><tr><th class="task-name px-4 py-2 border text-left">Task Name</th> <th class="task-status px-4 py-2 border text-left">Status</th> <th class="task-actions px-4 py-2 border text-left">Actions</th> </tr></thead>
                                <tbody class="text-gray-700 text-sm">
                                    <?php if (!empty($personal_tasks)): ?>
                                        <?php foreach ($personal_tasks as $p_task): ?><tr class="hover:bg-gray-50"><td class="border px-4 py-2 break-words"><?php echo htmlspecialchars($p_task['task_name']); ?></td><td class="border px-4 py-2 capitalize"><?php echo htmlspecialchars($p_task['status']); ?></td><td class="border px-4 py-2"><div class="flex flex-wrap gap-2 justify-start"><a href="student_dashboard.php?action=edit_task&task_id=<?php echo $p_task['task_id']; ?>" class="inline-block bg-yellow-500 hover:bg-yellow-600 text-white font-semibold py-1 px-2 rounded-md text-xs" title="Edit Task"><i class="fas fa-pencil-alt"></i></a><a href="student_dashboard.php?action=delete_task&task_id=<?php echo $p_task['task_id']; ?>" class="inline-block bg-red-500 hover:bg-red-600 text-white font-semibold py-1 px-2 rounded-md text-xs" title="Delete Task" onclick="return confirm('Are you sure?');"><i class="fas fa-trash-alt"></i></a></div></td></tr><?php endforeach; ?>
                                    <?php else: ?><tr><td colspan="3" class="border px-4 py-3 text-center text-gray-500 italic">You haven't added any personal tasks yet.</td></tr><?php endif; ?>
                                </tbody></table></div><?php endif; ?></div>

            </div> </div> </main>
    <footer class="ssm-footer"> &copy; <?php echo date("Y"); ?> SSM Project. All rights reserved. </footer>
     <script src="../public/scripts.js"></script>
</body>
</html>
