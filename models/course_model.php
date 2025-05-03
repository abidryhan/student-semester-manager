<?php
// ssm/models/course_model.php
// Contains functions for Admin Course Management, Student Course Info/Enrollment,
// Admin Task/Weight/Grade Management, Student Task/Notification Info, and Grade Calculation.
// UPDATED: Added functions for managing grades (Phase 7/8 follow-up)

// --- Phase 3: Admin Course Management Functions ---
// [Functions: getCoursesByAdmin, addCourse, getCourseById, updateCourse, deleteCourse]
// ... (These functions remain unchanged from the previous version) ...
function getCoursesByAdmin(mysqli $conn, int $admin_id): array {
    $sql = "SELECT course_id, course_name, semester, section, days_option, time_slot FROM Courses WHERE managed_by = ? ORDER BY course_name ASC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { error_log("Prepare failed (getCoursesByAdmin): " . $conn->error); return []; }
    $stmt->bind_param("i", $admin_id);
    if (!$stmt->execute()) { error_log("Execute failed (getCoursesByAdmin): " . $stmt->error); $stmt->close(); return []; }
    $result = $stmt->get_result();
    $courses = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $courses ?: [];
}
function addCourse(mysqli $conn, string $course_name, string $semester, string $section, int $managed_by, ?string $days_option, ?string $time_slot): bool {
    // Allow null for days/time if not provided (though form makes them required)
    $sql = "INSERT INTO Courses (course_name, semester, section, managed_by, days_option, time_slot) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { error_log("Prepare failed (addCourse): " . $conn->error); return false; }
    // Bind days_option and time_slot as strings (s)
    $stmt->bind_param("sssiss", $course_name, $semester, $section, $managed_by, $days_option, $time_slot);
    $success = $stmt->execute();
    if (!$success) { error_log("Execute failed (addCourse): " . $stmt->error); }
    $stmt->close();
    return $success;
}
function getCourseById(mysqli $conn, int $course_id): ?array {
    $sql = "SELECT course_id, course_name, semester, section, managed_by, days_option, time_slot FROM Courses WHERE course_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { error_log("Prepare failed (getCourseById): " . $conn->error); return null; }
    $stmt->bind_param("i", $course_id);
    if (!$stmt->execute()) { error_log("Execute failed (getCourseById): " . $stmt->error); $stmt->close(); return null; }
    $result = $stmt->get_result();
    $course = $result->fetch_assoc();
    $stmt->close();
    return $course;
}
function updateCourse(mysqli $conn, int $course_id, string $course_name, string $semester, string $section, ?string $days_option, ?string $time_slot): bool {
    $sql = "UPDATE Courses SET course_name = ?, semester = ?, section = ?, days_option = ?, time_slot = ? WHERE course_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { error_log("Prepare failed (updateCourse): " . $conn->error); return false; }
    // Bind days_option and time_slot as strings (s)
    $stmt->bind_param("sssssi", $course_name, $semester, $section, $days_option, $time_slot, $course_id);
    $success = $stmt->execute();
    if (!$success) { error_log("Execute failed (updateCourse): " . $stmt->error); }
    $stmt->close();
    return $success;
}
function deleteCourse(mysqli $conn, int $course_id): bool {
    $conn->begin_transaction();
    try {
        $task_ids_sql = "SELECT task_id FROM Tasks WHERE course_id = ?";
        $task_stmt = $conn->prepare($task_ids_sql);
        if (!$task_stmt) throw new Exception("Prepare failed (get task IDs): " . $conn->error);
        $task_stmt->bind_param("i", $course_id);
        if (!$task_stmt->execute()) throw new Exception("Execute failed (get task IDs): " . $task_stmt->error);
        $result = $task_stmt->get_result(); $task_ids = []; while ($row = $result->fetch_assoc()) { $task_ids[] = $row['task_id']; } $task_stmt->close();
        if (!empty($task_ids)) {
            $task_ids_placeholder = implode(',', array_fill(0, count($task_ids), '?')); $types = str_repeat('i', count($task_ids));
            $sql_delete_grades = "DELETE FROM Grades WHERE task_id IN ($task_ids_placeholder)";
            $stmt_grades = $conn->prepare($sql_delete_grades);
            if (!$stmt_grades) throw new Exception("Prepare failed (delete grades): " . $conn->error);
            $stmt_grades->bind_param($types, ...$task_ids);
            if (!$stmt_grades->execute()) throw new Exception("Execute failed (delete grades): " . $stmt_grades->error);
            $stmt_grades->close();
            $sql_delete_notifications = "DELETE FROM Notifications WHERE task_id IN ($task_ids_placeholder)";
            $stmt_notif = $conn->prepare($sql_delete_notifications);
            if (!$stmt_notif) throw new Exception("Prepare failed (delete notifications): " . $conn->error);
            $stmt_notif->bind_param($types, ...$task_ids);
            if (!$stmt_notif->execute()) throw new Exception("Execute failed (delete notifications): " . $stmt_notif->error);
            $stmt_notif->close();
        }
        $sql_delete_tasks = "DELETE FROM Tasks WHERE course_id = ?";
        $stmt_tasks = $conn->prepare($sql_delete_tasks);
        if (!$stmt_tasks) throw new Exception("Prepare failed (delete tasks): " . $conn->error);
        $stmt_tasks->bind_param("i", $course_id);
        if (!$stmt_tasks->execute()) throw new Exception("Execute failed (delete tasks): " . $stmt_tasks->error);
        $stmt_tasks->close();
        $sql_delete_weights = "DELETE FROM CourseWeights WHERE course_id = ?";
        $stmt_weights = $conn->prepare($sql_delete_weights);
        if (!$stmt_weights) throw new Exception("Prepare failed (delete weights): " . $conn->error);
        $stmt_weights->bind_param("i", $course_id);
        if (!$stmt_weights->execute()) throw new Exception("Execute failed (delete weights): " . $stmt_weights->error);
        $stmt_weights->close();
        $sql_delete_enrollments = "DELETE FROM StudentCourses WHERE course_id = ?";
        $stmt_enroll = $conn->prepare($sql_delete_enrollments);
        if (!$stmt_enroll) throw new Exception("Prepare failed (delete enrollments): " . $conn->error);
        $stmt_enroll->bind_param("i", $course_id);
        if (!$stmt_enroll->execute()) throw new Exception("Execute failed (delete enrollments): " . $stmt_enroll->error);
        $stmt_enroll->close();
        $sql_delete_course = "DELETE FROM Courses WHERE course_id = ?";
        $stmt_course = $conn->prepare($sql_delete_course);
        if (!$stmt_course) throw new Exception("Prepare failed (delete course): " . $conn->error);
        $stmt_course->bind_param("i", $course_id);
        if (!$stmt_course->execute()) throw new Exception("Execute failed (delete course): " . $stmt_course->error);
        $stmt_course->close();
        $conn->commit(); return true;
    } catch (Exception $e) { $conn->rollback(); error_log("Delete Course Transaction Failed: " . $e->getMessage()); return false; }
}

