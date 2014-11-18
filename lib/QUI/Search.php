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
     * Excecutes events and insert the standard
     *
     * @param \QUI\Projects\Project $Project
     */
    public function createFulltextSearch(Project $Project)
    {
        $Fulltext = new Fulltext();
        $Fulltext->clearSearchTable( $Project ); // @todo muss raus

        \QUI::getEvents()->fireEvent(
            'searchFulltextCreation',
            array( $Fulltext, $Project )
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
            'active'  => 1
        ));

        $Quicksearch = new Quicksearch();
        $Quicksearch->clearSearchTable( $Project );

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


                $Quicksearch->addEntry($Project, $siteId, array(
                    'data'  => $Site->getAttribute('name')
                ));

                $Quicksearch->addEntry($Project, $siteId, array(
                    'data'  => $Site->getAttribute('title')
                ));

            } catch ( \QUI\Exception $Exception )
            {
                Log::writeException( $Exception );
            }
        }

        \QUI::getEvents()->fireEvent(
            'searchQuicksearchCreation',
            array( $Fulltext, $Project )
        );
    }

    /**
     * Setup, create the extra fields
     */
    public static function setup()
    {
        $Table    = \QUI::getDataBase()->Table();
        $Manager  = \QUI::getProjectManager();
        $projects = $Manager->getProjects( true );

        $fieldList = \QUI\Search\Fulltext::getFieldList();
        $fields    = array();
        $fulltext  = array();
        $index     = array();

        foreach ( $fieldList as $fieldEntry )
        {
            $fields[ $fieldEntry['field'] ] = $fieldEntry['type'];

            if ( $fieldEntry['fulltext'] )
            {
                $fulltext[] = $fieldEntry['field'];
            } else
            {
                $index[] = $fieldEntry['field'];
            }
        }

        foreach ( $projects as $_Project )
        {
            $name  = $_Project->getName();
            $langs = $_Project->getAttribute('langs');

            foreach ( $langs as $lang )
            {
                $Project = $Manager->getProject( $name, $lang );
                $table   = \QUI::getDBProjectTableName( 'searchFull', $Project );

                $Table->appendFields( $table, $fields );

                foreach ( $fulltext as $field ) {
                    $Table->setFulltext( $table, $field );
                }

                foreach ( $index as $field ) {
                    $Table->setIndex( $table, $field );
                }
            }
        }
    }
}
