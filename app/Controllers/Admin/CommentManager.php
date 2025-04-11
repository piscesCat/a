<?php
namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\CommentModel;
use App\Models\LogModel;

class CommentManager extends BaseController
{
    protected $commentModel;
    protected $logModel;
    protected $perPage = 20;

    public function __construct()
    {
        $this->commentModel = new CommentModel();
        $this->logModel = new LogModel();
    }

    /**
     * Hiển thị danh sách bình luận cho trang quản trị
     */
    public function index()
    {
        $page = $this->request->getGet('page') ?? 1;
        $filter = $this->request->getGet('filter') ?? '';
        $search = $this->request->getGet('search') ?? '';

        // Tính toán offset dựa trên trang hiện tại
        $offset = ($page - 1) * $this->perPage;

        // Lấy dữ liệu bình luận theo bộ lọc
        $comments = $this->getFilteredComments($filter, $search, $this->perPage, $offset);

        // Lấy các phản hồi cho từng bình luận
        foreach ($comments as &$comment) {
            $comment['replies'] = $this->commentModel->getCommentReplies($comment['id']);
        }

        // Đếm tổng số bình luận cho phân trang
        $totalComments = $this->countFilteredComments($filter, $search);
        $totalPages = ceil($totalComments / $this->perPage);

        $data = [
            'title' => 'Quản lý bình luận',
            'comments' => $comments,
            'filter' => $filter,
            'search' => $search,
            'pager' => [
                'totalComments' => $totalComments,
                'totalPages' => $totalPages,
                'currentPage' => $page
            ]
        ];

        return $this->render('admin/comment/index.html', $data);
    }

    /**
     * Xử lý lọc bình luận
     */
    public function filter()
    {
        // Chuyển hướng về index với các tham số lọc
        return redirect()->to('/admin/comments?' . http_build_query([
            'filter' => $this->request->getGet('filter'),
            'search' => $this->request->getGet('search'),
            'page' => 1
        ]));
    }

    /**
     * Hiển thị form trả lời bình luận
     */
    public function reply($commentId)
    {
        $comment = $this->commentModel->find($commentId);
        if (!$comment) {
            return redirect()->to('/admin/comments')
                ->with('error', 'Bình luận không tồn tại');
        }

        // Lấy thông tin chi tiết bình luận
        $builder = $this->commentModel->builder();
        $commentDetail = $builder->select('comments.*, stories.title as story_title, stories.slug as story_slug, users.username, chapters.title as chapter_title, chapters.chapter_number')
            ->join('stories', 'comments.story_id = stories.id')
            ->join('users', 'comments.user_id = users.id', 'left')
            ->join('chapters', 'comments.chapter_id = chapters.id', 'left')
            ->where('comments.id', $commentId)
            ->get()
            ->getRowArray();

        $data = [
            'title' => 'Trả lời bình luận',
            'comment' => $commentDetail,
            'current_page' => 'comments'
        ];

        return $this->render('admin/comment/reply.html', $data);
    }

    /**
     * Xử lý gửi trả lời bình luận từ admin
     */
    public function sendReply($commentId)
    {
        $comment = $this->commentModel->find($commentId);
        if (!$comment) {
            return redirect()->to('/admin/comments')
                ->with('error', 'Bình luận không tồn tại');
        }

        // Xác thực dữ liệu
        $rules = [
            'content' => 'required|min_length[3]'
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()
                ->with('errors', $this->validator->getErrors())
                ->withInput();
        }

        // Chuẩn bị dữ liệu trả lời
        $replyData = [
            'parent_id' => $commentId,
            'story_id' => $comment['story_id'],
            'chapter_id' => $comment['chapter_id'],
            'content' => $this->request->getPost('content'),
            'user_id' => session()->get('user')['id'] ?? null,
            'is_admin_reply' => true
        ];

        // Thêm phản hồi
        $replyId = $this->commentModel->addAdminReply($replyData);

        if ($replyId) {
            // Ghi log
            $this->logModel->insert([
                'level' => 'info',
                'message' => 'Admin đã trả lời bình luận',
                'context' => json_encode([
                    'user_id' => session()->get('user')['id'] ?? null,
                    'comment_id' => $commentId,
                    'reply_id' => $replyId,
                    'story_id' => $comment['story_id'],
                    'chapter_id' => $comment['chapter_id'] ?? null,
                    'ip_address' => $this->request->getIPAddress()
                ])
            ]);

            return redirect()->to('/admin/comments')
                ->with('success', 'Đã trả lời bình luận thành công');
        }

        return redirect()->back()
            ->with('error', 'Có lỗi xảy ra khi trả lời bình luận')
            ->withInput();
    }

    /**
     * Xóa một bình luận
     */
    public function delete($commentId)
    {
        // Kiểm tra AJAX request
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(403)->setJSON([
                'status' => 'error',
                'message' => 'Yêu cầu không hợp lệ'
            ]);
        }

