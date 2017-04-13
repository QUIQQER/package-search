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
    'qui/utils/Elements',
    'utils/Controls',

    'Ajax',
    'Locale'

    //'css!package/quiqqer/search/bin/controls/Search.css'

], function (QUI, QUIControl, QUIButton, QUILoader, QUIElementUtils, ControlUtils,
             QUIAjax, QUILocale) {
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
            name          : 'search',
            placeholder   : 'Search...',
            delay         : 300,
            paginationtype: 'pagination',
            max           : false,
            sheet         : 1
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
            this.$MoreBtn             = null;
            this.$moreBtnClicked      = 0;
            this.$loadingMore         = false;
            this.$moreBtnVisible      = false;

            this.addEvents({
                onImport: this.$onImport
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

            this.$paginationType = this.getAttribute('paginationtype');

            if (this.$paginationType !== 'pagination') {
                this.$initializeInfiniteScrolling();
                return;
            }

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
         * Initialize infinite scrolling
         */
        $initializeInfiniteScrolling: function () {
            var self = this;

            this.$sheet = parseInt(this.getAttribute('sheet'));

            this.$MoreBtn = this.$Elm.getElement(
                '.quiqqer-search-pagination-inifinitescroll-more-btn'
            );

            if (!this.$MoreBtn.getProperty('data-hidden')) {
                this.$moreBtnVisible = true;
            }

            this.$FXMore = moofx(this.$MoreBtn.getParent());

            var FuncExecuteNextSearch = function () {
                self.setSearchParams({
                    sheet: ++self.$sheet
                });

                self.$loadingMore = true;

                var oldButtonText = self.$MoreBtn.get('text');

                self.$MoreBtn.set(
                    'html',
                    '<span class="fa fa-spinner fa-spin"></span>' +
                    '<span class="loading-btn-text">' +
                    QUILocale.get(lg, 'tpl.search.pagination.inifinitescroll.btn.loading') +
                    '</span>'
                );

                self.$MoreBtn.setStyle('color', null);
                self.$MoreBtn.addClass('loading');

                self.search().then(function () {
                    self.$loadingMore = false;

                    self.$MoreBtn.set({
                        html  : oldButtonText,
                        styles: {
                            width: null
                        }
                    });

                    self.$MoreBtn.removeClass('loading');
                });
            };

            this.$MoreBtn.addEvent('click', function (event) {
                event.stop();

                self.$moreBtnClicked++;
                FuncExecuteNextSearch();
            });

            QUI.addEvent('scroll', function () {
                if (!self.$MoreBtn) {
                    return;
                }

                if (self.$moreBtnClicked < 3) {
                    return;
                }

                if (self.$loadingMore) {
                    return;
                }

                if (!self.$moreBtnVisible) {
                    return;
                }

                var isInView = QUIElementUtils.isInViewport(self.$MoreBtn);

                if (!isInView) {
                    return;
                }

                FuncExecuteNextSearch();
            });
        },

        /**
         * hide the more button
         *
         * @return {Promise}
         */
        $hideMoreButton: function () {
            if (!this.$MoreBtn) {
                return Promise.resolve();
            }

            this.$MoreBtn.addClass('disabled');
            this.$MoreBtn.setStyle('cursor', 'default');
            this.$moreBtnVisible = false;

            return new Promise(function (resolve) {
                this.$FXMore.animate({
                    opacity: 0
                }, {
                    duration: 200,
                    callback: resolve
                });
            }.bind(this));
        },

        /**
         * shows the more button
         *
         * @return {Promise}
         */
        $showMoreButton: function () {
            if (!this.$MoreBtn) {
                return Promise.resolve();
            }

            return new Promise(function (resolve) {
                this.$FXMore.animate({
                    opacity: 1
                }, {
                    duration: 200,
                    callback: function () {
                        this.$MoreBtn.removeClass('disabled');
                        this.$MoreBtn.setStyle('cursor', null);
                        this.$moreBtnVisible = true;
                        resolve();
                    }.bind(this)
                });
            }.bind(this));
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

            var self = this;

            this.$lockPagination = true;
            this.$PaginationTop.setPage(Query.sheet - 1);
            this.$PaginationBottom.setPage(Query.sheet - 1);
            this.$lockPagination = false;

            this.setSearchParams({
                max: Query.limit
            });

            this.setSearchParams({
                sheet: Query.sheet
            });

            this.search().then(function (SearchResult) {
                // handle pagination controls
                if (!SearchResult.count) {
                    self.$hidePagination();
                } else {
                    self.$showPagination();

                    self.$lockPagination = true;
                    self.$PaginationTop.setPageCount(SearchResult.sheets);
                    self.$PaginationBottom.setPageCount(SearchResult.sheets);
                    self.$lockPagination = false;
                }
            });
        },

        /**
         * Set search parameters
         *
         * @param {Object} [SearchParams] - Custom search params
         */
        setSearchParams: function (SearchParams) {
            this.$SearchParams = Object.merge(this.$SearchParams, SearchParams);

            if ("sheet" in this.$SearchParams) {
                this.$sheet = this.$SearchParams.sheet;
            }
        },

        /**
         * Execute search
         *
         * @return {Promise}
         */
        search: function () {
            var self = this;

            this.Loader.show();

            return new Promise(function (resolve, reject) {
                QUIAjax.get(
                    'package_quiqqer_search_ajax_search',
                    function (SearchResult) {
                        self.fireEvent('search', [SearchResult, self]);
                        self.$renderResult(SearchResult);
                        resolve(SearchResult);
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
         * Hide Pagination controls
         */
        $hidePagination: function() {
            this.$PaginationTopElm.setStyle('display', 'none');
            this.$PaginationBottomElm.setStyle('display', 'none');
        },

        /**
         * Show Pagination controls
         */
        $showPagination: function() {
            this.$PaginationTopElm.setStyle('display', '');
            this.$PaginationBottomElm.setStyle('display', '');
        },

        /**
         * Event: onSearch
         *
         * Parse search results
         *
         * @param {Object} SearchResult
         */
        $renderResult: function (SearchResult) {
            if (this.$paginationType === 'infinitescroll') {
                if (this.$loadingMore) {
                    var Ghost = new Element('div', {
                        html: SearchResult.childrenListHtml
                    });

                    var ChildrenContainer = this.$Elm.getElement('article').getParent('section');
                    Ghost.getElements('article').inject(ChildrenContainer);
                } else {
                    this.$Results.set('html', SearchResult.childrenListHtml);
                }

                if (("more" in SearchResult) && !SearchResult.more) {
                    this.$hideMoreButton();
                } else {
                    this.$showMoreButton();
                }
            } else {
                this.$Results.set('html', SearchResult.childrenListHtml);

                if (!SearchResult.count) {
                    this.$hidePagination();
                } else {
                    this.$showPagination();

                    this.$lockPagination = true;
                    this.$PaginationTop.setPageCount(SearchResult.sheets);
                    this.$PaginationBottom.setPageCount(SearchResult.sheets);
                    this.$lockPagination = false;
                }
            }

            this.Loader.hide();

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
