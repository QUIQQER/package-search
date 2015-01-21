<?php

use QUI\Search\Fulltext;
use QUI\Utils\Security\Orthos;

/**
 * 404 Error Site
 */

if ( \QUI::getRewrite()->getHeaderCode() === 404 )
{
    if ( isset( $_REQUEST[ '_url' ] ) )
    {
        $requestUrl = $_REQUEST[ '_url' ];
        $path       = pathinfo( $requestUrl );

        $_REQUEST[ 'search' ] = $path['dirname'] .' '. $path['filename'];
    }
}

/**
 * Settings
 */

$searchValue       = '';
$searchType        = 'OR';
$fulltextFieldList = Fulltext::getFieldList();

$start = 0;
$max   = $Site->getAttribute( 'quiqqer.settings.search.list.max' );

$settingsFields         = $Site->getAttribute('quiqqer.settings.search.list.fields');
$settingsFieldsSelected = $Site->getAttribute('quiqqer.settings.search.list.fields.selected');

if ( !is_array( $settingsFields ) ) {
    $settingsFields = array();
}

if ( !is_array( $settingsFieldsSelected ) ) {
    $settingsFieldsSelected = array();
}

$children = array();
$sheets   = 0;
$count    = 0;

if ( !$max ) {
    $max = 10;
}


/**
 * requests
 */

if ( isset( $_REQUEST[ 'sheet' ] ) ) {
    $start = ( (int)$_REQUEST[ 'sheet' ] - 1 ) * $max;
}

if ( isset( $_REQUEST[ 'search' ] ) )
{
    if ( is_array( $_REQUEST[ 'search' ] ) )
    {
        $searchValue = implode( ' ', $_REQUEST[ 'search' ] );

    } else
    {
        $searchValue = $_REQUEST[ 'search' ];
    }

    $searchValue = preg_replace( "/[^a-zA-Z0-9äöüß]/", " ", $searchValue );
    $searchValue = Orthos::clear( $searchValue );
    $searchValue = preg_replace( '#([ ]){2,}#', "$1", $searchValue );
    $searchValue = trim( $searchValue );
}

if ( isset( $_REQUEST[ 'searchType' ] ) && $_REQUEST[ 'searchType' ] == 'AND' ) {
    $searchType = 'AND';
}

$fields = array();

// available field list
$availableFields = array();
$settingsFields  = array_flip( $settingsFields );

foreach ( $fulltextFieldList as $field )
{
    if ( isset( $settingsFields[ $field['field'] ] )  ) {
        $availableFields[ $field['field'] ] = true;
    }
}

// search fields
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
    // than select the settings ;-)
    foreach ( $settingsFieldsSelected as $field )
    {
        if ( isset( $availableFields[ $field ] ) ) {
            $fields[] = $field;
        }
    }

    if ( empty( $fields ) )
    {
        foreach ( $availableFields as $field => $v ) {
            $fields[] = $field;
        }
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

            $urlParams = json_decode( $entry[ 'urlParameter' ], true );

            if ( !is_array( $urlParams ) ) {
                $urlParams = array();
            }

            $url = URL_DIR . $Site->getUrlRewrited( $urlParams );
            $url = \QUI\Utils\String::replaceDblSlashes( $url );

            if ( !isset($entry['relevance']) || $entry['relevance'] > 100 ) {
                $entry['relevance'] = 100;
            }

            $Site->setAttribute( 'search-name', $entry['name'] );
            $Site->setAttribute( 'search-title', $entry['title'] );
            $Site->setAttribute( 'search-short', $entry['short'] );
            $Site->setAttribute( 'search-relevance', $entry['relevance'] );
            $Site->setAttribute( 'search-url', $url );
            $Site->setAttribute( 'search-icon', $entry['icon'] );

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
