<?php

namespace QUI\Search;

use QUI\Utils\Security\Orthos;

/**
 * Class Utils
 *
 * Utilities
 */
class Utils
{
    /**
     * Sanitizes a search string
     *
     * @param string $str
     * @return string - sanitized string
     */
    public static function sanitizeSearchString($str)
    {
        /* http://www.regular-expressions.info/unicode.html#prop */
        $str = \preg_replace("/[^\p{L}\p{N}\p{P}\-\+]/iu", " ", $str);
        $str = Orthos::clear($str);
        $str = \preg_replace('#([ ]){2,}#', "$1", $str);
        $str = \trim($str);

        return $str;
    }
}
