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
        base_control_prefix : '#customize-control-myatu_bgm_',

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
            var show = $(myatu_bgm.base_control_prefix + 'background_size input:checked').val() != 'full';

            // Show on !full(screen):
            myatu_bgm.showHide($(myatu_bgm.base_control_prefix + 'background_position'), show);
            myatu_bgm.showHide($(myatu_bgm.base_control_prefix + 'background_repeat'), show);
            myatu_bgm.showHide($(myatu_bgm.base_control_prefix + 'background_scroll'), show);
            myatu_bgm.showHide($(myatu_bgm.base_control_prefix + 'background_stretch_horizontal'), show);
            myatu_bgm.showHide($(myatu_bgm.base_control_prefix + 'background_stretch_vertical'), show);

            // Show on full(screen):
            myatu_bgm.showHide($(myatu_bgm.base_control_prefix + 'opacity'), !(show));

            // Ensure the transition is also shown or hidden
            myatu_bgm.showHideCustomFreq();
        },

        showHideCustomFreq: function() {
            var full         = $(myatu_bgm.base_control_prefix + 'background_size input:checked').val() == 'full',
                custom_freq  = $(myatu_bgm.base_control_prefix + 'change_freq input:checked').val() == 'custom',
                show_in_full = (custom_freq && full);

            // Show on custom_freq
            myatu_bgm.showHide($(myatu_bgm.base_control_prefix + 'change_freq_custom'), custom_freq);

            // Show or hide in full(screen)
            myatu_bgm.showHide($('#customize-control-divider_background_transitioning_effect'), show_in_full);
            myatu_bgm.showHide($(myatu_bgm.base_control_prefix + 'background_transition'), show_in_full);
            myatu_bgm.showHide($(myatu_bgm.base_control_prefix + 'transition_speed'),  show_in_full);
        },

        showHideInfoTab: function() {
            var show = $(myatu_bgm.base_control_prefix + 'info_tab input').is(':checked');

            myatu_bgm.showHide($(myatu_bgm.base_control_prefix + 'info_tab_location'), show);
        },

        showHidePinIt: function() {
            var show = $(myatu_bgm.base_control_prefix + 'pin_it_btn input').is(':checked');

            myatu_bgm.showHide($(myatu_bgm.base_control_prefix + 'pin_it_btn_location'), show);
        }
    });

    $(document).ready(function($){
        $(myatu_bgm.base_control_prefix + 'background_size input').change(myatu_bgm.showHideBgSize);    myatu_bgm.showHideBgSize();
        $(myatu_bgm.base_control_prefix + 'change_freq input').change(myatu_bgm.showHideCustomFreq);    myatu_bgm.showHideCustomFreq();
        $(myatu_bgm.base_control_prefix + 'info_tab input').change(myatu_bgm.showHideInfoTab);          myatu_bgm.showHideInfoTab();
        $(myatu_bgm.base_control_prefix + 'pin_it_btn input').change(myatu_bgm.showHidePinIt);          myatu_bgm.showHidePinIt();        
    });
})(jQuery);
