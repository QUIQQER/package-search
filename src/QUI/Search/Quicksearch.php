<?php

/**
 * This file contains \QUI\Search\Quicksearch
 */

namespace QUI\Search;

use QUI;
use QUI\Search;
use QUI\Projects\Project;
use QUI\Utils\Security\Orthos;

/**
 * Quicksearch Manager
 *
 * @author www.pcsg.de (Henning Leutz)
 */
class Quicksearch
{
    /**
     * Search
     */

    /**
     * Search something in a project
     *
     * @param string $str
     * @param Project $Project
     * @param array $params - Query params
     *                        $params['limit'] = default: 10
     *
     * @return array array(
     *        'list'   => array list of results
     *        'count'  => count of results
     * )
     */
    public function search($str, Project $Project, $params = array())
    {
        $PDO   = QUI::getPDO();
        $table = QUI::getDBProjectTableName(
            Search::tableSearchQuick,
            $Project
        );

        if (!is_array($params)) {
            $params = array();
        }

        if (!isset($params['limit'])) {
            $params['limit'] = 10;
        }

        $search = '%' . $str . '%';
        $limit  = QUI\Database\DB::createQueryLimit($params['limit']);

        $groupedBy = 'GROUP BY data';

        if (isset($params['group']) && $params['group'] === false) {
            $groupedBy = '';
        }

        $query
            = "
            SELECT *
            FROM
                {$table}
            WHERE
                data LIKE :search
            {$groupedBy}
        ";

        $selectQuery = "{$query} {$limit['limit']}";

        $countQuery
            = "
            SELECT COUNT(*) as count
            FROM ({$query}) as T
        ";

        // search
        $Statement = $PDO->prepare($selectQuery);
        $Statement->bindValue(':search', $search, \PDO::PARAM_STR);

        if (isset($limit['prepare'][':limit1'])) {
            $Statement->bindValue(
                ':limit1',
                $limit['prepare'][':limit1'][0],
                \PDO::PARAM_INT
            );
        }

        if (isset($limit['prepare'][':limit2'])) {
            $Statement->bindValue(
                ':limit2',
                $limit['prepare'][':limit2'][0],
                \PDO::PARAM_INT
            );
        }

        $Statement->execute();

        $result = $Statement->fetchAll(\PDO::FETCH_ASSOC);

        // count
        $Statement = $PDO->prepare($countQuery);
        $Statement->bindValue(':search', $search, \PDO::PARAM_STR);
        $Statement->execute();

        $count = $Statement->fetchAll(\PDO::FETCH_ASSOC);


        return array(
            'list'  => $result,
            'count' => $count[0]['count']
        );
    }

    /**
     * Creation
     */

    /**
     * Add entries to the quicksearch search table
     * Removes similar entries, with same siteId and siteParams
     *
     * @param Project $Project
     * @param Integer $siteId
     * @param array $data - data array -> every array entry is a data entry
     * @param array $siteParams - optional; Parameter for the site link
     */
    public static function setEntries(
        Project $Project,
        $siteId,
        $data = array(),
        $siteParams = array()
    ) {
        $table = QUI::getDBProjectTableName(
            Search::tableSearchQuick,
            $Project
        );

        $siteId = (int)$siteId;

        if (!$siteId) {
            return;
        }

        if (empty($data)) {
            return;
        }

        // clear the entries
        self::removeEntries($Project, $siteId);

        // url params
        $siteUrlParams = array();

        // site params
        if (is_array($siteParams) && !empty($siteParams)) {
            foreach ($siteParams as $key => $value) {
                $key   = Orthos::clear($key);
                $value = Orthos::clear($value);

                $siteUrlParams[$key] = $value;
            }
        }

        $urlParameter = json_encode($siteUrlParams);

        // data
        foreach ($data as $dataEntry) {
            QUI::getDataBase()->insert($table, array(
                'siteId'       => $siteId,
                'urlParameter' => $urlParameter,
                'data'         => Orthos::clear($dataEntry)
            ));
        }


        QUI::getEvents()->fireEvent(
            'searchQuicksearchSetEntry',
            array($Project, $siteId, $siteParams)
        );
    }

    /**
     * Add an entry to the quicksearch search table
     *
     * @param Project $Project
     * @param Integer $siteId
     * @param String $data
     * @param array $siteParams
     */
    public static function addEntry(
        Project $Project,
        $siteId,
        $data,
        $siteParams = array()
    ) {
        $table = QUI::getDBProjectTableName(
            Search::tableSearchQuick,
            $Project
        );

        $siteId = (int)$siteId;

        if (!$siteId) {
            return;
        }

        if (empty($data)) {
            return;
        }

        $urlParameter = json_encode($siteParams);


        QUI::getDataBase()->insert($table, array(
            'siteId'       => $siteId,
            'urlParameter' => $urlParameter,
            'data'         => Orthos::clear($data)
        ));
    }

    /**
     * Remove an search entry
     *
     * @param Project $Project
     * @param Integer $siteId
     * @param array $siteParams
     */
    public static function removeEntries(
        Project $Project,
        $siteId,
        $siteParams = array()
    ) {
        $table = QUI::getDBProjectTableName(
            Search::tableSearchQuick,
            $Project
        );

        $siteId = (int)$siteId;

        if (!$siteId) {
            return;
        }

        QUI::getDataBase()->delete($table, array(
            'siteId'       => $siteId,
            'urlParameter' => json_encode($siteParams)
        ));
    }

    /**
     * Return an fulltext entry
     *
     * @param Project $Project
     * @param integer $siteId
     * @param array $siteParams
     *
     * @return array
     *
     * @throws QUI\Exception
     */
    public static function getEntry(
        Project $Project,
        $siteId,
        $siteParams = array()
    ) {
        $table = QUI::getDBProjectTableName(
            Search::tableSearchQuick,
            $Project
        );

        $urlParameter = json_encode($siteParams);

        $result = QUI::getDataBase()->fetch(array(
            'from'  => $table,
            'where' => array(
                'siteId'       => (int)$siteId,
                'urlParameter' => $urlParameter
            )
        ));

        if (!isset($result[0])) {
            throw new QUI\Exception(
                'Quicksearch entry not exists'
            );
        }

        return $result[0];
    }

    /**
     * Clear a complete fulltext search table
     *
     * @param Project $Project
     */
    public static function clearSearchTable(Project $Project)
    {
        QUI::getDataBase()->Table()->truncate(
            QUI::getDBProjectTableName(Search::tableSearchQuick, $Project)
        );
    }
}
