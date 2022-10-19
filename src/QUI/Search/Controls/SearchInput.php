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
    public function __construct($attributes = [])
    {
        $this->Site = QUI::getRewrite()->getSite();

        $this->setAttributes([
            'search'            => '', // search term,
            'searchType'        => Search::SEARCH_TYPE_OR,
            'availableFields'   => $this->getAllAvailableFields(),
            'fields'            => [],     // selected fields
            'suggestSearch'     => true,
            'placeholder'       => QUI::getLocale()->get('quiqqer/search', 'tpl.search.placeholder'),
            'showFieldSettings' => true,
            'submitIcon'        => false
        ]);

        $this->addCSSClass('quiqqer-search-searchinput');
        $this->addCSSFile(dirname(__FILE__).'/SearchInput.css');

        parent::__construct($attributes);

        $this->setJavaScriptControl('package/quiqqer/search/bin/controls/SearchInput');
        $this->setJavaScriptControlOption('suggestsearch', $this->getAttribute('suggestSearch'));
        $this->setJavaScriptControlOption('showfieldsettings', $this->getAttribute('showFieldSettings'));

        $this->sanitizeFields();
    }

    /**
     * (non-PHPdoc)
     *
     * @see \QUI\Control::create()
     */
    public function getBody()
    {
        $Engine = QUI::getTemplateManager()->getEngine();

        $showFieldSettings = $this->getAttribute('showFieldSettings');
        $availableFields   = $this->getAttribute('availableFields');

        if (empty($availableFields)) {
            $showFieldSettings = false;
        }

        $Engine->assign([
            'Site'              => $this->Site,
            'searchType'        => $this->getAttribute('searchType'),
            'search'            => $this->getAttribute('search'),
            'availableFields'   => $availableFields,
            'fields'            => $this->getAttribute('fields'),
            'suggestSearch'     => $this->getAttribute('suggestSearch'),
            'placeholder'       => $this->getAttribute('placeholder'),
            'showFieldSettings' => $showFieldSettings,
            'submitIcon'        => $this->getAttribute('submitIcon')
        ]);

        return $Engine->fetch(\dirname(__FILE__).'/SearchInput.html');
    }

    /**
     * Set control attributes by reading $_REQUEST
     *
     * @return void
     */
    public function setAttributesFromRequest()
    {
        // requests
        if (isset($_REQUEST['searchterms'])) {
            $this->setAttribute('search', $_REQUEST['searchterms']);
        }

        if (isset($_REQUEST['searchType'])
            && $_REQUEST['searchType'] == Search::SEARCH_TYPE_AND
        ) {
            $this->setAttribute('searchType', Search::SEARCH_TYPE_AND);
        }

        // search fields
        if (isset($_REQUEST['searchIn'])) {
            $fields = [];

            if (!\is_array($_REQUEST['searchIn'])) {
                $_REQUEST['searchIn'] = \explode(',', \urldecode($_REQUEST['searchIn']));
            }

            foreach ($_REQUEST['searchIn'] as $field) {
                if (!\is_string($field)) {
                    continue;
                }

                $fields[] = Orthos::clear($field);
            }

            $this->setAttribute('fields', $fields);
        }

        $this->sanitizeFields();
    }

    /**
     * Sanitizes fields
     */
    protected function sanitizeFields()
    {
        // available fields
        $allFields       = $this->getAllAvailableFields();
        $availableFields = $this->getAttribute('availableFields');

        if (!\is_array($availableFields)
            /*|| empty($availableFields)*/
        ) {
            $availableFields = $allFields;
        } else {
            foreach ($availableFields as $k => $field) {
                if (!\in_array($field, $allFields)) {
                    unset($availableFields[$k]);
                }
            }
        }

        // selected fields
        $selectedFields = $this->getAttribute('fields');

        if (!\is_array($selectedFields)) {
            $selectedFields = [];
        }

        foreach ($selectedFields as $k => $field) {
            if (!\in_array($field, $availableFields)) {
                unset($selectedFields[$k]);
            }
        }

        $this->setAttribute('availableFields', $availableFields);
        $this->setAttribute('fields', $selectedFields);
    }

    /**
     * Get all available search fields
     *
     * @return array
     */
    protected function getAllAvailableFields()
    {
        $allFields = [];

        foreach (Fulltext::getFieldList() as $entry) {
            $allFields[] = $entry['field'];
        }

        return $allFields;
    }
}