// --- Phase 4: Student Course Info/Enrollment Functions ---
// [Functions: getEnrolledCourses, getAvailableCourses, enrollStudent]
// ... (These functions remain unchanged) ...
function getEnrolledCourses(mysqli $conn, int $student_id): array {
    // Modified SQL to include final grade info from StudentCourses
    // AND fetch days_option, time_slot from Courses
    $sql = "SELECT
                c.course_id, c.course_name, c.semester, c.section, c.days_option, c.time_slot,
                sc.final_grade_submitted, sc.final_letter_grade, sc.final_gpa
            FROM Courses c
            JOIN StudentCourses sc ON c.course_id = sc.course_id
            WHERE sc.student_id = ?
            ORDER BY c.course_name ASC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { error_log("Prepare failed (getEnrolledCourses): " . $conn->error); return []; }
    $stmt->bind_param("i", $student_id);
    if (!$stmt->execute()) { error_log("Execute failed (getEnrolledCourses): " . $stmt->error); $stmt->close(); return []; }
    $result = $stmt->get_result();
    $courses = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $courses ?: [];
}
function getAvailableCourses(mysqli $conn, int $student_id): array {
    $sql = "SELECT course_id, course_name, semester, section FROM Courses WHERE course_id NOT IN (SELECT course_id FROM StudentCourses WHERE student_id = ?) ORDER BY course_name ASC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { error_log("Prepare failed (getAvailableCourses): " . $conn->error); return []; }
    $stmt->bind_param("i", $student_id);
    if (!$stmt->execute()) { error_log("Execute failed (getAvailableCourses): " . $stmt->error); $stmt->close(); return []; }
    $result = $stmt->get_result(); $courses = $result->fetch_all(MYSQLI_ASSOC); $stmt->close(); return $courses ?: [];
}
function enrollStudent(mysqli $conn, int $student_id, int $course_id): bool {
    $sql = "INSERT INTO StudentCourses (student_id, course_id) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { error_log("Prepare failed (enrollStudent): " . $conn->error); return false; }
    $stmt->bind_param("ii", $student_id, $course_id);
    $success = $stmt->execute();
    if (!$success) { error_log("Execute failed (enrollStudent): (" . $stmt->errno . ") " . $stmt->error); }
    $stmt->close(); return $success;
}

