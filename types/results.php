<?php

use QUI\Search\Fulltext;

/**
 * settings
 */

$max   = $Site->getAttribute('quiqqer.settings.results.list.max');
$types = $Site->getAttribute('quiqqer.settings.results.list.types');

$count = 0;
$start = 0;
$types = explode(';', $types);

if (!$max) {
    $max = 10;
}


/**
 * request
 */

if (isset($_REQUEST['sheet'])) {
    $start = ((int)$_REQUEST['sheet'] - 1) * $max;
}


/**
 * search
 */

$Fulltext = new Fulltext(array(
    'limit'     => $start . ',' . $max,
    'datatypes' => $types,
    'Project'   => $Project
));

$result   = $Fulltext->search();
$children = array();

foreach ($result['list'] as $entry) {
    try {
        $_Site = $Project->get($entry['siteId']);

        $_Site->setAttribute('search-name', $entry['name']);
        $_Site->setAttribute('search-title', $entry['title']);
        $_Site->setAttribute('search-short', $entry['short']);
        $_Site->setAttribute('search-url', $_Site->getUrlRewritten());
        $_Site->setAttribute('search-icon', $entry['icon']);

        if (!empty($entry['urlParameter'])) {
            $urlParams = json_decode($entry['urlParameter'], true);

            if (is_array($urlParams)) {
                $_Site->setAttribute(
                    'search-url',
                    $_Site->getUrlRewritten($urlParams)
                );
            }
        }

        $children[] = $_Site;

    } catch (QUI\Exception $Exception) {

    }
}

if ($result['count']) {
    $sheets = ceil($result['count'] / $max);
    $count  = (int)$result['count'];
}

$Pagination = new QUI\Bricks\Controls\Pagination(array(
    'count'     => $count,
    'Site'      => $Site,
    'showLimit' => false,
    'limit'     => $max,
    'useAjax'   => false
));

$Pagination->loadFromRequest();

// assign
$Engine->assign(array(
    'Pagination' => $Pagination,
    'children'   => $children
));
