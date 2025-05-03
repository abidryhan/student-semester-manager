<?php
// ssm/views/manage_tasks.php
require_once '../config.php';
require_once '../models/course_model.php'; // Contains task and weight functions

// --- Security & Setup ---
// ... (Existing PHP code at the top - No changes needed here except ensuring 'deleteAdminTask' is used if renamed) ...
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: login.php"); exit(); }
$admin_id = $_SESSION['user_id'];
$error_message = ''; $success_message = '';
if (!isset($_GET['course_id']) || !filter_var($_GET['course_id'], FILTER_VALIDATE_INT)) { header("Location: admin_dashboard.php?status=invalid_course"); exit(); }
$course_id = (int)$_GET['course_id'];
$course = getCourseById($conn, $course_id);
if (!$course || $course['managed_by'] !== $admin_id) { header("Location: admin_dashboard.php?status=not_found"); exit(); }
$action = $_GET['action'] ?? 'view';
$task_id = isset($_GET['task_id']) ? (int)$_GET['task_id'] : null;

// DELETE Action
if ($action === 'delete' && $task_id) {
    $task_to_delete = getTaskById($conn, $task_id);
    if ($task_to_delete && $task_to_delete['course_id'] === $course_id) {
        // *** Use deleteAdminTask if renamed, otherwise keep deleteTask ***
        if (function_exists('deleteAdminTask') ? deleteAdminTask($conn, $task_id) : deleteTask($conn, $task_id)) {
             header("Location: manage_tasks.php?course_id=$course_id&status=deleted"); exit();
        } else { $error_message = "Error deleting task."; $action = 'view'; }
    } else { $error_message = "Task not found or does not belong to this course."; $action = 'view'; }
}
// POST Request Handler... (Keep existing POST logic) ...
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $task_name = trim($_POST['task_name'] ?? '');
    $weight_id_input = $_POST['weight_id'] ?? ''; $weight_id = ($weight_id_input !== '') ? (int)$weight_id_input : null;
    $due_date = trim($_POST['due_date'] ?? '');
    $max_score_input = $_POST['max_score'] ?? ''; // Get max_score input
    $max_score = ($max_score_input !== '' && is_numeric($max_score_input) && $max_score_input > 0) ? (float)$max_score_input : 100.0; // Default or validate > 0

    $current_action = $_POST['action_type'] ?? 'add';
    $posted_task_id = isset($_POST['task_id']) ? (int)$_POST['task_id'] : null;

    if (empty($task_name) || empty($due_date)) { // Weight is optional
        $error_message = "Task Name and Due Date are required.";
        $action = $current_action; $task_id = $posted_task_id;
    } else {
        $success = false;
        if ($current_action === 'add') {
            $success = addTask($conn, $course_id, $weight_id, $task_name, $due_date, $max_score);
        } elseif ($current_action === 'edit' && $posted_task_id) {
             $task_to_edit = getTaskById($conn, $posted_task_id);
             if ($task_to_edit && $task_to_edit['course_id'] === $course_id) {
                 $success = updateTask($conn, $posted_task_id, $course_id, $weight_id, $task_name, $due_date, $max_score);
             } else { $error_message = "Task not found or invalid for update."; }
        }
        if ($success) { header("Location: manage_tasks.php?course_id=$course_id&status=saved"); exit();
        } elseif (empty($error_message)) { $error_message = "Error saving task."; $action = $current_action; $task_id = $posted_task_id; }
    }
}
// Prepare Data & Form Vars... (Keep existing logic, ensuring max_score is fetched/set) ...
$weights = getWeightsByCourse($conn, $course_id); $tasks = getTasksByCourse($conn, $course_id);
$task_name_value = ''; $weight_id_value = null; $due_date_value = ''; $max_score_value = '100.00'; // Default max score
$page_title = "Manage Tasks for " . htmlspecialchars($course['course_name']); $form_title = 'Add New Task'; $submit_text = 'Add Task'; $form_action_type = 'add';
if ($action === 'edit' && $task_id && empty($error_message)) {
    $task_data = getTaskById($conn, $task_id);
    if ($task_data && $task_data['course_id'] === $course_id) {
        $task_name_value = $task_data['task_name']; $weight_id_value = $task_data['weight_id']; $due_date_value = $task_data['due_date']; $max_score_value = $task_data['max_score'] ?? '100.00'; // Set max score
        $form_title = 'Edit Task'; $submit_text = 'Update Task'; $form_action_type = 'edit';
    } else { $error_message = "Task to edit not found."; $action = 'view'; }
} elseif (!empty($error_message) && $_SERVER["REQUEST_METHOD"] == "POST") {
    $task_name_value = $task_name; $weight_id_value = $weight_id; $due_date_value = $due_date; $max_score_value = $max_score ?? '100.00'; // Retain max score
    if ($action === 'edit'){ $form_title = 'Edit Task'; $submit_text = 'Update Task'; }
    else { $form_title = 'Add New Task'; $submit_text = 'Add Task'; }
}
if (isset($_GET['status'])) { switch ($_GET['status']) { case 'deleted': $success_message = "Task deleted successfully."; break; case 'saved': $success_message = "Task saved successfully."; break; } }
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
</head>
<body class="bg-gray-100 min-h-screen">
    <header class="ssm-header">
        <div class="ssm-logo">SSM</div>
        <div class="ssm-title">Student Semester Manager</div>
    </header>

    <main class="container mx-auto p-4 md:p-6">
        <div class="ssm-card max-w-4xl mx-auto !p-6 md:!p-8">
            <h1 class="text-2xl font-bold text-gray-800 mb-6"><?php echo $page_title; ?></h1>

            <?php if (!empty($error_message)): ?>
                <div class="mb-4 p-3 rounded bg-red-100 border border-red-400 text-red-700 text-sm" role="alert"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            <?php if (!empty($success_message)): ?>
                 <div class="mb-4 p-3 rounded bg-green-100 border border-green-400 text-green-700 text-sm" role="alert"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <?php if ($action === 'add' || $action === 'edit'): ?>
                <div class="mb-6 p-6 border rounded-lg bg-gray-50 shadow-sm max-w-lg mx-auto">
                    <h2 class="text-xl font-semibold mb-4"><?php echo $form_title; ?></h2>
                    <form action="manage_tasks.php?course_id=<?php echo $course_id; ?><?php echo ($action === 'edit' ? '&action=edit&task_id=' . $task_id : '&action=add'); ?>" method="POST">
                        <input type="hidden" name="action_type" value="<?php echo $form_action_type; ?>">
                        <?php if ($action === 'edit' && $task_id): ?>
                            <input type="hidden" name="task_id" value="<?php echo $task_id; ?>">
                        <?php endif; ?>

                        <div class="mb-4">
                            <label for="task_name" class="block text-gray-700 text-sm font-bold mb-2">Task Name:</label>
                            <input type="text" id="task_name" name="task_name" required value="<?php echo htmlspecialchars($task_name_value); ?>" class="shadow-sm appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        <div class="mb-4">
                             <label for="weight_id" class="block text-gray-700 text-sm font-bold mb-2">Weight Category (Optional):</label>
                             <select name="weight_id" id="weight_id" class="shadow-sm border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white appearance-none">
                                 <option value="">-- None --</option>
                                 <?php if (!empty($weights)): ?>
                                     <?php foreach ($weights as $weight): ?>
                                         <option value="<?php echo $weight['weight_id']; ?>" <?php echo ($weight_id_value == $weight['weight_id']) ? 'selected' : ''; ?>>
                                             <?php echo htmlspecialchars($weight['container_type'] . ' (' . $weight['percentage'] . '%)'); ?>
                                         </option>
                                     <?php endforeach; ?>
                                 <?php endif; ?>
                             </select>
                             <?php if (empty($weights)): ?> <p class="text-xs text-gray-500 mt-1">No weight categories found. You can <a href="manage_weights.php?course_id=<?php echo $course_id; ?>" class="underline">add weights</a>.</p> <?php endif; ?>
                        </div>
                         <div class="mb-4">
                             <label for="max_score" class="block text-gray-700 text-sm font-bold mb-2">Max Score:</label>
                             <input type="number" step="any" min="0.01" id="max_score" name="max_score" required value="<?php echo htmlspecialchars($max_score_value); ?>" class="shadow-sm appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="e.g., 100.00">
                         </div>
                         <div class="mb-6">
                             <label for="due_date" class="block text-gray-700 text-sm font-bold mb-2">Due Date:</label>
                             <input type="date" id="due_date" name="due_date" required value="<?php echo htmlspecialchars($due_date_value); ?>" class="shadow-sm appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                         </div>
                         <div class="flex items-center gap-4">
                             <button type="submit" class="ssm-btn-primary !bg-green-600 hover:!bg-green-700 !py-2 !px-5 !rounded-md text-sm"><i class="fas <?php echo ($action === 'edit') ? 'fa-save' : 'fa-plus'; ?> mr-1"></i> <?php echo $submit_text; ?></button>
                             <a href="manage_tasks.php?course_id=<?php echo $course_id; ?>" class="text-gray-600 hover:underline text-sm">Cancel</a>
                         </div>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($action === 'view'): ?>
                <div class="mb-6">
                     <a href="manage_tasks.php?course_id=<?php echo $course_id; ?>&action=add" class="ssm-btn-primary !bg-blue-600 hover:!bg-blue-700 !py-2 !px-4 !rounded-lg text-sm mb-4"> <i class="fas fa-plus mr-1"></i> Add New Task </a>
                </div>
                <h2 class="text-xl font-semibold text-gray-700 mb-4">Existing Tasks</h2>
                <div class="overflow-x-auto shadow-md rounded-lg">
                    <table class="min-w-full bg-white border border-gray-200">
                         <thead class="bg-gray-100">
                            <tr>
                                <th class="px-4 py-3 border-b text-left text-xs font-semibold text-gray-600 uppercase">Task Name</th>
                                <th class="px-4 py-3 border-b text-left text-xs font-semibold text-gray-600 uppercase">Weight</th>
                                <th class="px-4 py-3 border-b text-left text-xs font-semibold text-gray-600 uppercase">Max Score</th>
                                <th class="px-4 py-3 border-b text-left text-xs font-semibold text-gray-600 uppercase">Due Date</th>
                                <th class="px-4 py-3 border-b text-left text-xs font-semibold text-gray-600 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 text-sm">
                             <?php if (!empty($tasks)): ?>
                                <?php foreach ($tasks as $task): ?>
                                     <tr class="hover:bg-gray-50">
                                         <td class="border-b px-4 py-2"><?php echo htmlspecialchars($task['task_name']); ?></td>
                                         <td class="border-b px-4 py-2"><?php echo isset($task['container_type']) ? htmlspecialchars($task['container_type'] . ' (' . $task['percentage'] . '%)') : '<span class="text-gray-400 italic">N/A</span>'; ?></td>
                                         <td class="border-b px-4 py-2"><?php echo isset($task['max_score']) ? htmlspecialchars(number_format($task['max_score'], 2)) : '<span class="text-gray-400 italic">N/A</span>'; ?></td>
                                         <td class="border-b px-4 py-2"><?php echo htmlspecialchars(date('M d, Y', strtotime($task['due_date']))); ?></td>
                                         <td class="border-b px-4 py-2">
                                             <div class="flex flex-wrap gap-2 justify-start">
                                                <a href="manage_tasks.php?course_id=<?php echo $course_id; ?>&action=edit&task_id=<?php echo $task['task_id']; ?>"
                                                   class="inline-block bg-yellow-500 hover:bg-yellow-600 text-white font-semibold py-1 px-2 rounded-md text-xs" title="Edit Task"><i class="fas fa-pencil-alt"></i></a>
                                                <a href="manage_tasks.php?course_id=<?php echo $course_id; ?>&action=delete&task_id=<?php echo $task['task_id']; ?>"
                                                   class="inline-block bg-red-500 hover:bg-red-600 text-white font-semibold py-1 px-2 rounded-md text-xs" title="Delete Task"
                                                   onclick="return confirm('Are you sure? This also deletes related grades/notifications.');"><i class="fas fa-trash-alt"></i></a>
                                                <a href="manage_grades.php?task_id=<?php echo $task['task_id']; ?>"
                                                   class="inline-block bg-purple-500 hover:bg-purple-600 text-white font-semibold py-1 px-2 rounded-md text-xs" title="Enter/View Grades">
                                                   <i class="fas fa-marker"></i> <span class="hidden sm:inline">Grades</span>
                                                </a>
                                             </div>
                                         </td>
                                     </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                 <tr><td colspan="5" class="border-b px-4 py-3 text-center text-gray-500 italic">No tasks found for this course.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
             <?php endif; ?>

             <div class="mt-8 pt-4 border-t">
                 <a href="admin_dashboard.php" class="text-blue-600 hover:underline text-sm">
                     &larr; Back to Dashboard
                 </a>
             </div>

        </div> </main>

     <footer class="ssm-footer">
        &copy; <?php echo date("Y"); ?> SSM Project. All rights reserved.
    </footer>

</body>
</html>
