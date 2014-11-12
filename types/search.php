<?php

$searchValue = '';
$start       = 0;
$max         = $Site->getAttribute( 'quiqqer.settings.search.list.max' );

$children = array();
$sheets   = 0;

if ( !$max ) {
    $max = 2;
}

if ( isset( $_REQUEST[ 'sheet' ] ) ) {
    $start = ( (int)$_REQUEST[ 'sheet' ] - 1 ) * $max;
}

if ( isset( $_REQUEST[ 'search' ] ) ) {
    $searchValue = \QUI\Utils\Security\Orthos::clear( $_REQUEST[ 'search' ] );
}


// search
if ( !empty( $searchValue ) )
{
    $Fulltext = new \QUI\Search\Fulltext();

    $result = $Fulltext->search( $searchValue, $Project, array(
        'limit' => $start .','. $max
    ) );

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
}


$Engine->assign(array(
    'sheets'      => $sheets,
    'children'    => $children,
    'searchValue' => $searchValue
));

