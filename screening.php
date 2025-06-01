<?php

require_once 'config.php';

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);


class ServerMonitor {
    
    private $username;
    private $linuxhome;
    private $conn;
    private $issues = [];
    
    public function __construct() {
        $this->setUsername();
        $this->linuxhome = $this->username ? "/home/" . $this->username : "/home";
        $this->connectDB();
        $this->checkIssues();
    }

    private function setUsername() {
        if (function_exists('posix_getuid') && function_exists('posix_getpwuid')) {
            $uid = posix_getuid();
            $userInfo = posix_getpwuid($uid);
            if ($userInfo && isset($userInfo['name'])) {
                $this->username = $userInfo['name'];
                return;
            }
        }
        
        if (function_exists('shell_exec')) {
            $this->username = trim(shell_exec('whoami'));
        }
    }

    private function connectDB() {
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        $this->conn->set_charset("utf8mb4");
    }

    public function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = $bytes ? floor(log($bytes, 1024)) : 0;
        return round($bytes / (1 << (10 * $pow)), $precision) . ' ' . $units[$pow];
    }

    public function getMemoryInfo() {
        $memTotal = $this->getMemoryTotal();
        $memAvailable = $this->getMemoryAvailable();
        $memUsed = $memTotal - $memAvailable;
        
        return [
            'total' => $memTotal,
            'used' => $memUsed,
            'free' => $memAvailable,
            'percent' => round(($memUsed / $memTotal) * 100, 2)
        ];
    }

    private function getMemoryTotal() {
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

    private function getMemoryAvailable() {
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

    public function getDiskInfo($path) {
        $total = disk_total_space($path);
        $free = disk_free_space($path);
        $used = $total - $free;
        
        return [
            'total' => $total,
            'used' => $used,
            'free' => $free,
            'percent' => round(($used / $total) * 100, 2)
        ];
    }

    public function getUptime() {
        $uptimeRaw = @file_get_contents("/proc/uptime");
        $uptimeSeconds = 0;
        if ($uptimeRaw) {
            $uptimeSeconds = (int)explode(" ", $uptimeRaw)[0];
        }
        
        return [
            'days' => floor($uptimeSeconds / 86400),
            'hours' => floor(($uptimeSeconds % 86400) / 3600),
            'minutes' => floor(($uptimeSeconds % 3600) / 60)
        ];
    }

    public function getCPUUsage() {
        if (!function_exists('sys_getloadavg')) {
            return null;
        }

        $load = sys_getloadavg();
        $cpuCores = substr_count(file_get_contents('/proc/cpuinfo'), 'processor') ?: 1;
        
        return [
            '1min' => round(($load[0] / $cpuCores) * 100, 2),
            '5min' => round(($load[1] / $cpuCores) * 100, 2),
            '15min' => round(($load[2] / $cpuCores) * 100, 2)
        ];
    }

    private function checkIssues() {
        $cpu = $this->getCPUUsage();
        if ($cpu && ($cpu['1min'] > 80 || $cpu['5min'] > 60 || $cpu['15min'] > 60)) {
            $this->issues[] = "High CPU usage";
        }

        $mem = $this->getMemoryInfo();
        if (($mem['free'] / $mem['total']) * 100 < 20) {
            $this->issues[] = "Free RAM < 20%";
        }

        $rootDisk = $this->getDiskInfo("/");
        if (($rootDisk['free'] / $rootDisk['total']) * 100 < 10) {
            $this->issues[] = "Disk «/» Free < 10%";
        }

        $tmpDisk = $this->getDiskInfo("/tmp/");
        if (($tmpDisk['free'] / $tmpDisk['total']) * 100 < 20) {
            $this->issues[] = "/tmp Free < 20%";
        }

        $homeDisk = $this->getDiskInfo($this->linuxhome);
        if (($homeDisk['free'] / $homeDisk['total']) * 100 < 20) {
            $this->issues[] = "/home Free < 20%";
        }

        $uptime = $this->getUptime();
        if ($uptime['days'] > 365) {
            $this->issues[] = "Uptime > 365 days";
        }

        if (!$this->checkMySQLStatus()) {
            $this->issues[] = "MySQL not connected";
        }
    }

    public function getIssues() {
        return $this->issues;
    }

    public function checkMySQLStatus() {
        return isset($this->conn) && $this->conn && $this->conn->ping();
    }

    public function getMySQLVersion() {
        return $this->checkMySQLStatus() ? $this->conn->server_info : 'N/A';
    }

    public function __destruct() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}

// Create instance of monitoring class
$monitor = new ServerMonitor();

// Get required information
$memInfo = $monitor->getMemoryInfo();
$cpuInfo = $monitor->getCPUUsage();
$uptime = $monitor->getUptime();
$rootDisk = $monitor->getDiskInfo("/");
$homeDisk = $monitor->getDiskInfo("/home");
$tmpDisk = $monitor->getDiskInfo("/tmp");
$issues = $monitor->getIssues();

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Info</title>
    <style>
        :root {
            --primary-color: #6a11cb;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --bg-color: #f9f9f9;
            --text-color: #333;
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: var(--bg-color);
            color: var(--text-color);
            line-height: 1.6;
        }

        main {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
            padding: 15px;
            max-width: 1400px;
            margin: 0 auto;
        }

        section {
            background: #fff;
            border-radius: 8px;
            padding: 15px;
            box-shadow: var(--shadow);
        }

        section h2 {
            font-size: 1.1em;
            margin: 0 0 10px;
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 5px;
        }

        .info-grid {
            display: grid;
            gap: 8px;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: left;
            padding: 5px 0;
            border-bottom: 1px dashed #eee;
        }

        .info-label {
            color: var(--primary-color);
            font-weight: bold;
        }

        .alert {
            color: #fff;
            background: var(--danger-color);
            padding: 8px;
            border-radius: 4px;
            font-weight: bold;
        }

        .warning {
            color: #fff;
            background: var(--warning-color);
            padding: 8px;
            border-radius: 4px;
            font-weight: bold;
            margin: 5px 0;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #eee;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 5px;
        }

        .progress-fill {
            height: 100%;
            background: var(--primary-color);
            transition: width 0.3s ease;
        }

        .critical {
            background: var(--danger-color);
        }

        .warning-fill {
            background: var(--warning-color);
        }

        footer {
            text-align: center;
            padding: 15px;
            background: #333;
            color: #fff;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            main {
                grid-template-columns: 1fr;
            }
            
            section {
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <main>
    <section aria-label="System Status">
    <h2>Status</h2>

    <?php
        if (!is_array($issues)) {
            $issues = [];
        }
    ?>

    <?php if (empty($issues)): ?>
        <p class="info-item success">
            <span class="info-label">All is Normal</span>
        </p>
    <?php else: ?>
        <div class="info-grid">
            <?php foreach ($issues as $issue): ?>
                <div class="warning"><?php echo htmlspecialchars($issue); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <p class="status-time">Last checked: <?php echo date('Y-m-d H:i'); ?></p>
</section>


        <section aria-label="Server Information">
            <h2>Server Info</h2>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Version:</span>
               
                    <span> 1.3 / 2025/05/31 </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Hostname:</span>
                   
                    <span><?php echo htmlspecialchars(gethostname()); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">PHP Version:</span>
                      
                    <span><?php echo htmlspecialchars(phpversion()); ?></span>
                </div>
                 <div class="info-item">
                    <span class="info-label">OS:</span>

                    <span><?php echo htmlspecialchars(php_uname()); ?></span>
                </div>
            </div>
        </section>

        <section aria-label="System Uptime">
            <h2>Uptime</h2>
            <p class="info-item">
                <span class="info-label">Active Time:</span>
                <span><?php echo "{$uptime['days']}d {$uptime['hours']}h {$uptime['minutes']}m"; ?></span>
            </p>
        </section>

        <?php if ($cpuInfo): ?>
        <section aria-label="CPU Usage">
            <h2>CPU Usage</h2>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">1 min:</span>
                    <span><?php echo $cpuInfo['1min']; ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill <?php echo $cpuInfo['1min'] > 80 ? 'critical' : ($cpuInfo['1min'] > 60 ? 'warning-fill' : ''); ?>" 
                         style="width: <?php echo $cpuInfo['1min']; ?>%"></div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">5 min:</span>
                    <span><?php echo $cpuInfo['5min']; ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill <?php echo $cpuInfo['5min'] > 80 ? 'critical' : ($cpuInfo['5min'] > 60 ? 'warning-fill' : ''); ?>" 
                         style="width: <?php echo $cpuInfo['5min']; ?>%"></div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">15 min:</span>
                    <span><?php echo $cpuInfo['15min']; ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill <?php echo $cpuInfo['15min'] > 80 ? 'critical' : ($cpuInfo['15min'] > 60 ? 'warning-fill' : ''); ?>" 
                         style="width: <?php echo $cpuInfo['15min']; ?>%"></div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <section aria-label="Memory Status">
            <h2>Memory</h2>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Total:</span>
                    <span><?php echo $monitor->formatBytes($memInfo['total'] * 1024); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Used:</span>
                    <span><?php echo $monitor->formatBytes($memInfo['used'] * 1024); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Free:</span>
                    <span><?php echo $monitor->formatBytes($memInfo['free'] * 1024); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Used Percent:</span>
                    <span><?php echo $memInfo['percent']; ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill <?php echo $memInfo['percent'] > 80 ? 'critical' : ($memInfo['percent'] > 60 ? 'warning-fill' : ''); ?>" 
                         style="width: <?php echo $memInfo['percent']; ?>%"></div>
                </div>
            </div>
        </section>

        <section aria-label="Disk Space">
            <h2>Disk Usage</h2>
            <div class="info-grid">
                <?php
                $diskPaths = [
                    'root' => $rootDisk,
                    'home' => $homeDisk,
                    'tmp' => $tmpDisk
                ];
                foreach ($diskPaths as $label => $disk):
                    $usedPercent = round(($disk['used'] / $disk['total']) * 100, 2);
                ?>
                <div class="info-item">
                    <span class="info-label">/<?php echo $label; ?>:</span>
                </div>
                <div class="info-grid" style="padding-left: 15px;">
                    <div class="info-item">
                        <span>Total: <?php echo $monitor->formatBytes($disk['total']); ?></span>
                    </div>
                    <div class="info-item">
                        <span>Used: <?php echo $monitor->formatBytes($disk['used']); ?></span>
                    </div>
                    <div class="info-item">
                        <span>Free: <?php echo $monitor->formatBytes($disk['free']); ?></span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill <?php echo $usedPercent > 90 ? 'critical' : ($usedPercent > 80 ? 'warning-fill' : ''); ?>" 
                             style="width: <?php echo $usedPercent; ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section aria-label="MySQL Status">
            <h2>MySQL Status</h2>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Status:</span>
                    <span class="<?php echo $monitor->checkMySQLStatus() ? '' : 'alert'; ?>">
                        <?php echo $monitor->checkMySQLStatus() ? 'Connected' : 'Disconnected'; ?>
                    </span>
                </div>
                <?php if ($monitor->checkMySQLStatus()): ?>
                <div class="info-item">
                    <span class="info-label">Version:</span>
                    <span><?php echo htmlspecialchars($monitor->getMySQLVersion()); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <section aria-label="HTTP Status">
            <h2>HTTP Status</h2>
            <p class="info-item">
                <span class="info-label">Status Code:</span>
                <span class="<?php echo http_response_code() === 200 ? '' : 'alert'; ?>">
                    <?php echo http_response_code(); ?>
                </span>
            </p>
        </section>
    </main>
    <footer>
       &copy; <?php echo date('Y'); ?> TalashNet Screening
    </footer>
</body>
</html>
