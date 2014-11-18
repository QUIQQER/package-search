<?php

/**
 * This file contains \QUI\Search\Fulltext
 */

namespace QUI\Search;

use QUI\Search;
use QUI\Projects\Project;
use QUI\Projects\Site;
use QUI\Projects\Site\Edit as SiteEdit;
use QUI\System\Log;
use QUI\Utils\Security\Orthos;

/**
 * Fulltextsearch Manager
 *
 * @author www.pcsg.de (Henning Leutz)
 */
class Fulltext extends \QUI\QDOM
{
    /**
     * Constructor
     * @param Array $params
     */
    public function __construct($params=array())
    {
        // defaults
        $this->setAttributes(array(
            'Project' => false,
            'limit'   => 10,
            'fields'  => false
        ));

        $this->setAttributes( $params );
    }

    /**
     * Search
     */

    /**
     * Search something in a project
     *
     * @param String $str
     * @return Array array(
     * 		'list'   => array list of results
     * 		'count'  => count of results
     * )
     */
    public function search($str)
    {
        $Project    = $this->getAttribute( 'Project' );
        $attrLimit  = $this->getAttribute( 'limit' );
        $attrFields = $this->getAttribute( 'fields' );

        if ( !$Project || get_class( $Project ) !== 'QUI\Projects\Project' ) {
            $Project = \QUI::getProjectManager()->get();
        }

        if ( !$attrLimit || is_integer( $attrLimit ) ) {
            $attrLimit = 10;
        }




        $PDO   = \QUI::getPDO();
        $table = \QUI::getDBProjectTableName( Search::tableSearchFull, $Project );

        $search = $str.'*';
        $limit  = \QUI\Database\DB::createQueryLimit( $attrLimit );

        $query = "
            SELECT
                *,
                MATCH(name)  AGAINST (:search IN BOOLEAN MODE) * 1.3 +
                MATCH(title) AGAINST (:search IN BOOLEAN MODE) * 1.4 +
                MATCH(short) AGAINST (:search IN BOOLEAN MODE) * 1.2 +
                MATCH(data)  AGAINST (:search IN BOOLEAN MODE) * 1.1 AS relevance
            FROM
                {$table}
            WHERE
                MATCH (name,title,short,data) AGAINST (:search IN BOOLEAN MODE)
            GROUP BY
                siteId
            ORDER BY
                relevance DESC
        ";

        $selectQuery = "{$query} {$limit['limit']}";

        $countQuery = "
            SELECT COUNT(*) as count
            FROM ({$query}) as T
        ";

        // search
        $Statement = $PDO->prepare( $selectQuery );
        $Statement->bindValue( ':search', $search, \PDO::PARAM_STR );
        $Statement->bindValue( ':limit1', $limit['prepare'][':limit1'][0], \PDO::PARAM_INT );
        $Statement->bindValue( ':limit2', $limit['prepare'][':limit2'][0], \PDO::PARAM_INT );
        $Statement->execute();

        $result = $Statement->fetchAll( \PDO::FETCH_ASSOC );

        // count
        $Statement = $PDO->prepare( $countQuery );
        $Statement->bindValue( ':search', $search, \PDO::PARAM_STR );
        $Statement->execute();

        $count = $Statement->fetchAll( \PDO::FETCH_ASSOC );


        return array(
            'list'   => $result,
            'count'  => $count[ 0 ]['count']
        );
    }

    /**
     * Creation
     */

    /**
     * Add an entry to the fulltext search table
     *
     * @param Project $Project
     * @param Integer $siteId
     * @param Array $params
     * @param Array $siteParams - optional; Parameter for the site link
     */
    public static function addEntry(Project $Project, $siteId, $params=array(), $siteParams=array())
    {
        $table  = \QUI::getDBProjectTableName( Search::tableSearchFull, $Project );
        $fields = self::getFieldList();

        $data = array(
            'siteId' => (int)$siteId
        );

        // data
        foreach ( $fields as $entry )
        {
            $field = $entry['field'];

            if ( !isset( $params[ $field ] ) ) {
                continue;
            }

            $data[ $field ] = $params[ $field ];
        }

        // site params
        if ( is_array( $siteParams ) && !empty( $siteParams ) )
        {
            $siteUrlParams = array();

            foreach ( $siteParams as $value => $value )
            {
                $param = Orthos::clear( $param );
                $value = Orthos::clear( $value );

                $siteUrlParams[ $param ] = $value;
            }

            $data['urlParameter'] = json_encode( $siteUrlParams );
        }

        \QUI::getDataBase()->insert( $table, $data );
    }

    /**
     * Clear a complete fulltext search table
     *
     * @param Project $Project
     */
    public static function clearSearchTable(Project $Project)
    {
        \QUI::getDataBase()->Table()->truncate(
            \QUI::getDBProjectTableName( Search::tableSearchFull, $Project )
        );
    }

    /**
     * event : onSearchFulltextCreation
     *
     * @param Fulltext $Fulltext
     * @param Project $Project
     */
    public static function onSearchFulltextCreation(Fulltext $Fulltext, Project $Project)
    {
        $list = $Project->getSitesIds(array(
            'active'  => 1
        ));

        foreach ( $list as $siteParams )
        {
            try
            {
                $siteId = (int)$siteParams['id'];
                $Site   = new SiteEdit( $Project, (int)$siteId );

                if ( !$Site->getAttribute('active') ) {
                    continue;
                }

                if ( $Site->getAttribute('deleted') ) {
                    continue;
                }


                $Fulltext->addEntry($Project, $siteId, array(
                    'name'  => $Site->getAttribute('name'),
                    'title' => $Site->getAttribute('title'),
                    'short' => $Site->getAttribute('short'),
                    'data'  => $Site->getAttribute('content')
                ));

            } catch ( \QUI\Exception $Exception )
            {
                Log::writeException( $Exception );
            }
        }
    }

    /**
     * Utils
     */

    /**
     * Return the search fields
     *
     * @return Array
     */
    public static function getFieldList()
    {
        $cache = 'quiqqer/search/fieldList';

        try
        {
            return \QUI\Cache\Manager::get( $cache );

        } catch ( \QUI\Exception $Exception )
        {

        }

        $result = array();
        $files  = self::getSearchXmlList();

        foreach ( $files as $file )
        {
            $Dom = \QUI\Utils\XML::getDomFromXml( $file );
            $Path = new \DOMXPath( $Dom );

            $fields = $Path->query( "//quiqqer/search/searchfields/field" );

            foreach ( $fields as $Field )
            {
                $result[] = array(
                    'field'    => trim( $Field->nodeValue ),
                    'type'     => $Field->getAttribute( 'type' ),
                    'fulltext' => $Field->getAttribute( 'fulltext' ) ? true : false
                );
            }
        }

        \QUI\Cache\Manager::set( $cache, $result );

        return $result;
    }

    /**
     * Return the plugins with a search.xml file
     *
     * @return Array
     */
    public static function getSearchXmlList()
    {
        $cache = 'quiqqer/search/xmlList';

        try
        {
            return \QUI\Cache\Manager::get( $cache );

        } catch ( \QUI\Exception $Exception )
        {

        }

        $packages = \QUI::getPackageManager()->getInstalled();
        $result   = array();

        foreach ( $packages as $package )
        {
            $xmlFile = OPT_DIR . $package['name'] .'/search.xml';

            if ( file_exists( $xmlFile ) ) {
                $result[] = $xmlFile;
            }
        }

        \QUI\Cache\Manager::set( $cache, $result );

        return $result;
    }
}
