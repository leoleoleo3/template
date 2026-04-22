<?php
/**
 * Connection Lost / Offline Page
 */
$errorCode = '';
$errorTitle = 'Connection Lost';
$errorMessage = 'It seems you have lost your internet connection. Please check your network settings and try again.';
$errorIcon = 'fa-wifi';
$showLoginButton = false;
$isOffline = true;
include __DIR__ . '/error_template.php';
