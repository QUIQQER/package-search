<?php

/**
 * Delete the permalink
 *
 * @param String $project
 * @param String $search
 */

function package_quiqqer_search_ajax_suggest($project, $search, $params)
{
    $project = json_decode( $project, true );
    $Project = \QUI::getProject( $project['name'], $project['lang'] );

    $Fulltext = new \QUI\Search\Quicksearch();

    return $Fulltext->search($search, $Project, array(
        'limit' => 10
    ));
}

\QUI::$Ajax->register(
    'package_quiqqer_search_ajax_suggest',
    array( 'project', 'search', 'params' )
);
