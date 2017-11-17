<?php

use QUI\Search\Controls\Search;
use QUI\Utils\Security\Orthos;

/**
 * Get Project main Search Site
 *
 * @param string $searchParams - search parameters
 * @return array - search result data
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_search_ajax_getSearchSiteUrl',
    function ($project, $getParams) {
        $getParams = json_decode($getParams, true);

        $Project    = QUI::getProjectManager()->decode($project);
        $SearchSite = $Project->get(8081); // @todo korrekte Seite holen

        // set default search params
        $defaultSearchParams = array(
            'quiqqer.settings.search.list.max'             => 'max',
            'quiqqer.settings.search.list.fields'          => 'searchFields',
            'quiqqer.settings.search.list.fields.selected' => 'fieldConstraints',
        );

        foreach ($defaultSearchParams as $siteParam => $searchParam) {
            if (isset($getParams[$searchParam])) {
                continue;
            }

            if ($SearchSite->getAttribute($siteParam)) {
                $attr = $SearchSite->getAttribute($siteParam);

                if (is_array($attr)) {
                    $attr = implode(',', $attr);
                }

                $getParams[$searchParam] = $attr;
            }
        }

        return $SearchSite->getUrlRewritten(array(), $getParams);
    },
    array('project', 'getParams')
);
