<?php
namespace App\Models;

use CodeIgniter\Model;

class SettingsModel extends Model
{
    protected $table = 'settings';
    protected $primaryKey = 'id';
    protected $allowedFields = ['id', 'value', 'description', 'updated_at'];
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $updatedField = 'updated_at';

    /**
     * Get all settings as key-value pairs
     */
    public function getAllSettings()
    {
        $settings = $this->findAll();
        $result = [];

        foreach ($settings as $setting) {
            $result[$setting['id']] = $setting['value'];
        }

        return $result;
    }

    /**
     * Get a setting value by key
     */
    public function getSetting($key, $default = '')
    {
        $setting = $this->find($key);
        return $setting ? $setting['value'] : $default;
    }

    /**
     * Save a setting
     */
    public function saveSetting($key, $value, $description = null)
    {
        $setting = $this->find($key);

        $data = [
            'id' => $key,
            'value' => $value,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($description !== null) {
            $data['description'] = $description;
        }

        if ($setting) {
            // Update existing setting
            return $this->update($key, $data);
        } else {
            // Insert new setting
            return $this->insert($data);
        }
    }

    /**
     * Save multiple settings at once
     */
    public function saveSettings($settings)
    {
        $db = \Config\Database::connect();
        $db->transStart();

        foreach ($settings as $key => $value) {
            $this->saveSetting($key, $value);
        }

        $db->transComplete();
        return $db->transStatus();
    }

    /**
     * Delete a setting
     */
    public function deleteSetting($key)
    {
        return $this->delete($key);
    }
}