// --- Phase 5: Admin Course Task & Weight Management Functions ---
// [Functions: getWeightsByCourse, addWeight, deleteWeight, addTask, getTasksByCourse, getTaskById, updateTask, deleteAdminTask]
function getWeightsByCourse(mysqli $conn, int $course_id): array {
    $sql = "SELECT weight_id, container_type, percentage, best_of FROM CourseWeights WHERE course_id = ? ORDER BY container_type ASC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { error_log("Prepare failed (getWeightsByCourse): " . $conn->error); return []; }
    $stmt->bind_param("i", $course_id);
    if (!$stmt->execute()) { error_log("Execute failed (getWeightsByCourse): " . $stmt->error); $stmt->close(); return []; }
    $result = $stmt->get_result(); $weights = $result->fetch_all(MYSQLI_ASSOC); $stmt->close(); return $weights ?: [];
}
function addWeight(mysqli $conn, int $course_id, string $container_type, int $percentage): bool {
    $sql = "INSERT INTO CourseWeights (course_id, container_type, percentage) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { error_log("Prepare failed (addWeight): " . $conn->error); return false; }
    $stmt->bind_param("isi", $course_id, $container_type, $percentage);
    $success = $stmt->execute(); if (!$success) { error_log("Execute failed (addWeight): " . $stmt->error); } $stmt->close(); return $success;
}
function deleteWeight(mysqli $conn, int $weight_id, int $course_id): string|bool {
    $check_sql = "SELECT COUNT(*) as task_count FROM Tasks WHERE weight_id = ? AND course_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    if (!$check_stmt) { error_log("Prepare failed (deleteWeight check): " . $conn->error); return "Database error checking tasks."; }
    $check_stmt->bind_param("ii", $weight_id, $course_id);
    if (!$check_stmt->execute()) { error_log("Execute failed (deleteWeight check): " . $check_stmt->error); $check_stmt->close(); return "Database error checking tasks."; }
    $result = $check_stmt->get_result(); $row = $result->fetch_assoc(); $check_stmt->close();
    if ($row['task_count'] > 0) { return "Cannot delete weight category: " . $row['task_count'] . " task(s) are currently assigned to it."; }
    $delete_sql = "DELETE FROM CourseWeights WHERE weight_id = ? AND course_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    if (!$delete_stmt) { error_log("Prepare failed (deleteWeight delete): " . $conn->error); return "Database error deleting weight."; }
    $delete_stmt->bind_param("ii", $weight_id, $course_id);
    $success = $delete_stmt->execute(); $affected_rows = $delete_stmt->affected_rows; $delete_stmt->close();
    if ($success && $affected_rows > 0) { return true; }
    elseif ($success && $affected_rows === 0) { return "Weight category not found or does not belong to this course."; }
    else { error_log("Execute failed (deleteWeight delete): " . $conn->error); return "Database error deleting weight."; }
}
function addTask(mysqli $conn, int $course_id, ?int $weight_id, string $task_name, string $due_date, ?float $max_score = 100.0): bool {
    $sql = "INSERT INTO Tasks (course_id, weight_id, task_name, due_date, max_score) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { error_log("Prepare failed (addTask): " . $conn->error); return false; }
    $stmt->bind_param("iisss", $course_id, $weight_id, $task_name, $due_date, $max_score);
    $success = $stmt->execute(); if (!$success) { error_log("Execute failed (addTask): " . $stmt->error); } $stmt->close(); return $success;
}
function getTasksByCourse(mysqli $conn, int $course_id): array {
    $sql = "SELECT t.task_id, t.course_id, t.weight_id, t.task_name, t.due_date, t.max_score, cw.container_type, cw.percentage, cw.best_of
            FROM Tasks t LEFT JOIN CourseWeights cw ON t.weight_id = cw.weight_id WHERE t.course_id = ? ORDER BY t.due_date ASC, t.task_name ASC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { error_log("Prepare failed (getTasksByCourse): " . $conn->error); return []; }
    $stmt->bind_param("i", $course_id);
    if (!$stmt->execute()) { error_log("Execute failed (getTasksByCourse): " . $stmt->error); $stmt->close(); return []; }
    $result = $stmt->get_result(); $tasks = $result->fetch_all(MYSQLI_ASSOC); $stmt->close(); return $tasks ?: [];
}
function getTaskById(mysqli $conn, int $task_id): ?array {
     $sql = "SELECT task_id, course_id, weight_id, task_name, due_date, max_score FROM Tasks WHERE task_id = ?";
     $stmt = $conn->prepare($sql);
     if (!$stmt) { error_log("Prepare failed (getTaskById - Course Task): " . $conn->error); return null; }
     $stmt->bind_param("i", $task_id);
     if (!$stmt->execute()) { error_log("Execute failed (getTaskById - Course Task): " . $stmt->error); $stmt->close(); return null; }
     $result = $stmt->get_result(); $task = $result->fetch_assoc(); $stmt->close(); return $task;
 }
