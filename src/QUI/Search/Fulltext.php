<?php

/**
 * This file contains \QUI\Search\Fulltext
 *
 * @todo Search-Entry als Objekt umsetzen
 */

namespace QUI\Search;

use QUI;
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
class Fulltext extends QUI\QDOM
{
    /**
     * Constructor
     *
     * @param Array $params
     */
    public function __construct($params = array())
    {
        // defaults
        $this->setAttributes(array(
            'Project'    => false,    // Project
            'limit'      => 10,        // limit of results
            'fields'     => false,    // array list of fields
            'searchtype' => 'OR',    // search type: OR / AND
            'datatypes'  => false   // only for some site types, can be an array
        ));

        $this->setAttributes($params);
    }

    /**
     * Search
     */

    /**
     * Search something in a project
     *
     * @param String $str
     *
     * @return Array array(
     *        'list'   => array list of results
     *        'count'  => count of results
     * )
     */
    public function search($str = '')
    {
        $Project    = $this->getAttribute('Project');
        $attrLimit  = $this->getAttribute('limit');
        $attrFields = $this->getAttribute('fields');

        if (!$Project || get_class($Project) !== 'QUI\Projects\Project') {
            $Project = QUI::getProjectManager()->get();
        }

        if (!$attrLimit || is_integer($attrLimit)) {
            $attrLimit = 10;
        }

        $strParts = explode(' ', $str);

        foreach ($strParts as $key => $part) {
            $strParts[$key] = $part . '*';
        }

        switch ($this->getAttribute('searchtype')) {
            case 'AND':
            case 'and':
                $search = '+' . implode(' +', $strParts);
                break;

            default:
                $search = implode(' ', $strParts);
                break;
        }


        // fields
        $fields    = array();
        $fieldList = self::getFieldList();

        $availableFields = array_map(function ($entry) {
            return $entry['field'];
        }, $fieldList);

        if (!$attrFields || !is_array($attrFields)) {
            $fields = $availableFields;

        } else {
            $availableFields = array_flip($availableFields);

            foreach ($attrFields as $field) {
                if (isset($availableFields[$field])) {
                    $fields[] = Orthos::clearNoneCharacters($field);
                }
            }
        }

        if (empty($fields)) {
            $fields = $availableFields;
        }

        foreach ($fields as $key => $value) {
            $fields[$key] = Orthos::clearNoneCharacters($fields[$key],
                array('_'));
        }

        // sql
        $count = array(
            'name'    => 8,
            'title'   => 10,
            'short'   => 5,
            'content' => 3
        );

        $PDO   = QUI::getPDO();
        $table = QUI::getDBProjectTableName(Search::tableSearchFull, $Project);
        $limit = QUI\Database\DB::createQueryLimit($attrLimit);

        // relevance match
        $relevanceMatch = array();
        $whereMatch     = implode(',', $fields);
        $relevanceSum   = 0;

        foreach ($fields as $field) {
            $matchCount = 9;

            if (isset($count[$field])) {
                $matchCount = $count[$field];
            }

            $relevanceMatch[]
                          = "MATCH({$field}) AGAINST (:search IN BOOLEAN MODE) * {$matchCount}";
            $relevanceSum = $relevanceSum + $matchCount;
        }

        $relevanceMatch = implode(' + ', $relevanceMatch);

        // site types
        $datatypes     = $this->getAttribute('datatypes');
        $datatypeQuery = '';

        if ($datatypes) {
            if (!is_array($datatypes)) {
                $datatypes = array($datatypes);
            }

            $datatypeQuery = ' AND (';

            for ($i = 0, $len = count($datatypes); $i < $len; $i++) {
                $datatypeQuery .= ' datatype LIKE :type' . $i;

                if ($len - 1 > $i) {
                    $datatypeQuery .= ' OR ';
                }
            }

            $datatypeQuery .= ' )';
        }


        // query
        $query
            = "
            SELECT *
            FROM
                {$table}
            WHERE
                (name LIKE :search OR
                title LIKE :search OR
                short LIKE :search OR
                data  LIKE :search)
                {$datatypeQuery}
            GROUP BY
                urlParameter,siteId
            ORDER BY
                e_date DESC
        ";

        if (strlen($search) > 2) {
            $query
                = "
                SELECT
                    *,
                    100 / {$relevanceSum} * ({$relevanceMatch}) AS relevance
                FROM
                    {$table}
                WHERE
                    MATCH ({$whereMatch}) AGAINST (:search IN BOOLEAN MODE)
                    {$datatypeQuery}
                GROUP BY
                    urlParameter,siteId
                ORDER BY
                    relevance DESC
            ";

        } else {
            $search = str_replace(array('*', '+'), '', $search);
            $search = "%{$search}%";
        }

        $selectQuery = "{$query} {$limit['limit']}";

        $countQuery
            = "
            SELECT COUNT(*) as count
            FROM ({$query}) as T
        ";

        /**
         * search
         */
        $Statement = $PDO->prepare($selectQuery);
        $Statement->bindValue(':limit1', $limit['prepare'][':limit1'][0],
            \PDO::PARAM_INT);
        $Statement->bindValue(':limit2', $limit['prepare'][':limit2'][0],
            \PDO::PARAM_INT);

        if (strlen($search) > 2 || $search == '%%') {
            $Statement->bindValue(':search', $search, \PDO::PARAM_STR);
        }

        if ($datatypes) {
            for ($i = 0, $len = count($datatypes); $i < $len; $i++) {
                $Statement->bindValue(
                    ':type' . $i,
                    $datatypes[$i],
                    \PDO::PARAM_STR
                );
            }
        }

        $Statement->execute();
        $result = $Statement->fetchAll(\PDO::FETCH_ASSOC);

        /**
         * count
         */
        $Statement = $PDO->prepare($countQuery);

        if (strlen($search) > 2 || $search == '%%') {
            $Statement->bindValue(':search', $search, \PDO::PARAM_STR);
        }

        if ($datatypes) {
            for ($i = 0, $len = count($datatypes); $i < $len; $i++) {
                $Statement->bindValue(
                    ':type' . $i,
                    $datatypes[$i],
                    \PDO::PARAM_STR
                );
            }
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
     * Add or set an entry to the fulltext search table
     *
     * @param Project $Project
     * @param Integer $siteId
     * @param Array $params
     * @param Array $siteParams - optional; Parameter for the site link
     */
    public static function setEntry(
        Project $Project,
        $siteId,
        $params = array(),
        $siteParams = array()
    ) {
        self::setEntryData($Project, $siteId, $params, $siteParams);

        QUI::getEvents()->fireEvent(
            'searchFulltextSetEntry',
            array($Project, $siteId, $siteParams)
        );
    }

    /**
     * Delete an entry from the search table
     *
     * @param Project $Project
     * @param integer $siteId
     * @param array $siteParams (optional); Parameter for the site link
     */
    public static function removeEntry(
        Project $Project,
        $siteId,
        $siteParams = array()
    ) {
        $tbl = QUI::getDBProjectTableName(Search::tableSearchFull, $Project);

        QUI::getDataBase()->delete($tbl, array(
            'siteId'       => (int)$siteId,
            'urlParameter' => json_encode($siteParams)
        ));
    }

    /**
     * Edit an entry to the fulltext search table
     *
     * @param Project $Project
     * @param Integer $siteId
     * @param Array $params
     * @param Array $siteParams - optional; Parameter for the site link
     */
    public static function setEntryData(
        Project $Project,
        $siteId,
        $params = array(),
        $siteParams = array()
    ) {
        $table  = QUI::getDBProjectTableName(Search::tableSearchFull, $Project);
        $fields = self::getFieldList();

        $urlParameter = json_encode($siteParams);
        $siteId       = (int)$siteId;

        try {
            $data = self::getEntry($Project, $siteId, $siteParams);

            unset($data['siteId']);
            unset($data['urlParameter']);

        } catch (QUI\Exception $Exception) {
            $siteUrlParams = array();

            // site params
            if (is_array($siteParams) && !empty($siteParams)) {
                foreach ($siteParams as $urlKey => $urlValue) {
                    $urlValue = Orthos::clear($urlValue);
                    $urlKey   = Orthos::clear($urlKey);

                    $siteUrlParams[$urlKey] = $urlValue;
                }
            }

            $urlParameter = json_encode($siteUrlParams);


            QUI::getDataBase()->insert($table, array(
                'siteId'       => (int)$siteId,
                'urlParameter' => $urlParameter
            ));

            $data = array();
        }

        // data
        foreach ($fields as $entry) {
            $field = $entry['field'];

            if (!isset($params[$field])) {
                continue;
            }

            $data[$field] = $params[$field];
        }

        QUI::getDataBase()->update($table, $data, array(
            'siteId'       => (int)$siteId,
            'urlParameter' => $urlParameter
        ));
    }

    /**
     * Append the data field of an specific search entry
     *
     * @param Project $Project
     * @param Integer $siteId
     * @param String $data
     * @param Array $siteParams
     */
    public static function appendFulltextSearchString(
        Project $Project,
        $siteId,
        $data = '',
        $siteParams = array()
    ) {
        $table = QUI::getDBProjectTableName(
            Search::tableSearchFull,
            $Project
        );

        $entry   = self::getEntry($Project, $siteId, $siteParams);
        $content = $entry['data'];

        $content      = $content . ' ' . $data;
        $urlParameter = json_encode($siteParams);

        QUI::getDataBase()->update($table, array(
            'data' => $content
        ), array(
            'siteId'       => (int)$siteId,
            'urlParameter' => $urlParameter
        ));
    }

    /**
     * Return an fulltext entry
     *
     * @param Project $Project
     * @param Integer $siteId
     * @param Array $siteParams
     *
     * @throws QUI\Exception
     */
    public static function getEntry(
        Project $Project,
        $siteId,
        $siteParams = array()
    ) {
        $table = QUI::getDBProjectTableName(
            Search::tableSearchFull,
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
                'Search entry not exists'
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
            QUI::getDBProjectTableName(Search::tableSearchFull, $Project)
        );
    }

    /**
     * event : onSearchFulltextCreation
     *
     * @param Fulltext $Fulltext
     * @param Project $Project
     */
    public static function onSearchFulltextCreate(
        Fulltext $Fulltext,
        Project $Project
    ) {
        $list = $Project->getSitesIds(array(
            'active' => 1
        ));

        foreach ($list as $siteParams) {
            try {
                $siteId = (int)$siteParams['id'];
                $Site   = new SiteEdit($Project, (int)$siteId);

                if (!$Site->getAttribute('active')) {
                    continue;
                }

                if ($Site->getAttribute('deleted')) {
                    continue;
                }

                if ($Site->getAttribute('quiqqer.settings.search.not.indexed')) {
                    continue;
                }

                $e_date = $Site->getAttribute('e_date');
                $e_date = strtotime($e_date);

                if (!$e_date) {
                    $e_date = 0;
                }

                $Fulltext->setEntry($Project, $siteId, array(
                    'name'     => $Site->getAttribute('name'),
                    'title'    => $Site->getAttribute('title'),
                    'short'    => $Site->getAttribute('short'),
                    'data'     => $Site->getAttribute('content'),
                    'datatype' => $Site->getAttribute('type'),
                    'icon'     => $Site->getAttribute('image_site'),
                    'e_date'   => $e_date
                ));

            } catch (QUI\Exception $Exception) {
                Log::writeException($Exception);
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

        try {
            return QUI\Cache\Manager::get($cache);

        } catch (QUI\Exception $Exception) {

        }

        $result = array();
        $files  = self::getSearchXmlList();

        foreach ($files as $file) {
            $Dom  = QUI\Utils\XML::getDomFromXml($file);
            $Path = new \DOMXPath($Dom);

            $fields = $Path->query("//quiqqer/search/searchfields/field");

            foreach ($fields as $Field) {
                /* @var $Field \DOMElement */
                $result[] = array(
                    'field'    => trim($Field->nodeValue),
                    'type'     => $Field->getAttribute('type'),
                    'fulltext' => $Field->getAttribute('fulltext') ? true
                        : false
                );
            }
        }

        QUI\Cache\Manager::set($cache, $result);

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

        try {
            return QUI\Cache\Manager::get($cache);

        } catch (QUI\Exception $Exception) {

        }

        $packages = QUI::getPackageManager()->getInstalled();
        $result   = array();

        foreach ($packages as $package) {
            $xmlFile = OPT_DIR . $package['name'] . '/search.xml';

            if (file_exists($xmlFile)) {
                $result[] = $xmlFile;
            }
        }

        QUI\Cache\Manager::set($cache, $result);

        return $result;
    }
}