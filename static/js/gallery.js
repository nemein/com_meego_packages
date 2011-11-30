jQuery(function()
{
    var galleries = jQuery('.slide-gallery').adGallery({
        loader_image: '/midgardmvc-static/com_meego_packages/images/gallery/loader.gif',
        callbacks: {
            // Executes right after the internal init, can be used to choose which images
            // you want to preload
            init: function()
            {
                gal = jQuery('.slide-gallery').width();
                w = jQuery('#slide-thumb-list').width();
                w = (gal - w) / 2;

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
