/**
 * SearchInput JavaScript Control
 *
 * @module package/quiqqer/search/bin/controls/SearchInput
 * @author www.pcsg.de (Henning Leutz)
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require Ajax
 */
define('package/quiqqer/search/bin/controls/SearchInput', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/loader/Loader',
    'qui/controls/buttons/Button',

    'package/quiqqer/search/bin/SearchUtils',

    'Locale',

    //'css!package/quiqqer/search/bin/controls/SearchInput.css'

], function (QUI, QUIControl, QUILoader, QUIButton, SearchUtils, QUILocale) {
    "use strict";

    var lg = 'quiqqer/search';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/search/bin/controls/SearchInput',

        Binds: [
            '$onImport',
            '$toggleSettings',
            '$submit'
        ],

        options: {
            name       : 'search',
            placeholder: 'Search...',
            delay      : 300
        },

        initialize: function (options) {
            this.parent(options);

            this.$SearchInput = null;
            this.$Search      = null;
            this.$SettingsElm = null;
            this.$Elm         = null;
            this.Loader       = new QUILoader();

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * Event: onImport
         */
        $onImport: function () {
            var self = this;

            this.$Elm = this.getElm();

            this.Loader.inject(this.$Elm);

            this.$SearchInput = this.$Elm.getElement(
                '.quiqqer-search-searchinput-input'
            );

            this.$SettingsElm = this.$Elm.getElement(
                '.qui-search-searchinput-settings'
            );

            // config toggle btn
            new QUIButton({
                'class': 'quiqqer-search-searchinput-settings-btn',
                icon   : 'fa fa-gears',
                title  : 'Sucheinstellungen',
                events : {
                    onClick: this.$toggleSettings
                }
            }).inject(
                this.$Elm.getElement(
                    '.quiqqer-search-searchinput-submit'
                ),
                'after'
            );

            this.Loader.show();

            SearchUtils.getSearchControl().then(function (SearchControl) {
                self.Loader.hide();
                self.$Search = SearchControl;
            }, function () {
                // @todo error handling
            });

            // form
            this.$Elm.getElement('form').addEvents({
                submit: function (event) {
                    event.stop();
                    self.$submit();
                }
            });
        },

        /**
         * Submit search
         */
        $submit: function () {
            var self         = this;
            var SearchParams = {
                search      : this.$SearchInput.value.trim(),
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
                            SearchParams.searchType = 'AND';
                        }
                        break;

                    default:
                        if (Input.checked) {
                            SearchParams.searchFields.push(Input.value);
                        }
                }
            });

            this.$Search.setSearchParams(SearchParams);

            this.Loader.show();

            this.$Search.search().then(function () {
                self.Loader.hide();
            });
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