function updateTask(mysqli $conn, int $task_id, int $course_id, ?int $weight_id, string $task_name, string $due_date, ?float $max_score): bool {
    $sql = "UPDATE Tasks SET course_id = ?, weight_id = ?, task_name = ?, due_date = ?, max_score = ? WHERE task_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { error_log("Prepare failed (updateTask - Course Task): " . $conn->error); return false; }
    $stmt->bind_param("iisssi", $course_id, $weight_id, $task_name, $due_date, $max_score, $task_id);
    $success = $stmt->execute(); if (!$success) { error_log("Execute failed (updateTask - Course Task): " . $stmt->error); } $stmt->close(); return $success;
}
function deleteAdminTask(mysqli $conn, int $task_id): bool {
    $conn->begin_transaction();
    try {
        $sql_notif = "DELETE FROM Notifications WHERE task_id = ?";
        $stmt_notif = $conn->prepare($sql_notif);
        if (!$stmt_notif) throw new Exception("Prepare failed (delete notifications for task): " . $conn->error);
        $stmt_notif->bind_param("i", $task_id);
        if (!$stmt_notif->execute()) throw new Exception("Execute failed (delete notifications for task): " . $stmt_notif->error);
        $stmt_notif->close();
        $sql_grades = "DELETE FROM Grades WHERE task_id = ?"; // Ensure grades are deleted too
        $stmt_grades = $conn->prepare($sql_grades);
        if (!$stmt_grades) throw new Exception("Prepare failed (delete grades for task): " . $conn->error);
        $stmt_grades->bind_param("i", $task_id);
        if (!$stmt_grades->execute()) throw new Exception("Execute failed (delete grades for task): " . $stmt_grades->error);
        $stmt_grades->close();
        $sql_task = "DELETE FROM Tasks WHERE task_id = ?";
        $stmt_task = $conn->prepare($sql_task);
        if (!$stmt_task) throw new Exception("Prepare failed (delete task): " . $conn->error);
        $stmt_task->bind_param("i", $task_id);
        if (!$stmt_task->execute()) throw new Exception("Execute failed (delete task): " . $stmt_task->error);
        $stmt_task->close();
        $conn->commit(); return true;
    } catch (Exception $e) { $conn->rollback(); error_log("Delete Admin Task Transaction Failed: " . $e->getMessage()); return false; }
}

