<?php
namespace App\Models;

use CodeIgniter\Model;

class ChapterModel extends Model
{
    protected $table = 'chapters';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'novel_id', 'chapter_number', 'title', 'content',
        'views', 'status', 'created_at', 'updated_at'
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    /**
     * Get chapters by novel
     */
    public function getChaptersByNovel($novelId, $limit = 50, $offset = 0)
    {
        return $this->where('novel_id', $novelId)
                    ->where('status', 'published')
                    ->orderBy('chapter_number', 'DESC')
                    ->findAll($limit, $offset);
    }

    /**
     * Count chapters by novel
     */
    public function getChaptersCountByNovel($novelId)
    {
        return $this->where('novel_id', $novelId)
                    ->where('status', 'published')
                    ->countAllResults();
    }

    /**
     * Get a specific chapter
     */
    public function getChapter($novelId, $chapterNumber)
    {
        return $this->where('novel_id', $novelId)
                    ->where('chapter_number', $chapterNumber)
                    ->where('status', 'published')
                    ->first();
    }

    /**
     * Get next chapter
     */
    public function getNextChapter($novelId, $currentChapter)
    {
        return $this->where('novel_id', $novelId)
                    ->where('chapter_number >', $currentChapter)
                    ->where('status', 'published')
                    ->orderBy('chapter_number', 'ASC')
                    ->first();
    }

    /**
     * Get previous chapter
     */
    public function getPreviousChapter($novelId, $currentChapter)
    {
        return $this->where('novel_id', $novelId)
                    ->where('chapter_number <', $currentChapter)
                    ->where('status', 'published')
                    ->orderBy('chapter_number', 'DESC')
                    ->first();
    }

    /**
     * Get latest chapter for a novel
     */
    public function getLatestChapterByNovel($novelId)
    {
        return $this->where('novel_id', $novelId)
                    ->where('status', 'published')
                    ->orderBy('chapter_number', 'DESC')
                    ->first();
    }

    /**
     * Get latest chapters across all novels
     */
    public function getLatestChapters($limit = 20)
    {
        return $this->select('chapters.*, novels.title as novel_title, novels.slug as novel_slug')
                    ->join('novels', 'novels.id = chapters.novel_id')
                    ->where('chapters.status', 'published')
                    ->where('novels.status', 'published')
                    ->orderBy('chapters.created_at', 'DESC')
                    ->findAll($limit);
    }

    /**
     * Increment chapter views
     */
    public function incrementViews($chapterId)
    {
        return $this->set('views', 'views + 1', false)
                    ->where('id', $chapterId)
                    ->update();
    }

    /**
     * Get chapter navigational data for lists
     */
    public function getChapterNavigationData($novelId)
    {
        return $this->select('id, chapter_number, title')
                    ->where('novel_id', $novelId)
                    ->where('status', 'published')
                    ->orderBy('chapter_number', 'ASC')
                    ->findAll();
    }

    /**
     * Get first chapter of a novel
     */
    public function getFirstChapter($novelId)
    {
        return $this->where('novel_id', $novelId)
                    ->where('status', 'published')
                    ->orderBy('chapter_number', 'ASC')
                    ->first();
    }

    /**
     * Get latest updated novels with their latest chapter
     */
    public function getLatestUpdatedNovels($limit = 12)
    {
        $db = \Config\Database::connect();

        $subquery = $db->table('chapters')
                     ->select('novel_id, MAX(created_at) as latest_update')
                     ->where('status', 'published')
                     ->groupBy('novel_id');

        $query = $db->table('novels as n')
                    ->select('n.*, u.username as author_name, c.chapter_number as latest_chapter')
                    ->join('(' . $subquery->getCompiledSelect() . ') as lc', 'lc.novel_id = n.id')
                    ->join('chapters as c', 'c.novel_id = n.id AND c.created_at = lc.latest_update', 'left')
                    ->join('users as u', 'u.id = n.author_id', 'left')
                    ->where('n.status', 'published')
                    ->orderBy('lc.latest_update', 'DESC')
                    ->limit($limit);

        return $query->get()->getResultObject();
    }
}
