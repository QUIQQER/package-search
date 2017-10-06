<?php

/**
 * This file contains QUI\Tags\Controls\TagMenu
 */

namespace QUI\Search\Controls;

use QUI;
use QUI\Search\Fulltext;
use QUI\Utils\Security\Orthos;
use QUI\Projects\Site;
use QUI\Utils\StringHelper;
use QUI\Bricks\Controls\Pagination;
use QUI\Controls\ChildrenList;
use QUI\Rating\Handler as RatingHandler;

/**
 * Class Search
 *
 * Display search results
 *
 * @package QUI\Tags\Controls
 */
class Search extends QUI\Control
{
    const SEARCH_TYPE_OR = 'OR';
    const SEARCH_TYPE_AND = 'AND';

    const PAGINATION_TYPE_PAGINATION = 'pagination';
    const PAGINATION_TYPE_INIFINITESCROLL = 'infinitescroll';

    /**
     * Site the control is on
     *
     * @var QUI\Projects\Site
     */
    protected $Site = null;

    /**
     * Search results runtime cache
     *
     * @var array
     */
    protected $searchResults = null;

    /**
     * constructor
     *
     * @param array $attributes
     */
    public function __construct($attributes = array())
    {
        if (isset($attributes['Site'])
            && $attributes['Site'] instanceof Site
        ) {
            $this->Site = $attributes['Site'];
            unset($attributes['Site']);
        } else {
            $this->Site = QUI::getRewrite()->getSite();
        }

        $directory = dirname(dirname(dirname(dirname(dirname(__FILE__)))));

        $this->setAttributes(array(
            'search'               => '',
            // search term
            'searchType'           => $this::SEARCH_TYPE_OR,
            'max'                  => $this->Site->getAttribute('quiqqer.settings.search.list.max') ?: 10,
            'searchFields'         => $this->getDefaultSearchFields(),
            'fieldConstraints'     => array(),
            // restrict search to certain site types
            'datatypes'            => array(),
            'sheet'                => 1,
            // "pagination" or "infinitescroll" (determined by getPaginationType())
            'paginationType'       => false,
            // use Fulltext relevance search
            'relevanceSearch'      => true,
            'childrenListTemplate' => $directory . '/templates/SearchResultList.html',
            'childrenListCss'      => $directory . '/templates/SearchResultList.css',
            'showResultCount'      => true
        ));

        // set attributes
        parent::__construct($attributes);

        // sanitize attributes
        $this->sanitizeAttribues();

        // set javascript control data
        $this->setJavaScriptControl('package/quiqqer/search/bin/controls/Search');
        $this->setJavaScriptControlOption('searchparams', json_encode($this->getJavaScriptControlAttributes()));

        // set template data
        $this->addCSSClass('quiqqer-search');
        $this->addCSSFile(dirname(__FILE__) . '/Search.css');
    }

