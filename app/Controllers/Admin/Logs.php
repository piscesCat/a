<?php
namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\LogModel;

class Logs extends BaseController
{
    protected $logModel;

    public function __construct()
    {
        $this->logModel = new LogModel();
    }

    public function index()
    {
        // Get pagination parameters
        $page = (int)($this->request->getGet('page') ?? 1);
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        // Get log level filter
        $level = $this->request->getGet('level') ?? null;

        // Get logs
        $logs = $this->logModel->getLogs($perPage, $offset, $level);
        $total = $this->logModel->countLogs($level);

        // Calculate pagination
        $totalPages = ceil($total / $perPage);

        return view('admin/logs/index.html', [
            'logs' => $logs,
            'current_page' => 'logs',
            'pagination' => [
                'page' => $page,
                'total_pages' => $totalPages,
                'total' => $total,
                'per_page' => $perPage
            ],
            'level' => $level
        ]);
    }

    public function clear()
    {
        $result = $this->logModel->deleteAllLogs();

        if ($result) {
            // Add a new log entry for the clearing
            $this->logModel->info('System logs cleared', [
                'user_id' => $this->session->get('user_id'),
                'username' => $this->session->get('username')
            ]);

            return redirect()->to(base_url('/admin/logs'))->with('success', 'Đã xóa tất cả nhật ký hệ thống.');
        } else {
            return redirect()->to(base_url('/admin/logs'))->with('error', 'Không thể xóa nhật ký hệ thống. Vui lòng thử lại.');
        }
    }

    public function clearOld()
    {
        $days = (int)($this->request->getPost('days') ?? 30);

        $result = $this->logModel->deleteOldLogs($days);

        if ($result) {
            // Add a new log entry for the clearing
            $this->logModel->info('Old system logs cleared', [
                'user_id' => $this->session->get('user_id'),
                'username' => $this->session->get('username'),
                'days' => $days
            ]);

            return redirect()->to(base_url('/admin/logs'))->with('success', "Đã xóa tất cả nhật ký hệ thống cũ hơn $days ngày.");
        } else {
            return redirect()->to(base_url('/admin/logs'))->with('error', 'Không thể xóa nhật ký hệ thống cũ. Vui lòng thử lại.');
        }
    }

    public function view($id)
    {
        $log = $this->logModel->find($id);

        if (!$log) {
            return redirect()->to(base_url('/admin/logs'))->with('error', 'Nhật ký không tồn tại.');
        }

        // Decode context if it's JSON
        if (isset($log['context']) && !empty($log['context'])) {
            $log['context'] = json_decode($log['context'], true);
        }

        return view('admin/logs/view.html', [
            'log' => $log,
            'current_page' => 'logs'
        ]);
    }

    public function export()
    {
        // Get log level filter
        $level = $this->request->getGet('level') ?? null;

        // Get all logs (with limit to prevent memory issues)
        $logs = $this->logModel->getLogs(1000, 0, $level);

        // Create CSV file
        $fileName = 'system_logs_' . date('Y-m-d_H-i-s') . '.csv';
        $filePath = WRITEPATH . 'temp/' . $fileName;

        $file = fopen($filePath, 'w');

        // Add CSV header
        fputcsv($file, ['ID', 'Level', 'Message', 'Context', 'Date']);

        // Add data rows
        foreach ($logs as $log) {
            $context = $log['context'] ?? '';
            if (is_string($context)) {
                $context = str_replace(["\r", "\n"], '', $context);
            } else {
                $context = json_encode($context);
            }

            fputcsv($file, [
                $log['id'],
                $log['level'],
                $log['message'],
                $context,
                $log['created_at']
            ]);
        }

        fclose($file);

        // Log export
        $this->logModel->info('System logs exported', [
            'user_id' => $this->session->get('user_id'),
            'username' => $this->session->get('username'),
            'file_name' => $fileName,
            'log_count' => count($logs)
        ]);

        // Download file
        return $this->response->download($filePath, null)->setFileName($fileName);
    }
}
