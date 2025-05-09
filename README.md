# Server Monitoring Dashboard

A lightweight PHP script to monitor and report system metrics including CPU load, memory usage, disk space, system uptime, and MySQL connectivity. The script collects data from `/proc` on Linux systems and outputs an HTML-formatted report.

## Features

* **CPU Load Monitoring**: Calculates 1, 5, and 15-minute load averages as a percentage of CPU cores.
* **Memory Usage**: Parses `/proc/meminfo` to report total, used, and available memory.
* **Disk Usage**: Reports total, used, and free space for root (`/`), home, and temporary (`/tmp`) directories.
* **System Uptime**: Reads `/proc/uptime` to display days, hours, and minutes since last boot.
* **MySQL Status Check**: Verifies database connectivity using MySQLi and reports server version.
* **Threshold Warnings**: Configurable alert thresholds for high CPU, low memory, low disk space, and long uptime.

## Prerequisites

* PHP 7.4 or higher with the following extensions enabled:

  * `mysqli`
  * `posix` (optional for user detection)
  * `proc_*` functions (for reading `/proc` filesystem)
* MySQL or MariaDB server
* Linux-based operating system (for `/proc` filesystem support)

## Installation

1. **Clone the Repository**

   ```bash
   git clone https://github.com/your-username/server-monitor.git
   cd server-monitor
   ```

2. **Configure Database Credentials**
   Edit `config.php` and set your database connection parameters:

   ```php
   <?php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'your_db_user');
   define('DB_PASS', 'your_db_password');
   define('DB_NAME', 'your_db_name');
   ```

3. **Adjust Thresholds**
   You can define alert thresholds in `config.php` or in a dedicated settings file:

   ```php
   define('THRESHOLD_CPU_LOAD_1', 0.8);
   define('THRESHOLD_CPU_LOAD_5', 0.6);
   define('THRESHOLD_CPU_LOAD_15', 0.6);
   define('THRESHOLD_RAM_PERCENT', 20);
   define('THRESHOLD_DISK_ROOT_PERCENT', 10);
   define('THRESHOLD_DISK_TMP_PERCENT', 20);
   define('THRESHOLD_DISK_HOME_PERCENT', 20);
   define('THRESHOLD_UPTIME_DAYS', 365);
   ```

4. **Deploy to Web Server**

   * Copy the project files to your web server root (e.g., `/var/www/html/server-monitor`).
   * Ensure PHP has read permissions for `/proc` and the project directory.

## Usage

Open your browser and navigate to:

```
http://<server-ip-or-domain>/server-monitor/index.php
```

You will see a formatted report showing system health and any warnings.

## Project Structure

```
server-monitor/
├── config.php         # Configuration for database and thresholds
├── index.php          # Main entry point, renders the dashboard
├── src/
│   ├── SystemInfo.php # Collects CPU, memory, disk, uptime metrics
│   ├── DatabaseChecker.php # Checks MySQL connectivity
│   └── Renderer.php   # Outputs HTML report
└── README.md          # Project documentation
```

## Troubleshooting

* **Blank Page or Errors**: Enable error reporting in `php.ini` or add at top of `index.php`:

  ```php
  ini_set('display_errors', 1);
  error_reporting(E_ALL);
  ```
* **Permission Denied Reading `/proc`**: Ensure the PHP process user (e.g., `www-data`) has read access to `/proc` files.
* **MySQL Connection Issues**: Verify credentials and network access. Test with command-line:

  ```bash
  mysql -u your_db_user -p -h localhost
  ```

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/your-feature`)
3. Commit your changes (`git commit -m 'Add some feature'`)
4. Push to the branch (`git push origin feature/your-feature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License. See [LICENSE](LICENSE) for details.

---

*Last updated: 2025-04-26*
