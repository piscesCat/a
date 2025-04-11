<?php
namespace App\Models;

use CodeIgniter\Model;

class CommentModel extends Model
{
    protected $table = 'comments';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'user_id',
        'story_id',
        'chapter_id',
        'content',
        'guest_name',
        'guest_email',
        'parent_id',
        'is_admin_reply'
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    /**
     * Lấy các bình luận cho một truyện
     */
    public function getStoryComments($storyId, $limit = 10, $offset = 0)
    {
        $builder = $this->db->table('comments c')
            ->select('c.*, u.username, u.avatar')
            ->join('users u', 'c.user_id = u.id', 'left')
            ->where('c.story_id', $storyId)
            ->where('c.chapter_id IS NULL')
            ->where('c.parent_id IS NULL') // Chỉ lấy các bình luận gốc, không lấy các reply
            ->orderBy('c.created_at', 'DESC')
            ->limit($limit, $offset);

        return $builder->get()->getResultArray();
    }

    /**
     * Lấy các bình luận cho một chương
     */
    public function getChapterComments($chapterId, $limit = 10, $offset = 0)
    {
        $builder = $this->db->table('comments c')
            ->select('c.*, u.username, u.avatar')
            ->join('users u', 'c.user_id = u.id', 'left')
            ->where('c.chapter_id', $chapterId)
            ->where('c.parent_id IS NULL') // Chỉ lấy các bình luận gốc, không lấy các reply
            ->orderBy('c.created_at', 'DESC')
            ->limit($limit, $offset);

        return $builder->get()->getResultArray();
    }

    /**
     * Lấy các phản hồi cho một bình luận
     */
    public function getCommentReplies($commentId)
    {
        $builder = $this->db->table('comments c')
            ->select('c.*, u.username, u.avatar')
            ->join('users u', 'c.user_id = u.id', 'left')
            ->where('c.parent_id', $commentId)
            ->orderBy('c.created_at', 'ASC');

        return $builder->get()->getResultArray();
    }

    /**
     * Đếm số bình luận của một truyện
     */
    public function countStoryComments($storyId)
    {
        return $this->where('story_id', $storyId)
                    ->where('chapter_id IS NULL')
                    ->where('parent_id IS NULL') // Chỉ đếm các bình luận gốc
                    ->countAllResults();
    }

    /**
     * Đếm số bình luận của một chương
     */
    public function countChapterComments($chapterId)
    {
        return $this->where('chapter_id', $chapterId)
                    ->where('parent_id IS NULL') // Chỉ đếm các bình luận gốc
                    ->countAllResults();
    }

    /**
     * Lấy các bình luận mới nhất trên toàn hệ thống
     */
    public function getLatestComments($limit = 5)
    {
        $builder = $this->db->table('comments c')
            ->select('c.*, s.title as story_title, s.slug as story_slug, u.username, u.avatar, ch.title as chapter_title, ch.chapter_number')
            ->join('stories s', 'c.story_id = s.id')
            ->join('users u', 'c.user_id = u.id', 'left')
            ->join('chapters ch', 'c.chapter_id = ch.id', 'left')
            ->where('c.parent_id IS NULL') // Chỉ lấy các bình luận gốc
            ->orderBy('c.created_at', 'DESC')
            ->limit($limit);

        return $builder->get()->getResultArray();
    }

    /**
     * Thêm bình luận mới (hỗ trợ cả người dùng đăng nhập và khách)
     */
    public function addComment($data)
    {
        // Kiểm tra xem có người dùng đăng nhập hay không
        if (isset($data['user_id']) && $data['user_id']) {
            // Nếu có người dùng đăng nhập thì bỏ thông tin khách
            unset($data['guest_name']);
            unset($data['guest_email']);
        } else {
            // Nếu là khách, đảm bảo có tên khách
            if (empty($data['guest_name'])) {
                $data['guest_name'] = 'Khách';
            }

            // Đặt user_id thành NULL
            $data['user_id'] = null;
        }

        return $this->insert($data);
    }

    /**
     * Thêm phản hồi từ admin cho bình luận
     */
    public function addAdminReply($data)
    {
        // Đảm bảo đây là phản hồi từ admin
        $data['is_admin_reply'] = true;

        return $this->insert($data);
    }

    /**
     * Xóa bình luận
     */
    public function deleteComment($commentId, $userId = null, $isAdmin = false)
    {
        // Nếu là admin, có thể xóa bất kỳ bình luận nào
        if ($isAdmin) {
            return $this->delete($commentId);
        }

        // Nếu là người dùng thường, chỉ có thể xóa bình luận của mình
        return $this->where('id', $commentId)
                    ->where('user_id', $userId)
                    ->delete();
    }

    /**
     * Lấy tất cả bình luận cho admin
     */
    public function getAllComments($limit = 20, $offset = 0, $filter = '', $search = '')
    {
        $builder = $this->db->table('comments c')
            ->select('c.*, s.title as story_title, s.slug as story_slug, u.username, ch.title as chapter_title, ch.chapter_number')
            ->join('stories s', 'c.story_id = s.id')
            ->join('users u', 'c.user_id = u.id', 'left')
            ->join('chapters ch', 'c.chapter_id = ch.id', 'left');

        // Áp dụng bộ lọc
        switch ($filter) {
            case 'user':
                $builder->whereNotNull('c.user_id');
                break;
            case 'guest':
                $builder->whereNull('c.user_id');
                break;
            case 'story':
                $builder->whereNull('c.chapter_id');
                break;
            case 'chapter':
                $builder->whereNotNull('c.chapter_id');
                break;
            case 'replies':
                $builder->whereNotNull('c.parent_id');
                break;
            case 'admin_replies':
                $builder->where('c.is_admin_reply', true);
                break;
            case 'no_replies':
                $builder->whereNull('c.parent_id');
                break;
        }

        // Áp dụng tìm kiếm
        if (!empty($search)) {
            $builder->groupStart()
                    ->like('c.content', $search)
                    ->orLike('s.title', $search)
                    ->orLike('u.username', $search)
                    ->orLike('c.guest_name', $search)
                    ->groupEnd();
        }

        return $builder->orderBy('c.created_at', 'DESC')
                       ->limit($limit, $offset)
                       ->get()
                       ->getResultArray();
    }
}
