/**
 * Main Search control
 *
 * @module package/quiqqer/search/bin/controls/Search
 * @author www.pcsg.de (Henning Leutz)
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require qui/controls/buttons/Button
 * @require qui/controls/loader/Loader
 * @require utils/Controls
 * @require Ajax
 * @require Locale
 *
 * @event onSearch [SearchResult, this]
 */
define('package/quiqqer/search/bin/controls/Search', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/buttons/Button',
    'qui/controls/loader/Loader',
    'utils/Controls',

    'Ajax',
    'Locale'

    //'css!package/quiqqer/search/bin/controls/Search.css'

], function (QUI, QUIControl, QUIButton, QUILoader, ControlUtils, QUIAjax, QUILocale) {
    "use strict";

    var lg = 'quiqqer/search';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/search/bin/controls/Search',

        Binds: [
            '$onImport',
            '$submit',
            '$onPaginationChange',
            'setSearchParams',
            'search'
        ],

        options: {
            name       : 'search',
            placeholder: 'Search...',
            delay      : 300
        },

        initialize: function (options) {
            this.parent(options);

            this.$Elm                 = null;
            this.$Results             = null;
            this.$PaginationTop       = null;
            this.$PaginationTopElm    = null;
            this.$PaginationBottom    = null;
            this.$PaginationBottomElm = null;
            this.$ResultCountElm      = null;
            this.$SearchParams        = {};
            this.Loader               = new QUILoader();
            this.$lockPagination      = false;

            this.addEvents({
                onImport: this.$onImport,
                onSearch: this.$onSearch
            });
        },

        /**
         * Event: onImport
         */
        $onImport: function () {
            var self  = this;
            this.$Elm = this.getElm();

            this.Loader.inject(this.$Elm);

            this.$Results = this.$Elm.getElement(
                '.quiqqer-search-results'
            );

            this.$ResultCountElm = this.$Elm.getElement(
                '.quiqqer-search-result-count'
            );

            this.Loader.show();

            this.$parsePaginationControls().then(function () {
                self.Loader.hide();
            });
        },

        /**
         * Parse async pagination controls
         *
         * @return {Promise}
         */
        $parsePaginationControls: function () {
            var self = this;

            this.$PaginationTopElm = this.$Elm.getElement(
                '.quiqqer-search-pagination-top .quiqqer-pagination'
            );

            this.$PaginationBottomElm = this.$Elm.getElement(
                '.quiqqer-search-pagination-bottom .quiqqer-pagination'
            );

            return new Promise(function (resolve, reject) {
                Promise.all([
                    ControlUtils.getControlByElement(self.$PaginationTopElm),
                    ControlUtils.getControlByElement(self.$PaginationBottomElm)
                ]).then(function (result) {
                    self.$PaginationTop    = result[0];
                    self.$PaginationBottom = result[1];

                    self.$PaginationTop.addEvent('change', self.$onPaginationChange);
                    self.$PaginationBottom.addEvent('change', self.$onPaginationChange);

                    self.$Elm.getElement(
                        '.quiqqer-search-pagination-top'
                    ).setStyle('display', 'block');

                    self.$Elm.getElement(
                        '.quiqqer-search-pagination-bottom'
                    ).setStyle('display', 'block');

                    resolve();
                }, reject);
            });
        },

        /**
         * Submit search
         */
        $submit: function () {
            var SearchAttributes = {
                searchType  : 'OR',
                searchFields: []
            };

            var settingInputs = this.$SettingsElm.getElements(
                '.qui-search-searchinput-settings-setting'
            );

            settingInputs.each(function (Input) {
                switch (Input.getProperty('name')) {
                    case 'searchType':
                        if (Input.checked) {
                            SearchAttributes.searchType = 'AND';
                        }
                        break;

                    default:
                        if (Input.checked) {
                            SearchAttributes.searchFields.push(Input.value);
                        }
                }
            });
        },

        /**
         * Handles changing of pagination controls
         *
         * @param {Object} Control
         * @param {Object} Sheet
         * @param {Object} Query
         */
        $onPaginationChange: function (Control, Sheet, Query) {
            if (this.$lockPagination) {
                return;
            }

            this.setSearchParams({
                max: Query.limit
            });

            this.search(Query.sheet);
        },

        /**
         * Set search parameters
         *
         * @param {Object} [SearchParams] - Custom search params
         */
        setSearchParams: function (SearchParams) {
            this.$SearchParams = Object.merge(this.$SearchParams, SearchParams);
        },

        /**
         * Execute search
         *
         * @param {number} [sheet] - The sheet (page) to start search with (if omitted, use 1)
         * @return {Promise}
         */
        search: function (sheet) {
            var self = this;

            sheet = parseInt(sheet);

            if (sheet < 1) {
                this.$SearchParams.sheet = 1;
            } else {
                this.$SearchParams.sheet = sheet;
            }

            this.Loader.show();

            return new Promise(function (resolve, reject) {
                QUIAjax.get(
                    'package_quiqqer_search_ajax_search',
                    function (SearchResult) {
                        self.fireEvent('search', [SearchResult, self]);
                        resolve();
                    }, {
                        'package'   : 'quiqqer/search',
                        searchParams: JSON.encode(self.$SearchParams),
                        project     : JSON.encode(QUIQQER_PROJECT),
                        siteId      : QUIQQER_SITE.id,
                        onError     : reject
                    }
                )
            });
        },

        /**
         * Event: onSearch
         *
         * Parse search results
         *
         * @param {Object} SearchResult
         */
        $onSearch: function (SearchResult) {
            this.$Results.set('html', SearchResult.childrenListHtml);
            this.Loader.hide();

            // handle pagination controls
            if (!SearchResult.count) {
                this.$PaginationTopElm.setStyle('display', 'none');
                this.$PaginationBottomElm.setStyle('display', 'none');
            } else {
                this.$PaginationTopElm.setStyle('display', '');
                this.$PaginationBottomElm.setStyle('display', '');

                this.$lockPagination = true;
                this.$PaginationTop.setPageCount(SearchResult.sheets);
                this.$PaginationBottom.setPageCount(SearchResult.sheets);
                this.$lockPagination = false;
            }

            // set result count
            if (!SearchResult.count) {
                this.$ResultCountElm.set(
                    'html',
                    QUILocale.get(lg, 'tpl.search.no.results')
                );
            } else if (SearchResult.count === 1) {
                this.$ResultCountElm.set(
                    'html',
                    QUILocale.get(lg, 'tpl.search.one.result')
                );
            } else {
                this.$ResultCountElm.set(
                    'html',
                    QUILocale.get(lg, 'tpl.search.more.result', {
                        count: SearchResult.count
                    })
                );
            }
        },

        /**
         * Toggle display of search settings
         */
        $toggleSettings: function () {
            var displayStatus = this.$SettingsElm.getStyle('display');

            if (displayStatus === 'none') {
                this.$SettingsElm.setStyle('display', 'block');

                return;
            }

            this.$SettingsElm.setStyle('display', 'none');
        }
    });
});
