<?php

use QUI\Search\Controls\Search;
use QUI\Utils\Security\Orthos;

/**
 * Execute search and result ChildrenList HTML
 *
 * @param string $searchParams - search parameters
 * @return array - search result data
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_search_ajax_search',
    function ($project, $siteId, $searchParams) {
        $Project = QUI::getProjectManager()->decode($project);
        $Site    = $Project->get($siteId);

    //        $searchParams         = Orthos::clearArray();
        $searchParams         = json_decode($searchParams, true);
        $searchParams['Site'] = $Site;

        $Search       = new Search($searchParams);
        $searchResult = $Search->search();

        $Output           = new \QUI\Output();
        $childrenListHtml = $Output->parse($Search->getChildrenList()->create());

        return array(
            'childrenListHtml' => $childrenListHtml,
            'sheets'           => $searchResult['sheets'],
            'count'            => $searchResult['count'],
            'more'             => $searchResult['more']
        );
    },
    array('project', 'siteId', 'searchParams')
);
