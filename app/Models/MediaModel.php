<?php
namespace App\Models;

use CodeIgniter\Model;

class MediaModel extends Model
{
    protected $table = 'media';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'file_name', 'file_path', 'file_type', 'file_size', 'user_id', 'uploaded_at'
    ];
    protected $returnType = 'array';
    protected $useTimestamps = false;

    /**
     * Get all media files with pagination
     */
    public function getMedia($limit = 20, $offset = 0, $user_id = null, $type = null)
    {
        $query = $this->select('media.*, users.username')
                      ->join('users', 'users.id = media.user_id', 'left');

        if ($user_id !== null) {
            $query->where('media.user_id', $user_id);
        }

        if ($type !== null) {
            $query->where('media.file_type', $type);
        }

        return $query->orderBy('media.uploaded_at', 'DESC')
                     ->limit($limit, $offset)
                     ->find();
    }

    /**
     * Count media files
     */
    public function countMedia($user_id = null, $type = null)
    {
        $query = $this;

        if ($user_id !== null) {
            $query = $query->where('user_id', $user_id);
        }

        if ($type !== null) {
            $query = $query->where('file_type', $type);
        }

        return $query->countAllResults();
    }

    /**
     * Get media by type
     */
    public function getByType($type, $limit = 20, $offset = 0)
    {
        return $this->where('file_type', $type)
                    ->orderBy('uploaded_at', 'DESC')
                    ->limit($limit, $offset)
                    ->find();
    }

    /**
     * Save a media file
     */
    public function saveMedia($file, $user_id)
    {
        if (!$file->isValid() || $file->hasMoved()) {
            return false;
        }

        // Generate a unique filename
        $newName = $file->getRandomName();

        // Define upload directory
        $uploadPath = 'uploads/media';

        // Ensure upload directory exists
        if (!is_dir(ROOTPATH . 'public/' . $uploadPath)) {
            mkdir(ROOTPATH . 'public/' . $uploadPath, 0777, true);
        }

        // Move the file
        $file->move(ROOTPATH . 'public/' . $uploadPath, $newName);

        // Insert into database
        $data = [
            'file_name' => $file->getClientName(),
            'file_path' => $uploadPath . '/' . $newName,
            'file_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'user_id' => $user_id,
            'uploaded_at' => date('Y-m-d H:i:s')
        ];

        return $this->insert($data);
    }

    /**
     * Delete a media file
     */
    public function deleteMedia($id)
    {
        $media = $this->find($id);

        if (!$media) {
            return false;
        }

        // Delete the physical file
        $filePath = ROOTPATH . 'public/' . $media['file_path'];

        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Delete the database record
        return $this->delete($id);
    }
}
