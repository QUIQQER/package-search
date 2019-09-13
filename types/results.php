<?php

use QUI\Search\Controls\Search;

if (isset($_REQUEST['sheet'])
    && \is_numeric($_REQUEST['sheet'])
    && (int)$_REQUEST['sheet'] > 1

    || isset($_REQUEST['limit'])
) {
    $Site->setAttribute('meta.robots', 'noindex,follow');
}


/**
 * settings
 */
$Search = new Search([
    'search'       => $Site->getAttribute('quiqqer.settings.results.list.search_term'),
    'max'          => $Site->getAttribute('quiqqer.settings.results.list.max'),
    'searchFields' => $Site->getAttribute('quiqqer.settings.search.list.fields.selected'),
    'datatypes'    => $Site->getAttribute('quiqqer.settings.results.list.types')
]);

$Engine->assign('Search', $Search);
