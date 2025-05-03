// ssm/public/scripts.js

/**
 * Validates the login form fields (email and password).
 * Checks for non-empty fields and basic email format (@ symbol).
 * @returns {boolean} Returns true if validation passes, false otherwise.
 */
function validateLoginForm() {
    // ... (Keep existing function code) ...
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    if (!emailInput || !passwordInput) { console.error("Login form elements not found"); alert("An error occurred."); return false; }
    const email = emailInput.value.trim();
    const password = passwordInput.value.trim();
    if (email === '' || password === '') { alert('Please fill in both fields.'); return false; }
    if (email.indexOf('@') === -1) { alert('Please enter a valid email.'); emailInput.focus(); return false; }
    return true;
}


/**
 * Validates the signup form fields (email and password).
 * Checks email format and minimum password length.
 * @returns {boolean} Returns true if validation passes, false otherwise.
 */
function validateSignupForm() {
    // ... (Keep existing function code) ...
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    if (!emailInput || !passwordInput) { console.error("Signup form elements not found"); alert("An error occurred."); return false; }
    const email = emailInput.value.trim();
    const password = passwordInput.value;
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (email === '') { alert("Please enter email."); emailInput.focus(); return false; }
    if (!emailRegex.test(email)) { alert("Please enter a valid email."); emailInput.focus(); return false; }
    if (password === '') { alert("Please enter password."); passwordInput.focus(); return false; }
    if (password.length < 8) { alert("Password must be at least 8 characters."); passwordInput.focus(); return false; }
    return true;
}

/**
 * Validates the course management form fields (days and time slot).
 * Checks if a non-default option has been selected for both dropdowns.
 * @returns {boolean} Returns true if validation passes, false otherwise.
 */
function validateCourseForm() {
    const daysSelect = document.getElementById('days_option');
    const timeSelect = document.getElementById('time_slot');
    let isValid = true;
    let message = "Please ensure the following fields are selected:\n";

    if (!daysSelect || !timeSelect) {
        console.error("Course form elements (days/time) not found");
        alert("An error occurred validating the form.");
        return false;
    }

    // Reset previous error styles (optional, but good practice)
    daysSelect.classList.remove('border-red-500');
    timeSelect.classList.remove('border-red-500');

    if (daysSelect.value === '') {
        message += "- Days\n";
        daysSelect.classList.add('border-red-500'); // Add error indication
        isValid = false;
    }

    if (timeSelect.value === '') {
        message += "- Time Slot\n";
        timeSelect.classList.add('border-red-500'); // Add error indication
        isValid = false;
    }

    if (!isValid) {
        alert(message);
        // Optionally focus the first invalid field
        if (daysSelect.value === '') {
            daysSelect.focus();
        } else {
            timeSelect.focus();
        }
    }

    console.log(`Course form validation result: ${isValid}`);
    return isValid;
}

/**
 * Toggles the visibility of the notification details section.
 * Added in Phase 5 support.
 */
function toggleNotifications() {
    const detailsDiv = document.getElementById('notification-details');
    if (detailsDiv) {
        detailsDiv.classList.toggle('hidden');
    } else {
        console.error("Notification details element not found.");
    }
}

/**
 * Toggles the visibility of the grades section on the student dashboard.
 * Added in Phase 7.
 */
function toggleGrades() {
    const gradesSection = document.getElementById('grades-section');
    if (gradesSection) {
        gradesSection.classList.toggle('hidden');
    } else {
        console.error("Grades section element not found.");
    }
}


// Add other global JavaScript functions below as needed

document.addEventListener('DOMContentLoaded', function() {
    console.log("DOM fully loaded and parsed.");

    // Handle Final Grade Submission Confirmation
    const gradeForms = document.querySelectorAll('.submit-final-grades-form');
    console.log(`Found ${gradeForms.length} final grade submission forms.`);

    gradeForms.forEach(form => {
        form.addEventListener('submit', function(event) {
            event.preventDefault(); // Stop default form submission

            const courseName = form.dataset.courseName || 'this course'; // Fallback name
            const ungradedCount = parseInt(form.dataset.ungradedCount, 10);
            const courseId = form.dataset.courseId; // Get course ID for potential logging

            console.log(`Attempting final grade submission for: ${courseName} (ID: ${courseId}), Ungraded: ${ungradedCount}`);

            let proceed = true; // Flag to track if submission should proceed

            // Step 1: Warning if ungraded tasks exist
            if (ungradedCount > 0) {
                const warningMsg = `Warning: There are ${ungradedCount} ungraded task instances for '${courseName}'.\n\nSubmitting now will treat these as 0% for the final grade calculation.\n\nDo you want to proceed anyway?`;
                proceed = confirm(warningMsg);
                console.log(`Ungraded task warning shown. User proceed decision: ${proceed}`);
            }

            // Step 2: Final confirmation if warning passed or wasn't needed
            if (proceed) {
                const finalConfirmMsg = `Are you sure you want to calculate and submit final grades for '${courseName}'?\n\nThis action cannot be undone.`;
                proceed = confirm(finalConfirmMsg);
                console.log(`Final confirmation shown. User proceed decision: ${proceed}`);
            }

            // Step 3: Submit form if all confirmations passed
            if (proceed) {
                console.log(`Submitting form for course: ${courseName} (ID: ${courseId})`);
                form.submit(); // Submit the form programmatically
            } else {
                console.log(`Submission cancelled by user for course: ${courseName} (ID: ${courseId})`);
            }
        });
    });

    // Add other DOM-dependent initializations here if needed

}); // End DOMContentLoaded


console.log("scripts.js loaded successfully.");
