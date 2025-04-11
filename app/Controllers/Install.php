<?php
namespace App\Controllers;

use CodeIgniter\Controller;
use Config\Database;
use Config\Services;

class Install extends Controller
{
    protected $session;

    public function __construct()
    {
        $this->session = Services::session();
    }

    /**
     * Check if the system is already installed
     */
    protected function isInstalled()
    {
        if (file_exists(ROOTPATH . 'installed.txt')) {
            return true;
        }

        return false;
    }

    /**
     * Installation index page
     */
    public function index()
    {
        // Redirect to home if already installed
        if ($this->isInstalled()) {
            return redirect()->to(base_url());
        }

        $data = [
            'title' => 'Cài đặt hệ thống',
            'step' => 1,
            'requirements' => $this->checkRequirements(),
            'permissions' => $this->checkPermissions()
        ];

        return view('install/index.html', $data);
    }

    /**
     * Step 2: Database configuration
     */
    public function database()
    {
        // Redirect to home if already installed
        if ($this->isInstalled()) {
            return redirect()->to(base_url());
        }

        // Redirect to step 1 if requirements are not met
        $requirements = $this->checkRequirements();
        $permissions = $this->checkPermissions();

        foreach ($requirements as $requirement) {
            if (!$requirement['status']) {
                $this->session->setFlashdata('error', 'Cấu hình hệ thống chưa đáp ứng yêu cầu. Vui lòng kiểm tra lại.');
                return redirect()->to(base_url('install'));
            }
        }

        foreach ($permissions as $permission) {
            if (!$permission['status']) {
                $this->session->setFlashdata('error', 'Thiếu quyền truy cập thư mục. Vui lòng cấp quyền ghi cho các thư mục được liệt kê.');
                return redirect()->to(base_url('install'));
            }
        }

        $data = [
            'title' => 'Cài đặt hệ thống - Cấu hình Cơ sở dữ liệu',
            'step' => 2
        ];

        return view('install/database.html', $data);
    }

    /**
     * Process database configuration and setup
     */
    public function processDatabaseSetup()
    {
        // Redirect to home if already installed
        if ($this->isInstalled()) {
            return redirect()->to(base_url());
        }

        $validation = Services::validation();
        $validation->setRules([
            'db_hostname' => 'required',
            'db_name' => 'required',
            'db_username' => 'required',
            'db_port' => 'required|numeric',
        ]);

        // If validation fails, go back with errors
        if (!$validation->withRequest($this->request)->run()) {
            $this->session->setFlashdata('errors', $validation->getErrors());
            $this->session->setFlashdata('input', $this->request->getPost());
            return redirect()->to(base_url('install/database'));
        }

        // Get database configuration from POST
        $dbHostname = $this->request->getPost('db_hostname');
        $dbName = $this->request->getPost('db_name');
        $dbUsername = $this->request->getPost('db_username');
        $dbPassword = $this->request->getPost('db_password');
        $dbPort = $this->request->getPost('db_port');

        // Test connection
        try {
            $dsn = "mysql:host=$dbHostname;port=$dbPort;dbname=$dbName";
            $test = new \PDO($dsn, $dbUsername, $dbPassword);

            // Save database configuration
            $this->saveDatabaseConfig($dbHostname, $dbName, $dbUsername, $dbPassword, $dbPort);

            // Import database schema
            $this->importDatabaseSchema();

            // Redirect to step 3 (Admin Setup)
            return redirect()->to(base_url('install/admin'));
        } catch (\Exception $e) {
            $this->session->setFlashdata('error', 'Kết nối cơ sở dữ liệu thất bại: ' . $e->getMessage());
            $this->session->setFlashdata('input', $this->request->getPost());
            return redirect()->to(base_url('install/database'));
        }
    }

    /**
     * Step 3: Admin account setup
     */
    public function admin()
    {
        // Redirect to home if already installed
        if ($this->isInstalled()) {
            return redirect()->to(base_url());
        }

        // Check if database is configured
        if (!file_exists(ROOTPATH . 'database_configured.txt')) {
            $this->session->setFlashdata('error', 'Bạn phải cấu hình cơ sở dữ liệu trước.');
            return redirect()->to(base_url('install/database'));
        }

        $data = [
            'title' => 'Cài đặt hệ thống - Tạo tài khoản quản trị',
            'step' => 3
        ];

        return view('install/admin.html', $data);
    }

