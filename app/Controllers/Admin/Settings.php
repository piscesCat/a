<?php
namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\SettingsModel;
use App\Models\LogModel;

class Settings extends BaseController
{
    protected $settingsModel;
    protected $logModel;

    public function __construct()
    {
        $this->settingsModel = new SettingsModel();
        $this->logModel = new LogModel();
    }

    public function index()
    {
        // Get all settings
        $settings = $this->settingsModel->getAllSettings();

        return view('admin/settings/index.html', [
            'settings' => $settings,
            'current_page' => 'settings'
        ]);
    }

    public function save()
    {
        // Process form submission
        $settings = $this->request->getPost();

        // Remove CSRF field
        unset($settings['csrf_token_name']);

        // Process specific settings
        if (isset($settings['maintenance_mode']) && $settings['maintenance_mode'] == '1') {
            $settings['maintenance_mode'] = 'on';
        } else {
            $settings['maintenance_mode'] = 'off';
        }

        if (isset($settings['register_enabled']) && $settings['register_enabled'] == '1') {
            $settings['register_enabled'] = 'on';
        } else {
            $settings['register_enabled'] = 'off';
        }

        if (isset($settings['comments_enabled']) && $settings['comments_enabled'] == '1') {
            $settings['comments_enabled'] = 'on';
        } else {
            $settings['comments_enabled'] = 'off';
        }

        // Process numeric values
        $numericSettings = [
            'max_featured_stories', 'max_latest_stories',
            'max_popular_stories', 'max_completed_stories'
        ];

        foreach ($numericSettings as $key) {
            if (isset($settings[$key])) {
                $settings[$key] = (int)$settings[$key];
            }
        }

        // Handle file uploads (logo and favicon)
        $logo = $this->request->getFile('site_logo_file');
        if ($logo && $logo->isValid() && !$logo->hasMoved()) {
            $newName = $logo->getRandomName();
            $logo->move(ROOTPATH . 'public/uploads', $newName);
            $settings['site_logo'] = '/uploads/' . $newName;
        }

        $favicon = $this->request->getFile('site_favicon_file');
        if ($favicon && $favicon->isValid() && !$favicon->hasMoved()) {
            $newName = $favicon->getRandomName();
            $favicon->move(ROOTPATH . 'public/uploads', $newName);
            $settings['site_favicon'] = '/uploads/' . $newName;
        }

        // Save all settings
        $result = $this->settingsModel->saveSettings($settings);

        if ($result) {
            // Log the action
            $this->logModel->info('Settings updated', [
                'user_id' => $this->session->get('user_id'),
                'username' => $this->session->get('username')
            ]);

            return redirect()->to(base_url('/admin/settings'))->with('success', 'Cài đặt đã được lưu thành công.');
        } else {
            return redirect()->to(base_url('/admin/settings'))->with('error', 'Có lỗi xảy ra khi lưu cài đặt.');
        }
    }

    public function email()
    {
        // Get email settings
        $settings = [
            'smtp_host' => $this->settingsModel->getSetting('smtp_host'),
            'smtp_port' => $this->settingsModel->getSetting('smtp_port'),
            'smtp_user' => $this->settingsModel->getSetting('smtp_user'),
            'smtp_pass' => $this->settingsModel->getSetting('smtp_pass'),
            'smtp_from' => $this->settingsModel->getSetting('smtp_from')
        ];

        return view('admin/settings/email.html', [
            'settings' => $settings,
            'current_page' => 'settings'
        ]);
    }

    public function saveEmail()
    {
        // Process form submission
        $settings = [
            'smtp_host' => $this->request->getPost('smtp_host'),
            'smtp_port' => $this->request->getPost('smtp_port'),
            'smtp_user' => $this->request->getPost('smtp_user'),
            'smtp_from' => $this->request->getPost('smtp_from')
        ];

        // Only update password if provided
        $smtpPass = $this->request->getPost('smtp_pass');
        if (!empty($smtpPass)) {
            $settings['smtp_pass'] = $smtpPass;
        }

        // Save settings
        $result = $this->settingsModel->saveSettings($settings);

        if ($result) {
            // Log the action
            $this->logModel->info('Email settings updated', [
                'user_id' => $this->session->get('user_id'),
                'username' => $this->session->get('username')
            ]);

            return redirect()->to(base_url('/admin/settings/email'))->with('success', 'Cài đặt email đã được lưu thành công.');
        } else {
            return redirect()->to(base_url('/admin/settings/email'))->with('error', 'Có lỗi xảy ra khi lưu cài đặt email.');
        }
    }

