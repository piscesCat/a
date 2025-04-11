<?php
namespace App\Controllers;

use App\Models\CommentModel;
use App\Models\LogModel;
use CodeIgniter\HTTP\ResponseInterface;

class Comment extends BaseController
{
    protected $commentModel;
    protected $logModel;

    public function __construct()
    {
        $this->commentModel = new CommentModel();
        $this->logModel = new LogModel();
    }

    /**
     * Thêm bình luận cho truyện
     */
    public function addStoryComment()
    {
        // Kiểm tra trạng thái bình luận
        if (!$this->isCommentsEnabled()) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Chức năng bình luận hiện đang tắt.'
            ]);
        }

        // Xác thực dữ liệu
        $rules = [
            'story_id' => 'required|numeric',
            'content' => 'required|min_length[3]'
        ];

        // Nếu là khách và cho phép khách bình luận
        if (!session()->get('isLoggedIn') && $this->isGuestCommentsEnabled()) {
            $rules['guest_name'] = 'required|min_length[2]|max_length[50]';
            $rules['guest_email'] = 'permit_empty|valid_email|max_length[100]';
        }

        if (!$this->validate($rules)) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => implode('<br>', $this->validator->getErrors())
            ]);
        }

        // Chuẩn bị dữ liệu
        $data = [
            'story_id' => $this->request->getPost('story_id'),
            'content' => $this->request->getPost('content'),
            'user_id' => session()->get('user')['id'] ?? null
        ];

        // Nếu là khách
        if (!session()->get('isLoggedIn')) {
            $data['guest_name'] = $this->request->getPost('guest_name');
            $data['guest_email'] = $this->request->getPost('guest_email');
        }

        // Thêm bình luận
        $commentId = $this->commentModel->addComment($data);

        if ($commentId) {
            // Ghi log
            $this->logActivity('added_story_comment', [
                'story_id' => $data['story_id'],
                'comment_id' => $commentId
            ]);

            return $this->response->setJSON([
                'status' => 'success',
                'message' => 'Bình luận của bạn đã được gửi thành công.',
                'redirect' => site_url('truyen/'. $this->request->getPost('story_slug') .'?comments_page=1#comments')
            ]);
        }

        return $this->response->setJSON([
            'status' => 'error',
            'message' => 'Đã xảy ra lỗi khi gửi bình luận. Vui lòng thử lại sau.'
        ]);
    }

    /**
     * Thêm bình luận cho chương
     */
    public function addChapterComment()
    {
        // Kiểm tra trạng thái bình luận
        if (!$this->isCommentsEnabled()) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Chức năng bình luận hiện đang tắt.'
            ]);
        }

        // Xác thực dữ liệu
        $rules = [
            'story_id' => 'required|numeric',
            'chapter_id' => 'required|numeric',
            'content' => 'required|min_length[3]'
        ];

        // Nếu là khách và cho phép khách bình luận
        if (!session()->get('isLoggedIn') && $this->isGuestCommentsEnabled()) {
            $rules['guest_name'] = 'required|min_length[2]|max_length[50]';
            $rules['guest_email'] = 'permit_empty|valid_email|max_length[100]';
        }

        if (!$this->validate($rules)) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => implode('<br>', $this->validator->getErrors())
            ]);
        }

        // Chuẩn bị dữ liệu
        $data = [
            'story_id' => $this->request->getPost('story_id'),
            'chapter_id' => $this->request->getPost('chapter_id'),
            'content' => $this->request->getPost('content'),
            'user_id' => session()->get('user')['id'] ?? null
        ];

        // Nếu là khách
        if (!session()->get('isLoggedIn')) {
            $data['guest_name'] = $this->request->getPost('guest_name');
            $data['guest_email'] = $this->request->getPost('guest_email');
        }

        // Thêm bình luận
        $commentId = $this->commentModel->addComment($data);

        if ($commentId) {
            // Ghi log
            $this->logActivity('added_chapter_comment', [
                'story_id' => $data['story_id'],
                'chapter_id' => $data['chapter_id'],
                'comment_id' => $commentId
            ]);

            // Chuyển hướng về trang có bình luận mới
            $chapterModel = new \App\Models\ChapterModel();
            $chapter = $chapterModel->find($data['chapter_id']);

            return $this->response->setJSON([
                'status' => 'success',
                'message' => 'Bình luận của bạn đã được gửi thành công.',
                'redirect' => site_url('truyen/'. $this->request->getPost('story_slug') .'/chuong-'. $chapter['chapter_number'] .'?comments_page=1#comments')
            ]);
        }

        return $this->response->setJSON([
            'status' => 'error',
            'message' => 'Đã xảy ra lỗi khi gửi bình luận. Vui lòng thử lại sau.'
        ]);
    }

    /**
     * Xóa bình luận
     */
    public function deleteComment()
    {
        // Đảm bảo người dùng đã đăng nhập
        if (!session()->get('isLoggedIn')) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Bạn cần đăng nhập để thực hiện thao tác này.'
            ]);
        }

        $commentId = $this->request->getPost('comment_id');
        if (!$commentId) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Không tìm thấy bình luận để xóa.'
            ]);
        }

        $userId = session()->get('user')['id'];
        $isAdmin = session()->get('user')['role'] === 'admin';

        if ($this->commentModel->deleteComment($commentId, $userId, $isAdmin)) {
            // Ghi log
            $this->logActivity('deleted_comment', [
                'comment_id' => $commentId
            ]);

            return $this->response->setJSON([
                'status' => 'success',
                'message' => 'Bình luận đã được xóa thành công.'
            ]);
        }

        return $this->response->setJSON([
            'status' => 'error',
            'message' => 'Bạn không có quyền xóa bình luận này.'
        ]);
    }

    /**
     * Tải thêm bình luận
     */
    public function loadMoreComments()
    {
        $storyId = $this->request->getGet('story_id');
        $chapterId = $this->request->getGet('chapter_id');
        $offset = (int)$this->request->getGet('offset') ?: 0;
        $limit = 10;

        if (!$storyId) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Thiếu thông tin để tải bình luận.'
            ]);
        }

        // Lấy bình luận dựa vào truyện hoặc chương
        if ($chapterId) {
            $comments = $this->commentModel->getChapterComments($chapterId, $limit, $offset);
            $total = $this->commentModel->countChapterComments($chapterId);
        } else {
            $comments = $this->commentModel->getStoryComments($storyId, $limit, $offset);
            $total = $this->commentModel->countStoryComments($storyId);
        }

        // Lấy các trả lời cho mỗi bình luận
        foreach ($comments as &$comment) {
            $comment['replies'] = $this->commentModel->getCommentReplies($comment['id']);
        }

        // Render HTML cho các bình luận
        $html = '';
        foreach ($comments as $comment) {
            $html .= view('comments/comment_item', ['comment' => $comment]);
        }

        return $this->response->setJSON([
            'status' => 'success',
            'html' => $html,
            'hasMore' => ($offset + $limit) < $total
        ]);
    }

    /**
     * Kiểm tra xem có cho phép bình luận không
     */
    private function isCommentsEnabled()
    {
        $settingsModel = new \App\Models\SettingsModel();
        return $settingsModel->getSetting('comments_enabled') === 'on';
    }

    /**
     * Kiểm tra xem có cho phép khách bình luận không
     */
    private function isGuestCommentsEnabled()
    {
        $settingsModel = new \App\Models\SettingsModel();
        return $settingsModel->getSetting('guest_comments_enabled') === 'on';
    }

    /**
     * Ghi log hoạt động
     */
    private function logActivity($action, $details)
    {
        $context = array_merge($details, [
            'user_id' => session()->get('user')['id'] ?? null,
            'guest_name' => $this->request->getPost('guest_name') ?? null,
            'guest_email' => $this->request->getPost('guest_email') ?? null,
            'ip_address' => $this->request->getIPAddress()
        ]);

        $this->logModel->insert([
            'level' => 'info',
            'message' => $action,
            'context' => json_encode($context)
        ]);
    }
}
