/**
 * SearchExtension JavaScript Control
 *
 * Search extensions can extend the basic search control by setting
 * search parameters
 *
 * @module package/quiqqer/search/bin/controls/SearchExtension
 * @author www.pcsg.de (Patrick Müller)
 */
define('package/quiqqer/search/bin/controls/SearchExtension', [

    'qui/controls/Control',
    'package/quiqqer/search/bin/SearchUtils',
    'package/quiqqer/search/bin/controls/Search',

    'URI'

], function (QUIControl, SearchUtils, Search, URI) {
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

            var self               = this;
            this.$Search           = null;
            this.$searchTerms      = [];
            this.$SearchParams     = {};
            this.$FieldConstraints = {};

            SearchUtils.getSearchControl().then(function (SearchControl) {
                self.$Search = SearchControl;
                self.$Search.registerSearchExtension(self);
            }, function() {
                self.$Search = new Search({
                    async: false
                });

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
         * Get field constraints
         *
         * @return {Array}
         */
        getFieldConstraints: function () {
            return this.$FieldConstraints;
        },

        /**
         * Set URI based on given uri parameters
         *
         * @param {Object} UriParams - Uri parameters
         */
        $setUri: function (UriParams) {
            var Uri = new URI();

            // keep url params that are not set by this class
            var ExternalUrlParams = Uri.search(true);
            Uri.search(Object.merge(ExternalUrlParams, UriParams));

            var url = Uri.toString();

            if ("history" in window) {
                window.history.pushState({}, "", url);
                window.fireEvent('popstate');
            } else {
                window.location = url;
            }
        }
    });
});
