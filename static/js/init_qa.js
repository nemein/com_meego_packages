jQuery(document).ready(function()
{
    // Opening the QA form
    jQuery('div.qa').live('click', function(event) {
        self = jQuery(event.currentTarget);
        if (self.hasClass('login'))
        {
            // call popup login
            popup_login();
        }
        else
        {
            // send an AJAX POST
            posturl = jQuery('div.qa').attr('post');
            redirect = self.attr('redirect');
            jQuery.ajax({
                url: posturl,
                type: 'POST',
                data: 'redirect_link=' + redirect,
                dataType: 'html',
                success: function(data, textStatus, jqXHR) {
                    jQuery('div.qa_form')
                        .html(data)
                        .slideDown('slow');
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.log('error: ' + textStatus);
                }
            });
        }
    });
    // Cancelling the QA form
    jQuery('a.midgardmvc_helper_forms_form_cancel').live('click', function(event) {
        event.preventDefault();
        jQuery('div.qa_form').slideUp('slow', function() {
            jQuery('div.qa_form').empty();
            //jQuery('html, body').animate({ scrollTop: 0 }, "slow");
        });
    });
    // Posting the QA form
    jQuery('input.midgardmvc_helper_forms_form_save').live('click', function(event) {
        event.preventDefault();
        self = jQuery(event.currentTarget);
        execution = jQuery('div.qa_form input[name="execution"]').val();
        posturl = jQuery('div.qa').attr('post') + execution;
        data = self.parentsUntil('form').parent().serialize();
        jQuery.ajax({
            url: posturl,
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function(data, textStatus, jqXHR) {
                jQuery('div.qa').remove();
                jQuery('div.qa_form')
                    .html(data)
                    .slideUp('slow')
                    .empty();
                //jQuery('html, body').animate({ scrollTop: 0 }, "slow");

                // refresh the posted forms on the app page
                if (data.workflow == 'resumed')
                {
                    jQuery('div.app_forms').html(data.posted_forms);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.log('error: ' + textStatus);
            }
        });
    });
});