    /**
     * Process admin account setup
     */
    public function processAdminSetup()
    {
        // Redirect to home if already installed
        if ($this->isInstalled()) {
            return redirect()->to(base_url());
        }

        $validation = Services::validation();
        $validation->setRules([
            'username' => 'required|alpha_numeric|min_length[4]|max_length[20]',
            'email' => 'required|valid_email',
            'password' => 'required|min_length[6]',
            'password_confirm' => 'required|matches[password]',
            'site_name' => 'required|max_length[100]'
        ]);

        // If validation fails, go back with errors
        if (!$validation->withRequest($this->request)->run()) {
            $this->session->setFlashdata('errors', $validation->getErrors());
            $this->session->setFlashdata('input', $this->request->getPost());
            return redirect()->to(base_url('install/admin'));
        }

        try {
            // Get admin and site info from POST
            $username = $this->request->getPost('username');
            $email = $this->request->getPost('email');
            $password = password_hash($this->request->getPost('password'), PASSWORD_DEFAULT);
            $siteName = $this->request->getPost('site_name');
            $siteDescription = $this->request->getPost('site_description');

            // Create admin user
            $this->createAdminUser($username, $email, $password);

            // Save site settings
            $this->saveSiteSettings($siteName, $siteDescription);

            // Complete installation
            $this->completeInstallation();

            // Set success message and redirect to completion
            $this->session->setFlashdata('success', 'Cài đặt hệ thống thành công!');
            return redirect()->to(base_url('install/complete'));
        } catch (\Exception $e) {
            $this->session->setFlashdata('error', 'Có lỗi xảy ra: ' . $e->getMessage());
            $this->session->setFlashdata('input', $this->request->getPost());
            return redirect()->to(base_url('install/admin'));
        }
    }

    /**
     * Installation complete page
     */
    public function complete()
    {
        // Redirect to home if no success message (direct access)
        if (!$this->session->getFlashdata('success') && !$this->isInstalled()) {
            return redirect()->to(base_url('install'));
        }

        $data = [
            'title' => 'Cài đặt hoàn tất',
            'step' => 4
        ];

        return view('install/complete.html', $data);
    }

    /**
     * Check system requirements
     */
    protected function checkRequirements()
    {
        $requirements = [
            [
                'name' => 'PHP Version',
                'required' => '7.4 or higher',
                'current' => PHP_VERSION,
                'status' => version_compare(PHP_VERSION, '7.4.0', '>=')
            ],
            [
                'name' => 'PDO MySQL Extension',
                'required' => 'Enabled',
                'current' => extension_loaded('pdo_mysql') ? 'Enabled' : 'Disabled',
                'status' => extension_loaded('pdo_mysql')
            ],
            [
                'name' => 'Fileinfo Extension',
                'required' => 'Enabled',
                'current' => extension_loaded('fileinfo') ? 'Enabled' : 'Disabled',
                'status' => extension_loaded('fileinfo')
            ],
            [
                'name' => 'CURL Extension',
                'required' => 'Enabled',
                'current' => extension_loaded('curl') ? 'Enabled' : 'Disabled',
                'status' => extension_loaded('curl')
            ],
            [
                'name' => 'GD Extension',
                'required' => 'Enabled',
                'current' => extension_loaded('gd') ? 'Enabled' : 'Disabled',
                'status' => extension_loaded('gd')
            ],
            [
                'name' => 'JSON Extension',
                'required' => 'Enabled',
                'current' => extension_loaded('json') ? 'Enabled' : 'Disabled',
                'status' => extension_loaded('json')
            ]
        ];

        return $requirements;
    }

    /**
     * Check directory permissions
     */
    protected function checkPermissions()
    {
        $permissions = [
            [
                'name' => 'writable/',
                'required' => 'Writable',
                'current' => is_writable(WRITEPATH) ? 'Writable' : 'Not Writable',
                'status' => is_writable(WRITEPATH)
            ],
            [
                'name' => 'writable/cache/',
                'required' => 'Writable',
                'current' => is_writable(WRITEPATH . 'cache') ? 'Writable' : 'Not Writable',
                'status' => is_writable(WRITEPATH . 'cache')
            ],
            [
                'name' => 'writable/logs/',
                'required' => 'Writable',
                'current' => is_writable(WRITEPATH . 'logs') ? 'Writable' : 'Not Writable',
                'status' => is_writable(WRITEPATH . 'logs')
            ],
            [
                'name' => 'writable/uploads/',
                'required' => 'Writable',
                'current' => is_writable(WRITEPATH . 'uploads') ? 'Writable' : 'Not Writable',
                'status' => is_writable(WRITEPATH . 'uploads')
            ],
            [
                'name' => 'app/Config/Database.php',
                'required' => 'Writable',
                'current' => is_writable(APPPATH . 'Config/Database.php') ? 'Writable' : 'Not Writable',
                'status' => is_writable(APPPATH . 'Config/Database.php')
            ]
        ];

        return $permissions;
    }

