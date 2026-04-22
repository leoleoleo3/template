<?php
/**
 * TEMPLATE Mail Queue Processor
 *
 * CLI script for processing the email queue via Windows Task Scheduler.
 * Locks, sends, retries, and cleans up stale jobs.
 *
 * Usage: php mailer_processor.php [batch_size]
 * Or via: mailer_processor.bat
 *
 * Configuration:
 *   DEFAULT_BATCH_SIZE - How many emails to process per run
 *   STALE_LOCK_MINUTES - Minutes before a locked job is considered stale
 */

// ============ CONFIGURATION ============
define('DEFAULT_BATCH_SIZE', 10);
define('STALE_LOCK_MINUTES', 30);
// =======================================

// CLI only — block web access
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script can only be run from the command line.');
}

define('BASE_PATH', __DIR__);
$logFile  = BASE_PATH . '/logs/mailer.log';
$lockFile = BASE_PATH . '/logs/mailer_processor.lock';

// ---- Helper: log message to file and stdout ----
function mailerLog(string $message): void
{
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    echo $line;
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    file_put_contents($logFile, $line, FILE_APPEND);
}

// ---- Lockfile: prevent concurrent execution ----
if (file_exists($lockFile)) {
    $lockAge = time() - filemtime($lockFile);
    if ($lockAge < 300) { // 5 minutes
        mailerLog('Another processor is running (lock age: ' . $lockAge . 's). Exiting.');
        exit(0);
    }
    mailerLog('Stale lock file found (age: ' . $lockAge . 's). Removing and continuing.');
    unlink($lockFile);
}

file_put_contents($lockFile, getmypid());

// ---- Register shutdown to clean lock ----
register_shutdown_function(function() use ($lockFile) {
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
});

try {
    mailerLog('=== Mail Queue Processor Started ===');

    // Load dependencies
    require_once BASE_PATH . '/vendor/autoload.php';
    require_once BASE_PATH . '/core/db.php';
    require_once BASE_PATH . '/core/MailerAccountManager.php';
    require_once BASE_PATH . '/core/MailQueueManager.php';

    // Load config
    if (file_exists(BASE_PATH . '/config/env.php')) {
        require_once BASE_PATH . '/config/env.php';
    }
    $config = require BASE_PATH . '/config/database.php';

    // Initialize database
    $db = new DB($config['host'], $config['user'], $config['pass'], $config['name'], $config['port'] ?? 3306);

    // Initialize managers
    $accountManager = MailerAccountManager::getInstance($db);
    $queueManager = MailQueueManager::getInstance($db);

    // Batch size from CLI argument or default
    $batchSize = isset($argv[1]) ? max(1, (int)$argv[1]) : DEFAULT_BATCH_SIZE;
    $lockId = gethostname() . '_' . getmypid();

    mailerLog("Processing queue (batch: $batchSize, lock: $lockId)");

    // Process queue
    $result = $queueManager->processQueue($batchSize, $lockId);
    mailerLog("Results: {$result['sent']} sent, {$result['failed']} failed, {$result['skipped']} skipped");

    // Cleanup stale locks from crashed processors
    $cleanup = $queueManager->cleanupStaleJobs(STALE_LOCK_MINUTES);
    if ($cleanup['success'] && ($cleanup['affected_rows'] ?? 0) > 0) {
        mailerLog("Cleaned up {$cleanup['affected_rows']} stale job(s)");
    }

    mailerLog('=== Mail Queue Processor Finished ===');

} catch (Exception $e) {
    mailerLog('FATAL ERROR: ' . $e->getMessage());
    exit(1);
}
