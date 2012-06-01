/*!
 * Copyright (c) 2011-2012 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Portions Copyright (c) 2010 Paul Irish & Andreé Hansson (imgLoaded jQuery extension, released under MIT license)
 */
var mainWin=window.dialogArguments||opener||parent||top;if(typeof myatu_bgm==="undefined"){var myatu_bgm={}}(function(b){b.fn.extend({scrollTo:function(a){b(this).clearQueue().animate({scrollTop:b(a).offset().top},"fast");return b(this)},imgLoaded:function(d){var a=this.filter("img");a.each(function(){if(this.complete===undefined||this.complete||this.readyState===4){d.call(this)}else{b(this).one("load",function(c){d.call(this)})}})}});b.extend(myatu_bgm,{base_prefix:"myatu_bgm_",GetAjaxData:function(g,a,h){var i=(typeof h==="function"),j=false;b.ajax({type:"POST",dataType:"json",url:background_manager_ajax.url,timeout:5000,async:i,data:{action:background_manager_ajax.action,func:g,data:a,_ajax_nonce:background_manager_ajax.nonce},success:function(c){if(c.nonce===background_manager_ajax.nonceresponse&&c.stat==="ok"){j=c.data;if(i){h(j)}}}});return j},showHide:function(e,a,f){if(typeof f==="undefined"){f="slow"}if(a){b(e).show(f)}else{if(!b(e).is(":visible")){b(e).css("display","none")}else{b(e).hide(f)}}}})}(jQuery));