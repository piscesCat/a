<?php
namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

class StoryManager extends BaseController
{
    protected $storyModel;
    protected $userModel;
    protected $categoryModel;
    protected $countryModel;

    public function __construct()
    {
        $this->storyModel = new \App\Models\StoryModel();
        $this->userModel = new \App\Models\UserModel();
        $this->categoryModel = new \App\Models\CategoryModel();
        $this->countryModel = new \App\Models\CountryModel();
    }

    /**
     * Hiển thị danh sách truyện
     */
    public function index()
    {
        $stories = $this->storyModel
                     ->select('stories.*, users.username as author_name, countries.name as country_name')
                     ->join('users', 'users.id = stories.author_id')
                     ->join('countries', 'countries.id = stories.country_id', 'left')
                     ->orderBy('stories.created_at', 'DESC')
                     ->findAll();

        return view('admin/story/index.html', [
            'stories' => $stories
        ]);
    }

    /**
     * Hiển thị form tạo truyện mới
     */
    public function new()
    {
        $users = $this->userModel->findAll();
        $categories = $this->categoryModel->findAll();
        $countries = $this->countryModel->findAll();

        return view('admin/story/create.html', [
            'users' => $users,
            'categories' => $categories,
            'countries' => $countries
        ]);
    }

    /**
     * Xử lý tạo truyện mới
     */
    public function create()
    {
        // Validate form input
        $rules = [
            'title' => 'required|min_length[3]|max_length[200]',
            'slug' => 'required|alpha_dash|min_length[3]|max_length[200]|is_unique[stories.slug]',
            'description' => 'required',
            'author_id' => 'required|integer',
            'status' => 'required|in_list[draft,published,completed]',
            'categories' => 'required',
            'country_id' => 'permit_empty|integer',
            'year' => 'permit_empty|integer|greater_than[1900]|less_than[2100]',
            'type' => 'permit_empty|in_list[single,series,review]'
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        // Handle image upload
        $coverImage = $this->handleCoverImageUpload();

        // Prepare data
        $data = [
            'title' => $this->request->getPost('title'),
            'slug' => $this->request->getPost('slug'),
            'description' => $this->request->getPost('description'),
            'author_id' => $this->request->getPost('author_id'),
            'status' => $this->request->getPost('status'),
            'cover_image' => $coverImage,
            'views' => 0,
            'rating' => 0,
            'country_id' => $this->request->getPost('country_id') ?: null,
            'year' => $this->request->getPost('year') ?: null,
            'type' => $this->request->getPost('type') ?: null,
            'is_featured' => $this->request->getPost('is_featured') ? 1 : 0,
            'is_hot' => $this->request->getPost('is_hot') ? 1 : 0,
            'is_completed' => $this->request->getPost('is_completed') ? 1 : 0
        ];

        // Insert record
        $db = \Config\Database::connect();
        $db->transStart();

        $storyId = $this->storyModel->insert($data);

        // Insert categories
        $categories = $this->request->getPost('categories');
        foreach ($categories as $categoryId) {
            $db->table('story_categories')->insert([
                'story_id' => $storyId,
                'category_id' => $categoryId
            ]);
        }

        // Add activity log
        $this->logActivity('created_story', [
            'target_type' => 'story',
            'target_id' => $storyId,
            'target_title' => $data['title'],
            'target_slug' => $data['slug']
        ]);

        $db->transComplete();

        if ($db->transStatus() === false) {
            return redirect()->back()->withInput()->with('error', 'Đã xảy ra lỗi khi thêm truyện.');
        }

        return redirect()->to('/admin/stories')->with('success', 'Truyện đã được thêm thành công.');
    }

    /**
     * Hiển thị form chỉnh sửa truyện
     */
    public function edit($id)
    {
        $story = $this->storyModel->find($id);

        if (!$story) {
            return redirect()->to('/admin/stories')->with('error', 'Truyện không tồn tại.');
        }

        $users = $this->userModel->findAll();
        $categories = $this->categoryModel->findAll();
        $countries = $this->countryModel->findAll();

        // Get selected categories
        $db = \Config\Database::connect();
        $selectedCategories = $db->table('story_categories')
                                 ->where('story_id', $id)
                                 ->get()
                                 ->getResultArray();

        $selectedCategoryIds = array_column($selectedCategories, 'category_id');

        return view('admin/story/edit.html', [
            'story' => $story,
            'users' => $users,
            'categories' => $categories,
            'countries' => $countries,
            'selected_categories' => $selectedCategoryIds
        ]);
    }

    /**
     * Xử lý cập nhật truyện
     */
    public function update($id)
    {
        // Validate form input
        $rules = [
            'title' => 'required|min_length[3]|max_length[200]',
            'slug' => "required|alpha_dash|min_length[3]|max_length[200]|is_unique[stories.slug,id,$id]",
            'description' => 'required',
            'author_id' => 'required|integer',
            'status' => 'required|in_list[draft,published,completed]',
            'categories' => 'required',
            'country_id' => 'permit_empty|integer',
            'year' => 'permit_empty|integer|greater_than[1900]|less_than[2100]',
            'type' => 'permit_empty|in_list[single,series,review]'
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $story = $this->storyModel->find($id);
        if (!$story) {
            return redirect()->to('/admin/stories')->with('error', 'Truyện không tồn tại.');
        }

        // Handle image upload
        $coverImage = $this->handleCoverImageUpload($story['cover_image']);

        // Prepare data
        $data = [
            'title' => $this->request->getPost('title'),
            'slug' => $this->request->getPost('slug'),
            'description' => $this->request->getPost('description'),
            'author_id' => $this->request->getPost('author_id'),
            'status' => $this->request->getPost('status'),
            'country_id' => $this->request->getPost('country_id') ?: null,
            'year' => $this->request->getPost('year') ?: null,
            'type' => $this->request->getPost('type') ?: null,
            'is_featured' => $this->request->getPost('is_featured') ? 1 : 0,
            'is_hot' => $this->request->getPost('is_hot') ? 1 : 0,
            'is_completed' => $this->request->getPost('is_completed') ? 1 : 0
        ];

        // Only update cover image if a new one was uploaded
        if ($coverImage) {
            $data['cover_image'] = $coverImage;
        }

        // Update record
        $db = \Config\Database::connect();
        $db->transStart();

        $this->storyModel->update($id, $data);

        // Update categories
        $db->table('story_categories')->where('story_id', $id)->delete();

        $categories = $this->request->getPost('categories');
        foreach ($categories as $categoryId) {
            $db->table('story_categories')->insert([
                'story_id' => $id,
                'category_id' => $categoryId
            ]);
        }

        // Add activity log
        $this->logActivity('updated_story', [
            'target_type' => 'story',
            'target_id' => $id,
            'target_title' => $data['title'],
            'target_slug' => $data['slug']
        ]);

        $db->transComplete();

        if ($db->transStatus() === false) {
            return redirect()->back()->withInput()->with('error', 'Đã xảy ra lỗi khi cập nhật truyện.');
        }

        return redirect()->to('/admin/stories')->with('success', 'Truyện đã được cập nhật thành công.');
    }

    /**
     * Xóa truyện
     */
    public function delete($id)
    {
        $story = $this->storyModel->find($id);

        if (!$story) {
            return redirect()->to('/admin/stories')->with('error', 'Truyện không tồn tại.');
        }

        $db = \Config\Database::connect();
        $db->transStart();

        // Delete story categories
        $db->table('story_categories')->where('story_id', $id)->delete();

        // Delete chapters
        $db->table('chapters')->where('story_id', $id)->delete();

        // Delete bookmarks
        $db->table('bookmarks')->where('story_id', $id)->delete();

        // Delete reading progress
        $db->table('reading_progress')->where('story_id', $id)->delete();

        // Delete ratings
        $db->table('ratings')->where('story_id', $id)->delete();

        // Delete comments
        $db->table('comments')->where('story_id', $id)->delete();

        // Delete the story
        $this->storyModel->delete($id);

        // Add activity log
        $this->logActivity('deleted_story', [
            'target_type' => 'story',
            'target_id' => $id,
            'target_title' => $story['title'],
            'target_slug' => $story['slug']
        ]);

        $db->transComplete();

        if ($db->transStatus() === false) {
            return redirect()->to('/admin/stories')->with('error', 'Đã xảy ra lỗi khi xóa truyện.');
        }

        return redirect()->to('/admin/stories')->with('success', 'Truyện đã được xóa thành công.');
    }

    /**
     * Xử lý upload ảnh bìa
     */
    private function handleCoverImageUpload($currentImage = null)
    {
        $coverImage = $this->request->getFile('cover_image');

        if ($coverImage && $coverImage->isValid() && !$coverImage->hasMoved()) {
            $newName = $coverImage->getRandomName();
            $coverImage->move(FCPATH . 'uploads/covers', $newName);

            // Xóa ảnh cũ nếu có
            if ($currentImage && file_exists(FCPATH . $currentImage)) {
                unlink(FCPATH . $currentImage);
            }

            return 'uploads/covers/' . $newName;
        }

        return $currentImage;
    }

    /**
     * Ghi log hoạt động
     */
    private function logActivity($action, $details)
    {
        $logModel = new \App\Models\LogModel();

        $logModel->insert([
            'level' => 'info',
            'message' => 'User ' . session()->get('user')['username'] . ' ' . $action . ': ' . $details['target_title'],
            'context' => json_encode(array_merge($details, [
                'user_id' => session()->get('user')['id'],
                'ip_address' => $this->request->getIPAddress()
            ]))
        ]);
    }
}
