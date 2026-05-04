<?php
/**
 * Authentication Manager
 * Handles user authentication securely
 * Works on XAMPP and Hostinger
 */

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../config/Environment.php';

class AuthManager {
    private const SESSION_TIMEOUT = 3600; // 1 hour
    private const BCRYPT_COST = 10;

    /**
     * Register new user
     */
    public static function register(array $userData): array {
        // Validate input
        $validation = self::validateRegistration($userData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        try {
            Database::beginTransaction();

            // Check if user exists
            $existing = Database::queryOne(
                "SELECT id FROM users WHERE email = ? OR username = ?",
                [$userData['email'], $userData['username']]
            );

            if ($existing) {
                return [
                    'success' => false,
                    'error' => 'User with this email or username already exists'
                ];
            }

            // Hash password
            $passwordHash = password_hash($userData['password'], PASSWORD_BCRYPT, [
                'cost' => self::BCRYPT_COST
            ]);

            // Create user
            $userId = Database::insert('users', [
                'user_type' => $userData['user_type'] ?? 'student',
                'username' => $userData['username'],
                'email' => $userData['email'],
                'password_hash' => $passwordHash,
                'full_name' => $userData['full_name'],
                'contact_number' => $userData['contact_number'] ?? null,
                'is_active' => true,
                'is_verified' => false
            ]);

            // Create student profile if student type
            if (($userData['user_type'] ?? 'student') === 'student') {
                Database::insert('student_profiles', [
                    'user_id' => $userId,
                    'student_id' => $userData['student_id'] ?? null,
                    'course' => $userData['course'] ?? null,
                    'year_level' => $userData['year_level'] ?? null,
                    'application_status' => 'pending'
                ]);
            }

            Database::commit();

            return [
                'success' => true,
                'user_id' => $userId,
                'message' => 'User registered successfully'
            ];
        } catch (Exception $e) {
            Database::rollback();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Login user
     */
    public static function login(string $username, string $password): array {
        // Prevent session fixation
        session_regenerate_id(true);

        try {
            $user = Database::queryOne(
                "SELECT u.id, u.username, u.email, u.password_hash, u.full_name, u.user_type, 
                        u.is_active, sp.student_id, sp.course, sp.application_status
                 FROM users u
                 LEFT JOIN student_profiles sp ON u.id = sp.user_id
                 WHERE u.username = ? OR u.email = ?",
                [$username, $username]
            );

            if (!$user) {
                // Security: Don't reveal if user exists
                sleep(1);
                return [
                    'success' => false,
                    'error' => 'Invalid credentials'
                ];
            }

            if (!$user['is_active']) {
                return [
                    'success' => false,
                    'error' => 'Account is inactive'
                ];
            }

            if (!password_verify($password, $user['password_hash'])) {
                sleep(1);
                return [
                    'success' => false,
                    'error' => 'Invalid credentials'
                ];
            }

            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['login_time'] = time();

            if ($user['user_type'] === 'student') {
                $_SESSION['student_id'] = $user['student_id'];
                $_SESSION['course'] = $user['course'];
                $_SESSION['application_status'] = $user['application_status'];
            }

            return [
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'full_name' => $user['full_name'],
                    'user_type' => $user['user_type']
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check if user is authenticated
     */
    public static function isAuthenticated(): bool {
        if (empty($_SESSION['user_id'])) {
            return false;
        }

        // Check session timeout
        $timeout = (int) Environment::get('SESSION_TIMEOUT', self::SESSION_TIMEOUT);
        if (time() - ($_SESSION['login_time'] ?? 0) > $timeout) {
            self::logout();
            return false;
        }

        return true;
    }

    /**
     * Check if user is student
     */
    public static function isStudent(): bool {
        return self::isAuthenticated() && ($_SESSION['user_type'] ?? null) === 'student';
    }

    /**
     * Check if user is admin
     */
    public static function isAdmin(): bool {
        return self::isAuthenticated() && ($_SESSION['user_type'] ?? null) === 'admin';
    }

    /**
     * Get current user ID
     */
    public static function getUserId(): ?int {
        return self::isAuthenticated() ? (int) $_SESSION['user_id'] : null;
    }

    /**
     * Logout user
     */
    public static function logout(): void {
        session_destroy();
        $_SESSION = [];
    }

    /**
     * Validate registration data
     */
    private static function validateRegistration(array $data): array {
        $errors = [];

        // Username validation
        if (empty($data['username'])) {
            $errors[] = 'Username is required';
        } elseif (strlen($data['username']) < 3) {
            $errors[] = 'Username must be at least 3 characters';
        }

        // Email validation
        if (empty($data['email'])) {
            $errors[] = 'Email is required';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }

        // Password validation
        if (empty($data['password'])) {
            $errors[] = 'Password is required';
        } elseif (strlen($data['password']) < 8) {
            $errors[] = 'Password must be at least 8 characters';
        } elseif (!preg_match('/[A-Z]/', $data['password'])) {
            $errors[] = 'Password must contain uppercase letter';
        } elseif (!preg_match('/[0-9]/', $data['password'])) {
            $errors[] = 'Password must contain number';
        }

        // Full name validation
        if (empty($data['full_name'])) {
            $errors[] = 'Full name is required';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Change password
     */
    public static function changePassword(int $userId, string $oldPassword, string $newPassword): array {
        $validation = self::validatePasswordChange($oldPassword, $newPassword);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }

        try {
            $user = Database::queryOne("SELECT password_hash FROM users WHERE id = ?", [$userId]);
            
            if (!$user || !password_verify($oldPassword, $user['password_hash'])) {
                return [
                    'success' => false,
                    'error' => 'Current password is incorrect'
                ];
            }

            $newHash = password_hash($newPassword, PASSWORD_BCRYPT, [
                'cost' => self::BCRYPT_COST
            ]);

            Database::update('users', ['password_hash' => $newHash], 'id = ?', [$userId]);

            return [
                'success' => true,
                'message' => 'Password changed successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Validate password change
     */
    private static function validatePasswordChange(string $oldPassword, string $newPassword): array {
        $errors = [];

        if (empty($oldPassword)) {
            $errors[] = 'Current password is required';
        }

        if (empty($newPassword)) {
            $errors[] = 'New password is required';
        } elseif (strlen($newPassword) < 8) {
            $errors[] = 'New password must be at least 8 characters';
        }

        if ($oldPassword === $newPassword) {
            $errors[] = 'New password must be different from current password';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}
