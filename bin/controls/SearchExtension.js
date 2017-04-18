/**
 * SearchExtension JavaScript Control
 *
 * Search extensions can extend the basic search control by setting
 * search parameters
 *
 * @module package/quiqqer/search/bin/controls/SearchExtension
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/controls/Control
 * @require package/quiqqer/search/bin/SearchUtils
 */
define('package/quiqqer/search/bin/controls/SearchExtension', [

    'qui/controls/Control',
    'package/quiqqer/search/bin/SearchUtils'

], function (QUIControl, SearchUtils) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/search/bin/controls/SearchExtension',

        Binds: [
            'getSearchTerms',
            'getSearchParams'
        ],

        initialize: function (options) {
            this.parent(options);

            var self           = this;
            this.$Search       = null;
            this.$searchTerms  = [];
            this.$SearchParams = {};

            SearchUtils.getSearchControl().then(function (SearchControl) {
                self.$Search = SearchControl;
                self.$Search.registerSearchExtension(self);
            });
        },

        /**
         * Get search terms
         *
         * @return {Array}
         */
        getSearchTerms: function () {
            return this.$searchTerms;
        },

        /**
         * Get search parameters
         *
         * @return {Object}
         */
        getSearchParams: function () {
            return this.$SearchParams;
        }
    });
});
