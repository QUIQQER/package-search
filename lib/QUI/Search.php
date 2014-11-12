<?php

/**
 * This file contains QUI\Search
 */

namespace QUI;

use QUI\Projects\Project;
use QUI\Projects\Site;
use QUI\Projects\Site\Edit as SiteEdit;
use QUI\System\Log;

use QUI\Search\Fulltext;
use QUI\Search\Quicksearch;

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
     * Create the fulltext search table for the Project
     * Exceutes events and insert the standard
     *
     * @param \QUI\Projects\Project $Project
     */
    public function createFulltextSearch(Project $Project)
    {
        $list = $Project->getSitesIds(array(
            'active'  => 1
        ));

        $Fulltext = new Fulltext();

        $Fulltext->clearSearchTable( $Project );

        foreach ( $list as $siteParams )
        {
            try
            {
                $siteId = (int)$siteParams['id'];
                $Site   = new SiteEdit( $Project, (int)$siteId );

                if ( !$Site->getAttribute('active') ) {
                    continue;
                }

                if ( $Site->getAttribute('deleted') ) {
                    continue;
                }


                $Fulltext->addEntry($Project, $siteId, array(
                    'name'  => $Site->getAttribute('name'),
                    'title' => $Site->getAttribute('title'),
                    'short' => $Site->getAttribute('short'),
                    'data'  => $Site->getAttribute('content')
                ));

            } catch ( \QUI\Exception $Exception )
            {
                Log::writeException( $Exception );
            }
        }


        $Fulltext->search( 'bank' , $Project );
    }

    /**
     *
     * @param \QUI\Projects\Project $Project
     */
    public function createQuickSearch(Project $Project)
    {

    }
}
