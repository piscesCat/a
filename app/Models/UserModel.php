<?php
namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $allowedFields = [
        'username', 'email', 'password', 'role', 'status',
        'avatar', 'bio', 'last_login'
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $beforeInsert = ['hashPassword'];
    protected $beforeUpdate = ['hashPassword'];

    protected $validationRules = [
        'username' => 'required|alpha_numeric_space|min_length[3]|max_length[50]|is_unique[users.username,id,{id}]',
        'email' => 'required|valid_email|is_unique[users.email,id,{id}]',
        'password' => 'required|min_length[6]',
        'role' => 'required|in_list[user,admin,moderator]',
        'status' => 'required|in_list[active,inactive,banned]'
    ];

    protected $validationMessages = [
        'username' => [
            'required' => 'Tên đăng nhập là bắt buộc',
            'is_unique' => 'Tên đăng nhập này đã được sử dụng'
        ],
        'email' => [
            'required' => 'Email là bắt buộc',
            'valid_email' => 'Email không hợp lệ',
            'is_unique' => 'Email này đã được sử dụng'
        ]
    ];

    protected function hashPassword(array $data)
    {
        if (!isset($data['data']['password'])) {
            return $data;
        }

        $data['data']['password'] = password_hash(
            $data['data']['password'],
            PASSWORD_BCRYPT
        );

        return $data;
    }

    public function attemptLogin($email, $password)
    {
        $user = $this->where('email', $email)
                     ->where('status', 'active')
                     ->first();

        if (!$user) {
            return false;
        }

        if (!password_verify($password, $user['password'])) {
            return false;
        }

        // Update last login
        $this->update($user['id'], ['last_login' => date('Y-m-d H:i:s')]);

        unset($user['password']);
        return $user;
    }

    public function getBookmarks($userId, $limit = 10, $offset = 0)
    {
        return $this->db->table('bookmarks')
                        ->select('stories.*, bookmarks.created_at as bookmarked_at')
                        ->join('stories', 'stories.id = bookmarks.story_id')
                        ->where('bookmarks.user_id', $userId)
                        ->orderBy('bookmarks.created_at', 'DESC')
                        ->limit($limit, $offset)
                        ->get()
                        ->getResultArray();
    }
}