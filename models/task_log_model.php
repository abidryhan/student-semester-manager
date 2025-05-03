<?php
// ssm/models/task_log_model.php

class TaskLogModel
{
    private $db; // Property to hold the database connection

    /**
     * Constructor
     * @param mysqli $conn The database connection object passed from config.php
     */
    public function __construct(mysqli $conn)
    {
        $this->db = $conn; // Store the connection
    }

    /**
     * Fetches all personal tasks for a specific student.
     * @param int $student_id
     * @return array
     */
    public function getTasksByStudent(int $student_id): array
    {
        $sql = "SELECT task_id, task_name, status FROM TaskLog WHERE student_id = ? ORDER BY task_id DESC"; // Show newest first or order as needed
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed (TaskLogModel::getTasksByStudent): " . $this->db->error);
            return [];
        }
        $stmt->bind_param("i", $student_id);
        if (!$stmt->execute()) {
            error_log("Execute failed (TaskLogModel::getTasksByStudent): " . $stmt->error);
            $stmt->close();
            return [];
        }
        $result = $stmt->get_result();
        $tasks = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $tasks ?: [];
    }

    /**
     * Fetches a single personal task by its ID.
     * Needed for pre-filling the edit form.
     * @param int $task_id
     * @param int $student_id (Optional but good for security check)
     * @return array|null
     */
     public function getTaskById(int $task_id, ?int $student_id = null): ?array
     {
        $sql = "SELECT task_id, task_name, status, student_id FROM TaskLog WHERE task_id = ?";
        // Optionally add student_id check directly in SQL for security
        // if ($student_id !== null) {
        //     $sql .= " AND student_id = ?";
        // }
        $stmt = $this->db->prepare($sql);
         if (!$stmt) {
             error_log("Prepare failed (TaskLogModel::getTaskById): " . $this->db->error);
             return null;
         }
         $stmt->bind_param("i", $task_id);
        // if ($student_id !== null) {
        //    $stmt->bind_param("ii", $task_id, $student_id);
        // } else {
        //    $stmt->bind_param("i", $task_id);
        // }

        if (!$stmt->execute()) {
             error_log("Execute failed (TaskLogModel::getTaskById): " . $stmt->error);
             $stmt->close();
             return null;
         }
         $result = $stmt->get_result();
         $task = $result->fetch_assoc();
         $stmt->close();

         // Security check if student_id was provided
         if ($student_id !== null && $task && $task['student_id'] != $student_id) {
             error_log("Security check failed: Student {$student_id} attempted to access task {$task_id}");
             return null; // Task doesn't belong to the student
         }

         return $task;
     }

    /**
     * Adds a new personal task for a student.
     * @param int $student_id
     * @param string $task_name
     * @param string $status (e.g., 'pending', 'working', 'done')
     * @return bool
     */
    public function addTask(int $student_id, string $task_name, string $status): bool
    {
        $sql = "INSERT INTO TaskLog (student_id, task_name, status) VALUES (?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed (TaskLogModel::addTask): " . $this->db->error);
            return false;
        }
        $stmt->bind_param("iss", $student_id, $task_name, $status);
        $success = $stmt->execute();
        if (!$success) {
            error_log("Execute failed (TaskLogModel::addTask): " . $stmt->error);
        }
        $stmt->close();
        return $success;
    }

    /**
     * Updates an existing personal task.
     * @param int $task_id
     * @param string $task_name
     * @param string $status
     * @param int $student_id (Optional: For security check to ensure student owns the task)
     * @return bool
     */
    public function updateTask(int $task_id, string $task_name, string $status, ?int $student_id = null): bool
    {
        $sql = "UPDATE TaskLog SET task_name = ?, status = ? WHERE task_id = ?";
        // Add student_id check for security
        if ($student_id !== null) {
            $sql .= " AND student_id = ?";
        }

        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed (TaskLogModel::updateTask): " . $this->db->error);
            return false;
        }

        if ($student_id !== null) {
             $stmt->bind_param("ssii", $task_name, $status, $task_id, $student_id);
        } else {
            $stmt->bind_param("ssi", $task_name, $status, $task_id);
        }

        $success = $stmt->execute();
        if (!$success) {
            error_log("Execute failed (TaskLogModel::updateTask): " . $stmt->error);
        } else if ($stmt->affected_rows === 0 && $student_id !== null) {
             // Optional: Check if rows were affected, might indicate task didn't belong to student
             error_log("Update task possibly failed: Task {$task_id} not found or does not belong to student {$student_id}.");
             // Return false if strict ownership is required for success indication
             // return false;
        }
        $stmt->close();
        // Return true even if 0 rows affected if query itself succeeded,
        // or return based on affected_rows if needed.
        return $success;
    }

    /**
     * Deletes a personal task.
     * @param int $task_id
     * @param int $student_id (Optional: For security check)
     * @return bool
     */
    public function deleteTask(int $task_id, ?int $student_id = null): bool
    {
        $sql = "DELETE FROM TaskLog WHERE task_id = ?";
         // Add student_id check for security
         if ($student_id !== null) {
             $sql .= " AND student_id = ?";
         }

        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed (TaskLogModel::deleteTask): " . $this->db->error);
            return false;
        }

         if ($student_id !== null) {
             $stmt->bind_param("ii", $task_id, $student_id);
         } else {
             $stmt->bind_param("i", $task_id);
         }

        $success = $stmt->execute();
        if (!$success) {
            error_log("Execute failed (TaskLogModel::deleteTask): " . $stmt->error);
        } else if ($stmt->affected_rows === 0 && $student_id !== null) {
             error_log("Delete task possibly failed: Task {$task_id} not found or does not belong to student {$student_id}.");
             // return false; // if strict ownership required
        }
        $stmt->close();
        return $success;
    }
}
?>
