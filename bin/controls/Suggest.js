/**
 * Suggest search utils
 * Helper for node elements
 *
 * @module package/quiqqer/search/bin/controls/Suggest
 * @author www.pcsg.de (Henning Leutz)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require Ajax
 *
 * @event onSuggestionClick [text, url, self] - fires if the user clicks a search suggestion
 */
define('package/quiqqer/search/bin/controls/Suggest', [

    'qui/QUI',
    'qui/controls/Control',
    'Ajax',
    'Locale',

    'css!package/quiqqer/search/bin/controls/Suggest.css'

], function (QUI, QUIControl, QUIAjax, QUILocale) {
    "use strict";

    var lg = 'quiqqer/search';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/search/bin/controls/Suggest',

        Binds: [
            '$onImport',
            '$onInsert',
            '$keyUp',
            '$blur',
            '$renderSearch',
            '$hideResults'
        ],

        options: {
            name          : 'search',
            placeholder   : 'Search...',
            delay         : 300,
            fireClickEvent: false
        },

        initialize: function (options) {
            this.$binds      = [];
            this.$timer      = null;
            this.$lastSearch = '';
            this.$showed     = false;

            this.$CurrentInput = null;
            this.$FX           = null;
            this.$Datalist     = null;
            this.$Form         = null;

            this.parent(options);

            this.addEvents({
                onImport: this.$onImport,
                onInsert: this.$onInsert
            });
        },

        /**
         * Create the node element
         *
         * @return {HTMLInputElement}
         */
        create: function () {
            this.$Elm = new Element('input', {
                type       : 'search',
                placeholder: this.getAttribute('placeholder'),
                name       : this.getAttribute('name')
            });

            this.bindElement(this.$Elm);

            this.$FX = moofx(this.getDataList());

            return this.$Elm;
        },

        /**
         * Return the datalist
         */
        getDataList: function () {
            if (this.$Datalist) {
                return this.$Datalist;
            }

            this.$Datalist = new Element('div', {
                'class': 'quiqqer-search-suggest'
            }).inject(document.body);

            return this.$Datalist;
        },

        /**
         * event : on import
         */
        $onImport: function (self, Elm) {
            if (!Elm.get('placeholder') || Elm.get('placeholder') === '') {
                Elm.set('placeholder', this.getAttribute('placeholder'));
            }

            this.$Form = Elm.getParent('form');

            this.$FX = moofx(this.getDataList());
            this.bindElement(Elm);
        },

        /**
         * Bind the suggest to an input element
         *
         * @param {HTMLFormElement} Node
         */
        bindElement: function (Node) {
            var self = this;

            this.$binds.push(Node);

            Node.set({
                autocomplete: "off"
            });

            Node.addEvents({
                keyup: this.$keyUp,
                blur : this.$blur
            });

            // hide results if users click "x" in search input
            Node.addEventListener('search', function (event) {
                event.stopPropagation();

                if (event.target.value.trim() === '') {
                    self.$hideResults();
                }
            });
        },

        /**
         * Unbind the suggest from an input element
         *
         * @param {HTMLFormElement} Node
         */
        unbindElement: function (Node) {
            Node.removeEvents({
                keyup: this.$keyUp
            });

            Node.set({
                list: null
            });

            var binds = [];

            this.$binds.push(Node);

            for (var i = 0, len = this.$binds.length; i < len; i++) {
                if (this.$binds[i] != Node) {
                    binds.push(Node);
                }
            }

            this.$binds = binds;
        },

        /**
         * Return the binded elements
         *
         * @return {Array}
         */
        getBindedElements: function () {
            return this.$binds;
        },

        /**
         * event : key up on binded DOMNode
         *
         * @param {DOMEvent} event
         */
        $keyUp: function (event) {
            if (this.$timer) {
                clearTimeout(this.$timer);
            }

            var self     = this,
                Elm      = event.target,
                DataList = this.getDataList();


            this.$CurrentInput = Elm;

            switch (event.key) {
                case 'esc':
                    Elm.value = '';
                    this.$hideResults();
                    event.stop();
                    break;

                case 'enter':
                    var Active = DataList.getElement('li.active');

                    if (Active) {
                        Active.fireEvent('click', {
                            target: Active
                        });
                        event.stop();
                        return;
                    }
                    break;

                case 'up':
                    this.$up();
                    event.stop();
                    return;

                case 'down':
                    this.$down();
                    event.stop();
                    return;
            }

            this.$resetResults();
            this.$showResults();

            this.$timer = (function () {
                if (Elm.value === '') {
                    return self.$hideResults();
                }
                self.search(Elm.value).then(self.$renderSearch);
            }).delay(this.getAttribute('delay'));
        },

        /**
         * Execute the suggest search
         *
         * @param {String} search - search string
         * @return {Promise}
         */
        search: function (search) {
            return new Promise(function (resolve) {
                QUIAjax.get('package_quiqqer_search_ajax_suggest', resolve, {
                    'package': 'quiqqer/search',
                    project  : JSON.encode(QUIQQER_PROJECT),
                    siteId   : QUIQQER_SITE.id,
                    search   : search
                });
            }.bind(this));
        },

        /**
         * set the results to the dropdown
         *
         * @param {string} data
         * @return {Promise}
         */
        $renderSearch: function (data) {
            var self     = this;
            var DropDown = this.getDataList();

            if (!data) {
                DropDown.set(
                    'html',
                    '<span class="quiqqer-search-suggest-noresult">' +
                    QUILocale.get(lg, 'controls.Suggest.no_results') +
                    '</span>'
                );
                return this.$showResults();
            }

            DropDown.set('html', data);

            DropDown.getElements('li').addEvents({
                mousedown: function (event) {
                    event.stop();
                },
                click    : function (event) {
                    var Target = event.target;

                    if (Target.nodeName !== 'LI') {
                        Target = Target.getParent('li');
                    }

                    if (self.getAttribute('fireClickEvent')) {
                        self.fireEvent('suggestionClick', [
                            Target.getElement('.quiqqer-search-suggest-text').innerHTML,
                            Target.get('data-url'),
                            self
                        ]);
                        return;
                    }

                    window.location = Target.get('data-url');
                }
            });

            return this.$showResults();
        },

        /**
         * Hide the results dropdown
         *
         * @returns {Promise}
         */
        $hideResults: function () {
            return new Promise(function (resolve) {
                this.$FX.animate({
                    opacity: 0
                }, {
                    duration: 200,
                    callback: function () {
                        this.$showed = false;
                        this.getDataList().setStyle('display', 'none');
                        resolve();
                    }.bind(this)
                });
            }.bind(this));
        },

        /**
         * Show the results dropdown
         *
         * @returns {Promise}
         */
        $showResults: function () {
            if (this.$showed) {
                return Promise.resolve();
            }

            return new Promise(function (resolve) {
                var pos  = this.$CurrentInput.getPosition(),
                    size = this.$CurrentInput.getSize(),
                    List = this.getDataList();

                List.setStyles({
                    left   : pos.x,
                    opacity: 0,
                    top    : pos.y + size.y,
                    width  : size.x
                });

                List.setStyle('display', 'block');
                this.$showed = true;

                this.$FX.animate({
                    opacity: 1
                }, {
                    duration: 200,
                    callback: resolve
                });
            }.bind(this));
        },

        /**
         * Reset the results, set a loader to the dropdown
         */
        $resetResults: function () {
            this.getDataList().set(
                'html',
                '<span class="quiqqer-search-suggest-loader fa fa-spinner fa-spin"></span>'
            );
        },

        /**
         * blur effect
         */
        $blur: function () {
            this.$hideResults();
        },

        /**
         * Move up to next result
         */
        $up: function () {
            var Active = this.getDataList().getElement('li.active');

            if (!Active) {
                Active = this.getDataList().getFirst('ul li');
            }

            if (!Active) {
                return;
            }

            var Previous = Active.getPrevious();

            if (!Previous) {
                Previous = this.getDataList().getLast('ul li');
            }

            Active.removeClass('active');
            Previous.addClass('active');
        },

        /**
         * Move down to next result
         */
        $down: function () {
            var Active = this.getDataList().getElement('li.active');

            if (!Active) {
                Active = this.getDataList().getLast('ul li');
            }

            if (!Active) {
                return;
            }

            var Next = Active.getNext();

            if (!Next) {
                Next = this.getDataList().getFirst('ul li');
            }

            Active.removeClass('active');
            Next.addClass('active');
        }
    });
});
