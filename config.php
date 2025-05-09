<?php
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    http_response_code(403);
    exit("Access denied.");
}

define('DB_HOST', 'localhost');
define('DB_USER', '');
define('DB_NAME', '');
define('DB_PASS', '');

?>