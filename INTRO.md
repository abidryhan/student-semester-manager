# Student Semester Manager (SSM) Project - Introduction

## Welcome, Junior SWE!

Welcome to the Student Semester Manager (SSM) project! As a Junior Software Engineer, you’ll be working on building a web application designed to help students and admins manage academic semesters efficiently. This document provides the background, goals, and context for the project to help you get started.

---

## Project Background

The SSM project is a web-based application aimed at simplifying semester management for students and administrators at a university. The goal is to create a system where:

- **Admins** can manage courses, define grading weights (e.g., quiz, midterm, final), assign tasks (e.g., exams, quizzes), and enter grades for students.
- **Students** can enroll in courses, view their grades, see upcoming tasks, and receive notifications (e.g., task reminders).
- The system prioritizes **functionality over aesthetics**, focusing on database manipulation, smart SQL queries, and a simple, easy-to-navigate user interface.

This is **version 0.1** of the project, meaning our primary focus is on building a functional prototype with core features. We’re not aiming for a fancy frontend yet—just a clean, minimalistic, and useful system that works well for both admins and students.

---

## Project Goals for Version 0.1

The main objectives for version 0.1 are:

1. **Core Functionality**:
   - Build a login system to distinguish between admins and students.
   - Allow admins to manage courses, grading weights, and tasks.
   - Allow students to enroll in courses, view grades, tasks, and notifications.
   - Calculate weighted grades for students based on task scores and weights.

2. **Database-Driven Design**:
   - Use the existing MariaDB database (`studentsemestermanager`) with predefined tables.
   - Write secure, efficient SQL queries using prepared statements to interact with the database.
   - Focus on smart data retrieval and manipulation (e.g., calculating grades, filtering tasks).

3. **Simple User Interface**:
   - Create a minimalistic frontend that’s easy to navigate.
   - Use Tailwind CSS for styling to achieve a clean, professional look with minimal effort.
   - Add JavaScript only for essential interactivity (e.g., form validation, confirmation prompts).

4. **Security**:
   - Implement user authentication with PHP sessions.
   - Use prepared statements to prevent SQL injection.
   - Hash passwords (note: we’ll need to update the sample data to use hashed passwords).
   - Validate user inputs on both client (JS) and server (PHP) sides.

5. **Development Approach**:
   - Build the application in phases (setup, login, admin features, student features, etc.).
   - Test each phase thoroughly before moving to the next.

---

## Tech Stack

Here’s the technology stack we’re using for the project:

- **Backend**: PHP
  - PHP is used for server-side logic and database interactions.
  - We’re running on XAMPP with Apache, so PHP is already set up.
- **Database**: MariaDB (via XAMPP)
  - The database is named `studentsemestermanager` and is already populated with sample data.
  - We’re using MySQLi for database interactions (with the option to switch to PDO later for flexibility).
- **Frontend**: HTML + Tailwind CSS + JavaScript (as needed)
  - HTML for structure, Tailwind CSS (via CDN) for styling, and JavaScript for basic interactivity.
  - Tailwind CSS provides a utility-first approach to styling, making it quick to build a clean UI.
- **Security**:
  - PHP sessions for user authentication.
  - Prepared statements for secure SQL queries.
  - Password hashing with `password_hash()` and `password_verify()`.

---

## Database Schema Overview

The `studentsemestermanager` database is already set up in MariaDB (via XAMPP) and contains the following tables with sample data. Here’s a summary of the schema and what each table does:

1. **Users**:
   - Stores user information (admins and students).
   - Columns: `user_id` (PK, auto-increment), `email` (unique), `password`, `role` (either `admin` or `student`).
   - Sample Data:
     - Admins: `admin1@example.com`/`adminpass1`, `admin2@example.com`/`adminpass2`.
     - Students: `student1@example.com`/`studpass1`, `student2@example.com`/`studpass2`.

2. **Courses**:
   - Stores course details managed by admins.
   - Columns: `course_id` (PK, auto-increment), `course_name`, `semester`, `section`, `managed_by` (FK to `Users.user_id`).
   - Sample Data:
     - Math 101 (Fall2025, Section A, managed by admin1).
     - Physics 101 (Fall2025, Section B, managed by admin2).

3. **StudentCourses**:
   - Tracks which students are enrolled in which courses.
   - Columns: `student_id` (FK to `Users.user_id`), `course_id` (FK to `Courses.course_id`), composite PK (`student_id`, `course_id`).
   - Sample Data:
     - Student1 enrolled in Math 101 and Physics 101.
     - Student2 enrolled in Math 101.

4. **CourseWeights**:
   - Defines grading weights for each course (e.g., quiz 20%, midterm 30%, final 50%).
   - Columns: `weight_id` (PK, auto-increment), `course_id` (FK to `Courses.course_id`), `container_type` (e.g., quiz, midterm), `percentage`.
   - Sample Data:
     - Math 101: quiz (20%), midterm (30%), final (50%).
     - Physics 101: quiz (25%), lab (25%), final (50%).

5. **Tasks**:
   - Stores tasks (e.g., quizzes, exams) for each course.
   - Columns: `task_id` (PK, auto-increment), `course_id` (FK), `weight_id` (FK to `CourseWeights.weight_id`), `task_name`, `due_date`, `status` (e.g., pending).
   - Sample Data:
     - Quiz 1 and Midterm Exam for Math 101.
     - Quiz 1 and Lab 1 for Physics 101.

