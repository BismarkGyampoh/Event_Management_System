<?php
/**
 * Database Backup - Admin Page
 * Event Management System 007
 * PostgreSQL-compatible data backup
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['admin']);

$pageTitle = 'Database Backup - Admin Dashboard';
$message = '';
$messageType = '';

// Handle backup/restore actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validateCsrfToken()) {
        $message = 'Invalid form submission.';
        $messageType = 'danger';
    } else {
        $action = $_POST['action'];
        
        if ($action === 'create_backup') {
            try {
                $backupFile = backupDatabase();
                $message = 'Database backup created successfully: ' . basename($backupFile);
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error creating backup: ' . $e->getMessage();
                $messageType = 'danger';
            }
        } elseif ($action === 'restore_backup') {
            if (empty($_POST['backup_file'])) {
                $message = 'Please select a backup file.';
                $messageType = 'danger';
            } else {
                try {
                    restoreDatabase($_POST['backup_file']);
                    $message = 'Database restored successfully from: ' . basename($_POST['backup_file']);
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = 'Error restoring backup: ' . $e->getMessage();
                    $messageType = 'danger';
                }
            }
        } elseif ($action === 'export_csv') {
            try {
                $exportFile = exportDatabaseToCsv();
                $message = 'Data exported to CSV successfully: ' . basename($exportFile);
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error exporting data: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }
}

/**
 * Create a compressed JSON backup of the application tables.
 */
