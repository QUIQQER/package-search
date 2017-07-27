<?php

/**
 * This file contains QUI\Search\Bricks\Search
 */

namespace QUI\Search\Bricks;

use QUI;

/**
 * Class Search
 *
 * @package namerobot/template-namingservice
 */
class Search extends QUI\Control
{
    /**
     * constructor
     *
     * @param array $attributes
     */
    public function __construct($attributes = array())
    {
        // default options
        $this->setAttributes(array(
            'class'         => 'search-brick',
            'resultSite'    => false,
            'suggestSearch' => false

        ));

        parent::__construct($attributes);
    }

    /**
     * Return the inner body of the element
     * Can be overwritten
     *
     * @return String
     */
    public function getBody()
    {
        //QUI::getPackage('quiqqer/search');
        $Engine  = QUI::getTemplateManager()->getEngine();
        $Site    = $this->getAttribute('Site');
        $Project = $Site->getProject();

        $resultSite = $this->getAttribute('resultSite');

        if (!$resultSite) {
            $types = array(
                'quiqqer/sitetypes:types/search',
                'quiqqer/search:types/search'
            );

            $searchSites = $Project->getSites(array(
                'where' => array(
                    'type' => array(
                        'type'  => 'IN',
                        'value' => $types
                    )
                ),
                'limit' => 1
            ));

            if (count($searchSites)) {
                $resultSite = $searchSites[0]->getUrlRewritten();
            }
        }

        $suggestSearch = '';

        if ($this->getAttribute('suggestSearch')) {
            $suggestSearch = 'package/quiqqer/search/bin/controls/Suggest';
        }


        $Engine->assign(array(
            'this'          => $this,
            'resultSite'    => $resultSite,
            'suggestSearch' => $suggestSearch
        ));

        $this->addCSSFile(dirname(__FILE__) . '/Search.css');
        return $Engine->fetch(dirname(__FILE__) . '/Search.html');
    }
}
