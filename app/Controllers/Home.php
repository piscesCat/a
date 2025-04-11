<?php
namespace App\Controllers;

use App\Models\NovelModel;
use App\Models\CategoryModel;
use App\Models\ChapterModel;

class Home extends BaseController
{
    public function index()
    {
        // Get NovelModel instance
        $novelModel = new NovelModel();

        // Get featured novels
        $featuredNovels = $novelModel->getFeaturedNovels(6);

        // Get latest updated novels
        $latestUpdatedNovels = $novelModel->getRecentlyUpdated(12);

        // Get popular novels
        $popularNovels = $novelModel->getPopularNovels(5);

        // Get completed novels
        $completedNovels = $novelModel->getCompleted(6);

        // Get top authors
        $userModel = new \App\Models\UserModel();
        $db = \Config\Database::connect();
        $query = $db->table('users')
                    ->select('users.id, users.username, users.avatar, COUNT(novels.id) as novel_count')
                    ->join('novels', 'novels.author_id = users.id')
                    ->where('novels.status', 'published')
                    ->groupBy('users.id')
                    ->orderBy('novel_count', 'DESC')
                    ->limit(5)
                    ->get();
        $topAuthors = $query->getResultArray();

        // Get categories with novel count
        $categoryModel = new CategoryModel();
        $categories = $categoryModel->getCategoriesWithCount();

        // Get latest chapters
        $chapterModel = new ChapterModel();
        $latestChapters = $chapterModel->getLatestChapters(15);

        // Render the view with data
        return $this->renderView('home.html', [
            'featured_novels' => $featuredNovels,
            'latest_updated_novels' => $latestUpdatedNovels,
            'popular_novels' => $popularNovels,
            'completed_novels' => $completedNovels,
            'top_authors' => $topAuthors,
            'categories' => $categories,
            'latest_chapters' => $latestChapters
        ]);
    }
}
