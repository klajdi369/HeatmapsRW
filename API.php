<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\HeatmapsRW;

use Piwik\Archive;
use Piwik\Common;
use Piwik\DataTable;
use Piwik\Db;
use Piwik\Piwik;
use Piwik\Site;
use Piwik\Date;

class API extends \Piwik\Plugin\API
{
    /**
     * Get heatmap data for a specific URL
     *
     * @param int $idSite
     * @param string $period
     * @param string $date
     * @param string $url
     * @param string $eventType
     * @return array
     */
    public function getHeatmapData($idSite, $period, $date, $url, $eventType = 'all')
    {
        Piwik::checkUserHasViewAccess($idSite);
        
        $table = Common::prefixTable('heatmap_events');
        $sql = "SELECT x_position, y_position, viewport_width, viewport_height, 
                       page_width, page_height, element_selector, COUNT(*) as count
                FROM $table
                WHERE idsite = ? AND url = ?";
        
        $bind = array($idSite, $url);
        
        if ($eventType !== 'all') {
            $sql .= " AND event_type = ?";
            $bind[] = $eventType;
        }
        
        // Add date filtering
        list($startDate, $endDate) = $this->getDateRange($idSite, $period, $date);
        $sql .= " AND server_time >= ? AND server_time <= ?";
        $bind[] = $startDate;
        $bind[] = $endDate;
        
        $sql .= " GROUP BY x_position, y_position, viewport_width, viewport_height
                  ORDER BY count DESC";
        
        $result = Db::fetchAll($sql, $bind);
        
        return $this->normalizeHeatmapData($result);
    }
    
    /**
     * Get top clicked elements
     *
     * @param int $idSite
     * @param string $period
     * @param string $date
     * @param string $url
     * @param int $limit
     * @return array
     */
    public function getTopClickedElements($idSite, $period, $date, $url, $limit = 50)
    {
        Piwik::checkUserHasViewAccess($idSite);
        
        list($startDate, $endDate) = $this->getDateRange($idSite, $period, $date);
        
        $table = Common::prefixTable('heatmap_events');
        $sql = "SELECT element_selector, element_text, COUNT(*) as clicks
                FROM $table
                WHERE idsite = ? AND url = ? AND event_type = 'click'
                      AND server_time >= ? AND server_time <= ?
                      AND element_selector IS NOT NULL
                GROUP BY element_selector, element_text
                ORDER BY clicks DESC
                LIMIT " . intval($limit);
        
        $bind = array($idSite, $url, $startDate, $endDate);
        
        return Db::fetchAll($sql, $bind);
    }
    
    /**
     * Get URLs with heatmap data
     *
     * @param int $idSite
     * @param string $period
     * @param string $date
     * @param int $limit
     * @return array
     */
    public function getUrlsWithHeatmapData($idSite, $period, $date, $limit = 100)
    {
        Piwik::checkUserHasViewAccess($idSite);
        
        list($startDate, $endDate) = $this->getDateRange($idSite, $period, $date);
        
        $table = Common::prefixTable('heatmap_events');
        $sql = "SELECT url, COUNT(DISTINCT idvisit) as visits, COUNT(*) as events
                FROM $table
                WHERE idsite = ? AND server_time >= ? AND server_time <= ?
                GROUP BY url
                ORDER BY visits DESC
                LIMIT " . intval($limit);
        
        $bind = array($idSite, $startDate, $endDate);
        
        return Db::fetchAll($sql, $bind);
    }
    
    private function getDateRange($idSite, $period, $date)
    {
        $timezone = Site::getTimezoneFor($idSite);
        $dateStart = Date::factory($date, $timezone);
        
        if ($period == 'day') {
            $dateEnd = $dateStart;
        } elseif ($period == 'week') {
            $dateEnd = $dateStart->addDay(6);
        } elseif ($period == 'month') {
            $dateEnd = $dateStart->addMonth(1)->subDay(1);
        } elseif ($period == 'year') {
            $dateEnd = $dateStart->addYear(1)->subDay(1);
        } else {
            // Range period
            $dates = explode(',', $date);
            if (count($dates) == 2) {
                $dateEnd = Date::factory($dates[1], $timezone);
            } else {
                $dateEnd = $dateStart;
            }
        }
        
        return array(
            $dateStart->toString('Y-m-d 00:00:00'),
            $dateEnd->toString('Y-m-d 23:59:59')
        );
    }
    
    private function normalizeHeatmapData($data)
    {
        $normalized = array();
        
        foreach ($data as $point) {
            // Group by viewport size for responsive normalization
            $viewportKey = $point['viewport_width'] . 'x' . $point['viewport_height'];
            
            if (!isset($normalized[$viewportKey])) {
                $normalized[$viewportKey] = array(
                    'viewport' => array(
                        'width' => $point['viewport_width'],
                        'height' => $point['viewport_height']
                    ),
                    'points' => array()
                );
            }
            
            $normalized[$viewportKey]['points'][] = array(
                'x' => $point['x_position'],
                'y' => $point['y_position'],
                'value' => $point['count']
            );
        }
        
        return array_values($normalized);
    }
}