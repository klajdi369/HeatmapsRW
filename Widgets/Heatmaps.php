<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\HeatmapsRW\Widgets;

use Piwik\Common;
use Piwik\Site;
use Piwik\Widget\WidgetConfig;

class Heatmaps extends \Piwik\Widget\Widget
{
    public static function configure(WidgetConfig $config)
    {
        $config->setCategoryId('General_Visitors');
        $config->setSubcategoryId('HeatmapsRW_Heatmaps');
        $config->setName('HeatmapsRW_Heatmaps');
        $config->setOrder(99);
    }

    public function render()
    {
        $idSite = Common::getRequestVar('idSite', null, 'int');
        
        return $this->renderTemplate('widget', array(
            'siteName' => Site::getNameFor($idSite),
            'idSite' => $idSite
        ));
    }
}