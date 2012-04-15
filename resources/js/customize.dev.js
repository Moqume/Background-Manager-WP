/*!
 * Copyright (c) 2011-2012 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */
if (myatu_bgm === undefined)
    var myatu_bgm = {};

(function($){
    $.extend(myatu_bgm, {
        /**
         * Hides a HTML element if an input value matches
         */
        showHide: function(what, show) {
            var speed = 'fast';

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

        showHideBgSize: function() {
            var base = '#customize-control-myatu_bgm', show = $(base + '_bg_size input:checked').val() != 'full';

            // Show on !full:
            myatu_bgm.showHide($(base + '_bg_pos'), show);
            myatu_bgm.showHide($(base + '_bg_repeat'), show);
            myatu_bgm.showHide($(base + '_bg_scroll'), show);
            myatu_bgm.showHide($(base + '_bg_stretch_hor'), show);
            myatu_bgm.showHide($(base + '_bg_stretch_ver'), show);

            // Show on full:
            myatu_bgm.showHide($(base + '_opacity'), !(show));
            myatu_bgm.showHide($('#customize-control-divider_background_transitioning_effect'), !(show));
            myatu_bgm.showHide($(base + '_active_transition'), !(show));
            myatu_bgm.showHide($(base + '_transition_speed'), !(show));
        },
    });

    $(document).ready(function($){        
        $('#customize-control-myatu_bgm_bg_size input').change(myatu_bgm.showHideBgSize);    myatu_bgm.showHideBgSize();

    });
})(jQuery);