// --- Phase 5: Student Task/Notification Functions ---
// [Functions: getTasksDueWithinWeek, getUpcomingTasks (Modified)]
function getTasksDueWithinWeek(mysqli $conn, int $student_id): array {
    $sql = "SELECT t.task_id, t.task_name, c.course_name, t.due_date, DATEDIFF(t.due_date, CURDATE()) as days_left FROM Tasks t JOIN Courses c ON t.course_id = c.course_id JOIN StudentCourses sc ON c.course_id = sc.course_id WHERE sc.student_id = ? AND t.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) ORDER BY t.due_date ASC";
    $stmt = $conn->prepare($sql);
     if (!$stmt) { error_log("Prepare failed (getTasksDueWithinWeek): " . $conn->error); return []; }
    $stmt->bind_param("i", $student_id);
     if (!$stmt->execute()) { error_log("Execute failed (getTasksDueWithinWeek): " . $stmt->error); $stmt->close(); return []; }
    $result = $stmt->get_result(); $tasks = $result->fetch_all(MYSQLI_ASSOC); $stmt->close(); return $tasks ?: [];
}
function getUpcomingTasks(mysqli $conn, int $student_id): array {
    // IMPORTANT: This is the function that was missing, causing the error on student_dashboard.php
    $sql = "SELECT t.task_id, t.task_name, c.course_name, t.due_date, t.max_score 
            FROM Tasks t 
            JOIN Courses c ON t.course_id = c.course_id 
            JOIN StudentCourses sc ON c.course_id = sc.course_id 
            WHERE sc.student_id = ? 
            ORDER BY t.due_date DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { error_log("Prepare failed (MODIFIED getUpcomingTasks): " . $conn->error); return []; }
    $stmt->bind_param("i", $student_id);
    if (!$stmt->execute()) { error_log("Execute failed (MODIFIED getUpcomingTasks): " . $stmt->error); $stmt->close(); return []; }
    $result = $stmt->get_result(); $tasks = $result->fetch_all(MYSQLI_ASSOC); $stmt->close(); return $tasks ?: [];
}

// --- Phase 7: Grade Calculation Functions ---
// [Functions: getStudentGradesData, calculateCategoryContribution, calculateCourseGradeSummary]
function getStudentGradesData(mysqli $conn, int $student_id): array {
    $sql = "SELECT c.course_id, c.course_name, t.task_id, t.task_name, t.max_score, cw.weight_id, cw.container_type, cw.percentage, cw.best_of, g.score
            FROM StudentCourses sc JOIN Courses c ON sc.course_id = c.course_id LEFT JOIN Tasks t ON c.course_id = t.course_id
            LEFT JOIN CourseWeights cw ON t.weight_id = cw.weight_id LEFT JOIN Grades g ON t.task_id = g.task_id AND sc.student_id = g.student_id
            WHERE sc.student_id = ? ORDER BY c.course_id, cw.weight_id, t.task_id";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { error_log("Prepare failed (getStudentGradesData): " . $conn->error); return []; }
    $stmt->bind_param("i", $student_id);
    if (!$stmt->execute()) { error_log("Execute failed (getStudentGradesData): " . $stmt->error); $stmt->close(); return []; }
    $result = $stmt->get_result(); $data = $result->fetch_all(MYSQLI_ASSOC); $stmt->close();
    $structured_data = [];
    foreach ($data as $row) {
        if ($row['task_id'] === null || $row['weight_id'] === null) continue;
        $course_id = $row['course_id']; $weight_id = $row['weight_id'];
        if (!isset($structured_data[$course_id])) { $structured_data[$course_id] = ['course_name' => $row['course_name'], 'weights' => []]; }
        if (!isset($structured_data[$course_id]['weights'][$weight_id])) {
            $structured_data[$course_id]['weights'][$weight_id] = [
                'container_type' => $row['container_type'], 'percentage' => (float)$row['percentage'],
                'best_of' => $row['best_of'] === null ? null : (int)$row['best_of'], 'tasks' => [] ]; }
        $structured_data[$course_id]['weights'][$weight_id]['tasks'][] = [
            'task_id' => $row['task_id'], 'task_name' => $row['task_name'],
            'score' => $row['score'] === null ? null : (float)$row['score'],
            'max_score' => $row['max_score'] === null ? null : (float)$row['max_score'] ];
    } return $structured_data;
}
function calculateCategoryContribution(array $tasks_in_category, float $category_weight_percentage, ?int $best_of): array {
    $task_percentages = []; $graded_count = 0; $total_count = count($tasks_in_category);
    foreach ($tasks_in_category as $task) {
        if ($task['score'] !== null && is_numeric($task['score']) && $task['max_score'] !== null && is_numeric($task['max_score']) && $task['max_score'] > 0) {
            $task_percentages[] = ($task['score'] / $task['max_score']) * 100.0; $graded_count++;
        } elseif ($task['score'] !== null) { $task_percentages[] = 0.0; $graded_count++; }
    }
    if (empty($task_percentages)) { return ['contribution' => 0.0, 'graded_count' => 0, 'total_count' => $total_count, 'used_count' => 0]; }
    $selected_percentages = $task_percentages; $used_count = count($selected_percentages);
    if ($best_of !== null && $best_of > 0 && count($task_percentages) > $best_of) {
        rsort($selected_percentages); $selected_percentages = array_slice($selected_percentages, 0, $best_of); $used_count = count($selected_percentages);
    }
    $average_category_percentage = array_sum($selected_percentages) / count($selected_percentages);
    $contribution = $average_category_percentage * ($category_weight_percentage / 100.0);
    return [ 'contribution' => round($contribution, 2), 'graded_count' => $graded_count, 'total_count' => $total_count, 'used_count' => $used_count ];
}
function calculateCourseGradeSummary(array $course_data): array {
    $total_secured_percentage = 0.0; $total_attempted_weight = 0.0; $category_details = [];
    if (!isset($course_data['weights'])) { return ['secured' => 0.0, 'attempted' => 0.0, 'details' => []]; }
    foreach ($course_data['weights'] as $weight_id => $weight_info) {
        $category_result = calculateCategoryContribution($weight_info['tasks'], $weight_info['percentage'], $weight_info['best_of']);
        $total_secured_percentage += $category_result['contribution'];
        if ($category_result['graded_count'] > 0) { $total_attempted_weight += $weight_info['percentage']; }
        $category_details[$weight_id] = [ 'name' => $weight_info['container_type'], 'weight' => $weight_info['percentage'], 'contribution' => $category_result['contribution'],
            'graded_count' => $category_result['graded_count'], 'total_count' => $category_result['total_count'], 'used_count' => $category_result['used_count'], 'best_of' => $weight_info['best_of'] ];
    }
    return [ 'secured' => round($total_secured_percentage, 2), 'attempted' => round($total_attempted_weight, 2), 'details' => $category_details ];
}

