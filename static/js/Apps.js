/**
 *  Apps handles UI interactions
 *
 *  Currently offers:
 *
 *   * fetching i18n strings from com_meego_packages
 *   * logging
 *
 * More to come!
 *
 */
Apps = (function ()
{
    var debug = false;

    // could store a bunch of i18n strings that are fetched once per session

    function Apps(debug)
    {
        self = this;

        if (typeof debug !== 'undefined')
        {
            self.debug = debug;
        }
    }

    Apps.prototype.i18n = {
        'showQAform': '',
        'hideQAform': ''
    };

    /**
     * Initialization
     */
    Apps.prototype.init = function()
    {
        self = this;
        self.log('Apps init');
        // fetch and populate i18n strings
        self.i18n.showQAform = self.getI18n('label_show_posted_form');
        self.i18n.hideQAform = self.getI18n('label_hide_posted_form');
    }

    /**
    * A logger
    * @param string The message to be written to the console
    */
    Apps.prototype.log = function(message)
    {
        self = this;

        if (typeof console !== 'undefined' && typeof console.log !== 'undefined' && self.debug)
        {
          console.log(message);
        }
    };


    /**
    * Fetches an i18n string from the server and caches it
    * @param string ID
    */
    Apps.prototype.getI18n = function(id)
    {
        retval = null;
        self = this;

        jQuery.ajax({
            url: '/i18n/' + id,
            type: 'GET',
            dataType: 'json',
            global: false,
            cache: true,
            async: false,
            success: function(data, textStatus, jqXHR)
            {
                retval = data.i18n;
                self.log('i18n string: ' + retval);
            },
            error: function(jqXHR, textStatus, errorThrown)
            {
                self.log('error fetching QA form for review: ' + textStatus);
            }
        });

        return retval;
    };

    return Apps;

})();