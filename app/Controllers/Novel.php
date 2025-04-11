<?php
namespace App\Controllers;

use App\Models\NovelModel;
use App\Models\CategoryModel;
use App\Models\ChapterModel;
use App\Models\CommentModel;

class Novel extends BaseController
{
    protected $novelModel;
    protected $categoryModel;
    protected $chapterModel;
    protected $commentModel;

    public function __construct()
    {
        $this->novelModel = new NovelModel();
        $this->categoryModel = new CategoryModel();
        $this->chapterModel = new ChapterModel();
        $this->commentModel = new CommentModel();
    }

    /**
     * Display a listing of novels
     */
    public function index()
    {
        $page = $this->request->getGet('page') ?? 1;
        $limit = 24;
        $offset = ($page - 1) * $limit;
        $sort = $this->request->getGet('sort') ?? 'latest';
        $status = $this->request->getGet('status') ?? '';

        // Get all novels based on sort and filter
        $novels = $this->novelModel->getNovelList($limit, $offset, $sort, $status);

        // Get total novels count for pagination
        $totalNovels = $this->novelModel->getNovelListCount($status);
        $totalPages = ceil($totalNovels / $limit);

        // Get all categories
        $categories = $this->categoryModel->getCategoriesWithCount();

        return $this->renderView('novel/index.html', [
            'novels' => $novels,
            'current_page' => (int)$page,
            'total_pages' => $totalPages,
            'total_results' => $totalNovels,
            'sort' => $sort,
            'status' => $status,
            'categories' => $categories
        ]);
    }

    /**
     * Display a specific novel
     */
    public function view($slug)
    {
        // Get novel details
        $novel = $this->novelModel->getBySlug($slug);

        if (!$novel) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        // Increment views
        $this->novelModel->incrementViews($novel->id);

        // Get chapters (paginated)
        $page = $this->request->getGet('page') ?? 1;
        $chaptersPerPage = 50;
        $offset = ($page - 1) * $chaptersPerPage;

        $chapters = $this->chapterModel->getChaptersByNovel($novel->id, $chaptersPerPage, $offset);

        // Get total chapters count for pagination
        $totalChapters = $this->chapterModel->getChaptersCountByNovel($novel->id);
        $totalPages = ceil($totalChapters / $chaptersPerPage);

        // Get novel categories
        $categories = $this->categoryModel->getNovelCategories($novel->id);

        // Get latest chapter
        $latestChapter = $this->chapterModel->getLatestChapterByNovel($novel->id);

        // Get comments (paginated)
        $commentsPage = $this->request->getGet('comments_page') ?? 1;
        $commentsPerPage = 10;
        $commentsOffset = ($commentsPage - 1) * $commentsPerPage;

        $comments = $this->commentModel->getNovelComments($novel->id, null, $commentsPerPage, $commentsOffset);

        // Get total comments count for pagination
        $totalComments = $this->commentModel->getNovelCommentsCount($novel->id, null);
        $commentsTotalPages = ceil($totalComments / $commentsPerPage);

        // Lấy replies cho mỗi bình luận
        foreach ($comments as &$comment) {
            $comment['replies'] = $this->commentModel->getCommentReplies($comment['id']);
        }

        // Check if novel is bookmarked by current user
        $isBookmarked = false;
        $userRating = 0;
        $readingProgress = null;

        if (session()->get('isLoggedIn')) {
            $userId = session()->get('user')['id'];
            $isBookmarked = $this->novelModel->isBookmarked($novel->id, $userId);
            $userRating = $this->novelModel->getUserRating($novel->id, $userId);
            $readingProgress = $this->novelModel->getReadingProgress($novel->id, $userId);
        }

        // Get similar novels based on categories
        $similarNovels = $this->novelModel->getSimilarNovels($novel->id, $categories, 6);

        // Get ratings count and bookmarks count
        $ratingsCount = $this->novelModel->getRatingsCount($novel->id);
        $bookmarksCount = $this->novelModel->getBookmarksCount($novel->id);

        return $this->renderView('novel/view.html', [
            'novel' => $novel,
            'chapters' => $chapters,
            'categories' => $categories,
            'comments' => $comments,
            'latest_chapter' => $latestChapter,
            'is_bookmarked' => $isBookmarked,
            'user_rating' => $userRating,
            'reading_progress' => $readingProgress,
            'similar_novels' => $similarNovels,
            'ratings_count' => $ratingsCount,
            'bookmarks_count' => $bookmarksCount,
            'current_page' => (int)$page,
            'total_pages' => $totalPages,
            'comments_page' => (int)$commentsPage,
            'comments_total_pages' => $commentsTotalPages,
            'current_url' => current_url()
        ]);
    }

