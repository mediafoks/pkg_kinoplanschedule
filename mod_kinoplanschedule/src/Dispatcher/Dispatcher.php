<?php

/**
 * @package     Vendor\Module\HelloWorld
 *
 * @copyright   Copyright (C) 2026 ks. All rights reserved.
 * @license     GNU General Public License version 2 or later;
 */

namespace Joomla\Module\KinoplanSchedule\Site\Dispatcher;

defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;
use Joomla\Module\KinoplanSchedule\Site\Helper\kinoplanscheduleHelper;

/**
 * Dispatcher class for mod_kinoplanschedule
 *
 * @since  1.1.0
 */
class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    /**
     * Returns the layout data
     *
     * @return  array
     *
     * @since   1.1.0
     */
    protected function getLayoutData(): array
    {
        $data = parent::getLayoutData();

        $helperName = 'KinoplanScheduleHelper';
        $data['list'] = $this->getHelperFactory()->getHelper($helperName)->getMovies($data['params'], $this->getApplication());

        return $data;
    }
}
