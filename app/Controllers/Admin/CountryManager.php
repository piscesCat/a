<?php
namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\CountryModel;
use App\Models\LogModel;

class CountryManager extends BaseController
{
    protected $countryModel;
    protected $logModel;

    public function __construct()
    {
        $this->countryModel = new CountryModel();
        $this->logModel = new LogModel();
    }

    /**
     * Hiển thị danh sách quốc gia
     */
    public function index()
    {
        $data = [
            'title' => 'Quản lý quốc gia',
            'countries' => $this->countryModel->getCountriesWithCount()
        ];

        return $this->render('admin/country/index.html', $data);
    }

    /**
     * Hiển thị form tạo quốc gia mới
     */
    public function create()
    {
        $data = [
            'title' => 'Thêm quốc gia mới'
        ];

        return $this->render('admin/country/create.html', $data);
    }

    /**
     * Xử lý tạo quốc gia mới
     */
    public function store()
    {
        $rules = [
            'name' => 'required|min_length[2]|max_length[100]',
            'slug' => 'required|min_length[2]|max_length[100]|is_unique[countries.slug]',
            'description' => 'permit_empty'
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $data = [
            'name' => $this->request->getPost('name'),
            'slug' => $this->request->getPost('slug'),
            'description' => $this->request->getPost('description')
        ];

        // Xử lý upload ảnh cờ nếu có
        $flagImage = $this->request->getFile('flag_image');
        if ($flagImage && $flagImage->isValid() && !$flagImage->hasMoved()) {
            $newName = $flagImage->getRandomName();
            $flagImage->move(FCPATH . 'uploads/flags', $newName);
            $data['flag_image'] = 'uploads/flags/' . $newName;
        }

        $this->countryModel->insert($data);

        // Ghi log
        $this->logModel->insert([
            'level' => 'info',
            'message' => 'Quốc gia mới đã được tạo: ' . $data['name'],
            'context' => json_encode([
                'user_id' => session()->get('user')['id'] ?? null,
                'country_id' => $this->countryModel->getInsertID(),
                'ip_address' => $this->request->getIPAddress()
            ])
        ]);

        return redirect()->to('/admin/countries')
            ->with('success', 'Quốc gia đã được tạo thành công.');
    }

    /**
     * Hiển thị form chỉnh sửa quốc gia
     */
    public function edit($id)
    {
        $country = $this->countryModel->find($id);
        if (!$country) {
            return redirect()->to('/admin/countries')
                ->with('error', 'Quốc gia không tồn tại.');
        }

        $data = [
            'title' => 'Chỉnh sửa quốc gia',
            'country' => $country
        ];

        return $this->render('admin/country/edit.html', $data);
    }

    /**
     * Xử lý cập nhật quốc gia
     */
    public function update($id)
    {
        $country = $this->countryModel->find($id);
        if (!$country) {
            return redirect()->to('/admin/countries')
                ->with('error', 'Quốc gia không tồn tại.');
        }

        $rules = [
            'name' => 'required|min_length[2]|max_length[100]',
            'slug' => 'required|min_length[2]|max_length[100]|is_unique[countries.slug,id,' . $id . ']',
            'description' => 'permit_empty'
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $data = [
            'name' => $this->request->getPost('name'),
            'slug' => $this->request->getPost('slug'),
            'description' => $this->request->getPost('description')
        ];

        // Xử lý upload ảnh cờ mới nếu có
        $flagImage = $this->request->getFile('flag_image');
        if ($flagImage && $flagImage->isValid() && !$flagImage->hasMoved()) {
            $newName = $flagImage->getRandomName();
            $flagImage->move(FCPATH . 'uploads/flags', $newName);

            // Xóa ảnh cũ nếu có
            if (!empty($country['flag_image']) && file_exists(FCPATH . $country['flag_image'])) {
                unlink(FCPATH . $country['flag_image']);
            }

            $data['flag_image'] = 'uploads/flags/' . $newName;
        }

        $this->countryModel->update($id, $data);

        // Ghi log
        $this->logModel->insert([
            'level' => 'info',
            'message' => 'Quốc gia đã được cập nhật: ' . $data['name'],
            'context' => json_encode([
                'user_id' => session()->get('user')['id'] ?? null,
                'country_id' => $id,
                'ip_address' => $this->request->getIPAddress()
            ])
        ]);

        return redirect()->to('/admin/countries')
            ->with('success', 'Quốc gia đã được cập nhật thành công.');
    }

    /**
     * Xử lý xóa quốc gia
     */
    public function delete($id)
    {
        $country = $this->countryModel->find($id);
        if (!$country) {
            return redirect()->to('/admin/countries')
                ->with('error', 'Quốc gia không tồn tại.');
        }

        // Xóa ảnh cờ nếu có
        if (!empty($country['flag_image']) && file_exists(FCPATH . $country['flag_image'])) {
            unlink(FCPATH . $country['flag_image']);
        }

        $this->countryModel->delete($id);

        // Ghi log
        $this->logModel->insert([
            'level' => 'warning',
            'message' => 'Quốc gia đã bị xóa: ' . $country['name'],
            'context' => json_encode([
                'user_id' => session()->get('user')['id'] ?? null,
                'country_id' => $id,
                'ip_address' => $this->request->getIPAddress()
            ])
        ]);

        return redirect()->to('/admin/countries')
            ->with('success', 'Quốc gia đã được xóa thành công.');
    }
}