    /**
     * Show novels by category
     */
    public function category($slug)
    {
        $category = $this->categoryModel->where('slug', $slug)->first();

        if (!$category) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $page = $this->request->getGet('page') ?? 1;
        $limit = 24;
        $offset = ($page - 1) * $limit;
        $sort = $this->request->getGet('sort') ?? 'latest';
        $status = $this->request->getGet('status') ?? '';

        // Get novels by category
        $novels = $this->novelModel->getByCategory($category['id'], $limit, $offset, $sort, $status);

        // Get total novels count for pagination
        $totalNovels = $this->novelModel->getByCategoryCount($category['id'], $status);
        $totalPages = ceil($totalNovels / $limit);

        // Get related categories (that have novels in common with this category)
        $relatedCategories = $this->categoryModel->getRelatedCategories($category['id'], 8);

        return $this->renderView('category/view.html', [
            'category' => $category,
            'novels' => $novels,
            'current_page' => (int)$page,
            'total_pages' => $totalPages,
            'total_results' => $totalNovels,
            'sort' => $sort,
            'status' => $status,
            'related_categories' => $relatedCategories
        ]);
    }

    /**
     * Show novels by country
     */
    public function country($slug)
    {
        $countries = [
            'viet-nam' => 'Việt Nam',
            'trung-quoc' => 'Trung Quốc',
            'han-quoc' => 'Hàn Quốc',
            'nhat-ban' => 'Nhật Bản',
            'au-my' => 'Âu Mỹ'
        ];

        if (!isset($countries[$slug])) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $countryName = $countries[$slug];

        $page = $this->request->getGet('page') ?? 1;
        $limit = 24;
        $offset = ($page - 1) * $limit;
        $sort = $this->request->getGet('sort') ?? 'latest';
        $status = $this->request->getGet('status') ?? '';

        // Get novels by country
        $novels = $this->novelModel->getByCountry($slug, $limit, $offset, $sort, $status);

        // Get total novels count for pagination
        $totalNovels = $this->novelModel->getByCountryCount($slug, $status);
        $totalPages = ceil($totalNovels / $limit);

        // Get all categories
        $categories = $this->categoryModel->getCategoriesWithCount();

        return $this->renderView('novel/country.html', [
            'country_slug' => $slug,
            'country_name' => $countryName,
            'novels' => $novels,
            'current_page' => (int)$page,
            'total_pages' => $totalPages,
            'total_results' => $totalNovels,
            'sort' => $sort,
            'status' => $status,
            'categories' => $categories
        ]);
    }

    /**
     * Show completed novels
     */
    public function completed()
    {
        $page = $this->request->getGet('page') ?? 1;
        $limit = 24;
        $offset = ($page - 1) * $limit;
        $sort = $this->request->getGet('sort') ?? 'latest';

        // Get completed novels
        $novels = $this->novelModel->getCompleted($limit, $offset, $sort);

        // Get total novels count for pagination
        $totalNovels = $this->novelModel->getCompletedCount();
        $totalPages = ceil($totalNovels / $limit);

        // Get all categories
        $categories = $this->categoryModel->getCategoriesWithCount();

        return $this->renderView('novel/completed.html', [
            'novels' => $novels,
            'current_page' => (int)$page,
            'total_pages' => $totalPages,
            'total_results' => $totalNovels,
            'sort' => $sort,
            'categories' => $categories
        ]);
    }

