<?php

use QUI\Search\Fulltext;
use QUI\Utils\Security\Orthos;

/**
 * settings
 */

$max   = $Site->getAttribute( 'quiqqer.settings.results.list.max' );
$types = $Site->getAttribute( 'quiqqer.settings.results.list.types' );

$start = 0;
$types = explode(';', $types);


/**
 * request
 */

if ( isset( $_REQUEST[ 'sheet' ] ) ) {
    $start = ( (int)$_REQUEST[ 'sheet' ] - 1 ) * $max;
}


/**
 * search
 */

$Fulltext = new Fulltext(array(
    'limit'     => $start .','. $max,
    'datatypes' => $types
));

$result   = $Fulltext->search();
$children = array();

foreach ( $result['list'] as $entry )
{
    try
    {
        $Site = $Project->get( $entry[ 'siteId' ] );

        $Site->setAttribute( 'search-name', $entry['name'] );
        $Site->setAttribute( 'search-title', $entry['title'] );
        $Site->setAttribute( 'search-short', $entry['short'] );
        $Site->setAttribute( 'search-url', $Site->getUrlRewrited() );
        $Site->setAttribute( 'search-icon', $entry['icon'] );

        if ( !empty( $entry['urlParameter'] ) )
        {
            $urlParams = json_decode( $entry['urlParameter'], true );

            if ( is_array( $urlParams ) ) {
                $Site->setAttribute( 'search-url', $Site->getUrlRewrited( $urlParams ) );
            }
        }

        $children[] = $Site;

    } catch ( \QUI\Exception $Exception )
    {

    }
}

$sheets = ceil( $result['count'] / $max );
$count  = (int)$result['count'];


// assign
$Engine->assign(array(
    'count'    => $count,
    'sheets'   => $sheets,
    'children' => $children
));
