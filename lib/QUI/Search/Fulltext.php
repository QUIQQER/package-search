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
     * @return Array
     */
    public function search($str, Project $Project)
    {
        $PDO   = \QUI::getPDO();
        $table = \QUI::getDBProjectTableName( Search::tableSearchFull, $Project );

        $query = "
            SELECT
                *,
                MATCH (name) AGAINST (:search IN BOOLEAN MODE) AS nameRel,
                MATCH (title) AGAINST (:search IN BOOLEAN MODE) AS titleRel,
                MATCH (short) AGAINST (:search IN BOOLEAN MODE) AS shortRel,
                MATCH (data) AGAINST (:search IN BOOLEAN MODE) AS dataRel
            FROM
                {$table}
            WHERE
                MATCH (name,title,short,data) AGAINST (:search IN BOOLEAN MODE)
            ORDER BY
                nameRel + titleRel + shortRel + dataRel DESC
        ";

        $Statement = $PDO->prepare( $query );
        $Statement->bindValue( ':search', $str, \PDO::PARAM_STR );
        $Statement->execute();

        $result = $Statement->fetchAll( \PDO::FETCH_ASSOC );


        return $result;
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
