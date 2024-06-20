<?php

/**
 * This file contains the category site type
 *
 * @var QUI\Projects\Project $Project
 * @var QUI\Projects\Site $Site
 * @var QUI\Interfaces\Template\EngineInterface $Engine
 * @var QUI\Template $Template
 **/

use QUI\Search\Controls\Search;

if (
    isset($_REQUEST['sheet'])
    && is_numeric($_REQUEST['sheet'])
    && (int)$_REQUEST['sheet'] > 1

    || isset($_REQUEST['limit'])
) {
    $Site->setAttribute('meta.robots', 'noindex,follow');
}


/**
 * settings
 */
$Search = new Search([
    'search' => $Site->getAttribute('quiqqer.settings.results.list.search_term'),
    'max' => $Site->getAttribute('quiqqer.settings.results.list.max'),
    'searchFields' => $Site->getAttribute('quiqqer.settings.search.list.fields.selected'),
    'datatypes' => $Site->getAttribute('quiqqer.settings.results.list.types')
]);

$Engine->assign('Search', $Search);
