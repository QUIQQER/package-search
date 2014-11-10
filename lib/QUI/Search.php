<?php

/**
 * This file contains QUI\Search
 */

namespace QUI;

use QUI\Projects\Project;

/**
 * Hauptsuche
 * @author www.pcsg.de (Henning Leutz)
 */
class Search
{
    /**
     * quick search table
     * @var String
     */
    const tableSearchQuick = 'searchQuick';

    /**
     * fulltext search table
     * @var String
     */
    const tableSearchFull = 'searchFull';

    /**
     *
     * @param \QUI\Projects\Project $Project
     */
    public function createFulltextSearch(Project $Project)
    {
        $list = $Project->getSitesIds();

        foreach ( $list as $id ) {

        }
    }

    /**
     *
     * @param \QUI\Projects\Project $Project
     */
    public function createQuickSearch(Project $Project)
    {

    }
}
