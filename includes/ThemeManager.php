<?php

class ThemeManager {
    private $db;
    private $themesPath;

    public function __construct($db) {
        $this->db = $db;
        $this->themesPath = BASE_PATH . '/themes';
    }

    public function getActiveTheme() {
        global $settings;
        return $settings->get('site_theme', 'modern');
    }

    public function listThemes() {
        $themes = [];
        $dirs = array_filter(glob($this->themesPath . '/*'), 'is_dir');

        foreach ($dirs as $dir) {
            $slug = basename($dir);
            $metadataFile = $dir . '/theme.json';
            
            if (file_exists($metadataFile)) {
                $metadata = json_decode(file_get_contents($metadataFile), true);
                if ($metadata) {
                    $metadata['slug'] = $slug;
                    $metadata['active'] = ($slug === $this->getActiveTheme());
                    $themes[] = $metadata;
                }
            }
        }
        return $themes;
    }

    public function activateTheme($slug) {
        if (!is_dir($this->themesPath . '/' . $slug)) {
            return false;
        }
        
        global $settings;
        $settings->set('site_theme', $slug);
        return true;
    }

    public function getThemeAsset($file) {
        $theme = $this->getActiveTheme();
        $path = 'themes/' . $theme . '/' . $file;
        $full = BASE_PATH . '/' . $path;
        if (file_exists($full)) {
            // Cache-busting : la version change dès que le fichier est modifié
            return SITE_URL . '/' . $path . '?v=' . @filemtime($full);
        }
        // Fallback to core if file doesn't exist in theme
        $coreFull = BASE_PATH . '/public/' . $file;
        $v = file_exists($coreFull) ? '?v=' . @filemtime($coreFull) : '';
        return SITE_URL . '/public/' . $file . $v;
    }

    public function getTemplate($name) {
        $theme = $this->getActiveTheme();
        $themeTemplate = BASE_PATH . '/themes/' . $theme . '/' . $name . '.php';
        if (file_exists($themeTemplate)) {
            return $themeTemplate;
        }
        
        $coreTemplate = BASE_PATH . '/templates/' . $name . '.php';
        if (file_exists($coreTemplate)) {
            return $coreTemplate;
        }

        return false;
    }

    public function importTheme($zipPath) {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) === TRUE) {
            $zip->extractTo($this->themesPath);
            $zip->close();
            return true;
        }
        return false;
    }
}
