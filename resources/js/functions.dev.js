/*!
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
        imgLoaded : function(callback) {
            var elems = this.filter('img');

            elems.each(function() {
                if (this.complete === undefined || this.complete || this.readyState === 4) {
                    // Cached
                    callback.call(this);
                } else {
                    // Uncached
                    $(this).one('load', function(e) {
                        callback.call(this);
                    });
                }
            });
        }
    });

    myatu_bgm = {
        base_prefix: 'myatu_bgm_',

        /** 
         * This is a simple wrapper for calling an Ajax function and obtaining its response
         *
         * @param string ajaxFunc The Ajax function to perform
         * @param mixed ajaxData the data to send along with the function
         * @param function callback A callback to trigger on an asynchronous call
         * @return mixed Returns the response from the Ajax function, or `false` if there was an error
         */
        GetAjaxData: function(ajaxFunc, ajaxData, callback) {
            var has_callback = (callback !== undefined && typeof(callback) === 'function'), resp = false;

            $.ajax({
                type     : 'POST',
                dataType : 'json',
                url      : background_manager_ajax.url,
                timeout  : 5000,
                async    : has_callback,
                data     : { action: background_manager_ajax.action, func: ajaxFunc, data: ajaxData, _ajax_nonce: background_manager_ajax.nonce },
                success  : function(ajaxResp) {
                    if (ajaxResp.nonce == background_manager_ajax.nonceresponse && ajaxResp.stat == 'ok') {
                        resp = ajaxResp.data;

                        if (has_callback) {
                            callback(resp);
                        }
                    }
                }
            });

            return resp;
        },

        /**
         * Shows or Hides a HTML element
         *
         * @param mixed what HTML element to show or hide
         * @param bool show Whether to show (true) or hide (false) the element
         * @param string speed The speed at which to show or hide an element
         */
        showHide: function(what, show, speed) {
            if (speed == undefined)
                speed = 'slow';

            if (show) {
                $(what).show(speed);
            } else {
                if (!$(what).is(':visible')) {
                    $(what).css('display', 'none');
                } else {
                    $(what).hide(speed);
                }
            }
        }

    } // myatu_bgm NS

})(jQuery);
