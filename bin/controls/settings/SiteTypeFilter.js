/**
 * Set site types filter for search site
 *
 * @module package/quiqqer/search/bin/controls/settings/SiteTypeFilter
 * @author www.pcsg.de (Patrick Müller)
 *
 * @require qui/controls/loader/Loader
 * @require qui/controls/buttons/Button
 * @require utils/Controls
 * @require package/quiqqer/search/bin/controls/settings/SearchExtension
 * @require Locale
 */
define('package/quiqqer/search/bin/controls/settings/SiteTypeFilter', [

    'qui/controls/Control',
    'controls/projects/project/site/Select',

    'Ajax',
    'Locale',

    //'css!package/quiqqer/search/bin/controls/settings/SiteTypeFilter.css'

], function (QUIControl, SiteTypeSelect, QUIAjax, QUILocale) {
    "use strict";

    var lg = 'quiqqer/search';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/search/bin/controls/settings/SiteTypeFilter',

        Binds: [
            '$onImport'
        ],

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * Event: onImport
         */
        $onImport: function () {
            this.$Elm        = this.getElm();
            this.$Elm.hidden = true;

            new SiteTypeSelect({
                styles      : {
                    height   : 'initial',
                    minHeight: 160
                },
                selectids   : false,
                selecttypes : true,
                selectparent: false
            }).imports(this.$Elm);
        }
    });
});
