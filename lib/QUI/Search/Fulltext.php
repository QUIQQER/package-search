<?php

/**
 * This file contains \QUI\Search\Fulltext
 */

namespace QUI\Search;

use QUI\Search;
use QUI\Projects\Project;

/**
 * Fulltextsearch Manager
 *
 * @author www.pcsg.de (Henning Leutz)
 */
class Fulltext
{
    /**
     *
     * @param Project $Project
     * @param unknown $siteId
     * @param unknown $params
     * @param unknown $siteParams
     */
    static function addEntry(Project $Project, $siteId, $params=array(), $siteParams=array())
    {
        $table = \QUI::getDBProjectTableName( Search::tableSearchFull, $Project );

    }

}
