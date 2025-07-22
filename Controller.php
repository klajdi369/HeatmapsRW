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
use Piwik\View;

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
    
    public function viewer()
    {
        Piwik::checkUserHasViewAccess($this->idSite);
        
        $url = Common::getRequestVar('url', '');
        $period = Common::getRequestVar('period', 'day');
        $date = Common::getRequestVar('date', 'today');
        
        return $this->renderTemplate('viewer', array(
            'url' => $url,
            'period' => $period,
            'date' => $date,
            'idSite' => $this->idSite
        ));
    }
}