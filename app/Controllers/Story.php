<?php
namespace App\Controllers;

use App\Models\StoryModel;
use App\Models\CategoryModel;

class Story extends BaseController
{
    protected $storyModel;
    protected $categoryModel;

    public function __construct()
    {
        $this->storyModel = new StoryModel();
        $this->categoryModel = new CategoryModel();
    }

    public function index()
    {
        $page = $this->request->getGet('page') ?? 1;
        $limit = 24;
        $offset = ($page - 1) * $limit;

        $stories = $this->storyModel->select('stories.*, users.username as author_name')
            ->join('users', 'users.id = stories.author_id')
            ->where('stories.status', 'published')
            ->orderBy('stories.created_at', 'DESC')
            ->findAll($limit, $offset);

        $total = $this->storyModel->where('status', 'published')->countAllResults();

        return $this->render('story/index.twig', [
            'stories' => $stories,
            'pager' => [
                'current' => $page,
                'total' => ceil($total / $limit)
            ],
            'categories' => $this->categoryModel->findAll()
        ]);
    }

    public function view($slug)
    {
        $story = $this->storyModel->select('stories.*, users.username as author_name')
            ->join('users', 'users.id = stories.author_id')
            ->where('stories.slug', $slug)
            ->where('stories.status', 'published')
            ->first();

        if (!$story) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        // Increment views
        $this->storyModel->incrementViews($story['id']);

        // Get chapters
        $chapters = model('ChapterModel')->getChaptersByStory($story['id'], 50, 0);
        
        // Get categories
        $categories = $this->categoryModel->getStoriesCategories($story['id']);

        return $this->render('story/view.twig', [
            'story' => $story,
            'chapters' => $chapters,
            'categories' => $categories
        ]);
    }

    public function search()
    {
        $query = $this->request->getGet('q');
        $page = $this->request->getGet('page') ?? 1;
        $limit = 24;
        $offset = ($page - 1) * $limit;

        if (!$query) {
            return redirect()->to('/stories');
        }

        $stories = $this->storyModel->select('stories.*, users.username as author_name')
            ->join('users', 'users.id = stories.author_id')
            ->like('stories.title', $query)
            ->orLike('stories.description', $query)
            ->where('stories.status', 'published')
            ->findAll($limit, $offset);

        return $this->render('story/search.twig', [
            'stories' => $stories,
            'query' => $query
        ]);
    }

    public function category($slug)
    {
        $category = $this->categoryModel->where('slug', $slug)->first();
        
        if (!$category) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $page = $this->request->getGet('page') ?? 1;
        $limit = 24;
        $offset = ($page - 1) * $limit;

        $stories = $this->categoryModel->getStoriesByCategory($slug, $limit, $offset);

        return $this->render('story/category.twig', [
            'category' => $category,
            'stories' => $stories
        ]);
    }
}