    /**
     * Execute search and return search result information
     *
     * @return array
     */
    public function search()
    {
        if (!is_null($this->searchResults)) {
            return $this->searchResults;
        }

        $search   = $this->getAttribute('search');
        $Project  = $this->Site->getProject();
        $max      = $this->getAttribute('max');
        $sheet    = $this->getAttribute('sheet');
        $children = array();

        $siteTypesFilter = $this->getAttribute('datatypes');

        if (empty($siteTypesFilter)) {
            $siteTypesFilter = $this->Site->getAttribute('quiqqer.settings.search.sitetypes.filter');

            if (!empty($siteTypesFilter)) {
                $siteTypesFilter = explode(';', $siteTypesFilter);
            } else {
                $siteTypesFilter = array();
            }
        }

        $FulltextSearch = new Fulltext(array(
            'limit'            => (($sheet - 1) * $max) . ',' . $max,
            'fields'           => $this->getAttribute('searchFields'),
            'fieldConstraints' => $this->getAttribute('fieldConstraints'),
            'searchtype'       => $this->getAttribute('searchType'),
            'Project'          => $this->Site->getProject(),
            'relevanceSearch'  => $this->getAttribute('relevanceSearch'),
            'datatypes'        => $siteTypesFilter
        ));

        $result = $FulltextSearch->search($search);

        foreach ($result['list'] as $entry) {
            try {
                // immer neues site objekt
                // falls die gleiche seite mit unterschiedlichen url params existiert
                $ResultSite = $Project->get((int)$entry['siteId']);//new Site($Project, );
                $urlParams  = json_decode($entry['urlParameter'], true);

                if (!is_array($urlParams)) {
                    $urlParams = array();
                }

                $url = $ResultSite->getUrlRewritten($urlParams);
                $url = StringHelper::replaceDblSlashes($url);

                if (!isset($entry['relevance']) || $entry['relevance'] > 100) {
                    $entry['relevance'] = 100;
                }

                $ResultSite->setAttribute('search-name', $entry['name']);
                $ResultSite->setAttribute('search-title', $entry['title']);
                $ResultSite->setAttribute('search-short', $entry['short']);
                $ResultSite->setAttribute('search-relevance', $entry['relevance']);
                $ResultSite->setAttribute('search-url', $url);
                $ResultSite->setAttribute('search-icon', $entry['icon']);

                $children[] = $ResultSite;
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::addDebug($Exception->getMessage());
            }
        }

        $sheets = (int)ceil($result['count'] / $max);
        $count  = (int)$result['count'];

        $this->searchResults = array(
            'count'    => $count,
            'max'      => $max,
            'sheets'   => $sheets,
            'children' => $children,
            'more'     => $sheet < $sheets
        );

        return $this->searchResults;
    }

    /**
     * Get children list (search results)
     *
     * @return ChildrenList
     */
    public function getChildrenList()
    {
        $searchResult = $this->search();

        $params = array(
            'showTitle'                  => false,
            'Site'                       => $this->Site,
            'limit'                      => $searchResult['max'],
            'showDate'                   => $this->Site->getAttribute('quiqqer.settings.sitetypes.list.showDate'),
            'showCreator'                => $this->Site->getAttribute('quiqqer.settings.sitetypes.list.showCreator'),
            'showTime'                   => true,
            'showSheets'                 => false,
            'showImages'                 => $this->Site->getAttribute('quiqqer.settings.sitetypes.list.showImages'),
            'showShort'                  => true,
            'showHeader'                 => true,
            'showContent'                => false,
            'itemtype'                   => 'http://schema.org/ItemList',
            'child-itemtype'             => 'http://schema.org/ListItem',
            'display'                    => $this->Site->getAttribute('quiqqer.settings.sitetypes.list.template'),
            'children'                   => $searchResult['children'],
            'loadAllChildrenOnEmptyList' => false
        );

        if (!$this->Site->getAttribute('quiqqer.settings.sitetypes.list.template')) {
            if ($this->getAttribute('childrenListTemplate')) {
                $params['displayTemplate'] = $this->getAttribute('childrenListTemplate');
            }

            if ($this->getAttribute('childrenListCss')) {
                $params['displayCss'] = $this->getAttribute('childrenListCss');
            }
        }

        if ($this->Site->getAttribute('quiqqer.settings.sitetypes.list.template') == 'standardSearch') {
            $params['displayTemplate'] = $this->getAttribute('childrenListTemplate');
            $params['displayCss']      = $this->getAttribute('childrenListCss');
        }

        $childrenListAttributes = $this->getAttribute('childrenListAttributes');

        if (!empty($childrenListAttributes)
            && is_array($childrenListAttributes)
        ) {
            $params = array_merge($childrenListAttributes, $params);
        }

        return new ChildrenList($params);
    }

