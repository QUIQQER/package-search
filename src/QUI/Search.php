<?php

/**
 * This file contains QUI\Search
 */

namespace QUI;

use QUI;
use QUI\Projects\Project;
use QUI\Projects\Site;
use QUI\Projects\Site\Edit as SiteEdit;
use QUI\System\Log;

use QUI\Search\Fulltext;
use QUI\Search\Quicksearch;

/**
 * Hauptsuche
 *
 * @author www.pcsg.de (Henning Leutz)
 */
class Search
{
    /**
     * quick search table
     *
     * @var string
     */
    const TABLE_SEARCH_QUICK = 'searchQuick';

    /**
     * fulltext search table
     *
     * @var string
     */
    const TABLE_SEARCH_FULL = 'searchFull';

    /**
     * Create the fulltext search table for the Project
     * Excecutes events and insert the standard
     *
     * @param \QUI\Projects\Project $Project
     */
    public function createFulltextSearch(Project $Project)
    {
        $Fulltext = new Fulltext();
        $Fulltext->clearSearchTable($Project); // @todo muss raus

        QUI::getEvents()->fireEvent(
            'searchFulltextCreate',
            array($Fulltext, $Project)
        );
    }

    /**
     * Create the quicksearch search table for the Project
     * Excecutes events and insert the standard
     *
     * @param \QUI\Projects\Project $Project
     */
    public function createQuicksearch(Project $Project)
    {
        $list = $Project->getSitesIds(array(
            'where' => array(
                'active' => 1
            )
        ));

        $Quicksearch = new Quicksearch();
        $Quicksearch->clearSearchTable($Project);

        foreach ($list as $siteParams) {
            try {
                set_time_limit(0);

                $siteId = (int)$siteParams['id'];
                $Site   = new SiteEdit($Project, (int)$siteId);

                if (!$Site->getAttribute('active')) {
                    continue;
                }

                if ($Site->getAttribute('deleted')) {
                    continue;
                }

                if ($Site->getAttribute('quiqqer.settings.search.not.indexed')) {
                    continue;
                }

                $Quicksearch->setEntries($Project, $siteId, array(
                    $Site->getAttribute('name') . ' ' . $Site->getAttribute('title'),
                ));
            } catch (QUI\Exception $Exception) {
                Log::writeException($Exception);
            }
        }

        QUI::getEvents()->fireEvent(
            'searchQuicksearchCreate',
            array($Quicksearch, $Project)
        );
    }

    /**
     * Setup, create the extra fields
     */
    public static function setup()
    {
        QUI\Cache\Manager::clear('quiqqer/search');

        $Table    = QUI::getDataBase()->table();
        $Manager  = QUI::getProjectManager();
        $projects = $Manager->getProjects(true);

        $fieldList = Search\Fulltext::getFieldList();
        $fields    = array();
        $fulltext  = array();
        $index     = array();

        foreach ($fieldList as $fieldEntry) {
            $fields[$fieldEntry['field']] = $fieldEntry['type'];

            if ($fieldEntry['fulltext']) {
                $fulltext[] = array(
                    'field'   => $fieldEntry['field'],
                    'package' => $fieldEntry['package']
                );
            } else {
                $index[] = array(
                    'field'   => $fieldEntry['field'],
                    'package' => $fieldEntry['package']
                );
            }
        }

        foreach ($projects as $_Project) {
            /* @var $_Project Project */
            $name  = $_Project->getName();
            $langs = $_Project->getLanguages();

            foreach ($langs as $lang) {
                $Project = $Manager->getProject($name, $lang);

                $table = QUI::getDBProjectTableName(
                    self::TABLE_SEARCH_FULL,
                    $Project
                );

                $Table->addColumn($table, $fields);

                foreach ($fulltext as $field) {
                    $Table->setFulltext($table, $field['field']);
                }

                foreach ($index as $field) {
                    try {
                        $Table->setIndex($table, $field['field']);
                    } catch (\Exception $Exception) {
                        QUI\System\Log::addWarning(
                            self::class . ' :: setup() -> Could not create Index for Fulltext'
                            . ' search column "' . $field['field'] . '" (Package: ' . $field['package'] . ').'
                            . ' The search column may be needed to be defined as "fulltext" for this to work.'
                            . ' Error Message: ' . $Exception->getMessage()
                        );
                    }
                }
            }
        }
    }

    /**
     * Events
     */

