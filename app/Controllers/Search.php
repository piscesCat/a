<?php
namespace App\Controllers;

use App\Models\NovelModel;
use App\Models\CategoryModel;

class Search extends BaseController
{
    protected $novelModel;
    protected $categoryModel;

    public function __construct()
    {
        $this->novelModel = new NovelModel();
        $this->categoryModel = new CategoryModel();
    }

    /**
     * Display search results
     */
    public function index()
    {
        $query = $this->request->getGet('q') ?? '';

        if (empty($query)) {
            return redirect()->to(base_url());
        }

        $page = $this->request->getGet('page') ?? 1;
        $limit = 24;
        $offset = ($page - 1) * $limit;
        $sort = $this->request->getGet('sort') ?? 'latest';
        $status = $this->request->getGet('status') ?? '';
        $category = $this->request->getGet('category') ?? '';

        // Get search results
        $novels = $this->novelModel->searchNovels($query, $limit, $offset, $sort, $status, $category);

        // Get total results count for pagination
        $totalResults = $this->novelModel->searchNovelsCount($query, $status, $category);
        $totalPages = ceil($totalResults / $limit);

        // Get all categories for filter
        $categories = $this->categoryModel->getCategoriesWithCount();

        // Get trending search terms
        $trendingSearchTerms = $this->getTrendingSearchTerms();

        // Save search query to database for trending terms
        $this->saveSearchQuery($query);

        return $this->renderView('search/results.html', [
            'query' => $query,
            'novels' => $novels,
            'current_page' => (int)$page,
            'total_pages' => $totalPages,
            'total_results' => $totalResults,
            'sort' => $sort,
            'status' => $status,
            'selected_category' => $category,
            'categories' => $categories,
            'trending_search_terms' => $trendingSearchTerms
        ]);
    }

    /**
     * Handle AJAX search suggestions
     */
    public function suggestions()
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(403);
        }

        $query = $this->request->getGet('q') ?? '';

        if (empty($query) || strlen($query) < 2) {
            return $this->response->setJSON(['suggestions' => []]);
        }

        // Get search suggestions
        $suggestions = $this->novelModel->getSearchSuggestions($query, 5);

        return $this->response->setJSON(['suggestions' => $suggestions]);
    }

    /**
     * Save search query to database
     */
    protected function saveSearchQuery($query)
    {
        // Ignore short queries or special characters only
        if (strlen($query) < 2 || !preg_match('/[a-zA-Z0-9\p{L}]/u', $query)) {
            return false;
        }

        $db = \Config\Database::connect();

        // Check if query already exists
        $existing = $db->table('search_queries')
            ->where('query', $query)
            ->get()
            ->getRow();

        if ($existing) {
            // Update count and timestamp
            return $db->table('search_queries')
                ->where('query', $query)
                ->update([
                    'count' => $existing->count + 1,
                    'last_searched' => date('Y-m-d H:i:s')
                ]);
        } else {
            // Insert new query
            return $db->table('search_queries')
                ->insert([
                    'query' => $query,
                    'count' => 1,
                    'last_searched' => date('Y-m-d H:i:s'),
                    'created_at' => date('Y-m-d H:i:s')
                ]);
        }
    }

    /**
     * Get trending search terms
     */
    protected function getTrendingSearchTerms($limit = 10)
    {
        $db = \Config\Database::connect();

        // Get most searched terms in last 7 days
        $result = $db->table('search_queries')
            ->select('query')
            ->where('last_searched >=', date('Y-m-d H:i:s', strtotime('-7 days')))
            ->orderBy('count', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();

        return array_column($result, 'query');
    }
}
