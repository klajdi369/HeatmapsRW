<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\HeatmapsRW;

use Piwik\Menu\MenuReporting;
use Piwik\Piwik;

class Menu extends \Piwik\Plugin\Menu
{
    public function configureReportingMenu(MenuReporting $menu)
    {
        $idSite = \Piwik\Common::getRequestVar('idSite', false, 'int');
        
        if (!empty($idSite) && Piwik::isUserHasViewAccess($idSite)) {
            $menu->addVisitorsItem(
                'Heatmaps',
                array(
                    'module' => 'HeatmapsRW',
                    'action' => 'index'
                ),
                30
            );
        }
    }
}