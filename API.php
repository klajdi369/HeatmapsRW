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
use Piwik\Piwik;

class API extends \Piwik\Plugin\API
{
    /**
     * Get heatmap data for a specific URL
     */
    public function getHeatmapData($idSite, $period = 'day', $date = 'today', $url = '', $eventType = 'click')
    {
        Piwik::checkUserHasViewAccess($idSite);
        
        $table = Common::prefixTable('heatmap_events');
        
        // Simple query for now
        $sql = "SELECT x_position, y_position, viewport_width, viewport_height, COUNT(*) as count
                FROM $table
                WHERE idsite = ? AND event_type = ?";
        
        $bind = array($idSite, $eventType);
        
        if (!empty($url)) {
            $sql .= " AND url = ?";
            $bind[] = $url;
        }
        
        $sql .= " GROUP BY x_position, y_position, viewport_width, viewport_height
                  ORDER BY count DESC
                  LIMIT 1000";
        
        $result = Db::fetchAll($sql, $bind);
        
        return $this->formatHeatmapData($result);
    }
    
    /**
     * Get top clicked elements
     */
    public function getTopClickedElements($idSite, $period = 'day', $date = 'today', $url = '', $limit = 50)
    {
        Piwik::checkUserHasViewAccess($idSite);
        
        $table = Common::prefixTable('heatmap_events');
        
        $sql = "SELECT element_selector, element_text, COUNT(*) as clicks
                FROM $table
                WHERE idsite = ? AND event_type = 'click'
                      AND element_selector IS NOT NULL";
        
        $bind = array($idSite);
        
        if (!empty($url)) {
            $sql .= " AND url = ?";
            $bind[] = $url;
        }
        
        $sql .= " GROUP BY element_selector, element_text
                  ORDER BY clicks DESC
                  LIMIT " . intval($limit);
        
        return Db::fetchAll($sql, $bind);
    }
    
    /**
     * Get URLs with heatmap data
     */
    public function getUrlsWithHeatmapData($idSite, $period = 'day', $date = 'today', $limit = 100)
    {
        Piwik::checkUserHasViewAccess($idSite);
        
        $table = Common::prefixTable('heatmap_events');
        
        $sql = "SELECT url, COUNT(*) as events, 
                       COUNT(CASE WHEN event_type = 'click' THEN 1 END) as clicks,
                       COUNT(CASE WHEN event_type = 'scroll' THEN 1 END) as scrolls
                FROM $table
                WHERE idsite = ?
                GROUP BY url
                ORDER BY events DESC
                LIMIT " . intval($limit);
        
        return Db::fetchAll($sql, array($idSite));
    }
    
    private function formatHeatmapData($data)
    {
        $formatted = array();
        $viewportGroups = array();
        
        // Group by viewport size
        foreach ($data as $point) {
            $viewportKey = $point['viewport_width'] . 'x' . $point['viewport_height'];
            
            if (!isset($viewportGroups[$viewportKey])) {
                $viewportGroups[$viewportKey] = array(
                    'viewport' => array(
                        'width' => $point['viewport_width'],
                        'height' => $point['viewport_height']
                    ),
                    'points' => array()
                );
            }
            
            $viewportGroups[$viewportKey]['points'][] = array(
                'x' => $point['x_position'],
                'y' => $point['y_position'],
                'value' => $point['count']
            );
        }
        
        return array_values($viewportGroups);
    }
}