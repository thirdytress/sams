<?php
/**
 * Environment Configuration Loader
 * Loads .env file and provides environment variables
 * Works on both XAMPP (localhost) and Hostinger (production)
 */

class Environment {
    private static array $config = [];
    private static bool $loaded = false;

    /**
     * Load environment variables from .env file
     */
    public static function load(string $envPath = ''): void {
        if (self::$loaded) {
            return;
        }

        if (empty($envPath)) {
            $envPath = dirname(__DIR__) . '/.env';
        }

        if (!file_exists($envPath)) {
            throw new Exception(".env file not found at: {$envPath}");
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parse KEY=VALUE
            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Remove quotes if present
                if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
                    (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
                    $value = substr($value, 1, -1);
                }

                self::$config[$key] = $value;
            }
        }

        self::$loaded = true;
    }

    /**
     * Get environment variable
     */
    public static function get(string $key, $default = null) {
        if (!self::$loaded) {
            self::load();
        }

        return self::$config[$key] ?? $_ENV[$key] ?? $default;
    }

    /**
     * Get all configuration
     */
    public static function all(): array {
        if (!self::$loaded) {
            self::load();
        }
        return self::$config;
    }

    /**
     * Check if in debug mode
     */
    public static function isDebug(): bool {
        return self::get('APP_DEBUG', false) === 'true' || self::get('APP_DEBUG') === true;
    }

    /**
     * Check if in production
     */
    public static function isProduction(): bool {
        return self::get('APP_ENV', 'development') === 'production';
    }
}

// Auto-load environment
Environment::load();
