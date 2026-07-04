<?php

class LanguageManager {
    private $db;
    private $currentLang;
    private $translations = [];
    private $fallback = [];   // chaîne de repli : en puis fr
    private $availableLangs = ['fr', 'en', 'ar', 'es', 'ru', 'zh'];

    public function __construct($db) {
        $this->db = $db;
        $this->initLanguage();
    }

    private function initLanguage() {
        // 1. URL parameter (overrides everything)
        if (isset($_GET['lang']) && in_array($_GET['lang'], $this->availableLangs)) {
            $this->currentLang = $_GET['lang'];
            $_SESSION['lang'] = $this->currentLang;
            setcookie('lang', $this->currentLang, time() + (86400 * 30), "/"); // 30 days
        } 
        // 2. Session
        elseif (isset($_SESSION['lang']) && in_array($_SESSION['lang'], $this->availableLangs)) {
            $this->currentLang = $_SESSION['lang'];
        } 
        // 3. Cookie
        elseif (isset($_COOKIE['lang']) && in_array($_COOKIE['lang'], $this->availableLangs)) {
            $this->currentLang = $_COOKIE['lang'];
            $_SESSION['lang'] = $this->currentLang;
        } 
        // 4. Default
        else {
            $this->currentLang = 'fr';
        }

        $this->loadTranslations();
    }

    private function loadTranslations() {
        $file = BASE_PATH . '/lang/' . $this->currentLang . '.php';
        if (file_exists($file)) {
            $this->translations = require $file;
        }

        // Chaîne de repli pour les clés non traduites dans la langue courante :
        // on charge l'anglais puis le français (sans écraser les clés déjà présentes).
        foreach (['en', 'fr'] as $fb) {
            if ($fb === $this->currentLang) continue;
            $fbFile = BASE_PATH . '/lang/' . $fb . '.php';
            if (file_exists($fbFile)) {
                $data = require $fbFile;
                if (is_array($data)) {
                    // les langues déjà ajoutées priment (en avant fr)
                    $this->fallback = $this->fallback + $data;
                }
            }
        }

        if (!is_array($this->translations)) $this->translations = [];
    }

    // Dernier recours : transforme une clé en libellé lisible.
    private function humanize($key) {
        $label = preg_replace('/^(admin|js)_/', '', $key);
        $label = str_replace(['_', '-'], ' ', $label);
        return ucfirst(trim($label));
    }

    public function getCurrent() {
        return $this->currentLang;
    }

    public function get($key) {
        // 1. langue courante  2. repli (en → fr)  3. libellé humanisé
        if (isset($this->translations[$key]) && $this->translations[$key] !== '') {
            return $this->translations[$key];
        }
        if (isset($this->fallback[$key]) && $this->fallback[$key] !== '') {
            return $this->fallback[$key];
        }
        return $this->humanize($key);
    }

    public function getAvailableLanguages() {
        return [
            'fr' => ['name' => 'Français', 'flag' => 'https://flagcdn.com/w40/fr.png'],
            'en' => ['name' => 'English', 'flag' => 'https://flagcdn.com/w40/gb.png'],
            'ar' => ['name' => 'العربية', 'flag' => 'https://flagcdn.com/w40/sa.png'],
            'es' => ['name' => 'Español', 'flag' => 'https://flagcdn.com/w40/es.png'],
            'ru' => ['name' => 'Русский', 'flag' => 'https://flagcdn.com/w40/ru.png'],
            'zh' => ['name' => '中文', 'flag' => 'https://flagcdn.com/w40/cn.png']
        ];
    }

    public function isRTL() {
        return $this->currentLang === 'ar';
    }
}
