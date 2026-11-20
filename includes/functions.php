<?php
/**
 * Helper Functions
 * Event Management System 007
 */

/**
 * Get base URL of the application (always returns project root)
 * @return string
 */
function getBaseUrl() {
    $forwardedProtocol = getenv('APP_TRUST_PROXY') === '1'
        ? strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')[0]))
        : '';
    $isHttps = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
        || $forwardedProtocol === 'https';
    $protocol = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (!preg_match('/\A(?:[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?|\[[a-f0-9:]+\])(?::[0-9]{1,5})?\z/i', $host)) {
        $host = 'localhost';
    }
    $basePath = getenv('APP_BASE_PATH');

    if ($basePath === false) {
        $documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
        $applicationRoot = realpath(__DIR__ . '/..');

        if ($documentRoot !== false && $applicationRoot !== false
            && strpos($applicationRoot, $documentRoot) === 0) {
            $basePath = substr($applicationRoot, strlen($documentRoot));
        } else {
            $basePath = '';
        }
    }

    $basePath = '/' . trim(str_replace('\\', '/', $basePath), '/');
    return $protocol . '://' . $host . ($basePath === '/' ? '' : $basePath) . '/';
}

/**
 * Get asset URL
 * @param string $path
 * @return string
 */
function getAssetUrl($path) {
    $assetPath = ltrim($path, '/');
    $filePath = __DIR__ . '/../assets/' . str_replace('/', DIRECTORY_SEPARATOR, $assetPath);
    $version = is_file($filePath) ? filemtime($filePath) : false;
    $url = getBaseUrl() . 'assets/' . $assetPath;

    return $version !== false ? $url . '?v=' . $version : $url;
}

/** Return a rotating set of photographic event covers for event cards. */
function getEventImageUrl($eventId, $coverImagePath = null, $category = null) {
    if (is_string($coverImagePath)
        && preg_match('/\Aevent-covers\/[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $coverImagePath)) {
        $localPath = __DIR__ . '/../assets/' . str_replace('/', DIRECTORY_SEPARATOR, $coverImagePath);
        if (is_file($localPath)) {
            return getAssetUrl($coverImagePath);
        }
    }

    $categoryImages = [
        'Arts & Culture' => 'photo-1503095396549-807759245b35',
        'Community' => 'photo-1511632765486-a01980e01a18',
        'Food & Drink' => 'photo-1517248135467-4c7edcad34c4',
        'Health & Wellness' => 'photo-1544367567-0f2fcb009e0b',
        'Learning' => 'photo-1524178232363-1fb2b075b655',
        'Music' => 'photo-1506157786151-b8491531f063',
        'Nightlife' => 'photo-1470229722913-7c0e2dbbafd3',
        'Sports' => 'photo-1461896836934-ffe607ba821e',
        'Technology' => 'photo-1540575467063-178a50c2df87',
        'Other' => 'photo-1511795409834-ef04bbd61622',
    ];
    $image = $categoryImages[$category] ?? $categoryImages['Other'];
    if (!isset($categoryImages[$category ?? ''])) {
        $fallbackImages = array_values($categoryImages);
        $image = $fallbackImages[abs((int)$eventId) % count($fallbackImages)];
    }
    return 'https://images.unsplash.com/' . $image . '?auto=format&fit=crop&w=1000&q=84';
}

function getEventCategories() {
    return ['Arts & Culture', 'Community', 'Food & Drink', 'Health & Wellness', 'Learning', 'Music', 'Nightlife', 'Sports', 'Technology', 'Other'];
}

/** Normalize PostgreSQL boolean values when the driver returns native bools or t/f strings. */
function isDatabaseBooleanTrue($value) {
    return $value === true || $value === 1 || $value === '1'
        || (is_string($value) && in_array(strtolower($value), ['t', 'true', 'yes'], true));
}

function expireStaleReservations() {
    try {
        fetchOne('SELECT ExpireReservations_007() AS expired_count');
    } catch (Throwable $e) {
        error_log('Reservation expiry task could not run.');
    }
}

function saveEventCoverUpload($file) {
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
        || !isset($file['tmp_name'], $file['size'])
        || (int)$file['size'] < 1 || (int)$file['size'] > 5 * 1024 * 1024
        || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Choose an image smaller than 5 MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime]) || @getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('Use a valid JPEG, PNG, or WebP image.');
    }

    $uploadDirectory = __DIR__ . '/../assets/event-covers';
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
        throw new RuntimeException('Image storage is unavailable.');
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], $uploadDirectory . '/' . $filename)) {
        throw new RuntimeException('The event image could not be saved.');
    }
    return 'event-covers/' . $filename;
}

