/*
 * Copyright (c) 2011-2012 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Portions Copyright (c) 2010 Paul Irish & Andreé Hansson (imgLoaded jQuery extension, released under MIT license)
 */
mainWin=window.dialogArguments||opener||parent||top;(function(a){a.fn.extend({scrollTo:function(b){a(this).clearQueue().animate({scrollTop:a(b).offset().top},"fast");return a(this)},imgLoaded:function(f,d){var c=arguments,b=this.filter("img"),e=b.length-1;b.bind("load",function(g){if(d){!e--&&f.call(b)}else{f.call(this)}});a(function(){b.each(function(){if(this.complete===undefined||this.complete||this.readyState===4){f.call(this)}})})}});myatu_bgm={GetObjSize:function(d){var c=0,b;for(b in d){if(d.hasOwnProperty(b)){c++}}return c},GetAjaxData:function(d,b){var c=false;a.ajax({type:"POST",dataType:"json",url:background_manager_ajax.url,timeout:5000,async:false,data:{action:background_manager_ajax.action,func:d,data:b,_ajax_nonce:background_manager_ajax.nonce},success:function(e){if(e.nonce==background_manager_ajax.nonceresponse&&e.stat=="ok"){c=e.data}}});return c},}})(jQuery);
