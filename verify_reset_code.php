<?php
/**
 * Verify Reset Code (OTP) - DEPRECATED
 * Manual Admin-mediated reset flow is now in effect.
 */
require_once 'includes/functions.php';
startSession();

header("Location: forgot_password.php");
exit();
?>
