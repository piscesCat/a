<?php
// app/Config/Routes.php
namespace Config;

use CodeIgniter\Router\RouteCollection;

$routes = Services::routes();

if (file_exists(SYSTEMPATH . 'Config/Routes.php')) {
    require SYSTEMPATH . 'Config/Routes.php';
}

$routes->setDefaultNamespace('App\Controllers');
$routes->setDefaultController('Home');
$routes->setDefaultMethod('index');
$routes->setTranslateURIDashes(false);
$routes->set404Override();
$routes->setAutoRoute(true);

// Installation routes
$routes->get('install', 'Install::index');
$routes->get('install/database', 'Install::database');
$routes->post('install/processDatabaseSetup', 'Install::processDatabaseSetup');
$routes->get('install/admin', 'Install::admin');
$routes->post('install/processAdminSetup', 'Install::processAdminSetup');
$routes->get('install/complete', 'Install::complete');

// Main routes with SEO friendly URLs (phimhottt.com style)
$routes->get('/', 'Home::index');
$routes->get('phim-le', 'Story::byType/single'); // Single stories (like movies)
$routes->get('phim-bo', 'Story::byType/series'); // Series stories (like TV shows)
$routes->get('phim-review', 'Story::reviews');
$routes->get('phim-hot', 'Story::popular');
$routes->get('phim-moi', 'Story::latest');
$routes->get('phim-hoan-thanh', 'Story::completed');
$routes->get('phim/(:segment)', 'Story::view/$1'); // Story detail view
$routes->get('phim/(:segment)/chuong-(:num)', 'Chapter::view/$1/$2'); // Chapter view
$routes->get('the-loai', 'Story::categories'); // All categories
$routes->get('the-loai/(:segment)', 'Story::category/$1'); // Single category
$routes->get('quoc-gia/(:segment)', 'Story::country/$1'); // Stories by country
$routes->get('nam-phat-hanh/(:num)', 'Story::byYear/$1'); // Stories by year
$routes->get('tac-gia/(:segment)', 'Story::author/$1'); // Stories by author
$routes->get('tim-kiem', 'Story::search'); // Search
$routes->get('bang-xep-hang', 'Story::rankings'); // Rankings page

// User account routes
$routes->get('login', 'Auth::login');
$routes->post('login/attempt', 'Auth::attemptLogin');
$routes->get('logout', 'Auth::logout');
$routes->get('forgot-password', 'Auth::forgotPassword');
$routes->post('reset-password', 'Auth::resetPassword');

// Comment routes
$routes->post('comments/story', 'Comment::addStoryComment');
$routes->post('comments/chapter', 'Comment::addChapterComment');
$routes->post('comments/delete', 'Comment::deleteComment');
$routes->get('comments/load-more', 'Comment::loadMoreComments');

// API endpoints
$routes->post('bookmarks/toggle', 'Story::toggleBookmark');
$routes->get('search/suggestions', 'Story::searchSuggestions');

// Admin routes
$routes->group('admin', ['namespace' => 'App\Controllers\Admin'], function($routes) {
    $routes->get('/', 'Dashboard::index');

    // Story management
    $routes->get('stories', 'StoryManager::index');
    $routes->get('stories/new', 'StoryManager::create');
    $routes->post('stories/save', 'StoryManager::save');
    $routes->get('stories/edit/(:num)', 'StoryManager::edit/$1');
    $routes->post('stories/update/(:num)', 'StoryManager::update/$1');
    $routes->post('stories/ajax-delete', 'StoryManager::delete');

    // Chapter management
    $routes->get('chapters', 'ChapterManager::index');
    $routes->get('chapters/new', 'ChapterManager::create');
    $routes->post('chapters/save', 'ChapterManager::save');
    $routes->get('chapters/edit/(:num)', 'ChapterManager::edit/$1');
    $routes->post('chapters/update/(:num)', 'ChapterManager::update/$1');
    $routes->post('chapters/ajax-delete', 'ChapterManager::delete');

    // Category management
    $routes->get('categories', 'CategoryManager::index');
    $routes->get('categories/new', 'CategoryManager::create');
    $routes->post('categories/save', 'CategoryManager::save');
    $routes->get('categories/edit/(:num)', 'CategoryManager::edit/$1');
    $routes->post('categories/update/(:num)', 'CategoryManager::update/$1');
    $routes->post('categories/ajax-delete', 'CategoryManager::delete');

    // Country management
    $routes->get('countries', 'CountryManager::index');
    $routes->get('countries/create', 'CountryManager::create');
    $routes->post('countries/store', 'CountryManager::store');
    $routes->get('countries/edit/(:num)', 'CountryManager::edit/$1');
    $routes->post('countries/update/(:num)', 'CountryManager::update/$1');
    $routes->get('countries/delete/(:num)', 'CountryManager::delete/$1');

    // Comment management
    $routes->get('comments', 'CommentManager::index');
    $routes->post('comments/delete/(:num)', 'CommentManager::delete/$1');
    $routes->post('comments/bulk-delete', 'CommentManager::bulkDelete');
    $routes->get('comments/reply/(:num)', 'CommentManager::reply/$1');
    $routes->post('comments/send-reply/(:num)', 'CommentManager::sendReply/$1');

    // User management
    $routes->get('users', 'UserManager::index');
    $routes->get('users/new', 'UserManager::create');
    $routes->post('users/save', 'UserManager::save');
    $routes->get('users/edit/(:num)', 'UserManager::edit/$1');
    $routes->post('users/update/(:num)', 'UserManager::update/$1');
    $routes->post('users/ajax-delete', 'UserManager::delete');
    $routes->post('users/ajax-update-status', 'UserManager::updateStatus');

    // Settings
    $routes->get('settings', 'Settings::index');
    $routes->post('settings/save', 'Settings::save');

    // Reports
    $routes->get('reports', 'Reports::index');
    $routes->get('reports/export/(:segment)', 'Reports::export/$1');

    // File uploads
    $routes->get('uploads', 'Uploads::index');
    $routes->post('uploads/upload', 'Uploads::upload');
    $routes->post('uploads/upload-image', 'Uploads::uploadImage');
    $routes->post('uploads/ajax-delete', 'Uploads::delete');

    // Dashboard data
    $routes->get('dashboard/stats', 'Dashboard::stats');
    $routes->get('dashboard/top-stories', 'Dashboard::topStories');
    $routes->get('dashboard/top-users', 'Dashboard::topUsers');
});

// Static pages
$routes->get('gioi-thieu', 'Page::about');
$routes->get('lien-he', 'Page::contact');
$routes->post('lien-he', 'Page::sendContact');
$routes->get('chinh-sach-bao-mat', 'Page::privacy');
$routes->get('dieu-khoan-su-dung', 'Page::terms');
