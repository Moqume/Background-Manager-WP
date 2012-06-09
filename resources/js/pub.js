/*!
 * Copyright (c) 2011-2012 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */
if(typeof myatu_bgm==="undefined"){var myatu_bgm={}}(function(b){b.extend(myatu_bgm,{previous_background:{},flux_transitions:["bars","zip","blinds","swipe","blocks","blocks2","concentric","warp"],image_holder:null,setTimer:function(){if(myatu_bgm.change_freq<=0){return}if(myatu_bgm.timer){clearTimeout(myatu_bgm.timer)}myatu_bgm.timer=setTimeout(myatu_bgm.switchBackground,myatu_bgm.change_freq*1000)},onBackgroundClick:function(e){var f=myatu_bgm.bg_track_clicks_category,a=myatu_bgm.current_background.bg_link;if(e.target===this||b(e.target).hasClass("myatu_bgm_fs")){b(document).trigger("myatu_bgm_background_click",[a]);if(myatu_bgm.bg_track_clicks==="true"&&typeof _gaq!=="undefined"&&b.isFunction(_gaq.push)){if(f===""){f="Background Manager"}_gaq.push(["_trackEvent",f,"Click",a])}setTimeout(function(){if(myatu_bgm.bg_click_new_window===true){window.open(a)}else{window.location.assign(a)}},500);return false}},onBackgroundHover:function(a){b(this).css("cursor",(a.target===this||b(a.target).hasClass("myatu_bgm_fs"))?"pointer":"auto")},setBackgroundLink:function(){var a=b("body");a.unbind("click",myatu_bgm.onBackgroundClick).unbind("mouseover",myatu_bgm.onBackgroundHover).css("cursor","auto");if(myatu_bgm.current_background.bg_link!==""&&myatu_bgm.current_background.bg_link!=="#"){a.bind("click",myatu_bgm.onBackgroundClick).bind("mouseover",myatu_bgm.onBackgroundHover)}},urlReplaceQueryArgVal:function(g,f,a){var h=new RegExp("(?![?&])"+f+"=(.*?(?=\\?|\\&(?!amp;)|#|$))","ig");return g.replace(h,f+"="+encodeURIComponent(a))},onAnimationCompleted:function(){b("#myatu_bgm_prev").remove();myatu_bgm.setTimer();b(document).trigger("myatu_bgm_finish_transition")},animateSlide:function(l,r,a){var m=b("#myatu_bgm_top"),q=b("#myatu_bgm_prev"),t=q.offset(),p={top:m.css("top"),left:m.css("left")},o,s,n;switch(l){case"top":s="top";o="-"+(m.height()-t.top)+"px";break;case"bottom":s="top";o=(q.height()+t.top)+"px";break;case"left":s="left";o="-"+(m.width()-t.left)+"px";break;case"right":s="left";o=(q.width()+t.left)+"px";break}m.css(s,o);m.show();if(typeof a==="undefined"||a===false){m.animate(p,{duration:r,complete:myatu_bgm.onAnimationCompleted,step:function(d,c){if(c.prop===s){switch(l){case"top":q.css(s,(m.height()+d)+"px");break;case"bottom":q.css(s,(d-q.height())+"px");break;case"left":q.css(s,(m.width()+d)+"px");break;case"right":q.css(s,(d-q.width())+"px");break}}}})}else{m.animate(p,{duration:r,complete:myatu_bgm.onAnimationCompleted})}},adjustImageSize:function(v){var r=(myatu_bgm.fs_center==="true"),t={left:0,top:0},n=b(window).height(),u=b(window).width(),s=u,x,q,p,a,w,o;if(myatu_bgm.is_fullsize!=="true"){return false}a=new Image();a.src=b(v).attr("src");w=a.width;o=a.height;a=null;q=w/o;x=s/q;if(x>=n){p=(x-n)/2;if(r){b.extend(t,{top:"-"+p+"px"})}}else{x=n;s=x*q;p=(s-u)/2;if(r){b.extend(t,{left:"-"+p+"px"})}}b(v).width(s).height(x).css(t);return true},loadImage:function(a,d){if(myatu_bgm.image_holder===null){myatu_bgm.image_holder=b("<img />").css({position:"absolute",display:"none"})}myatu_bgm.image_holder.attr("src",a).imgLoaded(function(){myatu_bgm.adjustImageSize(this);if(typeof d==="function"){d()}})},onWindowResize:function(d){var a=myatu_bgm.image_holder;if(a===null){return}myatu_bgm.adjustImageSize(a);b("div#myatu_bgm_top").css("background-size",a.width()+"px "+a.height()+"px");b("#myatu_bgm_top").css({left:a.css("left"),top:a.css("top"),width:a.width()+"px",height:a.height()+"px"})},addTopImage:function(k,h){var l,i=myatu_bgm.current_background.url,j=myatu_bgm.current_background.alt,a={display:"none"};myatu_bgm.loadImage(i,function(){if(myatu_bgm.Modernizr.backgroundsize){l=b("<div></div>");b.extend(a,{"background-image":"url("+i+")","background-size":myatu_bgm.image_holder.width()+"px "+myatu_bgm.image_holder.height()+"px","background-repeat":"no-repeat"})}else{l=myatu_bgm.image_holder.clone();l.attr("alt",j)}b.extend(a,{left:myatu_bgm.image_holder.css("left"),top:myatu_bgm.image_holder.css("top"),width:myatu_bgm.image_holder.width()+"px",height:myatu_bgm.image_holder.height()+"px"});l.attr({id:"myatu_bgm_top","class":"myatu_bgm_fs",style:k}).css(a).appendTo("#myatu_bgm_img_group");if(typeof h==="function"){h.call(l)}})},switchBackground:function(){var n=(myatu_bgm.is_fullsize==="true"),p=(myatu_bgm.is_preview==="true"),l=b("#myatu_bgm_info_tab"),m=false,j,o,k,a;if((n&&!b("#myatu_bgm_top").is(":visible"))){myatu_bgm.setTimer();return}b(document).trigger("myatu_bgm_switch_background");if(p){k=myatu_bgm.image_selection}myatu_bgm.GetAjaxData("select_image",{prev_img:myatu_bgm.current_background.url,selector:k,active_gallery:myatu_bgm.active_gallery},function(d){if(!d||d.url===myatu_bgm.current_background.url){return}myatu_bgm.previous_background=myatu_bgm.current_background;myatu_bgm.current_background=b.extend(myatu_bgm.current_background,d);if(n){if(p){j=Number(myatu_bgm.transition_speed);o=myatu_bgm.active_transition;if(o==="random"){o=myatu_bgm.transitions[Math.floor(Math.random()*myatu_bgm.transitions.length)]}}else{j=d.transition_speed;o=d.transition}b("#myatu_bgm_top").attr("id","myatu_bgm_prev");myatu_bgm.addTopImage(b("#myatu_bgm_prev").attr("style"),function(){if(myatu_bgm.Modernizr.backgroundsize){if(typeof a==="undefined"){a=new myatu_bgm_flux.slider("#myatu_bgm_img_group",{pagination:false,autoplay:false});b("#myatu_bgm_img_group").bind("fluxTransitionEnd",myatu_bgm.onAnimationCompleted)}}else{if(o!=="none"&&b.inArray(o,myatu_bgm.flux_transitions)>-1){o=""}}if(b.inArray(o,myatu_bgm.flux_transitions)>-1){j=j/50}b.extend(myatu_bgm.current_background,{transition:o,transition_speed:j});b(document).trigger("myatu_bgm_start_transition",[o,j,myatu_bgm.current_background]);switch(o){case"none":b(this).show();myatu_bgm.onAnimationCompleted();break;case"coverdown":m=true;case"slidedown":myatu_bgm.animateSlide("top",j,m);break;case"coverup":m=true;case"slideup":myatu_bgm.animateSlide("bottom",j,m);break;case"coverright":m=true;case"slideright":myatu_bgm.animateSlide("left",j,m);break;case"coverleft":m=true;case"slideleft":myatu_bgm.animateSlide("right",j,m);break;case"bars":case"blinds":case"zip":case"blocks":a.next(o,{delayBetweenBars:j});break;case"blocks2":a.next("blocks2",{delayBetweenDiagnols:j});break;case"concentric":case"warp":a.next(o,{delay:j*2});break;case"swipe":a.next(o);break;case"zoom":b("#myatu_bgm_prev").animate({opacity:0},{duration:j,queue:false});b(this).animate({width:b(this).width()*1.05,height:b(this).height()*1.05,opacity:"show"},{duration:j,complete:myatu_bgm.onAnimationCompleted});break;default:b("#myatu_bgm_prev").animate({opacity:0},{duration:j,queue:false});b(this).fadeIn(j,myatu_bgm.onAnimationCompleted);break}})}else{b("body").css("background-image",'url("'+d.url+'")');myatu_bgm.setTimer()}myatu_bgm.setBackgroundLink();if(l.length){if(b.isFunction(l.qtip)){l.qtip("api").hide()}}if(b("#myatu_bgm_pin_it_btn").length){var e=b("#myatu_bgm_pin_it_btn iframe").attr("src"),c=d.desc.replace(/(<([^>]+)>)/ig,"");e=myatu_bgm.urlReplaceQueryArgVal(e,"media",d.url);e=myatu_bgm.urlReplaceQueryArgVal(e,"description",c);b("#myatu_bgm_pin_it_btn iframe").attr("src",e)}})},initInfoTab:function(){var a=b("#myatu_bgm_info_tab");if(!b.isFunction(a.qtip)||!a.length){return}a.qtip({content:{text:function(h){var i=b("<img />").attr({src:myatu_bgm.current_background.thumb,alt:myatu_bgm.current_background.alt}),j=b("<div></div>").addClass("myatu_bgm_info_tab_content"),g=myatu_bgm.current_background.desc;if(myatu_bgm.info_tab_thumb==="true"){j.append(i)}if(g){i.css({width:"100px",height:"100px"});j.append(g)}else{i.css("margin","5px 0")}return j},title:{text:function(d){return myatu_bgm.current_background.caption},button:true}},style:{classes:"ui-tooltip-bootstrap ui-tooltip-shadow ui-tooltip-rounded"},hide:false,position:{adjust:{x:-10},viewport:b(window)}})}});b(document).ready(function(d){var a=d("#myatu_bgm_bg_link");if(a.length){myatu_bgm.setBackgroundLink();a.remove()}d(window).resize(myatu_bgm.onWindowResize);myatu_bgm.initInfoTab();myatu_bgm.setTimer()})}(jQuery));