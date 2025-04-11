<?php
namespace App\Models;

use CodeIgniter\Model;

class CategoryModel extends Model
{
    protected $table = 'categories';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['name', 'slug', 'description', 'parent_id'];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    /**
     * Get all categories with novel count
     */
    public function getCategoriesWithCount()
    {
        $db = \Config\Database::connect();
        $builder = $db->table('categories')
                      ->select('categories.*, COUNT(novel_categories.novel_id) as novel_count')
                      ->join('novel_categories', 'novel_categories.category_id = categories.id', 'left')
                      ->join('novels', 'novels.id = novel_categories.novel_id AND novels.status = "published"', 'left')
                      ->groupBy('categories.id')
                      ->orderBy('categories.name', 'ASC');

        return $builder->get()->getResultArray();
    }

    /**
     * Get categories for a specific novel
     */
    public function getNovelCategories($novelId)
    {
        $db = \Config\Database::connect();
        $builder = $db->table('categories')
                      ->select('categories.*')
                      ->join('novel_categories', 'novel_categories.category_id = categories.id')
                      ->where('novel_categories.novel_id', $novelId)
                      ->orderBy('categories.name', 'ASC');

        return $builder->get()->getResultArray();
    }

    /**
     * Get a category by slug
     */
    public function getCategoryBySlug($slug)
    {
        return $this->where('slug', $slug)->first();
    }

    /**
     * Get subcategories of a parent category
     */
    public function getSubcategories($parentId)
    {
        return $this->where('parent_id', $parentId)
                    ->orderBy('name', 'ASC')
                    ->findAll();
    }

    /**
     * Get related categories (categories that share novels with the given category)
     */
    public function getRelatedCategories($categoryId, $limit = 8)
    {
        $db = \Config\Database::connect();

        // Get novels in this category
        $novelSubquery = $db->table('novel_categories')
                           ->select('novel_id')
                           ->where('category_id', $categoryId);

        // Get other categories that have these novels
        $builder = $db->table('categories as c')
                     ->select('c.*, COUNT(nc.novel_id) as novel_count')
                     ->join('novel_categories as nc', 'nc.category_id = c.id')
                     ->whereIn('nc.novel_id', $novelSubquery)
                     ->where('c.id !=', $categoryId)
                     ->groupBy('c.id')
                     ->orderBy('novel_count', 'DESC')
                     ->limit($limit);

        return $builder->get()->getResultArray();
    }

    /**
     * Get popular categories (most used)
     */
    public function getPopularCategories($limit = 10)
    {
        $db = \Config\Database::connect();
        $builder = $db->table('categories')
                      ->select('categories.*, COUNT(novel_categories.novel_id) as novel_count')
                      ->join('novel_categories', 'novel_categories.category_id = categories.id')
                      ->join('novels', 'novels.id = novel_categories.novel_id AND novels.status = "published"')
                      ->groupBy('categories.id')
                      ->orderBy('novel_count', 'DESC')
                      ->limit($limit);

        return $builder->get()->getResultArray();
    }

    /**
     * Add categories to a novel
     */
    public function addCategoriesToNovel($novelId, array $categoryIds)
    {
        $db = \Config\Database::connect();

        // Delete existing categories for this novel
        $db->table('novel_categories')
           ->where('novel_id', $novelId)
           ->delete();

        // Add new categories
        $data = [];
        foreach ($categoryIds as $categoryId) {
            $data[] = [
                'novel_id' => $novelId,
                'category_id' => $categoryId
            ];
        }

        if (!empty($data)) {
            return $db->table('novel_categories')->insertBatch($data);
        }

        return true;
    }

    /**
     * Get all root categories (no parent)
     */
    public function getRootCategories()
    {
        return $this->where('parent_id IS NULL')
                    ->orderBy('name', 'ASC')
                    ->findAll();
    }

    /**
     * Get full category tree with hierarchy
     */
    public function getCategoryTree()
    {
        $categories = $this->orderBy('name', 'ASC')->findAll();

        $tree = [];
        $map = [];

        // Create map for quick lookup
        foreach ($categories as $category) {
            $category['children'] = [];
            $map[$category['id']] = &$category;
        }

        // Build tree structure
        foreach ($categories as $category) {
            if ($category['parent_id'] === null) {
                $tree[] = &$map[$category['id']];
            } else {
                if (isset($map[$category['parent_id']])) {
                    $map[$category['parent_id']]['children'][] = &$map[$category['id']];
                }
            }
        }

        return $tree;
    }
}