    /**
     * Show hot novels
     */
    public function hot()
    {
        $page = $this->request->getGet('page') ?? 1;
        $limit = 24;
        $offset = ($page - 1) * $limit;
        $sort = $this->request->getGet('sort') ?? 'views';
        $status = $this->request->getGet('status') ?? '';

        // Get hot novels
        $novels = $this->novelModel->getHotNovels($limit, $offset, $sort, $status);

        // Get total novels count for pagination
        $totalNovels = $this->novelModel->getHotNovelsCount($status);
        $totalPages = ceil($totalNovels / $limit);

        // Get all categories
        $categories = $this->categoryModel->getCategoriesWithCount();

        return $this->renderView('novel/hot.html', [
            'novels' => $novels,
            'current_page' => (int)$page,
            'total_pages' => $totalPages,
            'total_results' => $totalNovels,
            'sort' => $sort,
            'status' => $status,
            'categories' => $categories
        ]);
    }

    /**
     * Show recently updated novels
     */
    public function recentlyUpdated()
    {
        $page = $this->request->getGet('page') ?? 1;
        $limit = 24;
        $offset = ($page - 1) * $limit;
        $status = $this->request->getGet('status') ?? '';

        // Get recently updated novels
        $novels = $this->novelModel->getRecentlyUpdated($limit, $offset, $status);

        // Get total novels count for pagination
        $totalNovels = $this->novelModel->getRecentlyUpdatedCount($status);
        $totalPages = ceil($totalNovels / $limit);

        // Get all categories
        $categories = $this->categoryModel->getCategoriesWithCount();

        return $this->renderView('novel/recently_updated.html', [
            'novels' => $novels,
            'current_page' => (int)$page,
            'total_pages' => $totalPages,
            'total_results' => $totalNovels,
            'status' => $status,
            'categories' => $categories
        ]);
    }

    /**
     * Show novels by author
     */
    public function author($username)
    {
        $userModel = new \App\Models\UserModel();
        $author = $userModel->where('username', $username)->first();

        if (!$author) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $page = $this->request->getGet('page') ?? 1;
        $limit = 24;
        $offset = ($page - 1) * $limit;
        $sort = $this->request->getGet('sort') ?? 'latest';
        $status = $this->request->getGet('status') ?? '';

        // Get novels by author
        $novels = $this->novelModel->getByAuthor($author['id'], $limit, $offset, $sort, $status);

        // Get total novels count for pagination
        $totalNovels = $this->novelModel->getByAuthorCount($author['id'], $status);
        $totalPages = ceil($totalNovels / $limit);

        return $this->renderView('novel/author.html', [
            'author' => $author,
            'novels' => $novels,
            'current_page' => (int)$page,
            'total_pages' => $totalPages,
            'total_results' => $totalNovels,
            'sort' => $sort,
            'status' => $status
        ]);
    }

    /**
     * Rate a novel
     */
    public function rate()
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(403);
        }

        if (!session()->get('isLoggedIn')) {
            return $this->response->setJSON(['success' => false, 'message' => 'Bạn cần đăng nhập để đánh giá.']);
        }

        $rules = [
            'novel_id' => 'required|numeric',
            'rating' => 'required|numeric|min_value[1]|max_value[5]',
        ];

        if (!$this->validate($rules)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
        }

        $novelId = $this->request->getPost('novel_id');
        $rating = $this->request->getPost('rating');
        $userId = session()->get('user')['id'];

        // Save the rating
        $result = $this->novelModel->rateNovel($novelId, $userId, $rating);

        return $this->response->setJSON(['success' => $result]);
    }

    /**
     * Toggle bookmark
     */
    public function toggleBookmark()
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(403);
        }

        if (!session()->get('isLoggedIn')) {
            return $this->response->setJSON(['success' => false, 'message' => 'Bạn cần đăng nhập để lưu truyện.']);
        }

        $rules = [
            'novel_id' => 'required|numeric',
        ];

        if (!$this->validate($rules)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
        }

        $novelId = $this->request->getJSON(true)['novel_id'];
        $userId = session()->get('user')['id'];

        // Toggle the bookmark
        $isBookmarked = $this->novelModel->toggleBookmark($novelId, $userId);

        return $this->response->setJSON(['success' => true, 'bookmarked' => $isBookmarked]);
    }
}
