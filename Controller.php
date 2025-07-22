<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\HeatmapsRW;

use Piwik\Common;
use Piwik\Piwik;
use Piwik\Site;
use Piwik\Db;

class Controller extends \Piwik\Plugin\Controller
{
    public function index()
    {
        Piwik::checkUserHasViewAccess($this->idSite);
        
        return $this->renderTemplate('index', array(
            'siteName' => Site::getNameFor($this->idSite),
            'idSite' => $this->idSite
        ));
    }
    
    /**
     * Simple test endpoint
     */
    public function test()
    {
        // Set headers first
        header('Access-Control-Allow-Origin: *');
        header('Content-Type: application/json');
        
        echo json_encode([
            'status' => 'HeatmapsRW Plugin is working!',
            'time' => date('Y-m-d H:i:s'),
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
        ]);
        exit;
    }
    
    /**
     * Tracking endpoint for heatmap data
     */
    public function track()
    {
        // Set CORS headers FIRST, before any other code
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Content-Type: application/json');
        
        // Handle OPTIONS request
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            echo json_encode(['status' => 'options_ok']);
            exit;
        }
        
        try {
            // Basic validation
            $idSite = Common::getRequestVar('idSite', 0, 'int');
            if (!$idSite) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing idSite parameter']);
                exit;
            }
            
            // Get JSON data from POST body
            $input = file_get_contents('php://input');
            if (empty($input)) {
                http_response_code(400);
                echo json_encode(['error' => 'No data received', 'content_length' => $_SERVER['CONTENT_LENGTH'] ?? 0]);
                exit;
            }
            
            $data = json_decode($input, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Invalid JSON: ' . json_last_error_msg(),
                    'input_preview' => substr($input, 0, 100)
                ]);
                exit;
            }
            
            if (!is_array($data) || empty($data)) {
                http_response_code(400);
                echo json_encode(['error' => 'Data must be non-empty array']);
                exit;
            }
            
            // Simple database insert
            $processed = $this->saveHeatmapData($idSite, $data);
            
            echo json_encode([
                'status' => 'success',
                'processed' => $processed,
                'received_events' => count($data)
            ]);
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'error' => 'Server error: ' . $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine()
            ]);
        }
        exit;
    }
    
    /**
     * Save heatmap data to database
     */
    private function saveHeatmapData($idSite, $data)
    {
        $table = Common::prefixTable('heatmap_events');
        $processed = 0;
        
        // Ensure table exists
        $this->ensureTableExists();
        
        foreach ($data as $event) {
            // Skip invalid events
            if (!is_array($event) || empty($event['type']) || empty($event['url'])) {
                continue;
            }
            
            try {
                $sql = "INSERT INTO `$table` 
                       (idsite, server_time, url, event_type, x_position, y_position, 
                        viewport_width, viewport_height, page_width, page_height, element_selector, element_text)
                        VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $bind = [
                    (int)$idSite,
                    substr($event['url'], 0, 1000),
                    substr($event['type'], 0, 50),
                    isset($event['x']) ? (int)$event['x'] : null,
                    isset($event['y']) ? (int)$event['y'] : null,
                    isset($event['viewport_width']) ? (int)$event['viewport_width'] : null,
                    isset($event['viewport_height']) ? (int)$event['viewport_height'] : null,
                    isset($event['page_width']) ? (int)$event['page_width'] : null,
                    isset($event['page_height']) ? (int)$event['page_height'] : null,
                    isset($event['selector']) ? substr($event['selector'], 0, 500) : null,
                    isset($event['text']) ? substr($event['text'], 0, 200) : null
                ];
                
                Db::query($sql, $bind);
                $processed++;
                
            } catch (\Exception $e) {
                // Log but continue with other events
                error_log("HeatmapsRW: Failed to save event: " . $e->getMessage());
            }
        }
        
        return $processed;
    }
    
    /**
     * Ensure the heatmap_events table exists
     */
    private function ensureTableExists()
    {
        $table = Common::prefixTable('heatmap_events');
        
        try {
            // Check if table exists
            $exists = Db::fetchOne("SHOW TABLES LIKE ?", [$table]);
            
            if (!$exists) {
                // Create table
                $sql = "CREATE TABLE `$table` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `idsite` int(11) NOT NULL,
                    `idvisit` bigint(20) DEFAULT NULL,
                    `server_time` datetime NOT NULL,
                    `url` varchar(1000) NOT NULL,
                    `event_type` varchar(50) NOT NULL,
                    `x_position` int(11) DEFAULT NULL,
                    `y_position` int(11) DEFAULT NULL,
                    `viewport_width` int(11) DEFAULT NULL,
                    `viewport_height` int(11) DEFAULT NULL,
                    `page_width` int(11) DEFAULT NULL,
                    `page_height` int(11) DEFAULT NULL,
                    `element_selector` varchar(500) DEFAULT NULL,
                    `element_text` varchar(200) DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `idx_site_url` (`idsite`, `url`(255)),
                    KEY `idx_time` (`server_time`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                
                Db::exec($sql);
            }
        } catch (\Exception $e) {
            throw new \Exception("Could not create heatmap table: " . $e->getMessage());
        }
    }
}