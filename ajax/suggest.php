<?php

/**
 * Return the suggest html search result
 *
 * @param string $project - project data
 * @param string $search - search string
 *
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_search_ajax_suggest',
    function ($project, $search) {
        $Project     = QUI::getProjectManager()->decode($project);
        $QuickSearch = new QUI\Search\Quicksearch();

        $result = $QuickSearch->search($search, $Project, array(
            'limit' => 10
        ));

        $list = '<ul>';

        foreach ($result['list'] as $entry) {
            try {
                $Site = $Project->get($entry['siteId']);
                $url  = $Site->getUrlRewritten();
            } catch (QUI\Exception $exception) {
                continue;
            }

            $list .= '<li data-id="' . $entry['id'] . '" data-url="' . $url . '">';
            $list .= '<div class="quiqqer-search-suggest-icon">';

            if (empty($entry['icon'])) {
                $list .= '<span class="fa fa-file-o"></span>';
            } else {
                $list .= '<span class="fa ' . $entry['icon'] . '"></span>';
            }

            $list .= '</div>';
            $list .= '<div class="quiqqer-search-suggest-text">';
            $list .= $entry['data'];
            $list .= '</div>';
            $list .= '</li>';
        }

        $list .= '</ul>';

        return $list;
    },
    array('project', 'search')
);
