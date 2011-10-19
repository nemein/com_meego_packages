jQuery(function()
{
    var galleries = jQuery('.slide-gallery').adGallery({
        loader_image: '/midgardmvc-static/com_meego_packages/images/gallery/loader.gif',
        callbacks: {
            // Executes right after the internal init, can be used to choose which images
            // you want to preload
            init: function()
            {
                var w = 0;
                var list = jQuery('#slide-thumb-list li');

                jQuery.each(list, function(index, value) {
                    w += value.clientHeight;
                });

                w = (jQuery('.slide-gallery').width() - w) / 2;

                if (w >= 0)
                {
                    jQuery('.slide-back').css('display', 'none');
                    jQuery('.slide-forward').css('display', 'none');
                    jQuery('#slide-thumb-list').css('paddingLeft', w);
                }
            }
        }
    });

    jQuery('#switch-effect').change(
        function() {
            galleries[0].settings.effect = jQuery(this).val();
            return false;
        }
    );

    jQuery('#toggle-slideshow').click(
        function()
        {
            galleries[0].slideshow.toggle();
            return false;
        }
    );

    jQuery('#toggle-description').click(
        function()
        {
            if(! galleries[0].settings.description_wrapper)
            {
                galleries[0].settings.description_wrapper = jQuery('#descriptions');
            }
            else
            {
                galleries[0].settings.description_wrapper = false;
            }
            return false;
        }
    );

});

function initGallery()
{
    var w = 0;

    //var lis = document.getElementById('slide-thumb-list').getElementsByTagName('li');
    jQuery('#slide-thumb-list li').each(function(index)
    {
        w += jQuery(this).width();
    });

    w = (616 - w) / 2;

    if (w >= 0)
    {
        jQuery('.slide-back').css('display', 'none');
        jQuery('.slide-forward').css('display', 'none');
        jQuery('#slide-thumb-list').css('paddingLeft', w);
    }
};

//var t=setTimeout("initGallery()", 100);