// --- NEW Functions for Grade Management (Phase 8 Follow-up) ---

/**
 * Fetches details for a specific task and verifies admin ownership via the course.
 * @param mysqli $conn
 * @param int $task_id
 * @param int $admin_id
 * @return array|null Task details if found and owned, null otherwise.
 */
function getTaskDetailsForGrading(mysqli $conn, int $task_id, int $admin_id): ?array
{
    $sql = "SELECT t.task_id, t.task_name, t.max_score, t.course_id, c.managed_by
            FROM Tasks t
            JOIN Courses c ON t.course_id = c.course_id
            WHERE t.task_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed (getTaskDetailsForGrading): " . $conn->error);
        return null;
    }
    $stmt->bind_param("i", $task_id);
    if (!$stmt->execute()) {
        error_log("Execute failed (getTaskDetailsForGrading): " . $stmt->error);
        $stmt->close();
        return null;
    }
    $result = $stmt->get_result();
    $task = $result->fetch_assoc();
    $stmt->close();

    // Verify ownership
    if ($task && $task['managed_by'] == $admin_id) {
        return $task;
    } else {
        // Task not found or admin doesn't manage this course
        if ($task) { error_log("Ownership check failed for task ID {$task_id} by admin ID {$admin_id}"); }
        return null;
    }
}

/**
 * Fetches students enrolled in a course along with their existing grade for a specific task.
 * @param mysqli $conn
 * @param int $course_id
 * @param int $task_id
 * @return array List of students with their score for the task (score will be null if not graded).
 */
function getStudentsByCourseAndFetchGrades(mysqli $conn, int $course_id, int $task_id): array
{
    $sql = "SELECT u.user_id, u.email, g.score
            FROM Users u
            JOIN StudentCourses sc ON u.user_id = sc.student_id
            LEFT JOIN Grades g ON sc.student_id = g.student_id AND g.task_id = ? -- Task ID for grade join
            WHERE sc.course_id = ? AND u.role = 'student'
            ORDER BY u.email ASC"; // Order students alphabetically by email

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed (getStudentsByCourseAndFetchGrades): " . $conn->error);
        return [];
    }
    $stmt->bind_param("ii", $task_id, $course_id); // Bind task_id first, then course_id
    if (!$stmt->execute()) {
        error_log("Execute failed (getStudentsByCourseAndFetchGrades): " . $stmt->error);
        $stmt->close();
        return [];
    }
    $result = $stmt->get_result();
    $students = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $students ?: [];
}


/**
 * Saves or updates a grade for a student on a specific task.
 * Uses INSERT ... ON DUPLICATE KEY UPDATE. Assumes unique key on (student_id, task_id) in Grades table.
 * Assumes Grades.score column allows NULL.
 *
 * @param mysqli $conn
 * @param int $student_id
 * @param int $task_id
 * @param ?float $score The score to save (can be null to clear the grade).
 * @return bool True on success, false on failure.
 */
