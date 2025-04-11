<?php
namespace App\Models;

use CodeIgniter\Model;

class CountryModel extends Model
{
    protected $table = 'countries';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['name', 'slug', 'description', 'flag_image'];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    /**
     * Lấy tất cả quốc gia kèm số lượng truyện
     */
    public function getCountriesWithCount()
    {
        $db = \Config\Database::connect();
        $builder = $db->table('countries')
                      ->select('countries.*, COUNT(stories.id) as story_count')
                      ->join('stories', 'stories.country_id = countries.id AND stories.status = "published"', 'left')
                      ->groupBy('countries.id')
                      ->orderBy('countries.name', 'ASC');

        return $builder->get()->getResultArray();
    }

    /**
     * Lấy quốc gia theo slug
     */
    public function getCountryBySlug($slug)
    {
        return $this->where('slug', $slug)->first();
    }

    /**
     * Lấy quốc gia phổ biến (nhiều truyện nhất)
     */
    public function getPopularCountries($limit = 10)
    {
        $db = \Config\Database::connect();
        $builder = $db->table('countries')
                      ->select('countries.*, COUNT(stories.id) as story_count')
                      ->join('stories', 'stories.country_id = countries.id AND stories.status = "published"')
                      ->groupBy('countries.id')
                      ->orderBy('story_count', 'DESC')
                      ->limit($limit);

        return $builder->get()->getResultArray();
    }
}
