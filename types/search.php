<?php

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
$fields = $Site->getAttribute('quiqqer.settings.search.list.fields');
$searchType = Search::SEARCH_TYPE_OR;

if (in_array('searchTypeAnd', $fields)) {
    $searchType = Search::SEARCH_TYPE_AND;
}

$SearchInput = new SearchInput(array(
    'suggestSearch'     => $Site->getAttribute('quiqqer.search.sitetypes.search.suggestSearch'),
    'availableFields'   => $fields,
    'fields'            => $Site->getAttribute('quiqqer.settings.search.list.fields.selected'),
    'searchType'        => $searchType,
    'showFieldSettings' => !boolval($Site->getAttribute('quiqqer.settings.search.list.hideSettings'))
));

$Search = new Search();

$Search->setAttributesFromRequest();
$SearchInput->setAttributesFromRequest();

$Engine->assign(array(
    'SearchInput' => $SearchInput,
    'Search'      => $Search
));
