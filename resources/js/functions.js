/*!
 * Copyright (c) 2011-2012 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Portions Copyright (c) 2010 Paul Irish & Andreé Hansson (imgLoaded jQuery extension, released under MIT license)
 */
mainWin=window.dialogArguments||opener||parent||top;(function(a){a.fn.extend({scrollTo:function(b){a(this).clearQueue().animate({scrollTop:a(b).offset().top},"fast");return a(this)},imgLoaded:function(c){var b=this.filter("img");b.each(function(){if(this.complete===undefined||this.complete||this.readyState===4){c.call(this)}else{a(this).one("load",function(d){c.call(this)})}})}});myatu_bgm={base_prefix:"myatu_bgm_",GetAjaxData:function(f,b,e){var d=(e!==undefined&&typeof(e)==="function"),c=false;a.ajax({type:"POST",dataType:"json",url:background_manager_ajax.url,timeout:5000,async:d,data:{action:background_manager_ajax.action,func:f,data:b,_ajax_nonce:background_manager_ajax.nonce},success:function(g){if(g.nonce==background_manager_ajax.nonceresponse&&g.stat=="ok"){c=g.data;if(d){e(c)}}}});return c},showHide:function(d,b,c){if(c==undefined){c="slow"}if(b){a(d).show(c)}else{if(!a(d).is(":visible")){a(d).css("display","none")}else{a(d).hide(c)}}}}})(jQuery);
