<?php
namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\UserModel;

class UserManager extends BaseController
{
    protected $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();

        // Check if user is admin
        if (!$this->isAdmin()) {
            return redirect()->to('/')->with('error', 'Unauthorized access');
        }
    }

    public function index()
    {
        $page = $this->request->getGet('page') ?? 1;
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $users = $this->userModel->findAll($limit, $offset);
        $total = $this->userModel->countAllResults();

        return $this->render('admin/user/index.twig', [
            'users' => $users,
            'pager' => [
                'current' => $page,
                'total' => ceil($total / $limit)
            ]
        ]);
    }

    public function create()
    {
        return $this->render('admin/user/create.twig');
    }

    public function store()
    {
        $rules = [
            'username' => 'required|min_length[3]|max_length[50]|is_unique[users.username]',
            'email' => 'required|valid_email|is_unique[users.email]',
            'password' => 'required|min_length[6]',
            'role' => 'required|in_list[user,admin,moderator]'
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $this->userModel->insert([
            'username' => $this->request->getPost('username'),
            'email' => $this->request->getPost('email'),
            'password' => $this->request->getPost('password'),
            'role' => $this->request->getPost('role'),
            'status' => 'active'
        ]);

        return redirect()->to('/admin/users')
            ->with('success', 'Người dùng đã được tạo thành công');
    }

    public function edit($id)
    {
        $user = $this->userModel->find($id);
        if (!$user) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $this->render('admin/user/edit.twig', [
            'user' => $user
        ]);
    }

    public function update($id)
    {
        $rules = [
            'username' => "required|min_length[3]|max_length[50]|is_unique[users.username,id,$id]",
            'email' => "required|valid_email|is_unique[users.email,id,$id]",
            'role' => 'required|in_list[user,admin,moderator]',
            'status' => 'required|in_list[active,inactive,banned]'
        ];

        if ($this->request->getPost('password')) {
            $rules['password'] = 'min_length[6]';
        }

        if (!$this->validate($rules)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $data = [
            'username' => $this->request->getPost('username'),
            'email' => $this->request->getPost('email'),
            'role' => $this->request->getPost('role'),
            'status' => $this->request->getPost('status')
        ];

        if ($this->request->getPost('password')) {
            $data['password'] = $this->request->getPost('password');
        }

        $this->userModel->update($id, $data);

        return redirect()->to('/admin/users')
            ->with('success', 'Thông tin người dùng đã được cập nhật');
    }

    public function delete($id)
    {
        // Prevent deleting self
        if ($id == session()->get('user')['id']) {
            return redirect()->back()
                ->with('error', 'Không thể xóa tài khoản của chính mình');
        }

        $this->userModel->delete($id);
        return redirect()->to('/admin/users')
            ->with('success', 'Người dùng đã được xóa thành công');
    }

    protected function isAdmin()
    {
        return session()->get('user')['role'] === 'admin';
    }

    public function activities($userId)
    {
        $db = \Config\Database::connect();
        $activities = $db->table('activities')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->get()
            ->getResultArray();

        return $this->render('admin/user/activities.twig', [
            'activities' => $activities,
            'user' => $this->userModel->find($userId)
        ]);
    }
}