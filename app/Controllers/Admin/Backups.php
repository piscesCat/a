<?php
namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\BackupModel;
use App\Models\LogModel;

class Backups extends BaseController
{
    protected $backupModel;
    protected $logModel;

    public function __construct()
    {
        $this->backupModel = new BackupModel();
        $this->logModel = new LogModel();
    }

    public function index()
    {
        // Get pagination parameters
        $page = (int)($this->request->getGet('page') ?? 1);
        $perPage = 10;
        $offset = ($page - 1) * $perPage;

        // Get backups
        $backups = $this->backupModel->getBackups($perPage, $offset);
        $total = $this->backupModel->countBackups();

        // Calculate pagination
        $totalPages = ceil($total / $perPage);

        return view('admin/backups/index.html', [
            'backups' => $backups,
            'current_page' => 'backups',
            'pagination' => [
                'page' => $page,
                'total_pages' => $totalPages,
                'total' => $total,
                'per_page' => $perPage
            ]
        ]);
    }

    public function createDatabase()
    {
        // Get current user id
        $userId = $this->session->get('user_id');
        $description = $this->request->getPost('description') ?? '';

        $result = $this->backupModel->createDatabaseBackup($userId, $description);

        if ($result) {
            return redirect()->to(base_url('/admin/backups'))->with('success', 'Đã tạo sao lưu cơ sở dữ liệu thành công.');
        } else {
            return redirect()->to(base_url('/admin/backups'))->with('error', 'Không thể tạo sao lưu cơ sở dữ liệu. Vui lòng kiểm tra quyền hạn và cấu hình hệ thống.');
        }
    }

    public function createFiles()
    {
        // Get current user id
        $userId = $this->session->get('user_id');
        $description = $this->request->getPost('description') ?? '';

        // Get directories to backup
        $includeDirs = $this->request->getPost('include_dirs') ?? ['app', 'public'];
        if (!is_array($includeDirs)) {
            $includeDirs = explode(',', $includeDirs);
        }

        $result = $this->backupModel->createFilesBackup($userId, $description, $includeDirs);

        if ($result) {
            return redirect()->to(base_url('/admin/backups'))->with('success', 'Đã tạo sao lưu tệp tin thành công.');
        } else {
            return redirect()->to(base_url('/admin/backups'))->with('error', 'Không thể tạo sao lưu tệp tin. Vui lòng kiểm tra quyền hạn và cấu hình hệ thống.');
        }
    }

    public function download($id)
    {
        $backup = $this->backupModel->find($id);

        if (!$backup) {
            return redirect()->to(base_url('/admin/backups'))->with('error', 'Bản sao lưu không tồn tại.');
        }

        $filePath = ROOTPATH . $backup['file_path'];

        if (!file_exists($filePath)) {
            return redirect()->to(base_url('/admin/backups'))->with('error', 'Tệp sao lưu không tồn tại trên máy chủ.');
        }

        // Log download
        $this->logModel->info('Backup downloaded', [
            'user_id' => $this->session->get('user_id'),
            'username' => $this->session->get('username'),
            'backup_id' => $backup['id'],
            'file_name' => $backup['file_name']
        ]);

        return $this->response->download($filePath, null);
    }

    public function delete()
    {
        $id = $this->request->getPost('id');

        if (!$id) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'ID bản sao lưu không hợp lệ.'
            ]);
        }

        $result = $this->backupModel->deleteBackup($id);

        if ($result) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Bản sao lưu đã được xóa thành công.'
            ]);
        } else {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Không thể xóa bản sao lưu. Vui lòng thử lại.'
            ]);
        }
    }

    public function restore($id)
    {
        $backup = $this->backupModel->find($id);

        if (!$backup) {
            return redirect()->to(base_url('/admin/backups'))->with('error', 'Bản sao lưu không tồn tại.');
        }

        $filePath = ROOTPATH . $backup['file_path'];

        if (!file_exists($filePath)) {
            return redirect()->to(base_url('/admin/backups'))->with('error', 'Tệp sao lưu không tồn tại trên máy chủ.');
        }

        // Check if it's a database backup
        if (strpos($backup['file_name'], 'db_backup_') === 0) {
            // Restore database
            $result = $this->restoreDatabase($filePath);

            if ($result) {
                // Log restore
                $this->logModel->info('Database backup restored', [
                    'user_id' => $this->session->get('user_id'),
                    'username' => $this->session->get('username'),
                    'backup_id' => $backup['id'],
                    'file_name' => $backup['file_name']
                ]);

                return redirect()->to(base_url('/admin/backups'))->with('success', 'Đã khôi phục cơ sở dữ liệu thành công.');
            } else {
                return redirect()->to(base_url('/admin/backups'))->with('error', 'Không thể khôi phục cơ sở dữ liệu. Vui lòng kiểm tra quyền hạn và cấu hình hệ thống.');
            }
        } else if (strpos($backup['file_name'], 'files_backup_') === 0) {
            // Restore files
            $result = $this->restoreFiles($filePath);

            if ($result) {
                // Log restore
                $this->logModel->info('Files backup restored', [
                    'user_id' => $this->session->get('user_id'),
                    'username' => $this->session->get('username'),
                    'backup_id' => $backup['id'],
                    'file_name' => $backup['file_name']
                ]);

                return redirect()->to(base_url('/admin/backups'))->with('success', 'Đã khôi phục tệp tin thành công.');
            } else {
                return redirect()->to(base_url('/admin/backups'))->with('error', 'Không thể khôi phục tệp tin. Vui lòng kiểm tra quyền hạn và cấu hình hệ thống.');
            }
        } else {
            return redirect()->to(base_url('/admin/backups'))->with('error', 'Loại bản sao lưu không được hỗ trợ.');
        }
    }

    /**
     * Restore database from SQL backup file
     */
    private function restoreDatabase($filePath)
    {
        // Get database connection
        $db = \Config\Database::connect();
        $hostname = $db->hostname;
        $username = $db->username;
        $password = $db->password;
        $database = $db->database;
        $port = $db->port;

        // Execute the psql command to restore
        $command = "PGPASSWORD=\"{$password}\" psql -h {$hostname} -p {$port} -U {$username} -d {$database} -f {$filePath}";
        exec($command, $output, $returnVar);

        return $returnVar === 0;
    }

    /**
     * Restore files from ZIP backup
     */
    private function restoreFiles($filePath)
    {
        $zip = new \ZipArchive();

        if ($zip->open($filePath) === TRUE) {
            $extractPath = ROOTPATH;
            $zip->extractTo($extractPath);
            $zip->close();
            return true;
        }

        return false;
    }
}
