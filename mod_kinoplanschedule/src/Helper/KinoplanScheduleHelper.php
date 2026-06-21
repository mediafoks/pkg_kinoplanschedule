<?php

/**
 * @package     Vendor\Module\HelloWorld
 *
 * @copyright   Copyright (C) 2026 ks. All rights reserved.
 * @license     GNU General Public License version 2 or later;
 */

namespace Joomla\Module\KinoplanSchedule\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Database\DatabaseInterface;

/**
 * Helper for kinoplanschedule
 *
 * @since  1.1.0
 */
class KinoplanScheduleHelper
{
    /**
     * Retrieve data for the module
     *
     * @param   Registry  $params  The module parameters
     * @param   object    $app     The application
     *
     * @return  object
     *
     * @since   1.1.0
     */
    public function getMovies(Registry $params, $app): array
    {
        $cacheFile = JPATH_ROOT . '/media/kinoplanschedule_cache/schedule.json';

        clearstatcache(true, $cacheFile);

        if (!file_exists($cacheFile)) return [];

        $json = file_get_contents($cacheFile);

        if (!$json) return [];

        $movies = json_decode($json, true);

        if (!is_array($movies)) return [];

        $limit = (int) $params->get('limit', 12);

        return array_slice($movies, 0, $limit);
    }
}
