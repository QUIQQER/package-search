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
class Quicksearch extends QUI\QDOM
{
    /**
     * Search
     */

    /**
     * Constructor
     *
     * @param array $params - Attributes
     */
    public function __construct($params = array())
    {
        // defaults
        $this->setAttributes(array(
            'siteTypes' => false   // restrict search to certain site types
        ));

        $this->setAttributes($params);
    }

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
            Search::TABLE_SEARCH_QUICK,
            $Project
        );

        if (!is_array($params)) {
            $params = array();
        }

        if (!isset($params['limit'])) {
            $params['limit'] = 10;
        }

        $search = '%'.$str.'%';
        $limit  = QUI\Database\DB::createQueryLimit($params['limit']);
        $binds  = array();

        // restrict search to certain site types
        $siteTypes      = $this->getAttribute('siteTypes');
        $siteTypesQuery = '';

        if ($siteTypes) {
            if (!is_array($siteTypes)) {
                $siteTypes = array($siteTypes);
            }

            $siteTypesQuery = ' AND (';

            for ($i = 0, $len = count($siteTypes); $i < $len; $i++) {
                $siteTypesQuery .= ' siteType LIKE :type'.$i;

                if ($len - 1 > $i) {
                    $siteTypesQuery .= ' OR ';
                }

                $binds['type'.$i] = array(
                    'value' => $siteTypes[$i],
                    'type'  => \PDO::PARAM_STR
                );
            }

            $siteTypesQuery .= ' )';
        }

        if (version_compare(QUI::getDataBase()->getVersion(), '5.7.0')) {
            $query = "
                SELECT ANY_VALUE(id) AS id, 
                    siteId, 
                    urlParameter, 
                    ANY_VALUE(data) AS data, 
                    ANY_VALUE(rights) AS rights, 
                    ANY_VALUE(icon) AS icon
            ";
        } else {
            $query = "SELECT id, siteId, urlParameter, data, rights, icon";
        }


        $query .= "
            FROM
                {$table}
            WHERE
                data LIKE :search
                {$siteTypesQuery}
            GROUP BY siteId, urlParameter, id
        ";

        $selectQuery = "{$query} {$limit['limit']}";

        $countQuery = "
            SELECT COUNT(*) as count
            FROM ({$query}) as T
        ";

        // search
        $Statement = $PDO->prepare($selectQuery);
        $Statement->bindValue(':search', $search, \PDO::PARAM_STR);

        foreach ($binds as $placeholder => $bind) {
            $Statement->bindValue(':'.$placeholder, $bind['value'], $bind['type']);
        }

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

        if (!isset($params['group']) || $params['group'] !== false) {
            $groups = array();

            foreach ($result as $k => $row) {
                if (isset($groups[$row['data']])) {
                    unset($result[$k]);
                    continue;
                }

                $groups[$row['data']] = true;
            }
        }

        // count
        $Statement = $PDO->prepare($countQuery);
        $Statement->bindValue(':search', $search, \PDO::PARAM_STR);

        foreach ($binds as $placeholder => $bind) {
            $Statement->bindValue(':'.$placeholder, $bind['value'], $bind['type']);
        }

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
     * @param integer $siteId
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
            Search::TABLE_SEARCH_QUICK,
            $Project
        );

        $siteId = (int)$siteId;

        if (!$siteId) {
            return;
        }

        if (empty($data)) {
            return;
        }

        // cannot set entry for inactive sites!
        try {
            $Site = $Project->get($siteId);

            if (!$Site->getAttribute('active')) {
                return;
            }
        } catch (\Exception $Exception) {
            return;
        }

        // clear the entries
        self::removeEntries($Project, $siteId);

        // url params
        $siteUrlParams = array();

        // site params
        if (is_array($siteParams) && !empty($siteParams)) {
            foreach ($siteParams as $key => $value) {
                $key   = Orthos::clearMySQL($key, false);
                $value = Orthos::clearMySQL($value, false);

                $siteUrlParams[$key] = $value;
            }
        }

        $urlParameter = json_encode($siteUrlParams);

        // data
        foreach ($data as $dataEntry) {
            QUI::getDataBase()->insert($table, array(
                'siteId'       => $siteId,
                'urlParameter' => $urlParameter,
                'data'         => Orthos::clearMySQL($dataEntry, false),
                'siteType'     => $Site->getAttribute('type')
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
     * @param integer $siteId
     * @param string $data
     * @param array $siteParams
     */
    public static function addEntry(
        Project $Project,
        $siteId,
        $data,
        $siteParams = array()
    ) {
        $table = QUI::getDBProjectTableName(
            Search::TABLE_SEARCH_QUICK,
            $Project
        );

        $siteId = (int)$siteId;

        if (!$siteId) {
            return;
        }

        if (empty($data)) {
            return;
        }

        // cannot set entry for inactive sites!
        try {
            $Site = $Project->get($siteId);

            if (!$Site->getAttribute('active')) {
                return;
            }
        } catch (\Exception $Exception) {
            return;
        }

        $urlParameter = json_encode($siteParams);
//        $data         = QUI::getPDO()->quote($data);

        // check if entry exists
        if (self::existsEntry($Project, $siteId, $data, $siteParams)) {
            QUI::getDataBase()->update(
                $table,
                array(
                    'rights'   => null, // @todo auf was richtiges setzen, wenn der parameter implementiert wird
                    'icon'     => null,  // @todo auf was richtiges setzen, wenn der parameter implementiert wird
                    'siteType' => $Site->getAttribute('type')
                ),
                array(
                    'siteId'       => $siteId,
                    'urlParameter' => $urlParameter,
                    'data'         => $data
                )
            );

            return;
        }

        QUI::getDataBase()->insert($table, array(
            'siteId'       => $siteId,
            'urlParameter' => $urlParameter,
            'data'         => $data,
            'siteType'     => $Site->getAttribute('type')
        ));
    }

    /**
     * Remove an search entry
     *
     * @param Project $Project
     * @param integer $siteId
     * @param array $siteParams
     */
    public static function removeEntries(
        Project $Project,
        $siteId,
        $siteParams = array()
    ) {
        $table = QUI::getDBProjectTableName(
            Search::TABLE_SEARCH_QUICK,
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
            Search::TABLE_SEARCH_QUICK,
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
     * Check if a quicksearch entry already exists
     *
     * @param Project $Project
     * @param int $siteId
     * @param string $data
     * @param array $siteParams
     * @return bool
     */
    public static function existsEntry(
        Project $Project,
        $siteId,
        $data,
        $siteParams = array()
    ) {
        $table = QUI::getDBProjectTableName(
            Search::TABLE_SEARCH_QUICK,
            $Project
        );

        $urlParameter = json_encode($siteParams);

        $result = QUI::getDataBase()->fetch(array(
            'count' => 1,
            'from'  => $table,
            'where' => array(
                'siteId'       => (int)$siteId,
                'data'         => $data,
                'urlParameter' => $urlParameter
            )
        ));

        return boolval(current(current($result)));
    }

    /**
     * Clear a complete fulltext search table
     *
     * @param Project $Project
     */
    public static function clearSearchTable(Project $Project)
    {
        QUI::getDataBase()->table()->truncate(
            QUI::getDBProjectTableName(Search::TABLE_SEARCH_QUICK, $Project)
        );
    }
}