    public function testEmail()
    {
        // Get email settings
        $smtpHost = $this->settingsModel->getSetting('smtp_host');
        $smtpPort = $this->settingsModel->getSetting('smtp_port');
        $smtpUser = $this->settingsModel->getSetting('smtp_user');
        $smtpPass = $this->settingsModel->getSetting('smtp_pass');
        $smtpFrom = $this->settingsModel->getSetting('smtp_from');

        // Set up email library
        $email = \Config\Services::email();
        $email->initialize([
            'protocol' => 'smtp',
            'SMTPHost' => $smtpHost,
            'SMTPPort' => $smtpPort,
            'SMTPUser' => $smtpUser,
            'SMTPPass' => $smtpPass,
            'mailType' => 'html',
            'charset'  => 'utf-8',
            'newline'  => "\r\n"
        ]);

        $email->setFrom($smtpFrom, 'Admin Test');
        $email->setTo($this->request->getPost('test_email'));
        $email->setSubject('Test Email');
        $email->setMessage('<p>Đây là email kiểm tra từ hệ thống.</p>');

        if ($email->send()) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Email đã được gửi thành công.'
            ]);
        } else {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Không thể gửi email. Lỗi: ' . $email->printDebugger(['headers'])
            ]);
        }
    }

    public function social()
    {
        // Get social settings
        $settings = [
            'social_facebook' => $this->settingsModel->getSetting('social_facebook'),
            'social_twitter' => $this->settingsModel->getSetting('social_twitter'),
            'social_youtube' => $this->settingsModel->getSetting('social_youtube'),
            'social_instagram' => $this->settingsModel->getSetting('social_instagram')
        ];

        return view('admin/settings/social.html', [
            'settings' => $settings,
            'current_page' => 'settings'
        ]);
    }

    public function saveSocial()
    {
        // Process form submission
        $settings = [
            'social_facebook' => $this->request->getPost('social_facebook'),
            'social_twitter' => $this->request->getPost('social_twitter'),
            'social_youtube' => $this->request->getPost('social_youtube'),
            'social_instagram' => $this->request->getPost('social_instagram')
        ];

        // Save settings
        $result = $this->settingsModel->saveSettings($settings);

        if ($result) {
            // Log the action
            $this->logModel->info('Social settings updated', [
                'user_id' => $this->session->get('user_id'),
                'username' => $this->session->get('username')
            ]);

            return redirect()->to(base_url('/admin/settings/social'))->with('success', 'Cài đặt mạng xã hội đã được lưu thành công.');
        } else {
            return redirect()->to(base_url('/admin/settings/social'))->with('error', 'Có lỗi xảy ra khi lưu cài đặt mạng xã hội.');
        }
    }

    public function seo()
    {
        // Get SEO settings
        $settings = [
            'site_name' => $this->settingsModel->getSetting('site_name'),
            'site_description' => $this->settingsModel->getSetting('site_description'),
            'site_keywords' => $this->settingsModel->getSetting('site_keywords'),
            'analytics_code' => $this->settingsModel->getSetting('analytics_code'),
            'recaptcha_site_key' => $this->settingsModel->getSetting('recaptcha_site_key'),
            'recaptcha_secret_key' => $this->settingsModel->getSetting('recaptcha_secret_key')
        ];

        return view('admin/settings/seo.html', [
            'settings' => $settings,
            'current_page' => 'settings'
        ]);
    }

    public function saveSeo()
    {
        // Process form submission
        $settings = [
            'site_name' => $this->request->getPost('site_name'),
            'site_description' => $this->request->getPost('site_description'),
            'site_keywords' => $this->request->getPost('site_keywords'),
            'analytics_code' => $this->request->getPost('analytics_code'),
            'recaptcha_site_key' => $this->request->getPost('recaptcha_site_key'),
            'recaptcha_secret_key' => $this->request->getPost('recaptcha_secret_key')
        ];

        // Save settings
        $result = $this->settingsModel->saveSettings($settings);

        if ($result) {
            // Log the action
            $this->logModel->info('SEO settings updated', [
                'user_id' => $this->session->get('user_id'),
                'username' => $this->session->get('username')
            ]);

            return redirect()->to(base_url('/admin/settings/seo'))->with('success', 'Cài đặt SEO đã được lưu thành công.');
        } else {
            return redirect()->to(base_url('/admin/settings/seo'))->with('error', 'Có lỗi xảy ra khi lưu cài đặt SEO.');
        }
    }
}
