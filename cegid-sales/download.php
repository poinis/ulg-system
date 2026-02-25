<?php
require_once 'config.php';

if (!isset($_GET['file'])) {
    die('File not specified');
}

$filename = basename($_GET['file']);
$filepath = EXPORT_DIR . $filename;

if (!file_exists($filepath)) {
    die('File not found');
}

// Security check
if (strpos($filename, '..') !== false || strpos($filename, '/') !== false) {
    die('Invalid filename');
}

// Send headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

// Output file
readfile($filepath);
exit;
?>
