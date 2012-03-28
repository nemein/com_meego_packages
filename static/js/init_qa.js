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
    jQuery('div.workflow form input.midgardmvc_helper_forms_form_save').live('click', function(event) {
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
    // View submitted QA forms on the app page
    jQuery('div.app_forms div.list div.forms div.element a').live('click', function(event) {
        event.preventDefault();

        parent = jQuery(event.currentTarget).parentsUntil('div.element').parent();
        visible = parent.children('div.review_form').length;

        // reset labels
        jQuery('div.app_forms div.list div.forms div.element div.posts a').text(apps.i18n.showQAform);
        // remove any other review form
        jQuery('div.app_forms div.list div.forms div.element div.review_form')
            .slideUp('slow')
            .remove();

        if (visible == 0)
        {
            href = jQuery(event.currentTarget).attr("href");

            if (typeof href !== 'undefined')
            {
                jQuery.ajax({
                    url: href,
                    type: 'GET',
                    success: function(data, textStatus, jqXHR) {
                        parent.append('<div class="review_form"></div>');
                        parent.children('div.review_form').html(data);
                        parent.children('div.review_form').slideDown('slow');
                        parent.children('div.posts').children('a.qalink').text(apps.i18n.hideQAform);
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        apps.log.call(apps, 'error fetching QA form for review: ' + textStatus);
                    }
                });
            }
        }
    });
    // View submitted QA forms on the app history page
    jQuery('div.app_page.history div.element div.type span.qadetails').live('click', function(event) {
        apps.log.call(apps, 'Request QA details on history page for guid: ' + jQuery(this).attr('rel'));
        jQuery(this).parentsUntil('div.element').parent().find('div.app_qa_history:first').toggle();
    });
});