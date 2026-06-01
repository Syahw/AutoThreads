<?php
// AutoThreads - Root redirect
// The actual API lives in backend/public/index.php
// This file exists for GitHub Pages or basic hosting compatibility

// Build the redirect relative to wherever this project is served from,
// so it works at both http://localhost/ and http://localhost/AutoThreads/.
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
header('Location: ' . $base . '/backend/public/');
exit;


