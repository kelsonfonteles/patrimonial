<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
startSession();
session_destroy();
header('Location: ' . APP_URL . '/index.php');
exit;
