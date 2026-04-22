<?php
require_once __DIR__ . '/../core/DatabaseFactory.php';
require_once __DIR__ . '/../core/Session.php';

$db = DatabaseFactory::make();
$session = Session::getInstance($db);

// Logout user
$session->logout();

// Redirect to login page
header('Location: login.php');
exit;
?>
