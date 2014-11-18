
/**
 * Suggest search utils
 * Helper for node elements
 *
 * @module package/quiqqer/search/bin/controls/Suggest
 * @author www.pcsg.de (Henning Leutz)
 */

define([

    'qui/QUI',
    'qui/controls/Control',
    'Ajax'

], function(QUI, QUIControl, Ajax)
{
    "use strict";

    return new Class({

        Extends : QUIControl,
        Type    : 'package/quiqqer/search/bin/controls/Suggest',

        Binds :[
            '$onImport',
            '$keyUp'
        ],

        options : {
            name        : 'search',
            placeholder : 'Search...',
            delay       : 300
        },

        initialize : function(options)
        {
            this.$binds      = [];
            this.$timer      = null;
            this.$lastSearch = '';

            this.$Datalist = null;

            this.parent( options );

            this.addEvents({
                onImport : this.$onImport,
                onInsert : this.$onInsert
            });
        },

        /**
         * Create the node element
         *
         * @return {DOMNode}
         */
        create : function()
        {
            this.$Elm = new Element('input', {
                type         : 'text',
                required     : 'required',
                placeholder  : this.getAttribute( 'placeholder' ),
                name         : this.getAttribute( 'name' )
            });

            this.bindElement( this.$Elm );

            return this.$Elm;
        },

        /**
         * Return the datalist
         */
        getDataList : function()
        {
            if ( this.$Datalist ) {
                return this.$Datalist;
            }

            this.$Datalist = new Element('datalist', {
                id : 'datalist'+ this.getId()
            }).inject( document.body );

            return this.$Datalist;
        },

        /**
         * event : on import
         */
        $onImport : function(self, Elm)
        {
            this.bindElement( Elm );
        },

        /**
         * Bind the suggest to an input element
         *
         * @param {DOMNode} Node
         */
        bindElement : function(Node)
        {
            this.$binds.push( Node );

            Node.set({
                list : this.getDataList().get( 'id' ),
                autocomplete : "off"
            });

            Node.addEvents({
                keyup : this.$keyUp
            });
        },

        /**
         * Unbind the suggest from an input element
         *
         * @param {DOMNode} Node
         */
        unbindElement : function(Node)
        {
            Node.removeEvents({
                keyup : this.$keyUp
            });

            Node.set({
                list : null
            });

            var binds = [];

            this.$binds.push( Node );

            for ( var i = 0, len = this.$binds.length; i < len; i++ )
            {
                if ( this.$binds[ i ] != Node ) {
                    binds.push( Node );
                }
            }

            this.$binds = binds;
        },

        /**
         * Return the binded elements
         *
         * @return {Array}
         */
        getBindedElements : function()
        {
            return this.$binds;
        },

        /**
         * event : key up on binded DOMNode
         *
         * @param {DOMEvent} event
         */
        $keyUp : function(event)
        {
            if ( this.$timer ) {
                clearTimeout( this.$timer );
            }

            var self = this,
                Elm  = event.target;

            if ( this.$lastSearch == Elm.value ) {
                return;
            }

            this.getDataList().set( 'html', '' );

            this.$timer = (function()
            {
                self.$lastSearch = Elm.value;

                self.search( Elm.value, function(result)
                {
                    var str  = '',
                        list = result.list;

                    var DataList = self.getDataList();

                    DataList.set( 'html', '' );

                    for ( var i = 0, len = list.length; i < len; i++ )
                    {
                        new Element('option', {
                            value : list[ i ].data
                            // label : ""
                        }).inject( DataList );
                    }
                });

            }).delay( this.getAttribute( 'delay' ) );
        },

        /**
         * Execute the suggest search
         *
         * @param {String} search - search string
         * @param {Function} callback - callback function function( result ){}
         */
        search : function(search, callback)
        {
            Ajax.get('package_quiqqer_search_ajax_suggest', function(result)
            {
                callback( result );
            }, {
                'package' : 'quiqqer/search',
                project   : JSON.encode( QUIQQER_PROJECT ),
                search    : search
            });
        }
    });
});