6. **Grades**:
   - Stores student grades for tasks.
   - Columns: `grade_id` (PK, auto-increment), `task_id` (FK), `student_id` (FK), `score`.
   - Sample Data:
     - Student1: 85 on Quiz 1, 78 on Midterm (Math 101).
     - Student2: 90 on Quiz 1 (Math 101).

7. **Notifications**:
   - Stores task reminders for students.
   - Columns: `notification_id` (PK, auto-increment), `task_id` (FK), `student_id` (FK), `sent_time`, `message`.
   - Sample Data:
     - Reminders for Quiz 1 (both students) and Midterm (Student1).

You can explore the database in phpMyAdmin (`http://localhost/phpmyadmin`) to view the tables and data.

---

## Key Features to Implement

For version 0.1, we’ll focus on the following features:

1. **User Authentication**:
   - A login system to distinguish admins and students.
   - Admins manage courses; students view grades and tasks.

2. **Admin Features**:
   - Manage courses (add, edit, delete).
   - Define grading weights for courses.
   - Add tasks (e.g., quizzes, exams) and enter grades for students.

3. **Student Features**:
   - Enroll in courses.
   - View enrolled courses, grades, upcoming tasks, and notifications.
   - See weighted grades based on task scores and weights.

4. **Security and Validation**:
   - Secure login with session management.
   - Use prepared statements for all database queries.
   - Validate user inputs to prevent errors or malicious data.

---

## Development Strategy

We’ll build the application in phases to ensure each feature is fully functional before moving to the next:

1. **Phase 1: Project Setup and Initial Structure** (Current Phase):
   - Set up the project structure in XAMPP’s `htdocs`.
   - Configure the database connection.
   - Create a basic homepage (`index.php`).

2. **Phase 2: User Authentication**:
   - Build a login system with role-based redirection (admin or student).
   - Add form validation with JavaScript.

3. **Phase 3: Admin Dashboard and Course Management**:
   - Create an admin dashboard to display and manage courses.
   - Allow admins to add, edit, and delete courses.

4. **Phase 4: Student Dashboard and Course Enrollment**:
   - Create a student dashboard to show enrolled courses, grades, tasks, and notifications.
   - Allow students to enroll in courses.

5. **Phase 5: Task Management and Grade Calculation**:
   - Enable admins to add tasks and enter grades.
   - Calculate and display weighted grades for students.

6. **Phase 6: Testing and Final Touches**:
   - Test all features for functionality and security.
   - Add navigation (e.g., logout) and fix any bugs.

---

## Codebase Structure

Here’s the initial structure of the project, which you’ve already set up:

ssm/
├── INTRO.md               # This introduction file
├── index.php              # Main entry point (homepage)
├── config.php             # Database connection configuration
├── models/                # Database interaction logic
│   ├── user_model.php     # User-related functions (e.g., login)
│   ├── course_model.php   # Course-related functions (e.g., fetch courses)
├── views/                 # HTML/PHP templates (pages)
│   ├── login.php          # Login page
│   ├── admin_dashboard.php # Admin dashboard page
│   ├── student_dashboard.php # Student dashboard page
│   ├── manage_courses.php  # Page for managing courses
│   ├── manage_weights.php  # Page for managing weights
│   ├── manage_tasks.php    # Page for managing tasks
│   ├── enroll_course.php   # Page for enrolling in courses
│   ├── view_grades.php     # Page for viewing grades
│   ├── view_notifications.php # Page for viewing notifications
├── public/                # Static assets
│   ├── scripts.js         # JavaScript for interactivity


- **Root Files**:
  - `index.php`: The homepage users see when they visit the site.
  - `config.php`: Database connection setup (MariaDB).
  - `INTRO.md`: This file for project context.
- **models/**: Contains PHP files with functions for database operations (e.g., querying users, courses).
- **views/**: Contains PHP/HTML files for each page of the application.
- **public/**: Contains static assets like JavaScript files.

---

## Tips for Development

1. **Database Access**:
   - Use phpMyAdmin (`http://localhost/phpmyadmin`) to view the `studentsemestermanager` database and test queries.
   - Always use prepared statements for SQL queries to prevent SQL injection.

2. **Security**:
   - Validate all user inputs on both the client (JavaScript) and server (PHP) sides.
   - Use PHP sessions to manage user authentication.
   - Hash passwords (we’ll update the sample data to use hashed passwords later).

3. **Frontend**:
   - Use Tailwind CSS via CDN (`https://cdn.tailwindcss.com`) for styling.
   - Keep the UI simple and minimalistic, focusing on functionality.
   - Add JavaScript only for essential features (e.g., form validation).

4. **Testing**:
   - Test each feature as you build it to catch issues early.
   - Use the sample data for testing (e.g., log in as `admin1@example.com` or `student1@example.com`).

---

## Next Steps

You’re currently in **Phase 1: Project Setup and Initial Structure**. Your tasks are:

1. Configure the database connection in `config.php` and test it.
2. Create the homepage in `index.php` with a welcome message and a button to the login page.
3. Test the setup by accessing `http://localhost/ssm` in the browser.

Once you’ve completed Phase 1, I’ll provide instructions for Phase 2 (User Authentication), including the updated codebase structure. Let me know if you have any questions as you work through this project!

Happy coding! 🚀
