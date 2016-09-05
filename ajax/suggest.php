<?php

/**
 * Delete the permalink
 *
 * @param string $project - project data
 * @param string $search - search string
 * @param string $params - search params
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_search_ajax_suggest',
    function ($project, $search, $params) {
        $Project  = QUI::getProjectManager()->decode($project);
        $Fulltext = new QUI\Search\Quicksearch();

        return $Fulltext->search($search, $Project, array(
            'limit' => 10
        ));
    },
    array('project', 'search', 'params')
);
