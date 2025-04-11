<?php
namespace App\Controllers;

use CodeIgniter\Controller;
use App\Models\UserModel;

class Auth extends BaseController
{
    protected $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    // Phương thức hiển thị trang đăng nhập
    public function login()
    {
        if (session()->get('isLoggedIn')) {
            return redirect()->to('/');
        }
        return $this->render('auth/login.html');
    }

    // Phương thức xử lý đăng nhập
    public function attemptLogin()
    {
        $email = $this->request->getPost('email');
        $password = $this->request->getPost('password');

        $user = $this->userModel->attemptLogin($email, $password);

        if (!$user) {
            return redirect()->back()
                ->with('error', 'Email hoặc mật khẩu không chính xác');
        }

        session()->set([
            'isLoggedIn' => true,
            'user' => $user
        ]);

        return redirect()->to('/')->with('success', 'Đăng nhập thành công');
    }

    // Phương thức đăng xuất
    public function logout()
    {
        session()->destroy();
        return redirect()->to('/');
    }

    // Phương thức hiển thị trang quên mật khẩu
    public function forgotPassword()
    {
        return $this->render('auth/forgot_password.html');
    }

    // Phương thức xử lý đặt lại mật khẩu
    public function resetPassword()
    {
        // Phần triển khai cho việc đặt lại mật khẩu
    }
}
