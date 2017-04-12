/**
 * JavaScript SearchUtils
 *
 * @module package/quiqqer/search/bin/classes/SearchUtils
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require Ajax
 */
define('package/quiqqer/search/bin/classes/SearchUtils', [

    'utils/Controls'

], function (ControlUtils) {
    "use strict";

    return new Class({

        Type: 'package/quiqqer/search/bin/classes/SearchUtils',

        /**
         * Looks for a Search control in the document and return it
         *
         * @return {Promise}
         */
        getSearchControl: function()
        {
            var resultListElms = document.getElementsByClassName('quiqqer-search');

            if (!resultListElms.length) {
                return Promise.reject('No Search control found.');
            }

            return ControlUtils.getControlByElement(resultListElms[0]);
        }
    });
});