    /**
     * (non-PHPdoc)
     *
     * @see \QUI\Control::create()
     */
    public function getBody()
    {
        $Engine       = QUI::getTemplateManager()->getEngine();
        $search       = $this->getAttribute('search');
        $fields       = $this->getAttribute('searchFields');
        $searchResult = $this->search();

        // sync pagination
        $Pagination = new Pagination(array(
            'Site'      => $this->Site,
            'count'     => $searchResult['count'],
            'showLimit' => false,
            'limit'     => $searchResult['max'],
            'useAjax'   => false
        ));

        $Pagination->loadFromRequest();

        $Pagination->setGetParams('search', $search);
        $Pagination->setGetParams('searchIn', implode(',', $fields));

        $Engine->assign('Pagination', $Pagination);

        // async Pagination
        $PaginationAsync = new Pagination(array(
            'Site'      => $this->Site,
            'count'     => $searchResult['count'],
            'showLimit' => false,
            'limit'     => $searchResult['max'],
            'useAjax'   => true
        ));

        $PaginationAsync->loadFromRequest();

        $PaginationAsync->setGetParams('search', $search);
        $PaginationAsync->setGetParams('searchIn', implode(',', $fields));

        $Engine->assign('PaginationAsync', $PaginationAsync);

        $Engine->assign(array(
            'count'           => $searchResult['count'],
            'sheets'          => $searchResult['sheets'],
            'more'            => $searchResult['more'],
            'searchValue'     => $search,
            'searchType'      => $this->getAttribute('searchType'),
            'availableFields' => $this->Site->getAttribute('quiqqer.settings.search.list.fields'),
            'ChildrenList'    => $this->getChildrenList(),
            'paginationType'  => $this->getPaginationType(),
            'showResultCount' => $this->getAttribute('showResultCount')
        ));

        $this->setJavaScriptControlOption('resultcount', $searchResult['count']);

        return $Engine->fetch(dirname(__FILE__) . '/Search.html');
    }

    /**
     * Set control attributes by reading $_REQUEST
     *
     * @return void
     */
    public function setAttributesFromRequest()
    {
        // requests
        if (isset($_REQUEST['sheet'])) {
            $this->setAttribute('sheet', $_REQUEST['sheet']);
        }

        if (isset($_REQUEST['search'])) {
            $this->setAttribute('search', $_REQUEST['search']);
        }

        if (isset($_REQUEST['searchType'])
            && $_REQUEST['searchType'] == self::SEARCH_TYPE_AND
        ) {
            $this->setAttribute('searchType', self::SEARCH_TYPE_AND);
        }

        $fields = array();

        // search fields
        if (isset($_REQUEST['searchIn'])) {
            if (!is_array($_REQUEST['searchIn'])) {
                $_REQUEST['searchIn'] = explode(',', urldecode($_REQUEST['searchIn']));
            }

            foreach ($_REQUEST['searchIn'] as $field) {
                if (!is_string($field)) {
                    continue;
                }

                $fields[] = Orthos::clear($field);
            }

            $this->setAttribute('searchFields', $fields);
        }

        $this->sanitizeAttribues();
    }

    /**
     * Clears the given search fields (remove invalid fields)
     *
     * @param array $fields
     * @return array - cleared fields
     */
    protected function clearSearchFields($fields)
    {
        if (!is_array($fields)
            || empty($fields)
        ) {
            return $this->getDefaultSearchFields();
        }

        $allFields = array();

        foreach (Fulltext::getFieldList() as $entry) {
            $allFields[] = $entry['field'];
        }

        $settingsFields = $this->Site->getAttribute('quiqqer.settings.search.list.fields');
        $filteredFields = array();

        if (!empty($settingsFields)
            && is_array($settingsFields)
        ) {
            $settingsFields = array_flip($settingsFields);

            foreach ($fields as $field) {
                if (isset($settingsFields[$field])) {
                    $filteredFields[] = $field;
                }
            }

            return $filteredFields;
        }

        $allFields = array_flip($allFields);

        foreach ($fields as $field) {
            if (isset($allFields[$field])) {
                $filteredFields[] = $field;
            }
        }

        return $filteredFields;
    }

