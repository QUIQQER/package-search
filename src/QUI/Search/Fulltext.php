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
use QUI\Projects\Site\Edit as SiteEdit;
use QUI\System\Log;
use QUI\Utils\Security\Orthos;
use Tracy\Debugger;

/**
 * Fulltextsearch Manager
 *
 * @package QUI\Search\Fulltext
 * @author  www.pcsg.de (Henning Leutz) <info@pcsg.de>
 */
class Fulltext extends QUI\QDOM
{
    /**
     * Constructor
     *
     * @param array $params - Attributes
     */
    public function __construct($params = array())
    {
        // defaults
        $this->setAttributes(array(
            'Project'          => false,   // Project
            'limit'            => 10,      // limit of results
            'fields'           => false,   // array list of fields
            'fieldConstraints' => array(), // restrict certain search fields to specific values
            'searchtype'       => 'OR',    // search type: OR / AND
            'datatypes'        => false,   // restrict search to certain site types
            'relevanceSearch'  => true,     // use relevance search (if search string has minimum length),
            'orderFields'      => array()   // fields that the search results are ordered by (ordered by priority)
        ));

        $this->setAttributes($params);
    }

    /**
     * Search
     */

    /**
     * Search something in a project
     *
     * @param string $str - search string
     *
     * @return array array(
     *        'list'   => array list of results
     *        'count'  => count of results
     * )
     *
     * @throws QUI\Exception
     */
    public function search($str = '')
    {
        $str        = $this->sanitizeSearchString($str);
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
            $strParts[$key] = '*' . $part . '*';
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

        $availableFields = array();

        // filter
        foreach ($fieldList as $entry) {
            $type = mb_strtolower($entry['type']);

            if (mb_strpos($type, 'varchar') !== false
                || mb_strpos($type, 'text') !== false
            ) {
                $availableFields[] = $entry['field'];
            }
        }

        if (!$attrFields || !is_array($attrFields)) {
            $fields = $availableFields;
        } else {
            $availableFieldsTmp = array_flip($availableFields);

            foreach ($attrFields as $field) {
                if (isset($availableFieldsTmp[$field])) {
                    $fields[] = Orthos::clearNoneCharacters($field);
                }
            }
        }

        if (empty($fields)) {
            $fields = $availableFields;
        }

        foreach ($fields as $key => $value) {
            $fields[$key] = Orthos::clearNoneCharacters(
                $fields[$key],
                array('_')
            );
        }

        // sql
        $count = array(
            'name'    => 8,
            'title'   => 10,
            'short'   => 5,
            'content' => 3
        );

        $PDO   = QUI::getPDO();
        $table = QUI::getDBProjectTableName(Search::TABLE_SEARCH_FULL, $Project);
        $limit = QUI\Database\DB::createQueryLimit($attrLimit);
        $binds = array();

        // relevance match
        $relevanceMatch = array();
        $whereMatch     = implode(',', $fields);
        $relevanceSum   = 0;

        foreach ($fields as $field) {
            $matchCount = 9;

            if (isset($count[$field])) {
                $matchCount = $count[$field];
            }

            $relevanceMatch[] = "MATCH({$field}) AGAINST (:search IN BOOLEAN MODE) * {$matchCount}";
            $relevanceSum     = $relevanceSum + $matchCount;
        }

        $relevanceMatch = implode(' + ', $relevanceMatch);

        // restrict search to certain site types
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

                $binds['type' . $i] = array(
                    'value' => $datatypes[$i],
                    'type'  => \PDO::PARAM_STR
                );
            }

