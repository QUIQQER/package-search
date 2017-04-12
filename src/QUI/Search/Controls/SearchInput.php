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

/**
 * Class SearchInput
 *
 * Input for search
 *
 * @package QUI\Tags\Controls
 */
class SearchInput extends QUI\Control
{
    /**
     * Site the control is on
     *
     * @var QUI\Projects\Site
     */
    protected $Site = null;

    /**
     * constructor
     *
     * @param array $attributes
     */
    public function __construct($attributes = array())
    {
        $this->Site = QUI::getRewrite()->getSite();

        $this->setAttributes(array(
            'search'     => '', // search term,
            'searchType' => Search::SEARCH_TYPE_OR,
            'fields'     => array()     // selected fields
        ));

        $this->setJavaScriptControl('package/quiqqer/search/bin/controls/SearchInput');
//        $this->setJavaScriptControlOption('');

        $this->addCSSClass('quiqqer-search-searchinput');
        $this->addCSSFile(dirname(__FILE__) . '/SearchInput.css');

        parent::__construct($attributes);
    }

    /**
     * (non-PHPdoc)
     *
     * @see \QUI\Control::create()
     */
    public function getBody()
    {
        $Engine = QUI::getTemplateManager()->getEngine();

        $Engine->assign(array(
            'Site'            => $this->Site,
            'availableFields' => $this->getAvailableFields(),
            'searchType'      => $this->getAttribute('searchType'),
            'search'          => $this->getAttribute('search'),
            'fields'          => $this->getAttribute('fields')
        ));

        return $Engine->fetch(dirname(__FILE__) . '/SearchInput.html');
    }

    /**
     * Get available search fields
     *
     * @return array
     */
    protected function getAvailableFields()
    {
        $allFields = array();

        foreach (Fulltext::getFieldList() as $entry) {
            $allFields[] = $entry['field'];
        }

        $settingsFields = $this->Site->getAttribute('quiqqer.settings.search.list.fields');

        if (empty($settingsFields)
            || !is_array($settingsFields)
        ) {
            return $allFields;
        }

        return $settingsFields;
    }
}
