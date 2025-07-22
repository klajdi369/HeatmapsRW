<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\HeatmapsRW;

use Piwik\Menu\MenuAdmin;
use Piwik\Piwik;

class Menu extends \Piwik\Plugin\Menu
{
    public function configureAdminMenu(MenuAdmin $menu)
    {
        $idSite = \Piwik\Common::getRequestVar('idSite', false, 'int');
        
        if (!empty($idSite) && Piwik::isUserHasAdminAccess($idSite)) {
            $menu->addPlatformItem(
                'HeatmapsRW_Settings',
                $this->urlForDefaultAction(),
                30
            );
        }
    }
}