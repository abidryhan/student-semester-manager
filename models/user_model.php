<?php
// ssm/models/user_model.php
// UPDATED for Phase 6: Class-based structure, password hashing, user creation.

class UserModel
{
    private $db; // Database connection object

    /**
     * Constructor
     * @param mysqli $conn Database connection object
     */
    public function __construct(mysqli $conn)
    {
        $this->db = $conn;
    }

    /**
     * Creates a new user with a hashed password.
     *
     * @param string $email User's email (should be unique).
     * @param string $password Plain text password.
     * @param string $role User's role ('admin' or 'student').
     * @return bool True on success, false on failure (e.g., duplicate email, DB error).
     */
    public function createUser(string $email, string $password, string $role): bool
    {
        // Hash the password securely
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        if ($hashed_password === false) {
             error_log("Password hashing failed.");
             return false; // Indicate hashing failure
        }

        $sql = "INSERT INTO Users (email, password, role) VALUES (?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed (UserModel::createUser): " . $this->db->error);
            return false;
        }

        $stmt->bind_param("sss", $email, $hashed_password, $role);
        $success = $stmt->execute();
        if (!$success) {
            // Log error (MySQL error code 1062 likely indicates duplicate email)
            error_log("Execute failed (UserModel::createUser): (" . $stmt->errno . ") " . $stmt->error);
        }
        $stmt->close();
        return $success;
    }

    /**
     * Authenticates a user using email and password.
     * Verifies the password against the stored hash.
     *
     * @param string $email User's email.
     * @param string $password Plain text password entered by the user.
     * @return array|false User data array (user_id, email, role) on success, false on failure.
     */
    public function authenticateUser(string $email, string $password): array|false
    {
        $sql = "SELECT user_id, email, password, role FROM Users WHERE email = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed (UserModel::authenticateUser): " . $this->db->error);
            return false;
        }

        $stmt->bind_param("s", $email);
        if (!$stmt->execute()) {
            error_log("Execute failed (UserModel::authenticateUser): " . $stmt->error);
            $stmt->close();
            return false;
        }

        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        // Verify user exists and password matches the hash
        if ($user && password_verify($password, $user['password'])) {
            // Password is correct! Return user data (excluding the password hash)
            return [
                'user_id' => $user['user_id'],
                'email' => $user['email'],
                'role' => $user['role']
            ];
        } else {
            // Invalid email or password
            return false;
        }
    }

     /**
      * Checks if an email address already exists in the Users table.
      *
      * @param string $email Email address to check.
      * @return bool True if email exists, false otherwise.
      */
     public function checkEmailExists(string $email): bool
     {
         $sql = "SELECT user_id FROM Users WHERE email = ? LIMIT 1";
         $stmt = $this->db->prepare($sql);
         if (!$stmt) {
             error_log("Prepare failed (UserModel::checkEmailExists): " . $this->db->error);
             // Depending on requirements, might return true to prevent signup on error
             return true;
         }
         $stmt->bind_param("s", $email);
         if (!$stmt->execute()) {
             error_log("Execute failed (UserModel::checkEmailExists): " . $stmt->error);
             $stmt->close();
             return true; // Prevent signup on error
         }
         $stmt->store_result(); // Needed to check num_rows
         $exists = $stmt->num_rows > 0;
         $stmt->close();
         return $exists;
     }

} // End UserModel Class
?>
