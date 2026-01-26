<?php
// log_viewer.php - View logs in browser (ADMIN ONLY)
require_once __DIR__ . '/config/config.php';

// Only allow admins to access this
if (!isLoggedIn() || !hasRole('admin')) {
    die('Access denied. Admin only.');
}

// Get filter parameters
$filter = $_GET['filter'] ?? '';
$lines = $_GET['lines'] ?? 100;

// Path to PHP error log
$log_file = 'C:/xampp/php/logs/php_error_log';

// Check if file exists
if (!file_exists($log_file)) {
    die("Error log file not found at: {$log_file}");
}

// Read log file
$log_content = file_get_contents($log_file);
$log_lines = explode("\n", $log_content);
$log_lines = array_reverse($log_lines); // Most recent first
$log_lines = array_slice($log_lines, 0, $lines); // Limit lines

// Apply filter
if ($filter) {
    $log_lines = array_filter($log_lines, function($line) use ($filter) {
        return stripos($line, $filter) !== false;
    });
}

// Auto-refresh option
$auto_refresh = isset($_GET['refresh']) ? (int)$_GET['refresh'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Log Viewer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <?php if ($auto_refresh > 0): ?>
    <meta http-equiv="refresh" content="<?php echo $auto_refresh; ?>">
    <?php endif; ?>
    <style>
        .log-viewer {
            background: #1e1e1e;
            color: #d4d4d4;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            padding: 15px;
            border-radius: 5px;
            max-height: 600px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .log-line {
            padding: 2px 0;
            border-bottom: 1px solid #333;
        }
        .log-error {
            color: #f48771;
        }
        .log-warning {
            color: #dcdcaa;
        }
        .log-success {
            color: #4ec9b0;
        }
        .log-debug {
            color: #569cd6;
        }
        .highlight {
            background-color: #ffd700;
            color: #000;
            padding: 2px 4px;
        }
        .filter-badge {
            display: inline-block;
            padding: 5px 10px;
            margin: 5px;
            background: #0d6efd;
            color: white;
            border-radius: 15px;
            cursor: pointer;
            font-size: 12px;
        }
        .filter-badge:hover {
            background: #0b5ed7;
        }
    </style>
</head>
<body>
<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <i class="bi bi-terminal"></i> System Log Viewer
                        </h4>
                        <div>
                            <span class="badge bg-success">Live</span>
                            <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-sm btn-light">
                                <i class="bi bi-house"></i> Dashboard
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    
                    <!-- Controls -->
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <form method="GET" class="row g-2">
                                <div class="col-md-3">
                                    <label class="form-label small">Filter Logs:</label>
                                    <input type="text" name="filter" class="form-control form-control-sm" 
                                           placeholder="Search logs..." 
                                           value="<?php echo htmlspecialchars($filter); ?>">
                                </div>
                                
                                <div class="col-md-2">
                                    <label class="form-label small">Lines to Show:</label>
                                    <select name="lines" class="form-select form-select-sm">
                                        <option value="50" <?php echo $lines == 50 ? 'selected' : ''; ?>>50</option>
                                        <option value="100" <?php echo $lines == 100 ? 'selected' : ''; ?>>100</option>
                                        <option value="200" <?php echo $lines == 200 ? 'selected' : ''; ?>>200</option>
                                        <option value="500" <?php echo $lines == 500 ? 'selected' : ''; ?>>500</option>
                                        <option value="1000" <?php echo $lines == 1000 ? 'selected' : ''; ?>>1000</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-2">
                                    <label class="form-label small">Auto-Refresh:</label>
                                    <select name="refresh" class="form-select form-select-sm">
                                        <option value="0" <?php echo $auto_refresh == 0 ? 'selected' : ''; ?>>Off</option>
                                        <option value="5" <?php echo $auto_refresh == 5 ? 'selected' : ''; ?>>5 seconds</option>
                                        <option value="10" <?php echo $auto_refresh == 10 ? 'selected' : ''; ?>>10 seconds</option>
                                        <option value="30" <?php echo $auto_refresh == 30 ? 'selected' : ''; ?>>30 seconds</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary btn-sm w-100">
                                        <i class="bi bi-funnel"></i> Apply
                                    </button>
                                </div>
                                
                                <div class="col-md-3 d-flex align-items-end">
                                    <a href="?clear=1" class="btn btn-danger btn-sm w-100" 
                                       onclick="return confirm('Clear all logs?')">
                                        <i class="bi bi-trash"></i> Clear Logs
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Quick Filters -->
                    <div class="mb-3">
                        <strong class="small">Quick Filters:</strong><br>
                        <span class="filter-badge" onclick="applyFilter('APPRAISAL')">
                            <i class="bi bi-clipboard"></i> Appraisal
                        </span>
                        <span class="filter-badge" onclick="applyFilter('sendEmail')">
                            <i class="bi bi-envelope"></i> Email
                        </span>
                        <span class="filter-badge" onclick="applyFilter('PHPMailer')">
                            <i class="bi bi-bug"></i> PHPMailer
                        </span>
                        <span class="filter-badge" onclick="applyFilter('ERROR')">
                            <i class="bi bi-exclamation-triangle"></i> Errors
                        </span>
                        <span class="filter-badge" onclick="applyFilter('Exception')">
                            <i class="bi bi-x-circle"></i> Exceptions
                        </span>
                        <span class="filter-badge" onclick="applyFilter('Database')">
                            <i class="bi bi-database"></i> Database
                        </span>
                        <span class="filter-badge" onclick="applyFilter('')" style="background: #6c757d;">
                            <i class="bi bi-arrow-clockwise"></i> Show All
                        </span>
                    </div>
                    
                    <!-- Info Bar -->
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong>Log File:</strong> <?php echo $log_file; ?> | 
                        <strong>Showing:</strong> <?php echo count($log_lines); ?> lines
                        <?php if ($filter): ?>
                        | <strong>Filter:</strong> "<?php echo htmlspecialchars($filter); ?>"
                        <?php endif; ?>
                        <?php if ($auto_refresh > 0): ?>
                        | <i class="bi bi-arrow-repeat"></i> Auto-refreshing every <?php echo $auto_refresh; ?>s
                        <?php endif; ?>
                    </div>
                    
                    <!-- Log Content -->
                    <div class="log-viewer" id="logViewer">
                        <?php if (empty($log_lines)): ?>
                        <div class="text-center text-muted p-5">
                            <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                            <p class="mt-3">No log entries found</p>
                            <?php if ($filter): ?>
                            <p>Try removing the filter or checking different keywords</p>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <?php foreach ($log_lines as $index => $line): ?>
                            <?php if (empty(trim($line))) continue; ?>
                            <?php
                            // Highlight different types of log entries
                            $class = '';
                            if (stripos($line, 'error') !== false || stripos($line, 'failed') !== false) {
                                $class = 'log-error';
                            } elseif (stripos($line, 'warning') !== false) {
                                $class = 'log-warning';
                            } elseif (stripos($line, 'success') !== false || stripos($line, 'YES') !== false) {
                                $class = 'log-success';
                            } elseif (stripos($line, '===') !== false || stripos($line, '---') !== false) {
                                $class = 'log-debug';
                            }
                            
                            // Highlight filter term
                            $display_line = htmlspecialchars($line);
                            if ($filter) {
                                $display_line = preg_replace(
                                    '/(' . preg_quote($filter, '/') . ')/i',
                                    '<span class="highlight">$1</span>',
                                    $display_line
                                );
                            }
                            ?>
                            <div class="log-line <?php echo $class; ?>">
                                <small class="text-muted">[<?php echo $index + 1; ?>]</small> 
                                <?php echo $display_line; ?>
                            </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Actions -->
                    <div class="mt-3 text-end">
                        <button onclick="scrollToTop()" class="btn btn-sm btn-secondary">
                            <i class="bi bi-arrow-up"></i> Top
                        </button>
                        <button onclick="scrollToBottom()" class="btn btn-sm btn-secondary">
                            <i class="bi bi-arrow-down"></i> Bottom
                        </button>
                        <button onclick="window.location.reload()" class="btn btn-sm btn-primary">
                            <i class="bi bi-arrow-clockwise"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function applyFilter(filterText) {
    const url = new URL(window.location);
    url.searchParams.set('filter', filterText);
    window.location = url;
}

function scrollToTop() {
    document.getElementById('logViewer').scrollTop = 0;
}

function scrollToBottom() {
    const viewer = document.getElementById('logViewer');
    viewer.scrollTop = viewer.scrollHeight;
}

// Auto-scroll to bottom on load
window.addEventListener('load', function() {
    <?php if ($auto_refresh > 0): ?>
    scrollToBottom();
    <?php endif; ?>
});
</script>
</body>
</html>

<?php
// Handle clear logs request
if (isset($_GET['clear'])) {
    file_put_contents($log_file, '');
    header("Location: log_viewer.php");
    exit;
}
?>