function backupDatabase() {
    $backupDir = __DIR__ . '/backups';
    if (!is_dir($backupDir)) {
        if (!mkdir($backupDir, 0755, true) && !is_dir($backupDir)) {
            throw new Exception('Could not create the backup directory.');
        }
    }
    $tables = getBackupTables();
    $filename = $backupDir . '/backup_' . date('Y-m-d_H-i-s') . '.json.gz';
    if (file_exists($filename)) {
        throw new Exception('A backup already exists for this second. Wait briefly and retry.');
    }
    $temporaryFile = $filename . '.tmp';
    $stream = gzopen($temporaryFile, 'wb9');
    if ($stream === false) {
        throw new Exception('Could not create the compressed backup stream.');
    }

    $pdo = getDbConnection();
    try {
        $pdo->beginTransaction();
        $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
        $header = json_encode(['format_version' => 2, 'created_at' => date(DATE_ATOM), 'tables' => $tables]);
        if ($header === false || gzwrite($stream, $header . "\n") !== strlen($header . "\n")) {
            throw new Exception('Could not write the backup header.');
        }
        foreach ($tables as $table) {
            $stmt = $pdo->query('SELECT * FROM "' . strtolower($table) . '"');
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $line = json_encode(['table' => $table, 'row' => $row], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($line === false || gzwrite($stream, $line . "\n") !== strlen($line . "\n")) {
                    throw new Exception('Could not write a backup row.');
                }
            }
        }
        $endLine = "{\"end\":true}\n";
        if (gzwrite($stream, $endLine) !== strlen($endLine)) {
            throw new Exception('Could not finish the backup file.');
        }
        if (!gzclose($stream)) {
            $stream = false;
            throw new Exception('Could not finish the backup file.');
        }
        $stream = false;
        $pdo->commit();
        if (!rename($temporaryFile, $filename)) {
            throw new Exception('Could not finalize the backup file.');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($stream !== false) {
            gzclose($stream);
        }
        if (is_file($temporaryFile)) {
            unlink($temporaryFile);
        }
        throw $e;
    }

    return $filename;
}

function getBackupTables() {
    return [
        'Organizers_007',
        'Venues_007',
        'Attendees_007',
        'Events_007',
        'Vendors_007',
        'Tickets_007',
        'Payments_007',
        'Event_Vendors_007',
        'Audit_Log_007'
    ];
}

function resetBackupSequences($pdo) {
    $identityColumns = [
        'Organizers_007' => 'organizer_id', 'Venues_007' => 'venue_id',
        'Attendees_007' => 'attendee_id', 'Events_007' => 'event_id',
        'Vendors_007' => 'vendor_id', 'Tickets_007' => 'ticket_id',
        'Payments_007' => 'payment_id', 'Event_Vendors_007' => 'event_vendor_id',
        'Audit_Log_007' => 'audit_id'
    ];
    foreach ($identityColumns as $table => $column) {
        $tableName = strtolower($table);
        $stmt = $pdo->prepare('SELECT setval(pg_get_serial_sequence(?, ?), GREATEST(COALESCE(MAX("' . $column . '"), 1), 1), COUNT(*) > 0) FROM "' . $tableName . '"');
        $stmt->execute([$tableName, $column]);
    }
}

/**
 * Restore database from backup
 */
function restoreDatabase($backupFile) {
    $backupDir = __DIR__ . '/backups';
    if (!is_string($backupFile)
        || !preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.json\.gz$/', $backupFile)) {
        throw new Exception('Unsupported backup file.');
    }

    $filepath = $backupDir . '/' . $backupFile;
    if (!is_file($filepath)) {
        throw new Exception('Backup file not found.');
    }

    $probe = gzopen($filepath, 'rb');
    if ($probe === false) {
        throw new Exception('Backup file could not be opened.');
    }
    $headerLine = gzgets($probe);
    $header = $headerLine === false ? null : json_decode($headerLine, true);
    if (is_array($header) && ($header['format_version'] ?? null) === 2) {
        $tables = getBackupTables();
        if (!isset($header['tables']) || !is_array($header['tables'])
            || array_diff($tables, $header['tables']) || array_diff($header['tables'], $tables)) {
            gzclose($probe);
            throw new Exception('The backup does not contain the expected application tables.');
        }

        $pdo = getDbConnection();
        $pdo->beginTransaction();
        try {
            $quotedTables = array_map(function ($table) { return '"' . strtolower($table) . '"'; }, $tables);
            $pdo->exec('TRUNCATE TABLE ' . implode(', ', $quotedTables) . ' RESTART IDENTITY CASCADE');
            $validColumns = [];
            foreach ($tables as $table) {
                $query = $pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ?');
                $query->execute([strtolower($table)]);
                $validColumns[$table] = $query->fetchAll(PDO::FETCH_COLUMN);
            }

            $ended = false;
            while (!gzeof($probe)) {
                $line = gzgets($probe);
                if ($line === false || trim($line) === '') continue;
                $record = json_decode($line, true);
                if (!is_array($record) || json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('The backup contains an invalid record.');
                }
                if (($record['end'] ?? false) === true) {
                    $ended = true;
                    break;
                }
                $table = $record['table'] ?? '';
                $row = $record['row'] ?? null;
                if (!in_array($table, $tables, true) || !is_array($row) || !$row
                    || array_diff(array_keys($row), $validColumns[$table])) {
                    throw new Exception('The backup contains an invalid table row.');
                }
                $columns = array_keys($row);
                foreach ($columns as $column) {
                    if (!preg_match('/^[a-z_][a-z0-9_]*$/', $column)) {
                        throw new Exception('The backup contains an invalid column name.');
                    }
                }
                $quotedColumns = array_map(function ($column) { return '"' . $column . '"'; }, $columns);
                $placeholders = implode(', ', array_map(function ($column) {
                    return in_array($column, ['old_values', 'new_values'], true) ? 'CAST(? AS JSONB)' : '?';
                }, $columns));
                $sql = 'INSERT INTO "' . strtolower($table) . '" (' . implode(', ', $quotedColumns) . ') VALUES (' . $placeholders . ')';
                $pdo->prepare($sql)->execute(array_values($row));
            }
            if (!$ended) {
                throw new Exception('The backup is incomplete.');
            }
            resetBackupSequences($pdo);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            gzclose($probe);
            throw $e;
        }
        gzclose($probe);
        return;
    }
    gzclose($probe);

    $compressed = file_get_contents($filepath);
    $json = $compressed === false ? false : gzdecode($compressed);
    if ($json === false) {
        throw new Exception('The backup file could not be decompressed.');
    }

    $backup = json_decode($json, true);
    if (!is_array($backup) || json_last_error() !== JSON_ERROR_NONE
        || ($backup['format_version'] ?? null) !== 1
        || !isset($backup['tables']) || !is_array($backup['tables'])) {
        throw new Exception('The backup file has an invalid format.');
    }

    $tables = getBackupTables();
    if (array_diff($tables, array_keys($backup['tables']))
        || array_diff(array_keys($backup['tables']), $tables)) {
        throw new Exception('The backup does not contain the expected application tables.');
    }

    $pdo = getDbConnection();
    $pdo->beginTransaction();
    try {
        $quotedTables = array_map(function ($table) {
            return '"' . strtolower($table) . '"';
        }, $tables);
        $pdo->exec('TRUNCATE TABLE ' . implode(', ', $quotedTables) . ' RESTART IDENTITY CASCADE');

        foreach ($tables as $table) {
            $rows = $backup['tables'][$table];
            if (!is_array($rows)) {
                throw new Exception('The backup contains invalid table data.');
            }

            $tableName = strtolower($table);
            $columnQuery = $pdo->prepare("
                SELECT column_name
                FROM information_schema.columns
                WHERE table_schema = current_schema() AND table_name = ?
            ");
            $columnQuery->execute([$tableName]);
            $validColumns = $columnQuery->fetchAll(PDO::FETCH_COLUMN);

            foreach ($rows as $row) {
                if (!is_array($row) || !$row || array_diff(array_keys($row), $validColumns)) {
                    throw new Exception('The backup contains invalid row data.');
                }

                $columns = array_keys($row);
                foreach ($columns as $column) {
                    if (!preg_match('/^[a-z_][a-z0-9_]*$/', $column)) {
                        throw new Exception('The backup contains an invalid column name.');
                    }
                }

                $quotedColumns = array_map(function ($column) {
                    return '"' . $column . '"';
                }, $columns);
                $placeholders = implode(', ', array_map(function ($column) {
                    return in_array($column, ['old_values', 'new_values'], true)
                        ? 'CAST(? AS JSONB)'
                        : '?';
                }, $columns));
                $sql = 'INSERT INTO "' . $tableName . '" (' . implode(', ', $quotedColumns) . ') VALUES (' . $placeholders . ')';
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_values($row));
            }
        }

        resetBackupSequences($pdo);
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Export database data to CSV
 */
function exportDatabaseToCsv() {
    $backupDir = __DIR__ . '/backups';
    if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true) && !is_dir($backupDir)) {
        throw new Exception('Could not create the export directory.');
    }
    
    $filename = $backupDir . '/export_' . date('Y-m-d_H-i-s') . '.csv';
    $pdo = getDbConnection();
    
    // Export main tables
    $tables = ['Organizers_007', 'Attendees_007', 'Events_007', 'Tickets_007', 'Payments_007', 'Venues_007'];
    
    $fp = fopen($filename, 'w');
    if ($fp === false) {
        throw new Exception('Could not create the CSV export.');
    }
    
    foreach ($tables as $table) {
        // Write table header
        fputcsv($fp, ["=== $table ==="]);
        
        // Get columns
        $columnQuery = $pdo->prepare("
            SELECT column_name
            FROM information_schema.columns
            WHERE table_schema = current_schema() AND table_name = ?
            ORDER BY ordinal_position
        ");
        $columnQuery->execute([strtolower($table)]);
        $columns = $columnQuery->fetchAll(PDO::FETCH_COLUMN);
        
        fputcsv($fp, $columns);
        
        // Get data
        $data = $pdo->query('SELECT * FROM "' . strtolower($table) . '"');
        while ($row = $data->fetch(PDO::FETCH_NUM)) {
            fputcsv($fp, array_map('safeSpreadsheetCell', $row));
        }
        
        fputcsv($fp, []); // Empty line between tables
    }
    
    fclose($fp);
    
    return $filename;
}

function safeSpreadsheetCell($value) {
    $value = $value === null ? '' : (string)$value;
    return preg_match('/^[\x00-\x20]*[=+@-]/', $value) ? "'" . $value : $value;
}

// Get list of backup files
$backupDir = __DIR__ . '/backups';
$backups = [];
if (is_dir($backupDir)) {
    $files = scandir($backupDir);
    foreach ($files as $file) {
        if (preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.json\.gz$/', $file)
            || preg_match('/^export_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.csv$/', $file)) {
            $backups[] = [
                'filename' => $file,
                'size' => filesize($backupDir . '/' . $file),
                'date' => date('Y-m-d H:i:s', filemtime($backupDir . '/' . $file))
            ];
        }
    }
    usort($backups, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
}

// Get database info
$dbInfo = [
    'name' => DB_DATABASE,
    'tables' => 0,
    'records' => 0
];

try {
    $pdo = getDbConnection();
    $tables = $pdo->query("
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = current_schema() AND table_type = 'BASE TABLE'
    ")->fetchAll(PDO::FETCH_COLUMN);
    $dbInfo['tables'] = count($tables);
    
    foreach ($tables as $table) {
        $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        $dbInfo['records'] += $count;
    }
} catch (Exception $e) {
    // Ignore errors
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="<?php echo getAssetUrl('css/main.css'); ?>" rel="stylesheet">
<link rel="icon" type="image/svg+xml" href="<?php echo getAssetUrl('favicon.svg'); ?>">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse show" id="sidebarMenu">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_users.php">
                                <i class="fas fa-users-cog me-2"></i>Manage Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="audit_logs.php">
                                <i class="fas fa-history me-2"></i>Audit Logs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-chart-bar me-2"></i>Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="backup.php">
                                <i class="fas fa-database me-2"></i>Backup
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">
                                <i class="fas fa-user me-2"></i>Profile
                            </a>
                        </li>
                        <li class="nav-item mt-3">
                            <a class="nav-link text-danger" href="../logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-database me-2"></i>Database Backup</h1>
                </div>
                
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <!-- Create Backup -->
                    <div class="col-lg-4 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Create Backup</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">Save a compressed snapshot of application data. Restoring replaces the current data; the schema is unchanged.</p>
                                <form method="POST">
                                    <?php echo csrfTokenField(); ?>
                                    <input type="hidden" name="action" value="create_backup">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-download me-1"></i>Create Data Backup
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Export CSV -->
                    <div class="col-lg-4 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-file-csv me-2"></i>Export Data</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">Export all data to a CSV file for reporting or analysis.</p>
                                <form method="POST">
                                    <?php echo csrfTokenField(); ?>
                                    <input type="hidden" name="action" value="export_csv">
                                    <button type="submit" class="btn btn-success w-100">
                                        <i class="fas fa-file-export me-1"></i>Export to CSV
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Database Info -->
                    <div class="col-lg-4 mb-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Database Info</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm mb-0">
                                    <tr>
                                        <td><strong>Database:</strong></td>
                                        <td><?php echo htmlspecialchars($dbInfo['name']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Tables:</strong></td>
                                        <td><?php echo $dbInfo['tables']; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Total Records:</strong></td>
                                        <td><?php echo number_format($dbInfo['records']); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Backup Files List -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Backup History</h5>
                        <span class="badge bg-secondary"><?php echo count($backups); ?> files</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($backups)): ?>
                            <p class="text-muted text-center py-4">No backup files found.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Filename</th>
                                            <th>Size</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($backups as $backup): ?>
                                            <tr>
                                                <td>
                                                    <i class="fas <?php echo substr($backup['filename'], -4) === '.csv' ? 'fa-file-csv text-success' : 'fa-file-archive text-warning'; ?> me-2"></i>
                                                    <?php echo htmlspecialchars($backup['filename']); ?>
                                                </td>
                                                <td><?php echo number_format($backup['size'] / 1024, 2); ?> KB</td>
                                                <td><?php echo formatDate($backup['date']); ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="download_backup.php?file=<?php echo urlencode($backup['filename']); ?>" 
                                                           class="btn btn-outline-primary">
                                                            <i class="fas fa-download"></i>
                                                        </a>
                                                        <?php if (substr($backup['filename'], -3) === '.gz'): ?>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to restore this backup? Current data will be overwritten.');">
                                                            <?php echo csrfTokenField(); ?>
                                                            <input type="hidden" name="action" value="restore_backup">
                                                            <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($backup['filename']); ?>">
                                                            <button type="submit" class="btn btn-outline-warning">
                                                                <i class="fas fa-upload"></i>
                                                            </button>
                                                        </form>
                                                        <?php endif; ?>
                                                        <a href="delete_backup.php?file=<?php echo urlencode($backup['filename']); ?>" 
                                                           class="btn btn-outline-danger"
                                                           onclick="return confirm('Are you sure you want to delete this backup?');">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
