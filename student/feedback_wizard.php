<?php
// This file is deprecated. The student feedback wizard now lives inside feedback.php
// Keep this redirect stub in case any old links still point here.
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

// Preserve form_number if present (feedback.php now computes/backs it up anyway)
$qs = '';
if (!empty($_GET['form_number'])) {
    $qs = '?form_number=' . urlencode($_GET['form_number']);
}
header('Location: feedback.php' . $qs);
exit();
?>