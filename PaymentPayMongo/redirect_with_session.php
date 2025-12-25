<?php
$session_id = $_GET['session_id'] ?? null;

if (!$session_id || str_contains($session_id, '{')) {
    echo '<div style="color:red;text-align:center;margin-top:50px;">Invalid or missing payment session ID.</div>';
    exit;
}

header("Location: payment_success.php?session_id=" . urlencode($session_id));
exit;
