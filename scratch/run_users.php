<?php
session_start();
$_SESSION['logged_in'] = true;
$_SESSION['role'] = 'admin';
$_SESSION['user_id'] = 1;
chdir('admin');
require 'users.php';
?>
