<?php
require_once 'php/auth.php';

logout();
header('Location: /login.php');
exit;
?>