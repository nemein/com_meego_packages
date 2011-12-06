ua = navigator.userAgent.toLowerCase();
isIE = ((ua.indexOf("msie") != -1) );
isIE6 = ((ua.indexOf("msie 6.0") != -1) );
isIE7 = ((ua.indexOf("msie 7.0") != -1) );
isOPERA = ((ua.indexOf("opera") != -1) );
isFF = ((ua.indexOf("firefox") != -1) );
isSafari = ((ua.indexOf("safari") != -1) );

jQuery(document).ready(function()
{
    if (typeof apps !== 'object')
    {
        // our apps object that will handle UI interactions and logging
        apps = new Apps(false);
        apps.init.call(apps);
    }

    jQuery('.login  ul li:last').addClass('last');
    jQuery('nav ul li:first').addClass('first');
});

function popup_comment() {
    jQuery("#popup_comment").modal({
        opacity:95,
        overlayCss: {backgroundColor:"#fff"},
        onOpen: function (dialog) {
            dialog.overlay.fadeIn('fast', function () {
                dialog.data.hide();
                dialog.container.fadeIn('fast', function () {
                    dialog.data.fadeIn('fast');
                });
            });
        },
        onClose: function (dialog) {
            dialog.data.fadeOut('fast', function () {
                dialog.container.hide('fast', function () {
                    dialog.overlay.hide('fast', function () {
                        jQuery.modal.close();
                    });
                });
            });
        }
    })
};

function popup_ux() {
    jQuery("#popup_ux").modal({
        opacity:95,
        overlayCss: {backgroundColor:"#fff"},
        onOpen: function (dialog) {
            dialog.overlay.fadeIn('fast', function () {
                dialog.data.hide();
                dialog.container.fadeIn('fast', function () {
                    dialog.data.fadeIn('fast');
                });
            });
        },
        onClose: function (dialog) {
            dialog.data.fadeOut('fast', function () {
                dialog.container.hide('fast', function () {
                    dialog.overlay.hide('fast', function () {
                        jQuery.modal.close();
                    });
                });
            });
        }
    })
};

function popup_ver() {
    jQuery("#popup_ver").modal({
        opacity:95,
        overlayCss: {backgroundColor:"#fff"},
        onOpen: function (dialog) {
            dialog.overlay.fadeIn('fast', function () {
                dialog.data.hide();
                dialog.container.fadeIn('fast', function () {
                    dialog.data.fadeIn('fast');
                });
            });
        },
        onClose: function (dialog) {
            dialog.data.fadeOut('fast', function () {
                dialog.container.hide('fast', function () {
                    dialog.overlay.hide('fast', function () {
                        jQuery.modal.close();
                    });
                });
            });
        }
    })
};

function popup_poll() {
    jQuery("#popup_poll").modal({
        opacity:95,
        overlayCss: {backgroundColor:"#fff"},
        onOpen: function (dialog) {
            dialog.overlay.fadeIn('fast', function () {
                dialog.data.hide();
                dialog.container.fadeIn('fast', function () {
                    dialog.data.fadeIn('fast');
                });
            });
        },
        onClose: function (dialog) {
            dialog.data.fadeOut('fast', function () {
                dialog.container.hide('fast', function () {
                    dialog.overlay.hide('fast', function () {
                        jQuery.modal.close();
                    });
                });
            });
        }
    })
};

function popup_login() {
    jQuery("#popup_login").modal({
        opacity:95,
        overlayCss: {backgroundColor:"#fff"},
        onOpen: function (dialog) {
            dialog.overlay.fadeIn('fast', function () {
                dialog.data.hide();
                dialog.container.fadeIn('fast', function () {
                    dialog.data.fadeIn('fast');
                });
            });
        },
        onClose: function (dialog) {
            dialog.data.fadeOut('fast', function () {
                dialog.container.hide('fast', function () {
                    dialog.overlay.hide('fast', function () {
                        jQuery.modal.close();
                    });
                });
            });
        }
    })
};

//-----------------------------------------------
function debug(txt) {
    try {
        console.debug(txt);
    }
    catch(e) {}
}
//-----------------------------------------------
function inittabs(selector) {
    jQuery(selector).tabs();

    jQuery(selector).children('ul').localScroll({
        target:".tab-set",
        duration:0,
        hash:true
    });
}
//-----------------------------------------------
function initiconmenu(id) {
    var mainelement=jQuery('#' + id);

    jQuery(mainelement).find('.icons li a').live( function(event) {
        //event.preventDefault();

        jQuery(mainelement).find('.icons li a').removeClass('active');

        jQuery(this).addClass('active');

        refreshtab();
    });

    refreshtab();

    function refreshtab() {
        var index= jQuery(mainelement).find('.icons li a').index( jQuery(mainelement).find('.icons li a.active') );

        jQuery(mainelement).find('.content').css('display', 'none');
        jQuery(mainelement).find('.content').eq(index).css('display', 'block');
    }
//END;
}


//-----------------------------------------------
cnt = 0;
sum = 0;
jQuery('.login ul:first > li').each( function()
{
    var min=jQuery(this).width();

    cnt++;
    sum+=min;

    jQuery(this).find('ul').each(function() {
        min=Math.max( jQuery(this).width(), min );

        jQuery(this).find('li').each(function() {
            min=Math.max( jQuery(this).width(), min );
        });

    });

    jQuery(this).find('ul').css('width', min+'px');
    jQuery(this).find('ul li').css('width', min+'px');

    jQuery(this).find('ul li.last span.r').css('width', min-10+'px');
});
//-----------------------------------------------
