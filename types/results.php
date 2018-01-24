<?php

use QUI\Search\Controls\Search;

/**
 * settings
 */
$Search = new Search(array(
    'search'       => $Site->getAttribute('quiqqer.settings.results.list.search_term'),
    'max'          => $Site->getAttribute('quiqqer.settings.results.list.max'),
    'searchFields' => $Site->getAttribute('quiqqer.settings.search.list.fields.selected'),
    'datatypes'    => $Site->getAttribute('quiqqer.settings.results.list.types')
));

$Engine->assign('Search', $Search);