            $datatypeQuery .= ' )';
        }

        // field constraints
        $fieldConstraints      = $this->getAttribute('fieldConstraints');
        $whereFieldConstraints = '';

        if (!empty($fieldConstraints)) {
            $fieldConstraintsEntries = array();
            $i                       = 0;

            foreach ($fieldConstraints as $field => $constraintValues) {
                if (!in_array($field, $availableFields)) {
                    continue;
                }

                if (is_string($constraintValues)) {
                    $constraintValues = array($constraintValues);
                }

                $constraintEntriesOr = array();

                foreach ($constraintValues as $value) {
                    if (empty($value)) {
                        continue;
                    }

                    if (is_array($value)
                        && !empty($value['value'])
                        && !empty($value['type'])) {
                        if ($value['type'] === 'LIKE') {
                            $constraintEntriesOr[]    = $field . ' LIKE :constraint' . $i;
                            $binds['constraint' . $i] = array(
                                'value' => '%' . $value['value'] . '%',
                                'type'  => \PDO::PARAM_STR
                            );
                        }
                    } else {
                        $constraintEntriesOr[]    = $field . ' = :constraint' . $i;
                        $binds['constraint' . $i] = array(
                            'value' => $value,
                            'type'  => \PDO::PARAM_STR
                        );
                    }

                    $i++;
                }

                if (!empty($constraintEntriesOr)) {
                    $fieldConstraintsEntries[] = "(" . implode(" OR ", $constraintEntriesOr) . ")";
                }
            }

            if (!empty($fieldConstraintsEntries)) {
                $whereFieldConstraints = ' AND (' . implode(" AND ", $fieldConstraintsEntries) . ')';
            }
        }

        $minWordLength = QUI::getPackage('quiqqer/search')
            ->getConfig()
            ->get('search', 'booleanSearchMaxLength');

        // fallback
        if (!$minWordLength) {
            $minWordLength = 3;
        }

        $match = str_replace(array('*', '+'), '', $search);

        // order Fields
        $orderFields = $this->getAttribute('orderFields');
        $order       = '';

        if (is_array($orderFields) && !empty($orderFields)) {
            $order = implode(',', $orderFields) . ',';

            foreach ($orderFields as $orderField) {
                $orderFieldParts = explode(' ', $orderField);
                $orderField      = current($orderFieldParts);

                if (!in_array($orderField, $availableFields)) {
                    $availableFields[] = $orderField;
                }
            }
        }

        // query
        if (is_int(key($availableFields))) {
            $selectedFields = $availableFields;
        } else {
            $selectedFields = array_keys($availableFields);
        }

        // Relevance search (MATCH.. AGAINST)
        if ($this->getAttribute('relevanceSearch')
            && mb_strlen($match) >= $minWordLength
        ) {
            // filter $selectedFields
            $selectedFields = array_filter($selectedFields, function ($v) {
                return !in_array($v, array('urlParameter', 'siteId'));
            });

            $selectedFields = implode(',', $selectedFields);

            $query = "
                SELECT
                    siteId,
                    urlParameter,
                    100 / {$relevanceSum} * ({$relevanceMatch}) AS relevance,
                    {$selectedFields}
                FROM
                    {$table}
                WHERE
                    (MATCH ({$whereMatch}) AGAINST (:search IN BOOLEAN MODE))
                    {$datatypeQuery}
                    {$whereFieldConstraints}
                GROUP BY
                    urlParameter,siteId,{$selectedFields}
                ORDER BY
                    {$order}relevance DESC
            ";

            $search = str_replace('*', '', $search);

//            if (strlen($search) > 2 || $search == '%%') {
            $binds['search'] = array(
                'value' => $search,
                'type'  => \PDO::PARAM_STR
            );
//            }
        } else {
            $where = array();

            $searchFields = array(
                'name',
                'title',
                'short',
                'data'
            );

            $searchFields = array_merge($searchFields, $fields);
            $searchTerms  = explode(' ', $str);

            foreach ($searchTerms as $k => $searchTerm) {
                $whereOr = array();

                foreach ($searchFields as $field) {
                    $whereOr[] = $field . ' LIKE :search' . $k;
                }

                $binds['search' . $k] = array(
                    'value' => '%' . $searchTerm . '%',
                    'type'  => \PDO::PARAM_STR
                );

                $where[] = "(" . implode(" OR ", $whereOr) . ")";
            }

            if ($this->getAttribute('searchtype') === Search\Controls\Search::SEARCH_TYPE_AND) {
                $where = implode(" AND ", $where);
            } else {
                $where = implode(" OR ", $where);
            }

            // filter $selectedFields
            $selectedFields = array_filter($selectedFields, function ($v) {
                return !in_array($v, array('e_date', 'urlParameter', 'siteId'));
            });

            $selectedFields = implode(',', $selectedFields);

            $query = "
            SELECT e_date,urlParameter,siteId,{$selectedFields}
            FROM
                {$table}
            WHERE
                ({$where})
                {$whereFieldConstraints}
                {$datatypeQuery}
            GROUP BY
                urlParameter,siteId,e_date,{$selectedFields}
            ORDER BY
                {$order}e_date DESC
            ";
        }

        $selectQuery = "{$query} {$limit['limit']}";

        $countQuery = "
            SELECT COUNT(*) as count
            FROM ({$query}) as T
        ";

        // search
        $Statement = $PDO->prepare($selectQuery);
        $Statement->bindValue(
            ':limit1',
            $limit['prepare'][':limit1'][0],
            \PDO::PARAM_INT
        );

        $Statement->bindValue(
            ':limit2',
            $limit['prepare'][':limit2'][0],
            \PDO::PARAM_INT
        );

        foreach ($binds as $placeholder => $bind) {
            $Statement->bindValue(':' . $placeholder, $bind['value'], $bind['type']);
        }

        $Statement->execute();
        $result = $Statement->fetchAll(\PDO::FETCH_ASSOC);

        // count
        $Statement = $PDO->prepare($countQuery);

        foreach ($binds as $placeholder => $bind) {
            $Statement->bindValue(':' . $placeholder, $bind['value'], $bind['type']);
        }

        $Statement->execute();
        $count = $Statement->fetchAll(\PDO::FETCH_ASSOC);

        return array(
            'list'  => $result,
            'count' => $count[0]['count']
        );
    }

    /**
     * Sanitizes a search string
     *
     * @param string $str
     * @return string - sanitized string
     */
    protected function sanitizeSearchString($str)
    {
        /* http://www.regular-expressions.info/unicode.html#prop */
        $str = preg_replace("/[^\p{L}\p{N}\p{P}\-\+]/iu", " ", $str);
        $str = Orthos::clear($str);
        $str = preg_replace('#([ ]){2,}#', "$1", $str);
        $str = trim($str);

        return $str;
    }

    /**
     * Creation
     */

    /**
     * Add or set an entry to the fulltext search table
     *
     * @param Project $Project
     * @param integer $siteId
     * @param array $params
     * @param array $siteParams - optional; Parameter for the site link
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
     * @param Project $Project - Project
     * @param integer $siteId - ID of the site
     * @param array $siteParams - (optional); Parameter for the site link
     *
     * @return void
     */
    public static function removeEntry(
        Project $Project,
        $siteId,
        $siteParams = array()
    ) {
        $tbl = QUI::getDBProjectTableName(Search::TABLE_SEARCH_FULL, $Project);

        QUI::getDataBase()->delete($tbl, array(
            'siteId'       => (int)$siteId,
            'urlParameter' => json_encode($siteParams)
        ));
    }

    /**
     * Edit an entry to the fulltext search table
     *
     * @param Project $Project
     * @param integer $siteId
     * @param array $params
     * @param array $siteParams - optional; Parameter for the site link
     */
    public static function setEntryData(
        Project $Project,
        $siteId,
        $params = array(),
        $siteParams = array()
    ) {
        $table  = QUI::getDBProjectTableName(Search::TABLE_SEARCH_FULL, $Project);
        $fields = self::getFieldList();

        $urlParameter = json_encode($siteParams);
        $siteId       = (int)$siteId;

        // cannot set entry for inactive sites!
        try {
            $Site = $Project->get($siteId);

            if (!$Site->getAttribute('active')) {
                return;
            }
        } catch (\Exception $Exception) {
            return;
        }

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

        $data['datatype'] = $Site->getAttribute('type');

        QUI::getDataBase()->update($table, $data, array(
            'siteId'       => (int)$siteId,
            'urlParameter' => $urlParameter
        ));
    }

    /**
     * Append the data field of an specific search entry
     *
     * @param Project $Project
     * @param integer $siteId
     * @param string $data
     * @param array $siteParams
     */
    public static function appendFulltextSearchString(
        Project $Project,
        $siteId,
        $data = '',
        $siteParams = array()
    ) {
        // cannot set entry for inactive sites!
        try {
            $Site = $Project->get($siteId);

            if (!$Site->getAttribute('active')) {
                return;
            }
        } catch (\Exception $Exception) {
            return;
        }

        $table = QUI::getDBProjectTableName(
            Search::TABLE_SEARCH_FULL,
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
     * @param integer $siteId
     * @param array $siteParams
     *
     * @throws QUI\Exception
     */
    public static function getEntry(
        Project $Project,
        $siteId,
        $siteParams = array()
    ) {
        $table = QUI::getDBProjectTableName(
            Search::TABLE_SEARCH_FULL,
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
        QUI::getDataBase()->table()->truncate(
            QUI::getDBProjectTableName(Search::TABLE_SEARCH_FULL, $Project)
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
            set_time_limit(0);

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

                $c_date = $Site->getAttribute('c_date');
                $c_date = strtotime($c_date);

                if (!$c_date) {
                    $c_date = 0;
                }

                $Fulltext->setEntry($Project, $siteId, array(
                    'name'     => $Site->getAttribute('name'),
                    'title'    => $Site->getAttribute('title'),
                    'siteType' => $Site->getAttribute('type'),
                    'short'    => $Site->getAttribute('short'),
                    'data'     => $Site->getAttribute('content'),
                    'datatype' => $Site->getAttribute('type'),
                    'icon'     => $Site->getAttribute('image_site'),
                    'e_date'   => $e_date,
                    'c_date'   => $c_date
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
     * @return array
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

        foreach ($files as $package => $file) {
            $Dom  = QUI\Utils\Text\XML::getDomFromXml($file);
            $Path = new \DOMXPath($Dom);

            $fields = $Path->query("//quiqqer/search/searchfields/field");

            foreach ($fields as $Field) {
                /* @var $Field \DOMElement */
                $result[] = array(
                    'field'    => trim($Field->nodeValue),
                    'type'     => $Field->getAttribute('type'),
                    'fulltext' => $Field->getAttribute('fulltext') ? true : false,
                    'package'  => $package
                );
            }
        }

        QUI\Cache\Manager::set($cache, $result);

        return $result;
    }

    /**
     * Return the plugins with a search.xml file
     *
     * @return array
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
                $result[$package['name']] = $xmlFile;
            }
        }

        QUI\Cache\Manager::set($cache, $result);

        return $result;
    }
}
