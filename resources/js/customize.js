/*!
 * Copyright (c) 2011-2012 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */
if(myatu_bgm===undefined){var myatu_bgm={}}(function(a){a.extend(myatu_bgm,{showHide:function(d,b){var c="fast";if(b){a(d).show(c)}else{if(!a(d).is(":visible")){a(d).css("display","none")}else{a(d).hide(c)}}},showHideBgSize:function(){var c="#customize-control-myatu_bgm",b=a(c+"_bg_size input:checked").val()!="full";myatu_bgm.showHide(a(c+"_bg_pos"),b);myatu_bgm.showHide(a(c+"_bg_repeat"),b);myatu_bgm.showHide(a(c+"_bg_scroll"),b);myatu_bgm.showHide(a(c+"_bg_stretch_hor"),b);myatu_bgm.showHide(a(c+"_bg_stretch_ver"),b);myatu_bgm.showHide(a(c+"_opacity"),!(b));myatu_bgm.showHide(a("#customize-control-divider_background_transitioning_effect"),!(b));myatu_bgm.showHide(a(c+"_active_transition"),!(b));myatu_bgm.showHide(a(c+"_transition_speed"),!(b))},});a(document).ready(function(b){b("#customize-control-myatu_bgm_bg_size input").change(myatu_bgm.showHideBgSize);myatu_bgm.showHideBgSize()})})(jQuery);
