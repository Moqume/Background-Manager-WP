/*
 * Copyright (c) 2011-2012 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Portions Copyright (c) 2010 Paul Irish & Andreé Hansson (imgLoaded jQuery extension, released under MIT license)
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
        scrollTo : function(obj){ $(this).clearQueue().animate({scrollTop: $(obj).offset().top}, 'fast'); return $(this); },
        
        /**
         * Based on code by Paul Irish (MIT License)
         *
         * Same as load(), but supports cached images. Only activated when DOM is ready.
         *
         * @link https://github.com/paulirish/jquery.imgloaded
         */
        imgLoaded : function(callback, fireOne) {
            var args = arguments, elems = this.filter('img'), elemsLen = elems.length - 1;

            elems.bind('load', function(e) {
                if (fireOne) {
                    !elemsLen-- && callback.call(elems);
                } else {
                    callback.call(this);
                }
            });

            $(function() {
                elems.each(function() {
                    if (this.complete === undefined || this.complete || this.readyState === 4) {
                        callback.call(this);
                    }
                });
            });
        }
    });

    /* Within our own namespace, we define some frequently used functions */
    myatu_bgm = {
        /** Gets the count of named properties */
        GetObjSize: function(obj) {
            var size = 0, key;

            for (key in obj)
                if (obj.hasOwnProperty(key))
                    size++;

            return size;
        },

        /** 
         * This is a simple wrapper for calling an Ajax function and obtaining its response
         *
         * @param string ajaxFunc The Ajax function to perform
         * @param mixed ajaxData the data to send along with the function
         * @return mixed Returns the response from the Ajax function, or `false` if there was an error
         */
        GetAjaxData: function(ajaxFunc, ajaxData) {
            var resp = false;

            $.ajax({
                type     : 'POST',
                dataType : 'json',
                url      : background_manager_ajax.url,
                timeout  : 5000,
                async    : false,
                data     : { action: background_manager_ajax.action, func: ajaxFunc, data: ajaxData, _ajax_nonce: background_manager_ajax.nonce },
                success  : function(ajaxResp) {
                    if (ajaxResp.nonce == background_manager_ajax.nonceresponse && ajaxResp.stat == 'ok')
                        resp = ajaxResp.data;
                }
            });

            return resp;
        },

    } // myatu_bgm NS

})(jQuery);