    /**
     * Save database configuration to config file
     */
    protected function saveDatabaseConfig($hostname, $database, $username, $password, $port)
    {
        $configPath = APPPATH . 'Config/Database.php';
        $config = file_get_contents($configPath);

        // Replace database settings in config file
        $config = preg_replace("/'hostname' => '.*?'/", "'hostname' => '$hostname'", $config);
        $config = preg_replace("/'database' => '.*?'/", "'database' => '$database'", $config);
        $config = preg_replace("/'username' => '.*?'/", "'username' => '$username'", $config);
        $config = preg_replace("/'password' => '.*?'/", "'password' => '$password'", $config);
        $config = preg_replace("/'port' => .*?,/", "'port' => $port,", $config);

        // Save updated config file
        file_put_contents($configPath, $config);

        // Create a flag file to indicate database is configured
        file_put_contents(ROOTPATH . 'database_configured.txt', date('Y-m-d H:i:s'));
    }

    /**
     * Import database schema
     */
    protected function importDatabaseSchema()
    {
        $db = Database::connect();
        $schema = file_get_contents(ROOTPATH . 'database/schema.sql');

        // Split into individual statements
        $statements = array_filter(array_map('trim', explode(';', $schema)));

        // Execute each SQL statement
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $db->query($statement);
            }
        }
    }

    /**
     * Create admin user
     */
    protected function createAdminUser($username, $email, $password)
    {
        $db = Database::connect();

        // Check if admin user exists
        $query = $db->query("SELECT * FROM users WHERE role = 'admin'");

        if ($query->getNumRows() > 0) {
            // Update existing admin
            $db->query("UPDATE users SET username = ?, email = ?, password = ? WHERE role = 'admin'", [
                $username, $email, $password
            ]);
        } else {
            // Create new admin user
            $db->query("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, 'admin', 'active')", [
                $username, $email, $password
            ]);
        }
    }

    /**
     * Save site settings
     */
    protected function saveSiteSettings($siteName, $siteDescription)
    {
        $db = Database::connect();

        // Check if settings table exists
        $query = $db->query("SHOW TABLES LIKE 'settings'");

        if ($query->getNumRows() == 0) {
            // Create settings table if it doesn't exist
            $db->query("CREATE TABLE settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL UNIQUE,
                value TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )");
        }

        // Save site settings
        $settings = [
            'site_name' => $siteName,
            'site_description' => $siteDescription,
            'installed_at' => date('Y-m-d H:i:s')
        ];

        foreach ($settings as $name => $value) {
            $db->query("INSERT INTO settings (name, value) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE value = ?", [$name, $value, $value]);
        }
    }

    /**
     * Complete installation by creating a flag file
     */
    protected function completeInstallation()
    {
        // Create a flag file to indicate installation is complete
        file_put_contents(ROOTPATH . 'installed.txt', date('Y-m-d H:i:s'));

        // Remove the database configuration flag file
        if (file_exists(ROOTPATH . 'database_configured.txt')) {
            unlink(ROOTPATH . 'database_configured.txt');
        }

        // Delete installation files
        $this->removeInstallationFiles();
    }

    /**
     * Remove installation files to improve security
     */
    protected function removeInstallationFiles()
    {
        // Files to remove
        $filesToRemove = [
            APPPATH . 'Controllers/Install.php',
            APPPATH . 'Views/install/admin.html',
            APPPATH . 'Views/install/complete.html',
            APPPATH . 'Views/install/database.html',
            APPPATH . 'Views/install/index.html',
            ROOTPATH . 'scripts/db_init.php',
        ];

        // Remove files
        foreach ($filesToRemove as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }

        // Remove directory
        @rmdir(APPPATH . 'Views/install');

        // Remove install routes from Routes.php
        $this->removeInstallRoutes();
    }

    /**
     * Remove installation routes from Routes.php
     */
    protected function removeInstallRoutes()
    {
        $routesFile = APPPATH . 'Config/Routes.php';

        if (file_exists($routesFile)) {
            $content = file_get_contents($routesFile);

            // Find the installation route section and remove it
            $pattern = "/\/\/ Installation routes.*?\/\/ Main routes/s";
            $replacement = "// Main routes";

            $updatedContent = preg_replace($pattern, $replacement, $content);

            // Save the updated content
            file_put_contents($routesFile, $updatedContent);
        }
    }
}
