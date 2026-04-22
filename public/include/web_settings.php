<?php
/**
 * Web Settings Initialization
 *
 * This file loads the web settings from the database and makes them
 * available globally throughout the application.
 *
 * Include this file early in your layout to access:
 * - $webSettings array with all settings
 * - Individual variables: $siteName, $siteTagline, $siteLogo, etc.
 */

// Flag to track if we have loaded settings from database
$webSettingsLoaded = false;
$webSettings = [];

try {
    // Ensure we have database connection
    if (!isset($db)) {
        // Try to get from global scope or initialize
        global $db;
        if (!$db) {
            // Load DB class if not already loaded
            if (!class_exists('DB')) {
                require_once __DIR__ . '/../../core/DB.php';
            }
            $config = require __DIR__ . '/../../config/database.php';
            $db = new DB($config['host'], $config['user'], $config['pass'], $config['name'], $config['port']);
        }
    }

    // Include SettingsManager
    require_once __DIR__ . '/../../core/SettingsManager.php';

    // Initialize settings manager and get web settings
    $settingsManager = SettingsManager::getInstance($db);
    $webSettings = $settingsManager->getWebSettings();
    $webSettingsLoaded = true;
} catch (Exception $e) {
    // Silently fail and use default values
    // This allows error pages to work even when database is unavailable
    $webSettings = [];
}

// Extract individual settings for easy access
$siteName = $webSettings['site_name'] ?? 'Template';
$siteTagline = $webSettings['site_tagline'] ?? '';
$siteLogo = $webSettings['site_logo'] ?? '';
$siteLogoDark = $webSettings['site_logo_dark'] ?? '';
$siteFavicon = $webSettings['site_favicon'] ?? '';
$primaryColor = $webSettings['primary_color'] ?? '#0d6efd';
$footerText = $webSettings['footer_text'] ?? '';

// Appearance: chrome colors, login hero, dark mode default
$sidenavColor      = $webSettings['sidenav_color']          ?? '#212529';
$topbarColor       = $webSettings['topbar_color']           ?? '#212529';
$loginHeroEnabled  = $webSettings['login_hero_enabled']     ?? true;
$loginHeroStart    = $webSettings['login_hero_color_start'] ?? '';
$loginHeroEnd      = $webSettings['login_hero_color_end']   ?? '';
$darkModeEnabled   = $webSettings['dark_mode_enabled']      ?? false;

// Build full URLs for assets
$siteLogoUrl = $siteLogo ? '/uploads/' . $siteLogo : '';
$siteLogoDarkUrl = $siteLogoDark ? '/uploads/' . $siteLogoDark : '';
$siteFaviconUrl = $siteFavicon ? '/uploads/' . $siteFavicon : '';

// Calculate darker shade for gradients
function adjustColorBrightness($hex, $percent) {
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));

    $r = max(0, min(255, $r + $percent));
    $g = max(0, min(255, $g + $percent));
    $b = max(0, min(255, $b + $percent));

    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

$primaryColorDark = adjustColorBrightness($primaryColor, -30);
