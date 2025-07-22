<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\HeatmapsRW;

use Piwik\Plugin;
use Piwik\Common;
use Piwik\Db;
use Piwik\Piwik;

class HeatmapsRW extends Plugin
{
    public function registerEvents()
    {
        return array(
            'AssetManager.getJavaScriptFiles' => 'getJsFiles',
            'AssetManager.getStylesheetFiles' => 'getStylesheetFiles',
            'Tracker.Request.getIdSite' => 'handleHeatmapTracking',
            'Translate.getClientSideTranslationKeys' => 'getClientSideTranslationKeys',
        );
    }

    public function getJsFiles(&$files)
    {
        $files[] = "plugins/HeatmapsRW/javascripts/tracker.js";
        $files[] = "plugins/HeatmapsRW/javascripts/heatmap-viewer.js";
    }

    public function getStylesheetFiles(&$files)
    {
        $files[] = "plugins/HeatmapsRW/stylesheets/heatmap.less";
    }

    public function getClientSideTranslationKeys(&$translationKeys)
    {
        $translationKeys[] = 'HeatmapsRW_Heatmaps';
        $translationKeys[] = 'HeatmapsRW_GenerateHeatmap';
        $translationKeys[] = 'HeatmapsRW_Clicks';
    }

    /**
     * Handle heatmap tracking data
     */
    public function handleHeatmapTracking(&$request)
    {
        // Check if this request contains heatmap data
        $heatmapData = Common::getRequestVar('heatmap_data', '', 'string', $_GET);
        if (empty($heatmapData)) {
            return;
        }

        // Process the heatmap data
        $this->processHeatmapData($heatmapData, $request);
    }

    private function processHeatmapData($data, $request)
    {
        try {
            $decodedData = json_decode($data, true);
            if (!$decodedData || !is_array($decodedData)) {
                return;
            }

            $idSite = $request->getIdSite();
            $table = Common::prefixTable('heatmap_events');
            
            foreach ($decodedData as $event) {
                if (!isset($event['url']) || !isset($event['type'])) {
                    continue;
                }
                
                $bind = array(
                    $idSite,
                    null, // idvisit - will be populated if available
                    date('Y-m-d H:i:s'),
                    substr($event['url'], 0, 1000),
                    substr($event['type'], 0, 50),
                    isset($event['x']) ? intval($event['x']) : null,
                    isset($event['y']) ? intval($event['y']) : null,
                    isset($event['viewport_width']) ? intval($event['viewport_width']) : null,
                    isset($event['viewport_height']) ? intval($event['viewport_height']) : null,
                    isset($event['page_width']) ? intval($event['page_width']) : null,
                    isset($event['page_height']) ? intval($event['page_height']) : null,
                    isset($event['selector']) ? substr($event['selector'], 0, 500) : null,
                    isset($event['text']) ? substr($event['text'], 0, 200) : null
                );

                $sql = "INSERT INTO $table 
                       (idsite, idvisit, server_time, url, event_type, x_position, y_position, 
                        viewport_width, viewport_height, page_width, page_height, element_selector, element_text)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                try {
                    Db::query($sql, $bind);
                } catch (\Exception $e) {
                    Common::printDebug("HeatmapsRW: Error saving event - " . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            Common::printDebug("HeatmapsRW: Error processing heatmap data - " . $e->getMessage());
        }
    }

    public function install()
    {
        try {
            $this->createTables();
        } catch (\Exception $e) {
            throw new \Exception("HeatmapsRW installation failed: " . $e->getMessage());
        }
    }

    public function uninstall()
    {
        try {
            $this->dropTables();
        } catch (\Exception $e) {
            // Don't fail uninstall if table doesn't exist
        }
    }

    public function activate()
    {
        try {
            $this->createTables();
        } catch (\Exception $e) {
            // Table might already exist, that's OK
        }
    }

    public function deactivate()
    {
        // Keep data on deactivation
    }

    private function createTables()
    {
        $table = Common::prefixTable('heatmap_events');
        
        // Check if table already exists
        try {
            $exists = Db::fetchOne("SHOW TABLES LIKE ?", array($table));
            if ($exists) {
                return; // Table already exists
            }
        } catch (\Exception $e) {
            // Continue with creation
        }
        
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
                INDEX `idx_site_url` (`idsite`, `url`(255)),
                INDEX `idx_time` (`server_time`),
                INDEX `idx_type` (`event_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            
        Db::exec($sql);
    }

    private function dropTables()
    {
        Db::dropTables(Common::prefixTable('heatmap_events'));
    }
}