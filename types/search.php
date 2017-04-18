<?php

use QUI\Utils\Security\Orthos;
use QUI\Search\Controls\Search;
use QUI\Search\Controls\SearchInput;

/**
 * 404 Error Site
 */

if (QUI::getRewrite()->getHeaderCode() === 404) {
    if (isset($_REQUEST['_url'])) {
        $requestUrl = $_REQUEST['_url'];
        $path       = pathinfo($requestUrl);

        if (isset($path['dirname'])) {
            $_REQUEST['search'] = $path['dirname'] . ' ' . $path['filename'];
        } else {
            $_REQUEST['search'] = $path['filename'];
        }

        // replace all "-" with " " (space)
        $_REQUEST['search'] = str_replace($_REQUEST['search'], '-', ' ');
    }
}

/**
 * Settings
 */

$SearchInput = new SearchInput();
$Search  = new Search();

// requests
if (isset($_REQUEST['sheet'])) {
    $Search->setAttribute('sheet', $_REQUEST['sheet']);
}

if (isset($_REQUEST['search'])) {
    $SearchInput->setAttribute('search', $_REQUEST['search']);
    $Search->setAttribute('search', $_REQUEST['search']);
}

if (isset($_REQUEST['searchType'])
    && $_REQUEST['searchType'] == $Search::SEARCH_TYPE_AND
) {
    $SearchInput->setAttribute('searchType', $Search::SEARCH_TYPE_AND);
    $Search->setAttribute('searchType', $Search::SEARCH_TYPE_AND);
}

$fields = array();

// search fields
if (isset($_REQUEST['searchIn'])) {
    if (!is_array($_REQUEST['searchIn'])) {
        $_REQUEST['searchIn'] = explode(',', urldecode($_REQUEST['searchIn']));
    }

    foreach ($_REQUEST['searchIn'] as $field) {
        if (!is_string($field)) {
            continue;
        }

        $fields[] = Orthos::clear($field);
    }

    $SearchInput->setAttribute('fields', $fields);
    $Search->setAttribute('searchFields', $fields);
}

$Engine->assign(array(
    'SearchInput' => $SearchInput,
    'Search'  => $Search
));
