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

$SearchInput = new SearchInput(array(
    'suggestSearch'     => $Site->getAttribute('quiqqer.search.sitetypes.search.suggestSearch'),
    'availableFields'   => $Site->getAttribute('quiqqer.settings.search.list.fields'),
    'fields'            => $Site->getAttribute('quiqqer.settings.search.list.fields.selected'),
    'showFieldSettings' => !boolval($Site->getAttribute('quiqqer.settings.search.list.hideSettings'))
));

$Search = new Search();

$Search->setAttributesFromRequest();
$SearchInput->setAttributesFromRequest();

$Engine->assign(array(
    'SearchInput' => $SearchInput,
    'Search'      => $Search
));