    /**
     * Check all attributes for validity (and sets invalid attributes to valid/default value)
     *
     * @return void
     */
    protected function sanitizeAttribues()
    {
        $attributes = $this->getAttributes();

        foreach ($attributes as $k => $v) {
            switch ($k) {
                case 'search':
                    if (!is_string($v)) {
                        $v = '';
                        break;
                    }

                    $v = self::sanitizeSearchString($v);
                    break;

                case 'searchType':
                    if ($v !== $this::SEARCH_TYPE_OR) {
                        $settingsFields = $this->Site->getAttribute('quiqqer.settings.search.list.fields');

                        if (is_array($settingsFields)) {
                            if (in_array('searchTypeAnd', $settingsFields)) {
                                $v = $this::SEARCH_TYPE_AND;
                            } else {
                                $v = $this::SEARCH_TYPE_OR;
                            }
                        } else {
                            $v = $this::SEARCH_TYPE_AND;
                        }
                    }
                    break;

                case 'max':
                case 'sheet':
                    $v = (int)$v;
                    break;

                case 'searchFields':
                    if (!is_array($v)) {
                        $v = $this->getDefaultSearchFields();
                        break;
                    }

                    $v = $this->clearSearchFields($v);
                    break;

                case 'suggestSearch':
                case 'relevanceSearch':
                    $v = $v ? true : false;
                    break;

                case 'fieldConstraints':
                    if (!is_array($v)) {
                        $v = array();
                        break;
                    }

                    $fields      = $this->clearSearchFields(array_keys($v));
                    $constraints = array();

                    foreach ($v as $field => $constraint) {
                        if (!in_array($field, $fields)) {
                            continue;
                        }

                        if (!is_array($constraint)
                            && !is_string($constraint)
                        ) {
                            continue;
                        }

                        $constraints[$field] = array();

                        if (is_array($constraint)) {
                            foreach ($constraint as $value) {
                                if (!is_string($value)) {
                                    continue;
                                }

                                $constraints[$field][] = self::sanitizeSearchString($value);
                            }

                            continue;
                        }

                        $constraints[$field][] = self::sanitizeSearchString($constraint);
                    }

                    $v = $constraints;
                    break;

                case 'paginationType':
                    if (empty($v)) {
                        $v = $this->getPaginationType();
                    }
                    break;

                case 'childrenListTemplate':
                    $directory = dirname(dirname(dirname(dirname(dirname(__FILE__)))));

                    if (!file_exists($v)) {
                        $v = $directory . '/templates/SearchResultList.html';
                    }
                    break;

                case 'childrenListCss':
                    $directory = dirname(dirname(dirname(dirname(dirname(__FILE__)))));

                    if (!file_exists($v)) {
                        $v = $directory . '/templates/SearchResultList.css';
                    }
                    break;
            }

            $attributes[$k] = $v;
        }

        $this->setAttributes($attributes);
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
        $str = preg_replace("/[^\p{L}\p{N}\p{P}\-]/iu", " ", $str);
        $str = Orthos::clear($str);
        $str = preg_replace('#([ ]){2,}#', "$1", $str);
        $str = trim($str);

        return $str;
    }

    /**
     * Get the default search fields
     *
     * @return array
     */
    protected function getDefaultSearchFields()
    {
        $allFields = array();

        foreach (Fulltext::getFieldList() as $entry) {
            $allFields[] = $entry['field'];
        }

        $settingsFields         = $this->Site->getAttribute('quiqqer.settings.search.list.fields');
        $settingsFieldsSelected = $this->Site->getAttribute(
            'quiqqer.settings.search.list.fields.selected'
        );

        if (!is_array($settingsFields)) {
            $settingsFields = array();
        }

        if (!is_array($settingsFieldsSelected)) {
            $settingsFieldsSelected = array();
        }

        // if no available fields have been set for the site, use all fields
        if (empty($settingsFields)) {
            return $allFields;
        }

        // if no available fields have been selected by the user, use all fields
        if (empty($settingsFieldsSelected)) {
            return $settingsFields;
        }

        return $settingsFieldsSelected;
    }

    /**
     * Get attributes for the javascript control
     *
     * @return array
     */
    protected function getJavaScriptControlAttributes()
    {
        $attributes = $this->getAttributes();

        foreach ($attributes as $k => $v) {
            if (is_string($v)) {
                $attributes[$k] = str_replace(OPT_DIR, '', $v);
            }
        }

        return $attributes;
    }

    /**
     * Get search list pagination type
     *
     * @return string|false - pagination type or false if no pagination required
     */
    protected function getPaginationType()
    {
        $paginationType = $this->getAttribute('paginationType');

        if ($paginationType === false) {
            return false;
        }

        if (empty($paginationType)) {
            $paginationType = $this->Site->getAttribute('quiqqer.search.sitetypes.search.pagination.type');
        }

        if (empty($paginationType)) {
            $paginationType = $this::PAGINATION_TYPE_PAGINATION;
        }

        return $paginationType;
    }
}
