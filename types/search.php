<?php

use QUI\Search\Fulltext;
use QUI\Utils\Security\Orthos;

/**
 * Settings
 */

$searchValue       = '';
$searchType        = 'OR';
$fulltextFieldList = Fulltext::getFieldList();

$start = 0;
$max   = $Site->getAttribute( 'quiqqer.settings.search.list.max' );


$children = array();
$sheets   = 0;
$count    = 0;

if ( !$max ) {
    $max = 10;
}

if ( isset( $_REQUEST[ 'sheet' ] ) ) {
    $start = ( (int)$_REQUEST[ 'sheet' ] - 1 ) * $max;
}

if ( isset( $_REQUEST[ 'search' ] ) ) {
    $searchValue = Orthos::clear( $_REQUEST[ 'search' ] );
}

if ( isset( $_REQUEST[ 'searchType' ] ) && $_REQUEST[ 'searchType' ] == 'AND' ) {
    $searchType = 'AND';
}

$fields = array();

// available field list
$availableFields = array();

foreach ( $fulltextFieldList as $field ) {
    $availableFields[ $field['field'] ] = true;
}


/**
 * search fields
 */
if ( isset( $_REQUEST['searchIn'] ) && is_array( $_REQUEST['searchIn'] ) )
{
    foreach ( $_REQUEST['searchIn'] as $field )
    {
        $field = Orthos::clear( $field );

        if ( isset( $availableFields[ $field ] ) ) {
            $fields[] = $field;
        }
    }

} else
{
    // nothing selected?
    // than select all ;-)
    foreach ( $availableFields as $field => $v ) {
        $fields[] = $field;
    }
}


/**
 * search
 */

if ( !empty( $searchValue ) )
{
    $Fulltext = new Fulltext(array(
        'limit'      => $start .','. $max,
        'fields'     => $fields,
        'searchtype' => $searchType
    ));

    $result = $Fulltext->search( $searchValue );

    foreach ( $result['list'] as $entry )
    {
        try
        {
            $Site = $Project->get( $entry[ 'siteId' ] );

            $Site->setAttribute( 'search-name', $entry['name'] );
            $Site->setAttribute( 'search-title', $entry['title'] );
            $Site->setAttribute( 'search-short', $entry['short'] );
            $Site->setAttribute( 'search-relevance', $entry['relevance'] );
            $Site->setAttribute( 'search-url', $Site->getUrlRewrited() );

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
}


$Engine->assign(array(
    'fields'          => $fields,
    'count'           => $count,
    'sheets'          => $sheets,
    'children'        => $children,
    'searchValue'     => $searchValue,
    'searchType'      => $searchType,
    'availableFields' => $availableFields
));

