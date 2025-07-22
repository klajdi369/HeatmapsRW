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
    /**
     * @see Plugin::registerEvents
     */
    public function registerEvents()
    {
        return array(
            'AssetManager.getJavaScriptFiles' => 'getJsFiles',
            'AssetManager.getStylesheetFiles' => 'getStylesheetFiles',
            'Tracker.newVisitorInformation' => 'enrichVisitorInformation',
            'SitesManager.deleteSite.end' => 'deleteSiteData',
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

    public function enrichVisitorInformation(&$visitorInfo, \Piwik\Tracker\Request $request)
    {
        // Process heatmap data if present
        $heatmapData = $request->getParam('heatmap_data');
        if ($heatmapData) {
            $this->processHeatmapData($heatmapData, $request);
        }
    }

    private function processHeatmapData($data, $request)
    {
        $idSite = $request->getIdSite();
        $idVisit = $request->getVisitor()->getVisitorColumn('idvisit');
        
        $decodedData = json_decode($data, true);
        if (!$decodedData || !is_array($decodedData)) {
            return;
        }

        $table = Common::prefixTable('heatmap_events');
        
        foreach ($decodedData as $event) {
            if (!isset($event['url']) || !isset($event['type'])) {
                continue;
            }
            
            $bind = array(
                $idSite,
                $idVisit ?: null,
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
                // Log error but don't break tracking
                Common::printDebug("HeatmapsRW: Error saving event - " . $e->getMessage());
            }
        }
    }

    public function deleteSiteData($idSite)
    {
        $table = Common::prefixTable('heatmap_events');
        Db::deleteAllRows($table, "WHERE idsite = ?", array($idSite));
    }

    public function install()
    {
        $this->createTables();
    }

    public function uninstall()
    {
        $this->dropTables();
    }

    public function activate()
    {
        $this->createTables();
    }

    public function deactivate()
    {
        // Keep data on deactivation
    }

    private function createTables()
    {
        $table = Common::prefixTable('heatmap_events');
        
        $sql = "CREATE TABLE IF NOT EXISTS `$table` (
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
                INDEX idx_site_url (`idsite`, `url`(255)),
                INDEX idx_time (`server_time`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            
            Db::exec($sql);
    }

    private function dropTables()
    {
        Db::dropTables(Common::prefixTable('heatmap_events'));
    }
}