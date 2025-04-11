<?php
namespace App\Models;

use CodeIgniter\Model;

class LogModel extends Model
{
    protected $table = 'logs';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'level', 'message', 'context', 'created_at'
    ];
    protected $returnType = 'array';
    protected $useTimestamps = false;

    /**
     * Add a new log entry
     */
    public function addLog($level, $message, $context = [])
    {
        return $this->insert([
            'level' => $level,
            'message' => $message,
            'context' => json_encode($context),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Get logs with pagination
     */
    public function getLogs($limit = 50, $offset = 0, $level = null)
    {
        $query = $this;

        if ($level !== null) {
            $query = $query->where('level', $level);
        }

        return $query->orderBy('created_at', 'DESC')
                     ->limit($limit, $offset)
                     ->find();
    }

    /**
     * Count logs
     */
    public function countLogs($level = null)
    {
        $query = $this;

        if ($level !== null) {
            $query = $query->where('level', $level);
        }

        return $query->countAllResults();
    }

    /**
     * Delete logs older than a certain date
     */
    public function deleteOldLogs($days = 30)
    {
        $date = date('Y-m-d H:i:s', strtotime("-$days days"));
        return $this->where('created_at <', $date)->delete();
    }

    /**
     * Delete all logs
     */
    public function deleteAllLogs()
    {
        return $this->truncate();
    }

    /**
     * Add info log
     */
    public function info($message, $context = [])
    {
        return $this->addLog('info', $message, $context);
    }

    /**
     * Add error log
     */
    public function error($message, $context = [])
    {
        return $this->addLog('error', $message, $context);
    }

    /**
     * Add warning log
     */
    public function warning($message, $context = [])
    {
        return $this->addLog('warning', $message, $context);
    }

    /**
     * Add debug log
     */
    public function debug($message, $context = [])
    {
        return $this->addLog('debug', $message, $context);
    }
}
