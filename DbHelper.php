<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\HeatmapsRW;

use Piwik\Db;

class DbHelper
{
    public static function getTablesInstalled()
    {
        $allTables = Db::fetchAll("SHOW TABLES");
        $tables = array();
        
        foreach ($allTables as $table) {
            $tables[] = reset($table);
        }
        
        return $tables;
    }
}