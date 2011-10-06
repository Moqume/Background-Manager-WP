/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

mainWin = window.dialogArguments || opener || parent || top;

(function($) {
    // Implement a few helper functions for jQuery
    $.fn.extend({
        /**
         * Scrolls to the top-offset of a specified object
         *
         * @param object scrollTo The object to scroll to (top)
         * @return object Calling object (chainable)
         */
        scrollTo : function(obj){ $(this).clearQueue().animate({scrollTop: $(obj).offset().top}, 'fast'); return $(this); }
    });

    /** Selector `:above` selects all items above location X,Y within a certan width */
    $.expr[':'].above = function(obj, index, meta, stack) {
        var args = eval('([' + meta[3] + '])'), x = args[0], y = args[1], w = args[2]; //(x, y, w)

        return ($(obj).offset().top < y && $(obj).offset().left >= x && $(obj).offset().left <= (x+w));
    }

    /** Selector `:below` selects all items below location X,Y within a certan width */
     $.expr[':'].below = function(obj, index, meta, stack) {
        var args = eval('([' + meta[3] + '])'), x = args[0], y = args[1], w = args[2]; //(x, y, w)

        return ($(obj).offset().top > y && $(obj).offset().left >= x && $(obj).offset().left <= (x+w));
    }

    /** Selector `:right` selects all items to the right of location X,Y within a certan height */
    $.expr[':'].right = function(obj, index, meta, stack) {
        var args = eval('([' + meta[3] + '])'), x = args[0], y = args[1], h = args[2]; //(x, y, h)

        return ($(obj).offset().top >= y && $(obj).offset().top <= (y+h) && $(obj).offset().left > x);
    }

    /** Selector `:left` selects all items to the left of location X,Y within a certan height */
    $.expr[':'].left = function(obj, index, meta, stack) {
        var args = eval('([' + meta[3] + '])'), x = args[0], y = args[1], h = args[2]; //(x, y, h)

        return ($(obj).offset().top >= y && $(obj).offset().top <= (y+h) && $(obj).offset().left < x);
    }

    /** Gets the count of named properties */
    getObjSize = function(obj) {
        var size = 0, key;

        for (key in obj)
            if (obj.hasOwnProperty(key))
                size++;

        return size;
    }

    /** 
     * This is a simple wrapper for calling an Ajax function and obtaining its response
     *
     * @param string ajaxFunc The Ajax function to perform
     * @param mixed ajaxData the data to send along with the function
     * @return mixed Returns the response from the Ajax function, or `false` if there was an error
     */
    getAjaxData = function(ajaxFunc, ajaxData) {
        var resp = false;

        $.ajax({
            type     : 'POST',
            dataType : 'json',
            url      : ajaxurl,
            timeout  : 5000,
            async    : false,
            data     : { action: ajaxaction, func: ajaxFunc, data: ajaxData, _ajax_nonce: ajaxnonce },
            success  : function(ajaxResp) {
                if (ajaxResp.nonce == ajaxnonceresponse && ajaxResp.stat == 'ok')
                    resp = ajaxResp.data;
            }
        });

        return resp;
    }
})(jQuery);
