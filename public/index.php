<?php
// public/index.php
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);
chdir(FCPATH);

require FCPATH . '../vendor/autoload.php';

$app = require FCPATH . '../app/Config/Boot/production.php';
$kernel = $app->getKernel();
$kernel->boot();

$app->run();