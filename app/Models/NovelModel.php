<?php
namespace App\Models;

use CodeIgniter\Model;

class StoryModel extends Model
{
    protected $table = 'stories';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'title', 'slug', 'description', 'cover_image',
        'author_id', 'status', 'views', 'rating',
        'type', 'country', 'year', 'is_featured', 'is_hot', 'is_completed'
    ];
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    /**
     * Get featured stories
     */
    public function getFeaturedStories($limit = 6)
    {
        return $this->where('is_featured', true)
                    ->where('status', 'published')
                    ->orderBy('created_at', 'DESC')
                    ->limit($limit)
                    ->find();
    }

    /**
     * Get recently updated stories
     */
    public function getRecentlyUpdated($limit = 12)
    {
        return $this->where('status', 'published')
                    ->orderBy('updated_at', 'DESC')
                    ->limit($limit)
                    ->find();
    }

    /**
     * Get popular stories
     */
    public function getPopularStories($limit = 5)
    {
        return $this->where('status', 'published')
                    ->orderBy('views', 'DESC')
                    ->limit($limit)
                    ->find();
    }

    /**
     * Get completed stories
     */
    public function getCompleted($limit = 6)
    {
        return $this->where('is_completed', true)
                    ->where('status', 'published')
                    ->orderBy('updated_at', 'DESC')
                    ->limit($limit)
                    ->find();
    }

    /**
     * Get stories by category
     */
    public function getByCategory($categoryId, $limit = 12, $offset = 0)
    {
        $db = \Config\Database::connect();

        $query = $db->table('stories')
                    ->select('stories.*')
                    ->join('story_categories', 'story_categories.story_id = stories.id')
                    ->where('story_categories.category_id', $categoryId)
                    ->where('stories.status', 'published')
                    ->orderBy('stories.created_at', 'DESC')
                    ->limit($limit, $offset)
                    ->get();

        return $query->getResultArray();
    }

    /**
     * Get stories by country
     */
    public function getByCountry($country, $limit = 12, $offset = 0)
    {
        return $this->where('country', $country)
                    ->where('status', 'published')
                    ->orderBy('created_at', 'DESC')
                    ->limit($limit, $offset)
                    ->find();
    }

    /**
     * Get stories by type
     */
    public function getByType($type, $limit = 12, $offset = 0)
    {
        return $this->where('type', $type)
                    ->where('status', 'published')
                    ->orderBy('created_at', 'DESC')
                    ->limit($limit, $offset)
                    ->find();
    }

    /**
     * Get stories by year
     */
    public function getByYear($year, $limit = 12, $offset = 0)
    {
        return $this->where('year', $year)
                    ->where('status', 'published')
                    ->orderBy('created_at', 'DESC')
                    ->limit($limit, $offset)
                    ->find();
    }

    /**
     * Get stories by author
     */
    public function getByAuthor($authorId, $limit = 12, $offset = 0)
    {
        return $this->where('author_id', $authorId)
                    ->where('status', 'published')
                    ->orderBy('created_at', 'DESC')
                    ->limit($limit, $offset)
                    ->find();
    }

    /**
     * Search stories
     */
    public function search($keyword, $limit = 12, $offset = 0)
    {
        return $this->like('title', $keyword)
                    ->orLike('description', $keyword)
                    ->where('status', 'published')
                    ->orderBy('title', 'ASC')
                    ->limit($limit, $offset)
                    ->find();
    }

    /**
     * Increment views
     */
    public function incrementViews($id)
    {
        $this->where('id', $id)
             ->set('views', 'views + 1', false)
             ->update();
    }

    /**
     * Update rating
     */
    public function updateRating($id)
    {
        $db = \Config\Database::connect();

        // Get average rating
        $query = $db->table('ratings')
                    ->selectAvg('rating')
                    ->where('story_id', $id)
                    ->get();

        $result = $query->getRow();
        $avgRating = round($result->rating, 2) ?? 0;

        // Update story rating
        $this->where('id', $id)
             ->set('rating', $avgRating)
             ->update();
    }
}
