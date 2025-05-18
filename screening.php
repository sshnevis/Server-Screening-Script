<?php
require_once 'config.php';

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$username = null;
if (function_exists('posix_getuid') && function_exists('posix_getpwuid')) {
    $uid = posix_getuid();
    $userInfo = posix_getpwuid($uid);
    if ($userInfo && isset($userInfo['name'])) {
        $username = $userInfo['name'];
    }
}

if (!$username && function_exists('shell_exec')) {
    $username = trim(shell_exec('whoami'));
}
$linuxhome = $username ? "/home/" . $username : "/home";

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = $bytes ? floor(log($bytes, 1024)) : 0;
    return round($bytes / (1 << (10 * $pow)), $precision) . ' ' . $units[$pow];
}

function memory_usage() {
    $fh = fopen('/proc/meminfo','r');
    while ($line = fgets($fh)) {
        if (preg_match('/^MemAvailable:\s+(\d+)\skB$/', $line, $m)) {
            fclose($fh);
            return (int)$m[1];
        }
    }
    fclose($fh);
    return 0;
}

function memory_total() {
    $fh = fopen('/proc/meminfo','r');
    while ($line = fgets($fh)) {
        if (preg_match('/^MemTotal:\s+(\d+)\skB$/', $line, $m)) {
            fclose($fh);
            return (int)$m[1];
        }
    }
    fclose($fh);
    return 0;
}

function getFreeRamPercent() {
    $total = memory_total();
    $available = memory_usage();
    return ($available / $total) * 100;
}

function getDiskFreePercent($path = "/") {
    $total = disk_total_space($path);
    $free = disk_free_space($path);
    return ($free / $total) * 100;
}

function checkMySQLStatus($conn) {
    return isset($conn) && $conn && $conn->ping();
}

$uptimeRaw = @file_get_contents("/proc/uptime");
$uptimeSeconds = 0;
if ($uptimeRaw) {
    $uptimeSeconds = (int)explode(" ", $uptimeRaw)[0];
}

function getUptimeDays($uptimeSeconds) {
    return floor($uptimeSeconds / 86400);
}

if (function_exists('sys_getloadavg')) {
    $load = sys_getloadavg();
    $load1 = $load[0];
    $load5 = $load[1];
    $load15 = $load[2];

    $cpuCores = substr_count(file_get_contents('/proc/cpuinfo'), 'processor') ?: 1;

    $issues = [];

    if ($load1 > 0.8 * $cpuCores || $load5 > 0.6 * $cpuCores || $load15 > 0.6 * $cpuCores) {
        $issues[] = "High CPU usage";
    }

    if (getFreeRamPercent() < 20) {
        $issues[] = "Free RAM < 20%";
    }

    if (getDiskFreePercent("/") < 10) {
        $issues[] = "Disk «/» Free < 10%";
    }

if (getDiskFreePercent("/tmp/") < 20) { 
        $issues[] = "/tmp Free < 20%";
    }
	
    if (getDiskFreePercent($linuxhome) < 20) {
        $issues[] = "/home Free < 20%";
    }

    if (getUptimeDays($uptimeSeconds) > 365) {
        $issues[] = "Uptime > 365 days";
    }

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset("utf8mb4");

    if (!checkMySQLStatus($conn)) {
        $issues[] = "MySQL not connected";
    }
    

    echo "<pre>";
    if (empty($issues)) {
        echo "All is Normal\n";
    } else {
        echo "Warning:\n";
        foreach ($issues as $issue) {
            echo "- $issue\n";
        }
    }
    echo "</pre>";
}

echo "<br>=== Server Info ===<br>";
echo "version: 1.2 / 2025/04/26 <br>";
echo "Hostname: " . gethostname() . "<br>";
echo "PHP Version: " . phpversion() . "<br>";
echo "OS: " . (php_uname() ?: 'Unavailable') . "<br>";

echo "<br>=== Uptime ===<br>";
if ($uptimeSeconds > 0) {
    $days = floor($uptimeSeconds / 86400);
    $hours = floor(($uptimeSeconds % 86400) / 3600);
    $mins = floor(($uptimeSeconds % 3600) / 60);
    echo "Uptime: {$days}d {$hours}h {$mins}m<br>";
}

echo "<br>=== CPU Usage ===<br>";
echo "CPU Load Average (Percentage):<br>";
echo "1 min: " . round(($load1 / $cpuCores) * 100, 2) . "%<br>";
echo "5 min: " . round(($load5 / $cpuCores) * 100, 2) . "%<br>";
echo "15 min: " . round(($load15 / $cpuCores) * 100, 2) . "%<br>";

echo "<br>=== Memory ===<br>";
$memTotal = memory_total();
$memAvailable = memory_usage();
$memUsed = $memTotal - $memAvailable;
echo "Total: " . formatBytes($memTotal * 1024) . "<br>";
echo "Used: " . formatBytes($memUsed * 1024) . "<br>";
echo "Free: " . formatBytes($memAvailable * 1024) . "<br>";
echo "Used Percent: " . round(($memUsed / $memTotal) * 100, 2) . "%<br>";

echo "<br>=== Disk Usage ===<br>";
echo "<br><strong> / Directory:</strong><br>";
$diskTotal = disk_total_space("/");
$diskFree = disk_free_space("/");
$diskUsed = $diskTotal - $diskFree;
echo "Total: " . formatBytes($diskTotal) . "<br>";
echo "Used: " . formatBytes($diskUsed) . "<br>";
echo "Free: " . formatBytes($diskFree) . "<br>";

echo "<br><strong> home Directory:</strong><br>";
$homeTotal = disk_total_space($linuxhome);
$homeFree = disk_free_space($linuxhome);
$homeUsed = $homeTotal - $homeFree;
echo "Total: " . formatBytes($homeTotal) . "<br>";
echo "Used: " . formatBytes($homeUsed) . "<br>";
echo "Free: " . formatBytes($homeFree) . "<br>";

echo "<br><strong> tmp Directory:</strong><br>";
$tmpTotal = disk_total_space("/tmp/");
$tmpFree = disk_free_space("/tmp/");
$tmpUsed = $tmpTotal - $tmpFree;
echo "Total: " . formatBytes($tmpTotal) . "<br>";
echo "Used: " . formatBytes($tmpUsed) . "<br>";
echo "Free: " . formatBytes($tmpFree) . "<br>";

echo "<br>=== MySQL Status ===<br>";
if (checkMySQLStatus($conn)) {
    echo "Status: Connected<br>";
    echo "MySQL Version: " . $conn->server_info . "<br>";
} else {
    echo "Status: Disconnected<br>";
}
echo "<br>=== HTTP Status ===<br>";
$code = http_response_code();
echo "HTTP Status Code: $code";

$conn->close();
?>
