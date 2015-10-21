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
     * @var String
     */
    const tableSearchQuick = 'searchQuick';

    /**
     * fulltext search table
     *
     * @var String
     */
    const tableSearchFull = 'searchFull';

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
            'active' => 1
        ));

        $Quicksearch = new Quicksearch();
        $Quicksearch->clearSearchTable($Project);

        foreach ($list as $siteParams) {
            try {
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
                    $Site->getAttribute('name'),
                    $Site->getAttribute('title')
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
        $Table    = QUI::getDataBase()->Table();
        $Manager  = QUI::getProjectManager();
        $projects = $Manager->getProjects(true);

        $fieldList = Search\Fulltext::getFieldList();
        $fields    = array();
        $fulltext  = array();
        $index     = array();

        foreach ($fieldList as $fieldEntry) {
            $fields[$fieldEntry['field']] = $fieldEntry['type'];

            if ($fieldEntry['fulltext']) {
                $fulltext[] = $fieldEntry['field'];
            } else {
                $index[] = $fieldEntry['field'];
            }
        }

        foreach ($projects as $_Project) {
            /* @var $_Project Project */
            $name  = $_Project->getName();
            $langs = $_Project->getAttribute('langs');

            foreach ($langs as $lang) {
                $Project = $Manager->getProject($name, $lang);

                $table = QUI::getDBProjectTableName(
                    self::tableSearchFull,
                    $Project
                );

                $Table->appendFields($table, $fields);

                foreach ($fulltext as $field) {
                    $Table->setFulltext($table, $field);
                }

                foreach ($index as $field) {
                    $Table->setIndex($table, $field);
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
            self::tableSearchFull,
            $Project
        );

        $tableQuicksearch = QUI::getDBProjectTableName(
            self::tableSearchQuick,
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
        if (!$Site->getAttribute('active')) {
            return;
        }

        if ($Site->getAttribute('deleted')) {
            return;
        }


        /* @param $Site \QUI\Projects\Site */
        $Project = $Site->getProject();

        $Quicksearch = new Quicksearch();
        $Fulltext    = new Fulltext();

        // Fulltext
        $Fulltext->setEntry($Project, $Site->getId(), array(
            'name'  => $Site->getAttribute('name'),
            'title' => $Site->getAttribute('title'),
            'short' => $Site->getAttribute('short'),
            'data'  => $Site->getAttribute('content'),
            'icon'  => $Site->getAttribute('image_site')
        ));

        // Quicksearch
        $Quicksearch->setEntries($Project, $Site->getId(), array(
            $Site->getAttribute('name'),
            $Site->getAttribute('title')
        ));
    }

    /**
     * event onTemplateGetHeader
     *
     * @param Template $Template
     */
    static function onTemplateGetHeader(QUI\Template $Template)
    {
        $Template->extendHeader(
            '<script type="application/ld+json">
            {
                "@context": "http://schema.org",
              "@type": "WebSite",
              "url": "https://www.example.com/",
              "potentialAction": {
                "@type": "SearchAction",
                "target": "https://query.example.com/search?q={search_term_string}",
                "query-input": "required name=search_term_string"
              }
            }
            </script>'
        );

    }
}
