<?php

/**
 * This file contains QUI\BackendSearch\Search
 */

namespace QUI\Search;

use QUI;
use QUI\Package\Package;

/**
 * Class Events
 */
class Events
{
    /**
     * QUIQQER Event: onPackageSetup
     *
     * @param Package $Package
     * @return void
     *
     * @throws QUI\Exception
     */
    public static function onPackageSetup(Package $Package)
    {
        if ($Package->getName() !== 'quiqqer/search') {
            return;
        }

        $Conf        = $Package->getConfig();
        $CronManager = new QUI\Cron\Manager();
        $cronTitle   = QUI::getLocale()->get('quiqqer/search', 'cron.all_projects.title');
        $created     = $Conf->get('setup', 'cron_created');

        if (!empty($created)) {
            return;
        }

        if (!$CronManager->isCronSetUp($cronTitle)) {
            $CronManager->add($cronTitle, '0', '0', '*', '*', '*');
        }

        $Conf->set('setup', 'cron_created', 1);
        $Conf->save();
    }
}
