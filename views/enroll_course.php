<?php
// ssm/views/enroll_course.php
require_once '../config.php';
require_once '../models/course_model.php';

// --- Security Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$error_message = '';
$success_message = ''; // Optional: if needed

// --- Handle Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['course_id']) && !empty($_POST['course_id'])) {
        $course_id_to_enroll = (int)$_POST['course_id'];

        // Attempt to enroll the student
        if (enrollStudent($conn, $student_id, $course_id_to_enroll)) {
            // Enrollment successful, redirect back to dashboard
            header("Location: student_dashboard.php?status=enrolled");
            exit();
        } else {
            // Enrollment failed (likely already enrolled or DB error)
            $error_message = "Enrollment failed. You may already be enrolled in this course, or a database error occurred.";
            // Log the specific DB error on the server if possible (from enrollStudent function)
        }
    } else {
        $error_message = "Please select a course to enroll in.";
    }
}

// --- Fetch Available Courses for the Dropdown ---
$available_courses = getAvailableCourses($conn, $student_id);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enroll in Course - SSM</title>
    <script src="https://cdn.tailwindcss.com"></script>
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">

    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <h1 class="text-2xl font-bold text-center text-gray-800 mb-6">Enroll in a Course</h1>

        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>

         <?php if (!empty($available_courses)): ?>
            <form action="enroll_course.php" method="POST">
                <div class="mb-4">
                    <label for="course_id" class="block text-gray-700 text-sm font-bold mb-2">Available Courses:</label>
                    <select name="course_id" id="course_id" required
                            class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline focus:border-blue-500 focus:ring-1 focus:ring-blue-500 appearance-none bg-white">
                        <option value="" disabled selected>-- Select a Course --</option>
                        <?php foreach ($available_courses as $course): ?>
                            <option value="<?php echo $course['course_id']; ?>">
                                <?php echo htmlspecialchars($course['course_name'] . ' - ' . $course['semester'] . ' (' . $course['section'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                     <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700" style="position: relative; top: -2.3rem; float: right;">
                        <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg>
                    </div>
                </div>

                <div class="flex items-center justify-between mt-6">
                    <button type="submit"
                            class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-150 ease-in-out">
                        <i class="fas fa-check-circle mr-1"></i> Enroll
                    </button>
                </div>
            </form>
        <?php else: ?>
            <p class="text-center text-gray-500 my-6">There are no courses currently available for you to enroll in.</p>
        <?php endif; ?>

        <div class="text-center mt-6">
            <a href="student_dashboard.php" class="inline-block align-baseline font-bold text-sm text-blue-500 hover:text-blue-800 hover:underline">
                &larr; Back to Dashboard
            </a>
        </div>

    </div>

</body>
</html>
