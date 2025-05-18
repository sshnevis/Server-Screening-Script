<?php

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
    curl_close($ch);

    if ($code === false || $http_status !== 200) {
        throw new Exception("Download failed");
    }

    if (file_put_contents($local_file, $code) === false) {
        throw new Exception("Write failed");
    }

    echo "Update successful";

} catch (Exception $e) {
    echo "Update failed";
}
?>