function saveOrUpdateGrade(mysqli $conn, int $student_id, int $task_id, ?float $score): bool
{
    // Ensure score is formatted correctly for DB (e.g., handle potential locale issues if needed)
    // For this implementation, we assume standard float/null is fine.
    // The column type should be DECIMAL or FLOAT allowing NULLs.

    $sql = "INSERT INTO Grades (student_id, task_id, score)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE score = VALUES(score)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed (saveOrUpdateGrade): " . $conn->error);
        return false;
    }

    // Bind parameters. Use 'd' for double/decimal score.
    // Note: Directly binding NULL with 'd' type might require specific MySQLi/PHP versions or settings.
    // A common robust way is to explicitly handle null, but let's try direct binding first.
    // If score is null, mysqli should handle it correctly if column is nullable.
    $stmt->bind_param("iid", $student_id, $task_id, $score);

    $success = $stmt->execute();
    if (!$success) {
        error_log("Execute failed (saveOrUpdateGrade): (" . $stmt->errno . ") " . $stmt->error);
    }
    $stmt->close();
    return $success;
}


// --- Phase 7 Update: Final Grade Calculation and Ungraded Task Check ---

/**
 * Calculates the final course percentage for a student based on all tasks and weights.
 * Treats ungraded tasks (score IS NULL) as 0 for calculation purposes unless skipped.
 *
 * @param mysqli $conn Database connection.
 * @param int $student_id The ID of the student.
 * @param int $course_id The ID of the course.
 * @return float|null The final course percentage, or null if no weights/tasks found.
 */
function calculateFinalCoursePercentage(mysqli $conn, int $student_id, int $course_id): ?float
{
    // Fetch all tasks for the course with their weights and student's grade
    $sql = "SELECT 
                t.task_id, t.task_name, t.max_score,
                g.score,
                cw.weight_id, cw.container_type, cw.percentage, cw.best_of
            FROM Tasks t
            JOIN CourseWeights cw ON t.weight_id = cw.weight_id
            LEFT JOIN Grades g ON t.task_id = g.task_id AND g.student_id = ?
            WHERE t.course_id = ? 
            ORDER BY cw.weight_id, t.due_date";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed (calculateFinalCoursePercentage - tasks): " . $conn->error);
        return null;
    }
    $stmt->bind_param("ii", $student_id, $course_id);
    if (!$stmt->execute()) {
        error_log("Execute failed (calculateFinalCoursePercentage - tasks): " . $stmt->error);
        $stmt->close();
        return null;
    }
    $result = $stmt->get_result();
    $tasks_data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($tasks_data)) {
        // No tasks found for this course, cannot calculate percentage
        return 0.0; // Or null, depending on desired behavior for empty courses
    }

    // Group tasks by category (weight_id)
    $categories = [];
    foreach ($tasks_data as $task) {
        if ($task['weight_id'] === null) continue; // Skip tasks not assigned to a category
        $categories[$task['weight_id']]['details'] = [
            'percentage' => $task['percentage'],
            'best_of' => $task['best_of']
        ];
        $categories[$task['weight_id']]['tasks'][] = $task;
    }

    $total_course_percentage = 0.0;
    $total_weight_considered = 0.0; // Track sum of weights to normalize if needed (e.g., if weights don't sum to 100)

    // Calculate contribution for each category
    foreach ($categories as $weight_id => $category) {
        $category_details = $category['details'];
        $tasks_in_category = $category['tasks'];
        $category_weight = (float) $category_details['percentage'];
        $best_of = isset($category_details['best_of']) ? (int) $category_details['best_of'] : null;

        $scores_in_category = [];
        foreach ($tasks_in_category as $task) {
            // Treat NULL score as 0 for calculation, but ensure max_score is valid
            $score = ($task['score'] !== null) ? (float) $task['score'] : 0.0;
            $max_score = ($task['max_score'] !== null && $task['max_score'] > 0) ? (float) $task['max_score'] : 100.0; // Default max_score if invalid/null
            $scores_in_category[] = ($max_score > 0) ? ($score / $max_score) * 100 : 0; // Score as percentage
        }

        if (empty($scores_in_category)) continue; // Skip empty categories

        // Apply 'best_of' rule if applicable
        if ($best_of !== null && $best_of > 0 && $best_of < count($scores_in_category)) {
            rsort($scores_in_category); // Sort scores descending
            $scores_in_category = array_slice($scores_in_category, 0, $best_of); // Take top 'best_of' scores
        }

        // Calculate average percentage for the category
        $category_average = array_sum($scores_in_category) / count($scores_in_category);

        // Add weighted contribution to total course percentage
        $total_course_percentage += ($category_average * $category_weight / 100.0);
        $total_weight_considered += $category_weight;
    }
    
    // Optional: Normalize if total weights don't sum to 100
    // if ($total_weight_considered > 0 && $total_weight_considered != 100) {
    //     $total_course_percentage = ($total_course_percentage / $total_weight_considered) * 100;
    // }

     // Ensure percentage does not exceed 100 - edge case for extra credit scenarios if not handled above
    $final_percentage = min($total_course_percentage, 100.0);
    
    // Round to 1 decimal place for consistency
    return round($final_percentage, 1);

}

