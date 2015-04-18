<?php

/**
 * Delete the permalink
 *
 * @param string $project - project data
 * @param string $search  - search string
 * @param string $params  - search params
 *
 * @return array
 */
function package_quiqqer_search_ajax_suggest($project, $search, $params)
{
    $Project = \QUI::getProjectManager()->decode($project);
    $Fulltext = new \QUI\Search\Quicksearch();

    return $Fulltext->search($search, $Project, array(
        'limit' => 10
    ));
}

\QUI::$Ajax->register(
    'package_quiqqer_search_ajax_suggest',
    array('project', 'search', 'params')
);
