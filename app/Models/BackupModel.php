<?php
namespace App\Models;

use CodeIgniter\Model;

class BackupModel extends Model
{
    protected $table = 'backups';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'file_name', 'file_path', 'file_size', 'description', 'user_id', 'created_at'
    ];
    protected $returnType = 'array';
    protected $useTimestamps = false;

    /**
     * Get all backups with pagination
     */
    public function getBackups($limit = 20, $offset = 0)
    {
        return $this->select('backups.*, users.username')
                    ->join('users', 'users.id = backups.user_id', 'left')
                    ->orderBy('backups.created_at', 'DESC')
                    ->limit($limit, $offset)
                    ->find();
    }

    /**
     * Count backups
     */
    public function countBackups()
    {
        return $this->countAllResults();
    }

    /**
     * Create a database backup
     */
    public function createDatabaseBackup($userId, $description = '')
    {
        // Database config
        $db = \Config\Database::connect();
        $dbConfig = $db->getConnectStart();

        // Create backup directory if it doesn't exist
        $backupDir = ROOTPATH . 'writable/backups/database';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0777, true);
        }

        // Generate backup filename
        $date = date('Y-m-d_H-i-s');
        $fileName = "db_backup_{$date}.sql";
        $filePath = $backupDir . '/' . $fileName;

        // Get the database credentials
        $hostname = $db->hostname;
        $username = $db->username;
        $password = $db->password;
        $database = $db->database;
        $port = $db->port;

        // Execute the pg_dump command
        $command = "PGPASSWORD=\"{$password}\" pg_dump -h {$hostname} -p {$port} -U {$username} -d {$database} > {$filePath}";
        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            return false;
        }

        // Get the file size
        $fileSize = filesize($filePath);

        // Save the backup record in the database
        $data = [
            'file_name' => $fileName,
            'file_path' => 'writable/backups/database/' . $fileName,
            'file_size' => $fileSize,
            'description' => empty($description) ? 'Automatic database backup' : $description,
            'user_id' => $userId,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $backupId = $this->insert($data);

        // Log the backup
        $logModel = new LogModel();
        $logModel->info('Database backup created', [
            'backup_id' => $backupId,
            'file_name' => $fileName,
            'file_size' => $fileSize
        ]);

        return $backupId;
    }

    /**
     * Create a files backup
     */
    public function createFilesBackup($userId, $description = '', $includeDirs = ['app', 'public'])
    {
        // Create backup directory if it doesn't exist
        $backupDir = ROOTPATH . 'writable/backups/files';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0777, true);
        }

        // Generate backup filename
        $date = date('Y-m-d_H-i-s');
        $fileName = "files_backup_{$date}.zip";
        $filePath = $backupDir . '/' . $fileName;

        // Create a zip archive
        $zip = new \ZipArchive();
        if ($zip->open($filePath, \ZipArchive::CREATE) !== TRUE) {
            return false;
        }

        // Add directories to the zip
        foreach ($includeDirs as $dir) {
            $fullPath = ROOTPATH . $dir;
            if (is_dir($fullPath)) {
                $this->addDirToZip($zip, $fullPath, $dir);
            }
        }

        $zip->close();

        // Get the file size
        $fileSize = filesize($filePath);

        // Save the backup record in the database
        $data = [
            'file_name' => $fileName,
            'file_path' => 'writable/backups/files/' . $fileName,
            'file_size' => $fileSize,
            'description' => empty($description) ? 'Files backup' : $description,
            'user_id' => $userId,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $backupId = $this->insert($data);

        // Log the backup
        $logModel = new LogModel();
        $logModel->info('Files backup created', [
            'backup_id' => $backupId,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'included_dirs' => implode(', ', $includeDirs)
        ]);

        return $backupId;
    }

    /**
     * Delete a backup
     */
    public function deleteBackup($id)
    {
        $backup = $this->find($id);

        if (!$backup) {
            return false;
        }

        // Delete the physical file
        $filePath = ROOTPATH . $backup['file_path'];

        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Delete the database record
        $result = $this->delete($id);

        // Log the deletion
        $logModel = new LogModel();
        $logModel->info('Backup deleted', [
            'backup_id' => $id,
            'file_name' => $backup['file_name']
        ]);

        return $result;
    }

    /**
     * Helper method to add a directory to a zip archive
     */
    private function addDirToZip($zip, $dir, $zipDir)
    {
        if (is_dir($dir)) {
            if ($handle = opendir($dir)) {
                while (($file = readdir($handle)) !== false) {
                    if ($file != '.' && $file != '..') {
                        $filePath = $dir . '/' . $file;
                        $zipFilePath = $zipDir . '/' . $file;

                        if (is_dir($filePath)) {
                            // Add directory to zip
                            $zip->addEmptyDir($zipFilePath);
                            // Recurse into subdirectory
                            $this->addDirToZip($zip, $filePath, $zipFilePath);
                        } else {
                            // Add file to zip
                            $zip->addFile($filePath, $zipFilePath);
                        }
                    }
                }
                closedir($handle);
            }
        }
    }
}
