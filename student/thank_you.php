<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Feedback Submitted - Aarasys</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root{--primary:#4F46E5;--text:#0f172a;--muted:#64748b;--border:#e2e8f0;--bg:#f8fafc}
    *{box-sizing:border-box}
    body{margin:0;font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);display:grid;place-items:center;min-height:100vh;padding:24px}
    .card{background:#fff;border:1px solid var(--border);border-radius:16px;box-shadow:0 10px 30px rgba(2,6,23,.08);padding:32px 28px;max-width:560px;width:100%;text-align:center}
    .icon{width:56px;height:56px;border-radius:50%;background:rgba(79,70,229,.1);display:grid;place-items:center;color:var(--primary);margin:0 auto 12px;font-size:24px}
    h1{font-size:22px;margin:4px 0 8px}
    p{color:var(--muted);margin:0 0 18px}
    .actions{display:flex;gap:10px;justify-content:center;margin-top:8px}
    a.button{display:inline-block;padding:10px 16px;border-radius:10px;text-decoration:none;font-weight:600;border:1px solid var(--border);color:var(--text);background:#fff}
    a.button.primary{background:var(--primary);color:#fff;border-color:var(--primary)}
    a.button:hover{filter:brightness(.98)}
  </style>
</head>
<body>
  <div class="card">
    <div class="icon">✓</div>
    <h1>Thank you for your feedback!</h1>
    <p>Your responses have been recorded successfully.</p>
    <div class="actions">
      <a href="dashboard.php" class="button">Back to Dashboard</a>
      <a href="feedback.php" class="button primary">Fill Another Form</a>
    </div>
  </div>
</body>
</html>
