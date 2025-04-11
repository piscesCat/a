<?php
// app/Controllers/BaseController.php
namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Psr\Log\LoggerInterface;
use App\Models\SettingsModel;
use App\Models\UserModel;
use App\Models\CategoryModel;
use App\Models\LogModel;

/**
 * Class BaseController
 *
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 */
abstract class BaseController extends Controller
{
    /**
     * Instance of the main Request object.
     *
     * @var CLIRequest|IncomingRequest
     */
    protected $request;

    /**
     * An array of helpers to be loaded automatically upon
     * class instantiation. These helpers will be available
     * to all other controllers that extend BaseController.
     *
     * @var array
     */
    protected $helpers = ['url', 'form', 'text', 'date', 'cookie', 'security'];

    /**
     * Session instance
     */
    protected $session;

    /**
     * Models
     */
    protected $userModel;
    protected $settingsModel;
    protected $logModel;

    /**
     * Current logged in user
     */
    protected $currentUser;

    /**
     * Database instance
     */
    protected $db;

    /**
     * Is the system installed
     */
    protected $isInstalled = false;

    /**
     * View data
     */
    protected $viewData = [];

    /**
     * Settings cache
     */
    protected static $settingsCache = null;

    /**
     * Constructor.
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Do Not Edit This Line
        parent::initController($request, $response, $logger);

        // Initialize database
        $this->db = \Config\Database::connect();

        // Initialize session
        $this->session = Services::session();

        // Check if system is installed
        $this->isInstalled = file_exists(ROOTPATH . 'installed.txt');

        // Check CSRF for POST requests
        $this->validateCSRF();

        // Initialize models
        $this->initModels();

        // Get current user from session
        $this->loadCurrentUser();

        // Share common data with all views
        $this->shareCommonData();
    }

    /**
     * Initialize models
     */
    protected function initModels()
    {
        if ($this->isInstalled) {
            $this->userModel = new UserModel();
            $this->settingsModel = new SettingsModel();
            $this->logModel = new LogModel();
        }
    }

    /**
     * Validate CSRF token for POST, PUT, DELETE requests
     */
    protected function validateCSRF()
    {
        // Skip for installation or for GET requests
        if (!$this->isInstalled || $this->request->getMethod() === 'get') {
            return;
        }

        // Check CSRF token
        if (!$this->request->isAJAX() && ($this->request->getMethod() === 'post' || $this->request->getMethod() === 'put' || $this->request->getMethod() === 'delete')) {
            if (!csrf_verify($this->request)) {
                // Log the CSRF attack attempt
                if (isset($this->logModel)) {
                    $this->logModel->error('CSRF validation failed', [
                        'ip' => $this->request->getIPAddress(),
                        'uri' => $this->request->getUri(),
                        'user_agent' => $this->request->getUserAgent()
                    ]);
                }

                // Redirect with error
                $this->session->setFlashdata('error', 'Xác thực bảo mật thất bại. Vui lòng thử lại.');

                // If it's AJAX, return JSON
                if ($this->request->isAJAX()) {
                    $response = Services::response();
                    return $response->setJSON([
                        'success' => false,
                        'message' => 'CSRF validation failed'
                    ])->setStatusCode(403);
                }

                // Otherwise redirect
                return redirect()->back();
            }
        }
    }

    /**
     * Load current user from session
     */
    protected function loadCurrentUser()
    {
        if ($this->isInstalled && $this->session->has('user_id')) {
            $userId = (int) $this->session->get('user_id');

            if ($userId > 0) {
                $this->currentUser = $this->userModel->find($userId);

                // If user not found or inactive, log them out
                if (empty($this->currentUser) || $this->currentUser['status'] !== 'active') {
                    $this->session->remove(['user_id', 'username', 'role']);
                    $this->currentUser = null;

                    // Regenerate session ID for security
                    $this->session->regenerate(true);
                }
            }
        }
    }

    /**
     * Share common data with all views
     */
    protected function shareCommonData()
    {
        // Common data for all views
        if ($this->isInstalled) {
            try {
                // Load categories (with caching)
                $categoryModel = new CategoryModel();
                $categories = cache('categories') ?? $categoryModel->findAll();

                if (empty(cache('categories'))) {
                    cache()->save('categories', $categories, 300); // Cache for 5 minutes
                }

                // Get site settings
                $settings = $this->getSettings();

                // Prepare flash messages
                $flashMessages = [];

                if ($this->session->getFlashdata('success')) {
                    $flashMessages['success'] = $this->session->getFlashdata('success');
                }

                if ($this->session->getFlashdata('error')) {
                    $flashMessages['error'] = $this->session->getFlashdata('error');
                }

                if ($this->session->getFlashdata('warning')) {
                    $flashMessages['warning'] = $this->session->getFlashdata('warning');
                }

                if ($this->session->getFlashdata('info')) {
                    $flashMessages['info'] = $this->session->getFlashdata('info');
                }

                // Share data with views
                $this->viewData = [
                    'current_user' => $this->currentUser,
                    'categories' => $categories,
                    'settings' => $settings,
                    'current_url' => current_url(),
                    'is_installed' => $this->isInstalled,
                    'flash_messages' => $flashMessages
                ];
            } catch (\Exception $e) {
                log_message('error', 'Error loading common data: ' . $e->getMessage());

                // Minimal data set for error cases
                $this->viewData = [
                    'current_url' => current_url(),
                    'is_installed' => $this->isInstalled
                ];
            }
        } else {
            // Basic data for installation
            $this->viewData = [
                'current_url' => current_url(),
                'is_installed' => $this->isInstalled
            ];
        }
    }