/**
 * Redirect to a URL
 * @param string $url
 */
function redirect($url) {
    header("Location: " . $url);
    exit;
}

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if user has specific role
 * @param string|array $roles
 * @return bool
 */
function hasRole($roles) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $userRole = $_SESSION['user_role'] ?? '';
    
    if (is_array($roles)) {
        return in_array($userRole, $roles);
    }
    
    return $userRole === $roles;
}

/**
 * Require user to be logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        setFlashMessage('warning', 'Please login to access this page.');
        redirect(getBaseUrl() . 'login.php');
    }
}

/**
 * Require specific role(s)
 * @param string|array $roles
 */
function requireRole($roles) {
    requireLogin();
    
    if (!hasRole($roles)) {
        setFlashMessage('danger', 'You do not have permission to access this page.');
        
        // Redirect to appropriate dashboard based on role
        $userRole = $_SESSION['user_role'] ?? '';
        switch ($userRole) {
            case 'admin':
                redirect(getBaseUrl() . 'admin/dashboard.php');
                break;
            case 'organizer':
                redirect(getBaseUrl() . 'organizer/dashboard.php');
                break;
            case 'attendee':
                redirect(getBaseUrl() . 'attendee/dashboard.php');
                break;
            default:
                redirect(getBaseUrl() . 'login.php');
        }
    }
}

/**
 * Set flash message
 * @param string $type
 * @param string $message
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Display flash message and clear it
 */
function displayFlashMessages() {
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        echo '<div class="alert alert-' . htmlspecialchars($flash['type']) . ' alert-dismissible fade show" role="alert">';
        echo htmlspecialchars($flash['message']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
        unset($_SESSION['flash_message']);
    }
}

/**
 * Sanitize input
 * @param string $data
 * @return string
 */
function sanitize($data) {
    return trim(strip_tags((string)$data));
}

function parseEventDateTime($value) {
    if (!is_string($value) || $value === '') {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', $value, new DateTimeZone('Africa/Accra'));
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        || $date->format('Y-m-d\\TH:i') !== $value) {
        return null;
    }
    return $date->format('Y-m-d H:i:s');
}

function redactAuditValues($values) {
    if (!is_array($values)) {
        return $values;
    }
    foreach ($values as $key => $value) {
        if (preg_match('/password|secret|token|authorization/i', (string)$key)) {
            unset($values[$key]);
        } elseif (is_array($value)) {
            $values[$key] = redactAuditValues($value);
        }
    }
    return $values;
}

/**
 * Validate email format
 * @param string $email
 * @return bool
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Get database connection
 * @return PDO
 */

/**
 * Execute query and return single row
 * @param string $sql
 * @param array $params
 * @return array|null
 */
function fetchOne($sql, $params = []) {
    if (!function_exists('getDbConnection')) {
        require_once __DIR__ . '/../config/database.php';
    }
    $stmt = getDbConnection()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch() ?: null;
}

/**
 * Execute query and return all rows
 * @param string $sql
 * @param array $params
 * @return array
 */
