<?php
require_once 'database.php';
require_once __DIR__ . '/../../core/Session.php';
require_once __DIR__ . '/../../core/ErrorHandler.php';
require_once __DIR__ . '/../../core/SecurityHeaders.php';
require_once __DIR__ . '/web_settings.php';

// Initialize error handler for the login path
$_envFile = __DIR__ . '/../../.env';
$_envDebug = file_exists($_envFile) ? parse_ini_file($_envFile) : [];
$_isDebug = ($_envDebug['APP_DEBUG'] ?? 'false') === 'true';
ErrorHandler::init($_isDebug, __DIR__ . '/../../logs/error.log');
unset($_envFile, $_envDebug, $_isDebug);

// Send security headers for this request
SecurityHeaders::init();

// Initialize Session singleton
$session = Session::getInstance($db);
?>
