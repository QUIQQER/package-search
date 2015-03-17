
window.addEvent('domready', function()
{
    "use strict";

    require(['qui/controls/buttons/Button'], function(QUIButton)
    {
        var Submit   = document.getElement( '.qui-search-result [type="submit"]' ),
            Settings = document.getElement( '.qui-search-settings' );

        if ( !Submit || !Settings ) {
            return;
        }

        var FX = moofx( Settings );

        Settings.setStyles({
            display : 'none',
            opacity : 0
        });

        new QUIButton({
            icon   : 'icon-gears fa fa-gears',
            title  : 'Erweiterte Einstellungen für die Suche',
            styles : {
                marginLeft : 5
            },
            events :
            {
                onClick : function()
                {
                    if ( Settings.getStyle( 'display' ) == 'none' )
                    {
                        // show
                        var height = Settings.measure(function() {
                            return this.getSize().y;
                        });

                        Settings.setStyles({
                            display  : null,
                            height   : 0,
                            position : 'relative'
                        });

                        FX.animate({
                            height  : height,
                            opacity : 1
                        }, {
                            equation: 'ease-out',
                            duration : 250
                        });

                        return;
                    }

                    // hide
                    FX.animate({
                        opacity : 0
                    }, {
                        equation: 'ease-out',
                        duration : 250,
                        callback : function()
                        {
                            FX.animate({
                                height : 0
                            }, {
                                equation: 'ease-out',
                                duration : 250,
                                callback : function()
                                {
                                    Settings.setStyle.delay( 10, Settings, [ 'display', 'none' ] );
                                    Settings.setStyle.delay( 20, Settings, [ 'height', null ] );
                                }
                            });
                        }
                    });
                }
            }
        }).inject( Submit, 'after' )
    });
});