function fetchAll($sql, $params = []) {
    if (!function_exists('getDbConnection')) {
        require_once __DIR__ . '/../config/database.php';
    }
    $stmt = getDbConnection()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Execute insert/update/delete query
 * @param string $sql
 * @param array $params
 * @return int|bool
 */
function executeQuery($sql, $params = []) {
    if (!function_exists('getDbConnection')) {
        require_once __DIR__ . '/../config/database.php';
    }
    $stmt = getDbConnection()->prepare($sql);
    return $stmt->execute($params);
}

/**
 * Hash password securely
 * @param string $password
 * @return string
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify password
 * @param string $password
 * @param string $hash
 * @return bool
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Format date for display
 * @param string $date
 * @param string $format
 * @return string
 */
function formatDate($date, $format = 'M d, Y h:i A') {
    if (empty($date)) {
        return '-';
    }
    return date($format, strtotime($date));
}

/**
 * Format currency (Ghana Cedis)
 * @param float $amount
 * @return string
 */
function formatCurrency($amount) {
    return 'GH₵' . number_format($amount, 2);
}

/**
 * Format phone number
 * @param string $phone
 * @return string
 */
function formatPhone($phone) {
    if (empty($phone)) {
        return '-';
    }
    return preg_replace('/(\d{3})(\d{3})(\d{4})/', '$1-$2-$3', $phone);
}

/**
 * Get status badge class
 * @param string $status
 * @return string
 */
function getStatusBadgeClass($status) {
    $statusClasses = [
        'published' => 'success',
        'draft' => 'secondary',
        'cancelled' => 'danger',
        'expired' => 'secondary',
        'completed' => 'info',
        'booked' => 'success',
        'confirmed' => 'success',
        'checked_in' => 'info',
        'used' => 'secondary',
        'pending' => 'warning',
        'refund_pending' => 'warning',
        'failed' => 'danger',
        'refunded' => 'secondary'
    ];
    
    return $statusClasses[$status] ?? 'secondary';
}

/**
 * Get loyalty tier based on points
 * @param int $points
 * @return string
 */
function getLoyaltyTier($points) {
    if ($points >= 1000) {
        return 'Platinum';
    } elseif ($points >= 500) {
        return 'Gold';
    } elseif ($points >= 100) {
        return 'Silver';
    } else {
        return 'Bronze';
    }
}

/**
 * Generate ticket number
 * @param int $eventId
 * @return string
 */
function generateTicketNumber($eventId) {
    return 'TKT-' . $eventId . '-' . time() . '-' . rand(100, 999);
}

/**
 * Check if event has available tickets
 * @param int $eventId
 * @return bool
 */
function hasAvailableTickets($eventId) {
    $sql = "SELECT (max_capacity - (SELECT COUNT(*) FROM Tickets_007 WHERE event_id = :event_id1 AND ticket_status != 'cancelled')) AS available 
            FROM Events_007 WHERE event_id = :event_id2";
    $result = fetchOne($sql, [':event_id1' => $eventId, ':event_id2' => $eventId]);
    
    return $result && $result['available'] > 0;
}

/**
 * Get available tickets count for an event
 * @param int $eventId
 * @return int
 */
function getAvailableTickets($eventId) {
    $sql = "SELECT (max_capacity - (SELECT COUNT(*) FROM Tickets_007 WHERE event_id = :event_id1 AND ticket_status != 'cancelled')) AS available 
            FROM Events_007 WHERE event_id = :event_id2";
    $result = fetchOne($sql, [':event_id1' => $eventId, ':event_id2' => $eventId]);
    
    return $result ? max(0, (int)$result['available']) : 0;
}

/**
 * Get current user's ID
 * @return int|null
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user's role
 * @return string|null
 */
function getCurrentUserRole() {
    return $_SESSION['user_role'] ?? null;
}

/**
 * Get current user's name
 * @return string|null
 */
function getCurrentUserName() {
    return $_SESSION['user_name'] ?? null;
}

/**
 * Log action to audit log
 * @param int $userId
 * @param string $userType
 * @param string $actionType
 * @param string $tableName
 * @param int $recordId
 * @param array $oldValues
 * @param array $newValues
 */
function logAuditAction($userId, $userType, $actionType, $tableName = null, $recordId = null, $oldValues = [], $newValues = []) {
    $oldValues = redactAuditValues($oldValues);
    $newValues = redactAuditValues($newValues);
    $sql = "INSERT INTO Audit_Log_007 
            (user_id, user_type, action_type, table_name, record_id, old_values, new_values, ip_address, user_agent) 
            VALUES (:user_id, :user_type, :action_type, :table_name, :record_id, CAST(:old_values AS JSONB), CAST(:new_values AS JSONB), :ip_address, :user_agent)";
    
    $stmt = getDbConnection()->prepare($sql);
    $stmt->execute([
        ':user_id' => $userId,
        ':user_type' => $userType,
        ':action_type' => $actionType,
        ':table_name' => $tableName,
        ':record_id' => $recordId,
        ':old_values' => json_encode($oldValues),
        ':new_values' => json_encode($newValues),
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
}

/**
 * Get user IP address
 * @return string
 */
function getUserIP() {
    return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
}

/**
 * Truncate text with ellipsis
 * @param string $text
 * @param int $length
 * @return string
 */
function truncateText($text, $length = 100) {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . '...';
}

/**
 * Convert array to HTML attributes
 * @param array $attributes
 * @return string
 */
function arrayToAttributes($attributes) {
    $html = '';
    foreach ($attributes as $key => $value) {
        $html .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
    }
    return $html;
}

/**
 * Create nonce for CSRF protection
 */
function createNonce() {
    if (empty($_SESSION['nonce'])) {
        $_SESSION['nonce'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['nonce'];
}

/**
 * Verify nonce for CSRF protection
 * @param string $nonce
 * @return bool
 */
function verifyNonce($nonce) {
    return isset($_SESSION['nonce']) && hash_equals($_SESSION['nonce'], $nonce);
}

/**
 * Generate CSRF token field
 * @return string
 */
function csrfTokenField() {
    $nonce = createNonce();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($nonce) . '">';
}

/**
 * Validate CSRF token from form
 * @return bool
 */
function validateCsrfToken() {
    if (!isset($_POST['csrf_token'])) {
        return false;
    }
    return verifyNonce($_POST['csrf_token']);
}

function getLoginRateLimitKey() {
    return hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

function isLoginRateLimited() {
    try {
        $attempt = fetchOne(
            'SELECT CASE WHEN locked_until > CURRENT_TIMESTAMP THEN 1 ELSE 0 END AS is_locked FROM LoginAttempts_007 WHERE ip_hash = :ip_hash',
            [':ip_hash' => getLoginRateLimitKey()]
        );
        return $attempt && (int)$attempt['is_locked'] === 1;
    } catch (Throwable $e) {
        error_log('Login rate limit storage is unavailable.');
        return true;
    }
}

function recordLoginFailure() {
    $sql = "INSERT INTO LoginAttempts_007 (ip_hash, attempts, window_started, locked_until)
            VALUES (:ip_hash, 1, CURRENT_TIMESTAMP, NULL)
            ON CONFLICT (ip_hash) DO UPDATE SET
                attempts = CASE WHEN LoginAttempts_007.window_started < CURRENT_TIMESTAMP - INTERVAL '15 minutes'
                    THEN 1 ELSE LoginAttempts_007.attempts + 1 END,
                window_started = CASE WHEN LoginAttempts_007.window_started < CURRENT_TIMESTAMP - INTERVAL '15 minutes'
                    THEN CURRENT_TIMESTAMP ELSE LoginAttempts_007.window_started END,
                locked_until = CASE
                    WHEN LoginAttempts_007.window_started < CURRENT_TIMESTAMP - INTERVAL '15 minutes' THEN NULL
                    WHEN LoginAttempts_007.attempts + 1 >= 10 THEN CURRENT_TIMESTAMP + INTERVAL '15 minutes'
                    ELSE LoginAttempts_007.locked_until END";
    try {
        executeQuery($sql, [':ip_hash' => getLoginRateLimitKey()]);
    } catch (Throwable $e) {
        error_log('Could not record a login failure.');
    }
}

function clearLoginFailures() {
    try {
        executeQuery('DELETE FROM LoginAttempts_007 WHERE ip_hash = :ip_hash', [':ip_hash' => getLoginRateLimitKey()]);
    } catch (Throwable $e) {
        error_log('Could not clear login failures.');
    }
}

/**
 * Pagination helper - get offset
 * @param int $page
 * @param int $perPage
 * @return int
 */
function getOffset($page, $perPage = 10) {
    return max(0, ($page - 1) * $perPage);
}

/**
 * Pagination helper - generate page links
 * @param int $totalItems
 * @param int $currentPage
 * @param int $perPage
 * @param string $url
 * @return string
 */
function paginate($totalItems, $currentPage, $perPage, $url) {
    $totalPages = ceil($totalItems / $perPage);
    
    if ($totalPages <= 1) {
        return '';
    }
    
    $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center mb-0">';
    
    // Previous page link
    $prevDisabled = $currentPage <= 1 ? 'disabled' : '';
    $prevUrl = $currentPage > 1 ? $url . '?page=' . ($currentPage - 1) : '#';
    $html .= '<li class="page-item ' . $prevDisabled . '">';
    $html .= '<a class="page-link" href="' . $prevUrl . '" aria-label="Previous">';
    $html .= '<span aria-hidden="true">&laquo;</span></a></li>';
    
    // Page links
    for ($i = 1; $i <= $totalPages; $i++) {
        $active = $i === $currentPage ? 'active' : '';
        $html .= '<li class="page-item ' . $active . '">';
        $html .= '<a class="page-link" href="' . $url . '?page=' . $i . '">' . $i . '</a></li>';
    }
    
    // Next page link
    $nextDisabled = $currentPage >= $totalPages ? 'disabled' : '';
    $nextUrl = $currentPage < $totalPages ? $url . '?page=' . ($currentPage + 1) : '#';
    $html .= '<li class="page-item ' . $nextDisabled . '">';
    $html .= '<a class="page-link" href="' . $nextUrl . '" aria-label="Next">';
    $html .= '<span aria-hidden="true">&raquo;</span></a></li>';
    
    $html .= '</ul></nav>';
    
    return $html;
}

/**
 * Export data to CSV
 * @param array $data
 * @param string $filename
 */
function exportToCsv($data, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    if (!empty($data)) {
        // Write header
        fputcsv($output, array_keys($data[0]));
        
        // Write data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit;
}

/**
 * Debug function - display variable in formatted way
 * @param mixed $var
 * @param bool $die
 */
function debug($var, $die = true) {
    echo '<pre class="bg-light p-3 border">';
    print_r($var);
    echo '</pre>';
    
    if ($die) {
        die();
    }
}
