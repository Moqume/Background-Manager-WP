/*!
 * Copyright (c) 2011-2012 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Portions Copyright (c) 2010 Paul Irish & Andreé Hansson (imgLoaded jQuery extension, released under MIT license)
 */

var mainWin = window.dialogArguments || opener || parent || top;

if (typeof myatu_bgm === "undefined") {
    var myatu_bgm = {};
}

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
         * Same as load(), but supports cached images. Only activated when DOM is ready.
         */
        imgLoaded : function(callback) {
            var elems = this.filter('img');

            elems.one('load', function(e) {
                callback.call(this);
            }).each(function(i, el) {
                // Force the 'load' event to fire.
                // Complete and cach check
                if (el.complete && typeof el.naturalWidth !== "undefined") {
                    /* Create a fake image holder to see if the image has
                     * in fact been completed downloading or is cached
                     */
                    var img = new Image();

                    img.src = el.src;

                    if (img.complete) {
                        $(el).trigger('load');
                    }

                    return;
                }

                // Readystate Check
                if (el.readyState === "complete") {
                    $(el).trigger('load');
                    return;
                }
            });
        }
    });


    $.extend(myatu_bgm, {
        base_prefix: 'myatu_bgm_',

        /* Modernizr 2.5.3 (Custom Build) | MIT & BSD
         * Build: http://www.modernizr.com/download/#-backgroundsize-testprop-testallprops-domprefixes
         */
        Modernizr : function(a,b,c){function w(a){i.cssText=a}function x(a,b){return w(prefixes.join(a+";")+(b||""))}function y(a,b){return typeof a===b}function z(a,b){return!!~(""+a).indexOf(b)}function A(a,b){for(var d in a)if(i[a[d]]!==c)return b=="pfx"?a[d]:!0;return!1}function B(a,b,d){for(var e in a){var f=b[a[e]];if(f!==c)return d===!1?a[e]:y(f,"function")?f.bind(d||b):f}return!1}function C(a,b,c){var d=a.charAt(0).toUpperCase()+a.substr(1),e=(a+" "+m.join(d+" ")+d).split(" ");return y(b,"string")||y(b,"undefined")?A(e,b):(e=(a+" "+n.join(d+" ")+d).split(" "),B(e,b,c))}var d="2.5.3",e={},f=b.documentElement,g="modernizr",h=b.createElement(g),i=h.style,j,k={}.toString,l="Webkit Moz O ms",m=l.split(" "),n=l.toLowerCase().split(" "),o={},p={},q={},r=[],s=r.slice,t,u={}.hasOwnProperty,v;!y(u,"undefined")&&!y(u.call,"undefined")?v=function(a,b){return u.call(a,b)}:v=function(a,b){return b in a&&y(a.constructor.prototype[b],"undefined")},Function.prototype.bind||(Function.prototype.bind=function(b){var c=this;if(typeof c!="function")throw new TypeError;var d=s.call(arguments,1),e=function(){if(this instanceof e){var a=function(){};a.prototype=c.prototype;var f=new a,g=c.apply(f,d.concat(s.call(arguments)));return Object(g)===g?g:f}return c.apply(b,d.concat(s.call(arguments)))};return e}),o.backgroundsize=function(){return C("backgroundSize")};for(var D in o)v(o,D)&&(t=D.toLowerCase(),e[t]=o[D](),r.push((e[t]?"":"no-")+t));return w(""),h=j=null,e._version=d,e._domPrefixes=n,e._cssomPrefixes=m,e.testProp=function(a){return A([a])},e.testAllProps=C,e}(this,this.document),

        /**
         * This is a simple wrapper for calling an Ajax function and obtaining its response
         *
         * @param string ajaxFunc The Ajax function to perform
         * @param mixed ajaxData the data to send along with the function
         * @param function callback A callback to trigger on an asynchronous call
         * @return mixed Returns the response from the Ajax function, or `false` if there was an error
         */
        GetAjaxData: function(ajaxFunc, ajaxData, callback) {
            var has_callback = (typeof callback === "function"), resp = false;

            $.ajax({
                type     : 'POST',
                dataType : 'json',
                url      : background_manager_ajax.url,
                timeout  : 15000,
                async    : has_callback,
                data     : { 'action': background_manager_ajax.action, 'func': ajaxFunc, 'data': ajaxData, '_ajax_nonce': background_manager_ajax.nonce },
                success  : function(ajaxResp) {
                    if (ajaxResp.stat === 'ok') {
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
            speed = speed || 'slow';

            if (show) {
                $(what).show(speed);
            } else {
                if (!$(what).is(':visible')) {
                    $(what).css('display', 'none');
                } else {
                    $(what).hide(speed);
                }
            }
        },

        /**
         * Used for version test cases - by kflorence, scentos and myatu
         *
         * @param string left A string containing the version that will become the left hand operand.
         * @param string oper The comparison operator to test against. By default, the "==" operator will be used.
         * @param string right A string containing the version that will become the right hand operand. By default, the current jQuery version will be used.
         * @return boolean Returns the evaluation of the expression, either
         */
        isVersion: function(left, oper, right) {
            // Default values
            oper  = oper || "==";
            right = right || $().jquery;

            var pre       = /pre/i
                , replace = /[^\d]+/g
                , l       = left.replace(replace, '')
                , r       = right.replace(replace, '')
                , l_len   = l.length
                , r_len   = r.length
                , l_pre   = pre.test(left)
                , r_pre   = pre.test(right);

            l = (r_len > l_len ? Number(l) * Math.pow(10, (r_len - l_len)) : Number(l));
            r = (l_len > r_len ? Number(r) * Math.pow(10, (l_len - r_len)) : Number(r));

            switch(oper) {
                case "==" : return (true === (l === r && (l_pre === r_pre)));
                case ">=" : return (true === (l >= r && (!l_pre || l_pre === r_pre)));
                case "<=" : return (true === (l <= r && (!r_pre || r_pre === l_pre)));
                case ">"  : return (true === (l > r || (l === r && r_pre)));
                case "<"  : return (true === (l < r || (l === r && l_pre)));
            }

            return false;
        }
    });
}(jQuery));