    /**
     * event : on site deactivate
     *
     * @param \QUI\Projects\Site $Site
     */
    public static function onSiteDeactivate($Site)
    {
        /* @param $Site \QUI\Projects\Site */
        $Project = $Site->getProject();

        $tableSearchFull = QUI::getDBProjectTableName(
            self::TABLE_SEARCH_FULL,
            $Project
        );

        $tableQuicksearch = QUI::getDBProjectTableName(
            self::TABLE_SEARCH_QUICK,
            $Project
        );

        // remove entries from tables
        QUI::getDataBase()->delete($tableSearchFull, array(
            'siteId' => $Site->getId()
        ));

        QUI::getDataBase()->delete($tableQuicksearch, array(
            'siteId' => $Site->getId()
        ));
    }

    /**
     * event : on site activate / change
     *
     * @param \QUI\Projects\Site $Site
     */
    public static function onSiteChange($Site)
    {
        // check default settings
        if ($Site->getAttribute('type') === 'quiqqer/search:types/search') {
            self::setSiteDefaultSettings($Site);
        }

        if (!$Site->getAttribute('active')) {
            return;
        }

        if ($Site->getAttribute('deleted')) {
            return;
        }

        if ($Site->getAttribute('quiqqer.settings.search.not.indexed')) {
            return;
        }

        /* @param $Site \QUI\Projects\Site */
        $Project = $Site->getProject();

        $Quicksearch = new Quicksearch();
        $Fulltext    = new Fulltext();

        $e_date = $Site->getAttribute('e_date');
        $e_date = strtotime($e_date);

        if (!$e_date) {
            $e_date = 0;
        }

        $c_date = $Site->getAttribute('c_date');
        $c_date = strtotime($c_date);

        if (!$c_date) {
            $c_date = 0;
        }

        // Fulltext
        $Fulltext->setEntry($Project, $Site->getId(), array(
            'name'   => $Site->getAttribute('name'),
            'title'  => $Site->getAttribute('title'),
            'short'  => $Site->getAttribute('short'),
            'data'   => $Site->getAttribute('content'),
            'icon'   => $Site->getAttribute('image_site'),
            'e_date' => $e_date,
            'c_date' => $c_date
        ));

        // Quicksearch
        $Quicksearch->setEntries($Project, $Site->getId(), array(
//            $Site->getAttribute('name'),
            $Site->getAttribute('title')
        ));
    }

    /**
     * Set default search params for search sites
     *
     * @param Site $Site
     * @return void
     *
     * @throws QUI\Exception
     */
    protected static function setSiteDefaultSettings(Site $Site)
    {
        $fields         = $Site->getAttribute('quiqqer.settings.search.list.fields');
        $selectedFields = $Site->getAttribute('quiqqer.settings.search.list.fields.selected');

        if (!empty($fields) || !empty($selectedFields)) {
            return;
        }

        $selectedFields = array('name', 'title', 'short', 'data');
        $Site           = $Site->getEdit();

        $Site->setAttribute('quiqqer.settings.search.list.fields', array());
        $Site->setAttribute('quiqqer.settings.search.list.fields.selected', $selectedFields);

        $Site->save();
    }

    /**
     * event onTemplateGetHeader
     *
     * @param QUI\Template $Template - Template object
     */
    public static function onTemplateGetHeader(QUI\Template $Template)
    {
        $Project = $Template->getAttribute('Project');

        if (!is_object($Project)) {
            $Project = QUI::getProjectManager()->get();
        }

        $result = $Project->getSites(array(
            'where' => array(
                'type' => 'quiqqer/search:types/search'
            ),
            'limit' => 1
        ));

        if (!isset($result[0])) {
            return;
        }

        $host = $Project->getVHost(true, true);

        /* @var $SearchSite QUI\Projects\Site */
        $SearchSite = $result[0];
        $searchUrl  = $SearchSite->getUrlRewritten();
        $start      = $Project->firstChild()->getUrlRewritten();

        if (strpos($searchUrl, 'http') !== 0) {
            $searchUrl = $host . $searchUrl;
            $start     = $host . $start;
        }

        $Template->extendHeader(
            '
            <script type="application/ld+json">
            {
                "@context": "http://schema.org",
                "@type": "WebSite",
                "url": "' . $start . '",
                "potentialAction": {
                    "@type": "SearchAction",
                    "target": "' . $searchUrl . '?search={search}",
                    "query-input": "required name=search"
                }
            }
            </script>
            '
        );
    }
}
