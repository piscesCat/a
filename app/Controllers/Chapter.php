<?php
namespace App\Controllers;

use App\Models\NovelModel;
use App\Models\ChapterModel;
use App\Models\CommentModel;

class Chapter extends BaseController
{
    protected $novelModel;
    protected $chapterModel;
    protected $commentModel;

    public function __construct()
    {
        $this->novelModel = new NovelModel();
        $this->chapterModel = new ChapterModel();
        $this->commentModel = new CommentModel();
    }

    /**
     * View a specific chapter
     */
    public function view($novelSlug, $chapterNumber)
    {
        // Extract chapter number from URL format (chuong-X)
        if (preg_match('/chuong-(\d+)/', $chapterNumber, $matches)) {
            $chapterNumber = $matches[1];
        } else {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        // Get novel details
        $novel = $this->novelModel->getBySlug($novelSlug);

        if (!$novel) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        // Get chapter details
        $chapter = $this->chapterModel->getChapter($novel->id, $chapterNumber);

        if (!$chapter) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        // Increment chapter views
        $this->chapterModel->incrementViews($chapter['id']);

        // Get next and previous chapters
        $nextChapter = $this->chapterModel->getNextChapter($novel->id, $chapterNumber);
        $prevChapter = $this->chapterModel->getPreviousChapter($novel->id, $chapterNumber);

        // Get chapter comments (paginated)
        $commentsPage = $this->request->getGet('comments_page') ?? 1;
        $commentsPerPage = 10;
        $commentsOffset = ($commentsPage - 1) * $commentsPerPage;

        // Lấy bình luận cho chương
        $comments = $this->commentModel->getChapterComments($chapter['id'], $commentsPerPage, $commentsOffset);
        $totalComments = $this->commentModel->countChapterComments($chapter['id']);
        $commentsTotalPages = ceil($totalComments / $commentsPerPage);

        // Lấy replies cho mỗi bình luận
        foreach ($comments as &$comment) {
            $comment['replies'] = $this->commentModel->getCommentReplies($comment['id']);
        }

        // Save reading progress if user is logged in
        if (session()->get('isLoggedIn')) {
            $this->saveReadingProgress($novel->id, $chapter['id'], $chapterNumber);
        }

        return $this->renderView('chapter/view.html', [
            'novel' => $novel,
            'chapter' => $chapter,
            'next_chapter' => $nextChapter,
            'prev_chapter' => $prevChapter,
            'comments' => $comments,
            'comments_page' => (int)$commentsPage,
            'comments_total_pages' => $commentsTotalPages,
            'current_url' => current_url()
        ]);
    }

    /**
     * List all chapters for a novel
     */
    public function list($novelSlug)
    {
        // Get novel details
        $novel = $this->novelModel->getBySlug($novelSlug);

        if (!$novel) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        // Get all chapters
        $chapters = $this->chapterModel->getChaptersByNovel($novel->id, 1000, 0); // Get all chapters

        return $this->renderView('chapter/list.html', [
            'novel' => $novel,
            'chapters' => $chapters
        ]);
    }

    /**
     * Save the user's reading progress
     */
    protected function saveReadingProgress($novelId, $chapterId, $chapterNumber)
    {
        if (!session()->get('isLoggedIn')) {
            return false;
        }

        $userId = session()->get('user')['id'];

        $data = [
            'user_id' => $userId,
            'novel_id' => $novelId,
            'chapter_id' => $chapterId,
            'last_read' => date('Y-m-d H:i:s')
        ];

        $db = \Config\Database::connect();

        // Check if a record already exists
        $existing = $db->table('reading_progress')
            ->where('user_id', $userId)
            ->where('novel_id', $novelId)
            ->get()
            ->getRow();

        if ($existing) {
            // Update existing record
            return $db->table('reading_progress')
                ->where('user_id', $userId)
                ->where('novel_id', $novelId)
                ->update($data);
        } else {
            // Insert new record
            return $db->table('reading_progress')
                ->insert($data);
        }
    }

    /**
     * Report an error in a chapter
     */
    public function reportError()
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(403);
        }

        $rules = [
            'novel_id' => 'required|numeric',
            'chapter_id' => 'required|numeric',
            'error_type' => 'required|in_list[typo,missing,wrong_order,other]',
            'error_detail' => 'required|min_length[10]'
        ];

        if (!$this->validate($rules)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
        }

        $data = [
            'novel_id' => $this->request->getPost('novel_id'),
            'chapter_id' => $this->request->getPost('chapter_id'),
            'error_type' => $this->request->getPost('error_type'),
            'error_detail' => $this->request->getPost('error_detail'),
            'user_id' => session()->get('isLoggedIn') ? session()->get('user')['id'] : null,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $db = \Config\Database::connect();
        $result = $db->table('reported_errors')->insert($data);

        return $this->response->setJSON(['success' => (bool)$result]);
    }

    /**
     * Bookmark a chapter
     */
    public function bookmarkChapter()
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(403);
        }

        if (!session()->get('isLoggedIn')) {
            return $this->response->setJSON(['success' => false, 'message' => 'Bạn cần đăng nhập để đánh dấu chương.']);
        }

        $rules = [
            'novel_id' => 'required|numeric',
            'chapter_id' => 'required|numeric'
        ];

        if (!$this->validate($rules)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
        }

        $data = $this->request->getJSON(true);
        $novelId = $data['novel_id'];
        $chapterId = $data['chapter_id'];
        $userId = session()->get('user')['id'];

        // Save reading progress and bookmark the novel
        $this->saveReadingProgress($novelId, $chapterId, null);
        $isBookmarked = $this->novelModel->bookmarkNovel($novelId, $userId);

        return $this->response->setJSON(['success' => true, 'bookmarked' => $isBookmarked]);
    }
}
