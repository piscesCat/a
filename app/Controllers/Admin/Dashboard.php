<?php
namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class Dashboard extends BaseController
{
    public function index()
    {
        $db = \Config\Database::connect();

        // Get statistics
        $stats = [
            'total_stories' => $db->table('stories')->countAllResults(),
            'total_chapters' => $db->table('chapters')->countAllResults(),
            'total_users' => $db->table('users')->countAllResults(),
            'total_views' => $db->table('stories')->selectSum('views')->get()->getRow()->views ?? 0
        ];

        // Get recent activities
        $recentActivities = $db->table('activities')
                                ->select('activities.*, users.username, users.avatar')
                                ->join('users', 'users.id = activities.user_id')
                                ->orderBy('activities.created_at', 'DESC')
                                ->limit(10)
                                ->get()
                                ->getResultArray();

        // Process activities to get target details
        foreach ($recentActivities as &$activity) {
            if ($activity['details']) {
                $details = json_decode($activity['details'], true);
                $activity = array_merge($activity, $details);
            }
        }

        // Get recent stories
        $recentStories = $db->table('stories')
                            ->select('stories.*, users.username as author_name')
                            ->join('users', 'users.id = stories.author_id')
                            ->orderBy('stories.created_at', 'DESC')
                            ->limit(5)
                            ->get()
                            ->getResultArray();

        // Get system information
        $systemInfo = [
            'php_version' => PHP_VERSION,
            'ci_version' => \CodeIgniter\CodeIgniter::CI_VERSION,
            'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'timezone' => date_default_timezone_get(),
            'uptime' => $this->getServerUptime()
        ];

        // Set current page for navigation highlighting
        $this->twig->addGlobal('current_page', 'dashboard');

        return view('admin/dashboard.html', [
            'stats' => $stats,
            'recent_activities' => $recentActivities,
            'recent_stories' => $recentStories,
            'system_info' => $systemInfo
        ]);
    }

    /**
     * Get stats for dashboard charts
     */
    public function stats()
    {
        $db = \Config\Database::connect();

        // Get daily views for the last 7 days
        $visitsData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));

            // Query to get views for this date
            $query = $db->query(
                "SELECT SUM(views_count) as total FROM (
                    SELECT COUNT(*) as views_count FROM activities
                    WHERE action = 'viewed_story' AND DATE(created_at) = ?
                    UNION ALL
                    SELECT COUNT(*) as views_count FROM activities
                    WHERE action = 'viewed_chapter' AND DATE(created_at) = ?
                ) as combined_views",
                [$date, $date]
            );

            $visitsData[] = (int)($query->getRow()->total ?? 0);
        }

        return $this->response->setJSON([
            'success' => true,
            'visits_data' => $visitsData
        ]);
    }

    /**
     * Get top stories by views
     */
    public function topStories()
    {
        $db = \Config\Database::connect();

        // Get top 10 stories by views
        $topStories = $db->table('stories')
                         ->select('stories.id, stories.title, stories.slug, stories.views, users.username as author_name')
                         ->join('users', 'users.id = stories.author_id')
                         ->orderBy('stories.views', 'DESC')
                         ->limit(10)
                         ->get()
                         ->getResultArray();

        return $this->response->setJSON([
            'success' => true,
            'stories' => $topStories
        ]);
    }

    /**
     * Get top users by story count
     */
    public function topUsers()
    {
        $db = \Config\Database::connect();

        // Get top 10 users by story count
        $topUsers = $db->table('users')
                       ->select('users.id, users.username, users.avatar, COUNT(stories.id) as story_count')
                       ->join('stories', 'stories.author_id = users.id', 'left')
                       ->groupBy('users.id')
                       ->orderBy('story_count', 'DESC')
                       ->limit(10)
                       ->get()
                       ->getResultArray();

        return $this->response->setJSON([
            'success' => true,
            'users' => $topUsers
        ]);
    }

    /**
     * Get server uptime in a human-readable format
     */
    private function getServerUptime()
    {
        if (stristr(PHP_OS, 'win')) {
            // Windows system - unsupported
            return 'Không khả dụng (Windows)';
        } else {
            // Linux/Unix system
            $uptime = shell_exec('uptime -p');
            if ($uptime) {
                // translate to Vietnamese
                $uptime = str_replace('up ', '', $uptime);
                $uptime = str_replace(' days', ' ngày', $uptime);
                $uptime = str_replace(' day', ' ngày', $uptime);
                $uptime = str_replace(' hours', ' giờ', $uptime);
                $uptime = str_replace(' hour', ' giờ', $uptime);
                $uptime = str_replace(' minutes', ' phút', $uptime);
                $uptime = str_replace(' minute', ' phút', $uptime);
                return $uptime;
            }
            return 'Không xác định';
        }
    }
}
