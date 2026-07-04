<?php

class Settings {
    private $db;
    private $settings = [];

    public function __construct($db) {
        $this->db = $db;
        $this->loadSettings();
    }

    private function loadSettings() {
        try {
            $stmt = $this->db->query("SELECT setting_key, setting_value FROM settings");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $this->settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (PDOException $e) {
            // Silently fail if table doesn't exist (e.g. during installation)
        }
    }

    public function get($key, $default = null) {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }

    public function set($key, $value, $description = null) {
        $this->settings[$key] = $value;
        $stmt = $this->db->prepare("INSERT INTO settings (setting_key, setting_value, description) 
                                   VALUES (?, ?, ?) 
                                   ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = CURRENT_TIMESTAMP");
        return $stmt->execute([$key, $value, $description, $value]);
    }

    public function getAll() {
        return $this->settings;
    }
}
