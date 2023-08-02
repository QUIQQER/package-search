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
 * @author www.pcsg.de (Patrick Müller)
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
        $Search = new Search();

        $Search->createFulltextSearch($Project);
        $Search->createQuicksearch($Project);
    }

    /**
     * Create search database for all projects and all languages
     *
     * @param array $params
     * @param \QUI\Cron\Manager $CronManager
     * @return void
     */
    public static function createSearchDatabaseAllProjects($params, $CronManager)
    {
        $projects = QUI::getProjectManager()->getProjects();
        $Search = new Search();

        foreach ($projects as $project) {
            $Project = QUI::getProject($project);

            foreach ($Project->getLanguages() as $language) {
                $SearchProject = QUI::getProject($project, $language);

                $Search->createFulltextSearch($SearchProject);
                $Search->createQuicksearch($SearchProject);
            }
        }
    }
}