/**
 * Calculates the final letter grade and GPA based on the BRAC University grading scale.
 *
 * @param float $percentage The total course percentage (e.g., 87.5).
 * @return array An array containing 'letter' and 'gpa'.
 */
function calculateFinalGrade(float $percentage): array
{
    if ($percentage >= 97) return ['letter' => 'A+', 'gpa' => 4.0];
    if ($percentage >= 90) return ['letter' => 'A', 'gpa' => 4.0];
    if ($percentage >= 85) return ['letter' => 'A-', 'gpa' => 3.7];
    if ($percentage >= 80) return ['letter' => 'B+', 'gpa' => 3.3];
    if ($percentage >= 75) return ['letter' => 'B', 'gpa' => 3.0];
    if ($percentage >= 70) return ['letter' => 'B-', 'gpa' => 2.7];
    if ($percentage >= 65) return ['letter' => 'C+', 'gpa' => 2.3];
    if ($percentage >= 60) return ['letter' => 'C', 'gpa' => 2.0];
    if ($percentage >= 57) return ['letter' => 'C-', 'gpa' => 1.7];
    if ($percentage >= 55) return ['letter' => 'D+', 'gpa' => 1.3];
    if ($percentage >= 52) return ['letter' => 'D', 'gpa' => 1.0];
    if ($percentage >= 50) return ['letter' => 'D-', 'gpa' => 0.7];
    return ['letter' => 'F', 'gpa' => 0.0];
}

/**
 * Checks for ungraded tasks for a specific student in a given course.
 *
 * @param mysqli $conn Database connection.
 * @param int $student_id The ID of the student.
 * @param int $course_id The ID of the course.
 * @return int The count of tasks where the student's score is NULL.
 */
function checkUngradedTasks(mysqli $conn, int $student_id, int $course_id): int
{
    $sql = "SELECT COUNT(t.task_id) as ungraded_count
            FROM Tasks t
            LEFT JOIN Grades g ON t.task_id = g.task_id AND g.student_id = ?
            WHERE t.course_id = ? AND g.score IS NULL";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed (checkUngradedTasks): " . $conn->error);
        return -1; // Return -1 to indicate an error
    }
    $stmt->bind_param("ii", $student_id, $course_id);
    if (!$stmt->execute()) {
        error_log("Execute failed (checkUngradedTasks): " . $stmt->error);
        $stmt->close();
        return -1; // Return -1 to indicate an error
    }
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['ungraded_count'] : -1; // Return count or -1 on error
}

/**
 * Checks if final grades have been submitted for ANY student in a specific course.
 *
 * @param mysqli $conn Database connection.
 * @param int $course_id The ID of the course.
 * @return bool True if at least one student has final_grade_submitted = 1, false otherwise.
 */
function checkIfFinalGradesSubmittedForCourse(mysqli $conn, int $course_id): bool
{
    $sql = "SELECT 1 FROM StudentCourses WHERE course_id = ? AND final_grade_submitted = 1 LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed (checkIfFinalGradesSubmittedForCourse): " . $conn->error);
        return false; // Or throw an exception, depending on error handling strategy
    }
    $stmt->bind_param("i", $course_id);
    if (!$stmt->execute()) {
        error_log("Execute failed (checkIfFinalGradesSubmittedForCourse): " . $stmt->error);
        $stmt->close();
        return false;
    }
    $result = $stmt->get_result();
    $submitted = $result->fetch_assoc();
    $stmt->close();
    return $submitted !== null; // Returns true if a row was found (meaning submitted=1), false otherwise
}

?>
