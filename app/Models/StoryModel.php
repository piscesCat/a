<?php
namespace App\Models;

use CodeIgniter\Model;

class StoryModel extends Model
{
    protected $table = 'stories';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'title', 'slug', 'description', 'cover_image', 'author_id',
        'status', 'views', 'rating', 'created_at', 'updated_at',
        'type', 'country', 'year', 'is_featured', 'is_hot'
    ];

    protected $returnType = 'object';

    /**
     * Get a story by its slug
     */
    public function getBySlug($slug)
    {
        return $this->where('slug', $slug)->first();
    }

    /**
     * Get featured stories
     */
    public function getFeaturedStories($limit = 6)
    {
        return $this->select('stories.*, COUNT(chapters.id) as latest_chapter, users.username as author_name')
                    ->join('chapters', 'chapters.story_id = stories.id', 'left')
                    ->join('users', 'users.id = stories.author_id', 'left')
                    ->where('stories.status', 'published')
                    ->where('stories.is_featured', 1)
                    ->groupBy('stories.id')
                    ->orderBy('stories.created_at', 'DESC')
                    ->limit($limit)
                    ->find();
    }

    /**
     * Get latest stories
     */
    public function getLatestStories($limit = 12)
    {
        return $this->select('stories.*, COUNT(chapters.id) as latest_chapter, users.username as author_name')
                    ->join('chapters', 'chapters.story_id = stories.id', 'left')
                    ->join('users', 'users.id = stories.author_id', 'left')
                    ->where('stories.status', 'published')
                    ->groupBy('stories.id')
                    ->orderBy('stories.created_at', 'DESC')
                    ->limit($limit)
                    ->find();
    }

    /**
     * Get popular stories
     */
    public function getPopularStories($limit = 5)
    {
        return $this->select('stories.*, COUNT(chapters.id) as latest_chapter, users.username as author_name')
                    ->join('chapters', 'chapters.story_id = stories.id', 'left')
                    ->join('users', 'users.id = stories.author_id', 'left')
                    ->where('stories.status', 'published')
                    ->groupBy('stories.id')
                    ->orderBy('stories.views', 'DESC')
                    ->limit($limit)
                    ->find();
    }

    /**
     * Get recently updated stories
     */
    public function getRecentlyUpdatedStories($limit = 6)
    {
        return $this->select('stories.*, COUNT(chapters.id) as latest_chapter, users.username as author_name')
                    ->join('chapters', 'chapters.story_id = stories.id', 'left')
                    ->join('users', 'users.id = stories.author_id', 'left')
                    ->where('stories.status', 'published')
                    ->groupBy('stories.id')
                    ->orderBy('stories.updated_at', 'DESC')
                    ->limit($limit)
                    ->find();
    }

    /**
     * Get stories by category
     */
    public function getByCategory($categoryId, $limit = 12, $offset = 0)
    {
        return $this->select('stories.*, COUNT(chapters.id) as latest_chapter, users.username as author_name')
                    ->join('story_categories', 'story_categories.story_id = stories.id', 'inner')
                    ->join('chapters', 'chapters.story_id = stories.id', 'left')
                    ->join('users', 'users.id = stories.author_id', 'left')
                    ->where('story_categories.category_id', $categoryId)
                    ->where('stories.status', 'published')
                    ->groupBy('stories.id')
                    ->orderBy('stories.created_at', 'DESC')
                    ->limit($limit, $offset)
                    ->find();
    }

    /**
     * Get stories by author
     */
    public function getByAuthor($authorId, $limit = 12, $offset = 0)
    {
        return $this->select('stories.*, COUNT(chapters.id) as latest_chapter, users.username as author_name')
                    ->join('chapters', 'chapters.story_id = stories.id', 'left')
                    ->join('users', 'users.id = stories.author_id', 'left')
                    ->where('stories.author_id', $authorId)
                    ->where('stories.status', 'published')
                    ->groupBy('stories.id')
                    ->orderBy('stories.created_at', 'DESC')
                    ->limit($limit, $offset)
                    ->find();
    }

    /**
     * Get stories by type (single/series)
     */
    public function getByType($type, $limit = 12, $offset = 0)
    {
        return $this->select('stories.*, COUNT(chapters.id) as latest_chapter, users.username as author_name')
                    ->join('chapters', 'chapters.story_id = stories.id', 'left')
                    ->join('users', 'users.id = stories.author_id', 'left')
                    ->where('stories.type', $type)
                    ->where('stories.status', 'published')
                    ->groupBy('stories.id')
                    ->orderBy('stories.created_at', 'DESC')
                    ->limit($limit, $offset)
                    ->find();
    }

    /**
     * Get stories by country
     */
    public function getByCountry($country, $limit = 12, $offset = 0)
    {
        return $this->select('stories.*, COUNT(chapters.id) as latest_chapter, users.username as author_name')
                    ->join('chapters', 'chapters.story_id = stories.id', 'left')
                    ->join('users', 'users.id = stories.author_id', 'left')
                    ->where('stories.country', $country)
                    ->where('stories.status', 'published')
                    ->groupBy('stories.id')
                    ->orderBy('stories.created_at', 'DESC')
                    ->limit($limit, $offset)
                    ->find();
    }

    /**
     * Get stories by year
     */
    public function getByYear($year, $limit = 12, $offset = 0)
    {
        return $this->select('stories.*, COUNT(chapters.id) as latest_chapter, users.username as author_name')
                    ->join('chapters', 'chapters.story_id = stories.id', 'left')
                    ->join('users', 'users.id = stories.author_id', 'left')
                    ->where('stories.year', $year)
                    ->where('stories.status', 'published')
                    ->groupBy('stories.id')
                    ->orderBy('stories.created_at', 'DESC')
                    ->limit($limit, $offset)
                    ->find();
    }

    /**
     * Get completed stories
     */
    public function getCompletedStories($limit = 12, $offset = 0)
    {
        return $this->select('stories.*, COUNT(chapters.id) as latest_chapter, users.username as author_name')
                    ->join('chapters', 'chapters.story_id = stories.id', 'left')
                    ->join('users', 'users.id = stories.author_id', 'left')
                    ->where('stories.status', 'completed')
                    ->groupBy('stories.id')
                    ->orderBy('stories.created_at', 'DESC')
                    ->limit($limit, $offset)
                    ->find();
    }

    /**
     * Search stories
     */
    public function searchStories($query, $limit = 12, $offset = 0)
    {
        return $this->select('stories.*, COUNT(chapters.id) as latest_chapter, users.username as author_name')
                    ->join('chapters', 'chapters.story_id = stories.id', 'left')
                    ->join('users', 'users.id = stories.author_id', 'left')
                    ->like('stories.title', $query)
                    ->orLike('stories.description', $query)
                    ->where('stories.status', 'published')
                    ->groupBy('stories.id')
                    ->orderBy('stories.created_at', 'DESC')
                    ->limit($limit, $offset)
                    ->find();
    }

    /**
     * Get search suggestions
     */
    public function getSearchSuggestions($query, $limit = 5)
    {
        return $this->select('stories.id, stories.title, stories.slug, stories.cover_image')
                    ->like('stories.title', $query)
                    ->where('stories.status', 'published')
                    ->orderBy('stories.views', 'DESC')
                    ->limit($limit)
                    ->find();
    }

    /**
     * Increment story views
     */
    public function incrementViews($storyId)
    {
        $this->set('views', 'views + 1', false)
            ->where('id', $storyId)
            ->update();
    }

    /**
     * Update story rating
     */
    public function updateRating($storyId)
    {
        $db = \Config\Database::connect();

        // Calculate average rating
        $query = $db->table('ratings')
                    ->selectAvg('rating')
                    ->where('story_id', $storyId)
                    ->get();

        $result = $query->getRow();

        if ($result && $result->rating) {
            $this->update($storyId, ['rating' => round($result->rating, 1)]);
        }
    }

    /**
     * Get all story categories
     */
    public function getCategories($storyId)
    {
        $db = \Config\Database::connect();

        $query = $db->table('categories')
                    ->select('categories.*')
                    ->join('story_categories', 'story_categories.category_id = categories.id')
                    ->where('story_categories.story_id', $storyId)
                    ->get();

        return $query->getResult();
    }

    /**
     * Set story categories
     */
    public function setCategories($storyId, $categoryIds)
    {
        $db = \Config\Database::connect();

        // Delete existing categories
        $db->table('story_categories')
           ->where('story_id', $storyId)
           ->delete();

        // Insert new categories
        $data = [];
        foreach ($categoryIds as $categoryId) {
            $data[] = [
                'story_id' => $storyId,
                'category_id' => $categoryId
            ];
        }

        if (!empty($data)) {
            $db->table('story_categories')->insertBatch($data);
        }
    }
}
