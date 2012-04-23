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
        base_control_prefix : '#customize-control-' + myatu_bgm.base_prefix,

        showHideBgSize: function() {
            var show = $(myatu_bgm.base_control_prefix + 'background_size input:checked').val() != 'full';

            // Show on !full(screen):
            myatu_bgm.showHide($(myatu_bgm.base_control_prefix + 'background_position'), show);
            myatu_bgm.showHide($(myatu_bgm.base_control_prefix + 'background_repeat'), show);
            myatu_bgm.showHide($(myatu_bgm.base_control_prefix + 'background_scroll'), show);
            myatu_bgm.showHide($(myatu_bgm.base_control_prefix + 'background_stretch_horizontal'), show);
            myatu_bgm.showHide($(myatu_bgm.base_control_prefix + 'background_stretch_vertical'), show);

            // Show on full(screen):
            myatu_bgm.showHide($(myatu_bgm.base_control_prefix + 'background_opacity'), !(show));
            myatu_bgm.showHide($(myatu_bgm.base_control_prefix + 'full_screen_adjust'), !(show));

            // Ensure the transition and center adjust is also shown or hidden
            myatu_bgm.showHideCustomFreq();
            myatu_bgm.showHideAdjust();
        },

        showHideCustomFreq: function() {
            var full         = $(myatu_bgm.base_control_prefix + 'background_size input:checked').val() == 'full',
                custom_freq  = $(myatu_bgm.base_control_prefix + 'change_freq input:checked').val() == 'custom',
                show_in_full = (custom_freq && full),
                random_selector = $(myatu_bgm.base_control_prefix + 'image_selection input:radio[value=random]');

            // Show on custom_freq
            myatu_bgm.showHide($(myatu_bgm.base_control_prefix + 'change_freq_custom'), custom_freq);
            myatu_bgm.showHide($(myatu_bgm.base_control_prefix + 'image_selection input:radio[value=asc]').parent(), custom_freq);
            myatu_bgm.showHide($(myatu_bgm.base_control_prefix + 'image_selection input:radio[value=desc]').parent(), custom_freq);
            myatu_bgm.showHide(random_selector, custom_freq);

            // A bit of extra magic for 'Random' image selector
            if (!custom_freq) {
                if (!random_selector.is(':checked')) {
                    $(random_selector).attr('checked',true);
                    $(myatu_bgm.base_control_prefix + 'image_selection input').change();
                }
            }
            

            // Show or hide in full(screen)
            myatu_bgm.showHide($('#customize-control-divider_background_transitioning_effect'), show_in_full);
            myatu_bgm.showHide($(myatu_bgm.base_control_prefix + 'background_transition'), show_in_full);
            myatu_bgm.showHide($(myatu_bgm.base_control_prefix + 'transition_speed'),  show_in_full);
        },

        showHideInfoTab: function() {
            var show = $(myatu_bgm.base_control_prefix + 'info_tab input').is(':checked');

            myatu_bgm.showHide($(myatu_bgm.base_control_prefix + 'info_tab_desc'), show);
            myatu_bgm.showHide($(myatu_bgm.base_control_prefix + 'info_tab_thumb'), show);
            myatu_bgm.showHide($(myatu_bgm.base_control_prefix + 'info_tab_link'), show);
            myatu_bgm.showHide($(myatu_bgm.base_control_prefix + 'info_tab_location'), show);
        },

        showHidePinIt: function() {
            var show = $(myatu_bgm.base_control_prefix + 'pin_it_btn input').is(':checked');

            myatu_bgm.showHide($(myatu_bgm.base_control_prefix + 'pin_it_btn_location'), show);
        },

        showHideAdjust: function() {
            var full    = $(myatu_bgm.base_control_prefix + 'background_size input:checked').val() == 'full',
                checked = $(myatu_bgm.base_control_prefix + 'full_screen_adjust input').is(':checked'),
                show    = true;

            if (!full) {
                show = false;
            } else {
                show = checked;
            }

            myatu_bgm.showHide($(myatu_bgm.base_control_prefix + 'full_screen_center'), show);
        }
    });

    $(document).ready(function($){
        $(myatu_bgm.base_control_prefix + 'background_size input').change(myatu_bgm.showHideBgSize);    myatu_bgm.showHideBgSize();
        $(myatu_bgm.base_control_prefix + 'change_freq input').change(myatu_bgm.showHideCustomFreq);    myatu_bgm.showHideCustomFreq();
        $(myatu_bgm.base_control_prefix + 'info_tab input').change(myatu_bgm.showHideInfoTab);          myatu_bgm.showHideInfoTab();
        $(myatu_bgm.base_control_prefix + 'pin_it_btn input').change(myatu_bgm.showHidePinIt);          myatu_bgm.showHidePinIt();
        $(myatu_bgm.base_control_prefix + 'full_screen_adjust input').change(myatu_bgm.showHideAdjust); myatu_bgm.showHideAdjust();                
    });
})(jQuery);