        // Lấy thông tin bình luận trước khi xóa để ghi log
        $comment = $this->commentModel->find($commentId);
        if (!$comment) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Bình luận không tồn tại'
            ]);
        }

        // Xóa bình luận với quyền admin
        if ($this->commentModel->deleteComment($commentId, null, true)) {
            // Ghi log
            $this->logModel->insert([
                'level' => 'warning',
                'message' => 'Bình luận đã bị xóa bởi admin',
                'context' => json_encode([
                    'user_id' => session()->get('user')['id'] ?? null,
                    'comment_id' => $commentId,
                    'story_id' => $comment['story_id'],
                    'chapter_id' => $comment['chapter_id'] ?? null,
                    'ip_address' => $this->request->getIPAddress()
                ])
            ]);

            return $this->response->setJSON([
                'status' => 'success',
                'message' => 'Bình luận đã được xóa thành công'
            ]);
        }

        return $this->response->setJSON([
            'status' => 'error',
            'message' => 'Không thể xóa bình luận'
        ]);
    }

    /**
     * Xóa nhiều bình luận cùng lúc
     */
    public function bulkDelete()
    {
        // Kiểm tra AJAX request
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(403)->setJSON([
                'status' => 'error',
                'message' => 'Yêu cầu không hợp lệ'
            ]);
        }

        $commentIds = $this->request->getPost('comment_ids');
        if (empty($commentIds) || !is_array($commentIds)) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Không có bình luận nào được chọn'
            ]);
        }

        // Lấy thông tin bình luận trước khi xóa để ghi log
        $comments = $this->commentModel->whereIn('id', $commentIds)->findAll();
        if (empty($comments)) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Không tìm thấy bình luận để xóa'
            ]);
        }

        // Xóa các bình luận
        $this->commentModel->whereIn('id', $commentIds)->delete();

        // Ghi log
        $this->logModel->insert([
            'level' => 'warning',
            'message' => 'Nhiều bình luận đã bị xóa cùng lúc bởi admin (' . count($commentIds) . ' bình luận)',
            'context' => json_encode([
                'user_id' => session()->get('user')['id'] ?? null,
                'comment_ids' => $commentIds,
                'ip_address' => $this->request->getIPAddress()
            ])
        ]);

        return $this->response->setJSON([
            'status' => 'success',
            'message' => count($commentIds) . ' bình luận đã được xóa thành công'
        ]);
    }

    /**
     * Lấy danh sách bình luận theo bộ lọc
     */
    private function getFilteredComments($filter, $search, $limit, $offset)
    {
        $builder = $this->commentModel->builder();

        $builder->select('comments.*, stories.title as story_title, stories.slug as story_slug, users.username, chapters.title as chapter_title, chapters.chapter_number')
                ->join('stories', 'comments.story_id = stories.id')
                ->join('users', 'comments.user_id = users.id', 'left')
                ->join('chapters', 'comments.chapter_id = chapters.id', 'left')
                ->orderBy('comments.created_at', 'DESC');

        // Áp dụng bộ lọc
        switch ($filter) {
            case 'user':
                $builder->whereNotNull('comments.user_id');
                break;
            case 'guest':
                $builder->whereNull('comments.user_id');
                break;
            case 'story':
                $builder->whereNull('comments.chapter_id');
                break;
            case 'chapter':
                $builder->whereNotNull('comments.chapter_id');
                break;
            case 'has_replies':
                // Lấy các bình luận đã có phản hồi (dùng subquery)
                $subquery = $this->db->table('comments')
                    ->select('parent_id')
                    ->whereNotNull('parent_id')
                    ->distinct();
                $builder->whereIn('comments.id', $subquery);
                break;
            case 'no_replies':
                // Lấy các bình luận chưa có phản hồi (dùng subquery)
                $subquery = $this->db->table('comments')
                    ->select('parent_id')
                    ->whereNotNull('parent_id')
                    ->distinct();
                $builder->whereNotIn('comments.id', $subquery);
                break;
        }

        // Chỉ lấy các bình luận gốc (không phải phản hồi)
        $builder->whereNull('comments.parent_id');

        // Áp dụng tìm kiếm
        if (!empty($search)) {
            $builder->groupStart()
                    ->like('comments.content', $search)
                    ->orLike('stories.title', $search)
                    ->orLike('users.username', $search)
                    ->orLike('comments.guest_name', $search)
                    ->groupEnd();
        }

        return $builder->limit($limit, $offset)->get()->getResultArray();
    }

    /**
     * Đếm tổng số bình luận theo bộ lọc
     */
    private function countFilteredComments($filter, $search)
    {
        $builder = $this->commentModel->builder();

        $builder->select('comments.id')
                ->join('stories', 'comments.story_id = stories.id')
                ->join('users', 'comments.user_id = users.id', 'left')
                ->join('chapters', 'comments.chapter_id = chapters.id', 'left');

        // Áp dụng bộ lọc
        switch ($filter) {
            case 'user':
                $builder->whereNotNull('comments.user_id');
                break;
            case 'guest':
                $builder->whereNull('comments.user_id');
                break;
            case 'story':
                $builder->whereNull('comments.chapter_id');
                break;
            case 'chapter':
                $builder->whereNotNull('comments.chapter_id');
                break;
            case 'has_replies':
                // Lấy các bình luận đã có phản hồi (dùng subquery)
                $subquery = $this->db->table('comments')
                    ->select('parent_id')
                    ->whereNotNull('parent_id')
                    ->distinct();
                $builder->whereIn('comments.id', $subquery);
                break;
            case 'no_replies':
                // Lấy các bình luận chưa có phản hồi (dùng subquery)
                $subquery = $this->db->table('comments')
                    ->select('parent_id')
                    ->whereNotNull('parent_id')
                    ->distinct();
                $builder->whereNotIn('comments.id', $subquery);
                break;
        }

        // Chỉ đếm các bình luận gốc (không phải phản hồi)
        $builder->whereNull('comments.parent_id');

        // Áp dụng tìm kiếm
        if (!empty($search)) {
            $builder->groupStart()
                    ->like('comments.content', $search)
                    ->orLike('stories.title', $search)
                    ->orLike('users.username', $search)
                    ->orLike('comments.guest_name', $search)
                    ->groupEnd();
        }

        return $builder->countAllResults();
    }
}
