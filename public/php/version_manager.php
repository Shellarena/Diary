<?php
/**
 * Version Management System
 * Handles version display and update checking
 */

class VersionManager {
    private $localVersionFile;
    private $repoUrl;
    private $owner;
    private $repo;
    
    public function __construct() {
        $this->localVersionFile = dirname(__DIR__) . '/version.json';
        $this->owner = 'Shellarena';
        $this->repo = 'Diary';
        // GitHub API URL für Tags/Releases
        $this->repoUrl = "https://api.github.com/repos/{$this->owner}/{$this->repo}";
    }
    
    /**
     * Get current local version
     */
    public function getCurrentVersion() {
        if (!file_exists($this->localVersionFile)) {
            return [
                'version' => '1.0.0',
                'release_date' => date('Y-m-d'),
                'description' => 'Initial version'
            ];
        }
        
        $content = file_get_contents($this->localVersionFile);
        $versionData = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'version' => '1.0.0',
                'release_date' => date('Y-m-d'),
                'description' => 'Initial version'
            ];
        }
        
        return $versionData;
    }
    
    /**
     * Get latest version from GitHub repository
     */
    public function getLatestVersionFromGit() {
        try {
            // Versuche zuerst die Releases API
            $url = $this->repoUrl . '/releases/latest';
            $context = stream_context_create([
                'http' => [
                    'header' => [
                        'User-Agent: Diary-App/1.0',
                        'Accept: application/vnd.github.v3+json'
                    ],
                    'timeout' => 10
                ]
            ]);
            
            $response = @file_get_contents($url, false, $context);
            
            if ($response !== false) {
                $data = json_decode($response, true);
                if (isset($data['tag_name'])) {
                    return [
                        'version' => ltrim($data['tag_name'], 'v'),
                        'release_date' => substr($data['published_at'], 0, 10),
                        'description' => $data['name'] ?? 'Release',
                        'url' => $data['html_url'] ?? ''
                    ];
                }
            }
            
            // Fallback: Versuche Tags API
            $url = $this->repoUrl . '/tags';
            $response = @file_get_contents($url, false, $context);
            
            if ($response !== false) {
                $tags = json_decode($response, true);
                if (!empty($tags) && is_array($tags)) {
                    $latestTag = $tags[0];
                    return [
                        'version' => ltrim($latestTag['name'], 'v'),
                        'release_date' => date('Y-m-d'),
                        'description' => 'Latest tag',
                        'url' => "https://github.com/{$this->owner}/{$this->repo}/releases/tag/{$latestTag['name']}"
                    ];
                }
            }
            
        } catch (Exception $e) {
            error_log("Version check error: " . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Compare two version strings
     * Returns: -1 if v1 < v2, 0 if equal, 1 if v1 > v2
     */
    public function compareVersions($version1, $version2) {
        return version_compare($version1, $version2);
    }
    
    /**
     * Check if there's a newer version available
     */
    public function checkForUpdates() {
        $currentVersion = $this->getCurrentVersion();
        $latestVersion = $this->getLatestVersionFromGit();
        
        if ($latestVersion === null) {
            return [
                'hasUpdate' => false,
                'current' => $currentVersion,
                'latest' => null,
                'error' => 'Could not check for updates'
            ];
        }
        
        $hasUpdate = $this->compareVersions($currentVersion['version'], $latestVersion['version']) < 0;
        
        return [
            'hasUpdate' => $hasUpdate,
            'current' => $currentVersion,
            'latest' => $latestVersion,
            'error' => null
        ];
    }
    
    /**
     * Get version info for display
     */
    public function getVersionInfo() {
        $updateCheck = $this->checkForUpdates();
        
        return [
            'currentVersion' => $updateCheck['current']['version'],
            'hasUpdate' => $updateCheck['hasUpdate'],
            'latestVersion' => $updateCheck['latest']['version'] ?? null,
            'updateUrl' => $updateCheck['latest']['url'] ?? null,
            'error' => $updateCheck['error']
        ];
    }
}

// API Endpoint für AJAX-Aufrufe
if (isset($_GET['action']) && $_GET['action'] === 'check_version') {
    header('Content-Type: application/json');
    
    $versionManager = new VersionManager();
    $versionInfo = $versionManager->getVersionInfo();
    
    echo json_encode($versionInfo);
    exit;
}