<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);


$remote_url = "https://raw.githubusercontent.com/sshnevis/Server-Screening-Script/refs/heads/master/screening.php";
$local_file = "screening.php";

try {
    $ch = curl_init($remote_url);
    if (!$ch) {
        throw new Exception("curl_init failed");
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $code = curl_exec($ch);
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($code === false) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        throw new Exception("cURL error: $error_msg");
    }

    curl_close($ch);

    if ($http_status !== 200) {
        throw new Exception("HTTP error: status code $http_status");
    }

    if (file_put_contents($local_file, $code) === false) {
        throw new Exception("Failed to write to file: $local_file");
    }

    echo "Update successful";

} catch (Exception $e) {
    echo "Update failed: " . $e->getMessage();
}
