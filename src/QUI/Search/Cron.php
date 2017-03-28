<?php

/**
 * This file contains \QUI\Search\Cron
 */

namespace QUI\Search;

use QUI;
use QUI\Search;

/**
 * Search cron
 *
 * @author www.pcsg.de (Henning Leutz)
 * @todo search as jobs
 */
class Cron
{
    /**
     * Cron : create search database
     *
     * @param array $params
     * @param \QUI\Cron\Manager $CronManager
     */
    public static function createSearchDatabase($params, $CronManager)
    {
        if (!isset($params['project'])) {
            return;
        }

        if (!isset($params['lang'])) {
            return;
        }

        $Project = QUI::getProject($params['project'], $params['lang']);
        $Search  = new Search();

        $Search->createFulltextSearch($Project);
        $Search->createQuicksearch($Project);
    }
}
