<?php
// app/Config/Database.php
namespace Config;

use CodeIgniter\Database\Config;

class Database extends Config
{
    public $defaultGroup = 'default';

    public $default = [
        'DSN'      => '',
        'hostname' => 'localhost',
        'username' => 'postgres',
        'password' => 'postgres',
        'database' => 'story_website',
        'DBDriver' => 'Postgre',
        'DBPrefix' => '',
        'pConnect' => false,
        'DBDebug'  => true,
        'charset'  => 'utf8',
        'DBCollat' => 'utf8_general_ci',
        'swapPre'  => '',
        'encrypt'  => false,
        'compress' => false,
        'strictOn' => false,
        'failover' => [],
        'port'     => 5432,
    ];

    public function __construct()
    {
        parent::__construct();

        if (getenv('CI_ENVIRONMENT') === 'development') {
            $this->default['hostname'] = $_ENV['DB_HOSTNAME'] ?? 'localhost';
            $this->default['username'] = $_ENV['DB_USERNAME'] ?? '';
            $this->default['password'] = $_ENV['DB_PASSWORD'] ?? '';
            $this->default['database'] = $_ENV['DB_DATABASE'] ?? '';
            $this->default['port']     = $_ENV['DB_PORT'] ?? 5432;
        }
    }
}