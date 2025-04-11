<?php
namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\MediaModel;
use App\Models\LogModel;

class Uploads extends BaseController
{
    protected $mediaModel;
    protected $logModel;

    public function __construct()
    {
        $this->mediaModel = new MediaModel();
        $this->logModel = new LogModel();
    }

    public function index()
    {
        // Get pagination parameters
        $page = (int)($this->request->getGet('page') ?? 1);
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        // Get type filter
        $type = $this->request->getGet('type') ?? null;

        // Get media files
        $media = $this->mediaModel->getMedia($perPage, $offset, null, $type);
        $total = $this->mediaModel->countMedia(null, $type);

        // Calculate pagination
        $totalPages = ceil($total / $perPage);

        return view('admin/uploads/index.html', [
            'media' => $media,
            'current_page' => 'uploads',
            'pagination' => [
                'page' => $page,
                'total_pages' => $totalPages,
                'total' => $total,
                'per_page' => $perPage
            ],
            'type' => $type
        ]);
    }

    public function upload()
    {
        // Check if files were uploaded
        $files = $this->request->getFiles();

        if (empty($files['files'])) {
            return redirect()->to(base_url('/admin/uploads'))->with('error', 'Không có tệp nào được tải lên.');
        }

        $successCount = 0;
        $failedCount = 0;

        foreach ($files['files'] as $file) {
            if ($file->isValid() && !$file->hasMoved()) {
                $result = $this->mediaModel->saveMedia($file, $this->session->get('user_id'));

                if ($result) {
                    $successCount++;

                    // Log file upload
                    $this->logModel->info('File uploaded', [
                        'user_id' => $this->session->get('user_id'),
                        'username' => $this->session->get('username'),
                        'file_name' => $file->getClientName(),
                        'file_type' => $file->getClientMimeType(),
                        'file_size' => $file->getSize()
                    ]);
                } else {
                    $failedCount++;
                }
            } else {
                $failedCount++;
            }
        }

        if ($successCount > 0) {
            $message = "$successCount tệp đã được tải lên thành công.";
            if ($failedCount > 0) {
                $message .= " $failedCount tệp tải lên thất bại.";
            }
            return redirect()->to(base_url('/admin/uploads'))->with('success', $message);
        } else {
            return redirect()->to(base_url('/admin/uploads'))->with('error', 'Không thể tải lên tệp. Vui lòng thử lại.');
        }
    }

    public function uploadImage()
    {
        // This method is for image upload via AJAX (for editors like Summernote)
        $file = $this->request->getFile('file');

        if ($file && $file->isValid() && !$file->hasMoved()) {
            $result = $this->mediaModel->saveMedia($file, $this->session->get('user_id'));

            if ($result) {
                // Get file info
                $media = $this->mediaModel->find($result);

                // Log file upload
                $this->logModel->info('Image uploaded via editor', [
                    'user_id' => $this->session->get('user_id'),
                    'username' => $this->session->get('username'),
                    'file_name' => $file->getClientName(),
                    'file_type' => $file->getClientMimeType(),
                    'file_size' => $file->getSize()
                ]);

                return $this->response->setJSON([
                    'success' => true,
                    'url' => base_url($media['file_path'])
                ]);
            }
        }

        return $this->response->setJSON([
            'success' => false,
            'message' => 'Không thể tải lên tệp. Vui lòng thử lại.'
        ]);
    }

    public function delete()
    {
        $id = $this->request->getPost('id');

        if (!$id) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'ID tệp không hợp lệ.'
            ]);
        }

        // Get media info before deletion
        $media = $this->mediaModel->find($id);

        if (!$media) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Tệp không tồn tại.'
            ]);
        }

        $result = $this->mediaModel->deleteMedia($id);

        if ($result) {
            // Log file deletion
            $this->logModel->info('File deleted', [
                'user_id' => $this->session->get('user_id'),
                'username' => $this->session->get('username'),
                'file_name' => $media['file_name'],
                'file_path' => $media['file_path']
            ]);

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Tệp đã được xóa thành công.'
            ]);
        } else {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Không thể xóa tệp. Vui lòng thử lại.'
            ]);
        }
    }

    public function browser()
    {
        // This method is for the file browser (for editors like Summernote)
        $page = (int)($this->request->getGet('page') ?? 1);
        $perPage = 12;
        $offset = ($page - 1) * $perPage;

        // Filter by image type only
        $type = $this->request->getGet('type') ?? 'image';
        if ($type === 'image') {
            $type = 'image/%';
        }

        // Get media files
        $media = $this->mediaModel->getMedia($perPage, $offset, null, $type);
        $total = $this->mediaModel->countMedia(null, $type);

        // Calculate pagination
        $totalPages = ceil($total / $perPage);

        return view('admin/uploads/browser.html', [
            'media' => $media,
            'pagination' => [
                'page' => $page,
                'total_pages' => $totalPages,
                'total' => $total,
                'per_page' => $perPage
            ],
            'type' => $type
        ]);
    }
}