    /**
     * Get settings from database with caching
     */
    protected function getSettings()
    {
        // Return cached settings if available
        if (self::$settingsCache !== null) {
            return self::$settingsCache;
        }

        // Check for cache
        $cachedSettings = cache('site_settings');
        if ($cachedSettings !== null) {
            self::$settingsCache = $cachedSettings;
            return $cachedSettings;
        }

        // Default settings as fallback
        $defaultSettings = [
            'site_name' => 'Web Review Phim',
            'site_description' => 'Trang web review phim hay, cập nhật thông tin phim mới nhất'
        ];

        if (!$this->isInstalled) {
            return $defaultSettings;
        }

        try {
            // Use SettingsModel to get settings
            $settings = $this->settingsModel->getAllSettings();

            if (empty($settings)) {
                return $defaultSettings;
            }

            // Cache the settings
            cache()->save('site_settings', $settings, 300); // Cache for 5 minutes
            self::$settingsCache = $settings;

            return $settings;
        } catch (\Exception $e) {
            log_message('error', 'Error loading settings: ' . $e->getMessage());
            return $defaultSettings;
        }
    }

    /**
     * Clear the settings cache
     */
    protected function clearSettingsCache()
    {
        self::$settingsCache = null;
        cache()->delete('site_settings');
        cache()->delete('categories');
    }

    /**
     * Render a view with common data and optimized caching
     */
    protected function renderView(string $view, array $data = [])
    {
        // Merge common data with view data
        $mergedData = array_merge($this->viewData, $data);

        // Check if this is an AJAX request
        $isAjaxRequest = $this->request->isAJAX();

        // For AJAX requests, only return the view content without the layout
        if ($isAjaxRequest) {
            // If it's an AJAX request and the view includes a layout,
            // we need to set a flag to prevent layout rendering
            $mergedData['ajax_request'] = true;

            // Render the view
            return view($view, $mergedData);
        }

        // For normal requests, render with layout as usual
        return view($view, $mergedData);
    }

    /**
     * Check if user is logged in
     */
    protected function isLoggedIn()
    {
        return !empty($this->currentUser);
    }

    /**
     * Check if user is admin
     */
    protected function isAdmin()
    {
        if (empty($this->currentUser)) {
            return false;
        }

        return $this->currentUser['role'] === 'admin';
    }

    /**
     * Redirect to login page if not logged in
     */
    protected function requireLogin()
    {
        if (!$this->isLoggedIn()) {
            // Log the unauthorized access attempt
            if (isset($this->logModel)) {
                $this->logModel->warning('Unauthorized access attempt', [
                    'ip' => $this->request->getIPAddress(),
                    'uri' => $this->request->getUri(),
                    'user_agent' => $this->request->getUserAgent()
                ]);
            }

            $this->session->setFlashdata('error', 'Vui lòng đăng nhập để tiếp tục.');
            return redirect()->to(base_url('login?redirect=' . current_url()));
        }

        return true;
    }

    /**
     * Redirect to home page if not admin
     */
    protected function requireAdmin()
    {
        // First check login
        $loginCheck = $this->requireLogin();
        if ($loginCheck !== true) {
            return $loginCheck;
        }

        if (!$this->isAdmin()) {
            // Log the unauthorized admin access attempt
            if (isset($this->logModel)) {
                $this->logModel->warning('Unauthorized admin access attempt', [
                    'user_id' => $this->currentUser['id'] ?? null,
                    'username' => $this->currentUser['username'] ?? 'unknown',
                    'ip' => $this->request->getIPAddress(),
                    'uri' => $this->request->getUri()
                ]);
            }

            $this->session->setFlashdata('error', 'Bạn không có quyền truy cập trang này.');
            return redirect()->to(base_url());
        }

        return true;
    }

    /**
     * Redirect to installation if not installed
     */
    protected function requireInstalled()
    {
        if (!$this->isInstalled) {
            return redirect()->to(base_url('install'));
        }

        return true;
    }

    /**
     * Protect against maintenance mode
     */
    protected function checkMaintenanceMode()
    {
        if (!$this->isInstalled) {
            return true;
        }

        $settings = $this->getSettings();

        // Check if maintenance mode is on and user is not admin
        if (isset($settings['maintenance_mode']) && $settings['maintenance_mode'] === 'on' && !$this->isAdmin()) {
            return view('maintenance');
        }

        return true;
    }

    /**
     * Format date to human readable
     */
    protected function formatDate($date, $format = 'd/m/Y H:i')
    {
        if (empty($date)) {
            return '';
        }

        return date($format, strtotime($date));
    }

    /**
     * Calculate time ago from a date
     */
    protected function timeAgo($datetime, $full = false)
    {
        $now = new \DateTime;
        $ago = new \DateTime($datetime);
        $diff = $now->diff($ago);

        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;

        $string = [
            'y' => 'năm',
            'm' => 'tháng',
            'w' => 'tuần',
            'd' => 'ngày',
            'h' => 'giờ',
            'i' => 'phút',
            's' => 'giây',
        ];

        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? '' : '');
            } else {
                unset($string[$k]);
            }
        }

        if (!$full) $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . ' trước' : 'vừa xong';
    }
}
