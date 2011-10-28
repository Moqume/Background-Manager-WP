/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
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
         * Provided by Paul Irish
         *
         * Same as load(), but supports cached images
         *
         * @link https://github.com/paulirish/jquery.imgloaded
         */
        imgLoaded : function(callback, fireOne) {
            var args = arguments, elems = this.filter('img'), elemsLen = elems.length - 1;

            elems.bind('load', function(e) {
                if (fireOne) {
                    !elemsLen-- && callback.call(elems, e);
                } else {
                    callback.call(this, e);
                }
            }).each(function() {
                if (this.complete || this.complete === undefined) {
                    this.src = this.src;
                }
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
        }

    }
})(jQuery);
