<?php

/**
 * This file contains \QUI\Search\Fulltext
 */

namespace QUI\Search;

use QUI\Search;
use QUI\Projects\Project;

use QUI\Utils\Security\Orthos;

/**
 * Fulltextsearch Manager
 *
 * @author www.pcsg.de (Henning Leutz)
 */
class Fulltext
{
    /**
     * Search
     */

    /**
     * Search something in a project
     *
     * @param Strng $str
     * @param Project $Project
     * @param Array $params - Query params
     * 		$params['limit'] = default: 10
     * @return Array array(
     * 		'list'   => array list of results
     * 		'count'  => count of results
     * )
     */
    public function search($str, Project $Project, $params=array())
    {
        $PDO   = \QUI::getPDO();
        $table = \QUI::getDBProjectTableName( Search::tableSearchFull, $Project );

        if ( !is_array( $params ) ) {
            $params = array();
        }

        if ( !isset( $params['limit'] ) ) {
            $params['limit'] = 10;
        }

        $search = $str.'*';
        $limit  = \QUI\Database\DB::createQueryLimit( $params['limit'] );

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
        $fields = array( 'name', 'title', 'short', 'data' );

        $data = array(
            'siteId' => (int)$siteId
        );

        // data
        foreach ( $fields as $entry )
        {
            if ( !isset( $params[ $entry ] ) ) {
                continue;
            }

            $data[ $entry ] = $params[ $entry ];
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

}
