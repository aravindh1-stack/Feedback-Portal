<?php
$queryString = $_SERVER['QUERY_STRING'] ?? '';
$target = 'download_feedback_pdf.php' . ($queryString ? '?' . $queryString : '');

header('Location: ' . $target);
header('Connection: close');
exit;
