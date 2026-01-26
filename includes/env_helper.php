
<?php
// includes/env_helper.php (Optional - Create this file)

/**
 * Get environment variable with default value
 */
function env($key, $default = null) {
    $value = $_ENV[$key] ?? getenv($key);
    
    if ($value === false) {
        return $default;
    }
    
    // Convert string boolean to actual boolean
    switch (strtolower($value)) {
        case 'true':
        case '(true)':
            return true;
        case 'false':
        case '(false)':
            return false;
        case 'empty':
        case '(empty)':
            return '';
        case 'null':
        case '(null)':
            return null;
    }
    
    return $value;
}

/**
 * Check if app is in production
 */
function isProduction() {
    return env('APP_ENV', 'production') === 'production';
}

/**
 * Check if app is in debug mode
 */
function isDebug() {
    return (bool) env('APP_DEBUG', false);
}
?>
