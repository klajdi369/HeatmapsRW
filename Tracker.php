<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\HeatmapsRW;

use Piwik\Common;
use Piwik\Db;
use Piwik\Date;

class Tracker
{
    public function processHeatmapData($idSite, $data)
    {
        if (!$data || !is_array($data)) {
            return false;
        }

        $table = Common::prefixTable('heatmap_events');
        
        foreach ($data as $event) {
            if (!isset($event['url']) || !isset($event['type'])) {
                continue;
            }
            
            $bind = array(
                $idSite,
                null, // idvisit - will be populated if available
                Date::now()->getDatetime(),
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
        
        return true;
    }
}