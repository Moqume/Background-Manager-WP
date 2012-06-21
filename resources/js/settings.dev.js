/*!
 * Copyright (c) 2011-2012 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */
if (typeof myatu_bgm === "undefined") {
    var myatu_bgm = {};
}

(function($){
    $.extend(myatu_bgm, {
        /** Shows additional layouts if 'Fullscreen' is not selected, hides otherwise. */
        showHideLayoutTable: function() {
            var is_full = ($('input[name="background_size"]:checked').val() === 'full');

            myatu_bgm.showHide('.bg_fs_layout', is_full);                   // Show/hide 'Full Screen' layout extras
            myatu_bgm.showHide('.bg_extra_layout', !is_full, false);        // Show/hide 'Normal' layout extras
            myatu_bgm.updateBackgroundOpacity((!is_full) ? 100 : false);    // Fix opacity to 100 if not 'Full Screen'

            myatu_bgm.showHideBackgroundTransition();                       // Determine if we can show Background Transition settings
        },

        /** Hides or shows additional settings for Background Information */
        showHideInfoExtra: function() {
            myatu_bgm.showHide('.info_tab_extra', $('#info_tab').is(':checked'));
        },

        /** Hides or shows additional settings for "Pin It" button */
        showHidePinItBtnExtra: function() {
            myatu_bgm.showHide('.pin_it_btn_extra', $('#pin_it_btn').is(':checked'));
        },

        /** Hides or shows additional settings for "Track Background Clicks" setting */
        showHideTrackClicksExtra: function() {
            myatu_bgm.showHide('#bg_track_clicks_extra', $('#bg_track_clicks').is(':checked'));
        },

        /** Hides or shows the "Ascending" and "Descending" option, if the change frequency is custom (see Background Transition event) */
        showHideSelector: function() {
            var is_custom_freq = ($('input[name="change_freq"]:checked').val() === 'custom');

            myatu_bgm.showHide('.image_sel_ad', is_custom_freq);
            myatu_bgm.showHide('#image_sel_random', is_custom_freq); // Hides the radio button

            // Make sure 'Random' is selected when not using a custom change frequency
            if (!is_custom_freq) {
                $('#image_sel_random').prop('checked',true);
            }
        },

        /** Hides or shows the "Remeber last displayed image" */
        showHideRememberLastImage: function() {
            var is_custom = ($('input[name="change_freq"]:checked').val() === 'custom');

            myatu_bgm.showHide('.change_freq_lc', is_custom);
        },

        /** Hides or shows the Background Transition settings */
        showHideBackgroundTransition: function() {
            var is_full = ($('input[name="background_size"]:checked').val() === 'full'),
                is_custom_freq = ($('input[name="change_freq"]:checked').val() === 'custom');

            myatu_bgm.showHide('.bg_transition', (is_full && is_custom_freq));

            // Also trigger the event for the Image Selector and Last Displayed Image option
            myatu_bgm.showHideSelector();
            myatu_bgm.showHideRememberLastImage();
        },

        /** Changes the preview background color according to the selection */
        updatePreviewColor: function() {
            var color = $('#background_color').val();

            if (color && color.charAt(0) === '#') {
                if (color.length > 1) {
                    $('#bg_preview_bg_color').css('background-color', color);
                    $('#clear_color').show();
                } else {
                    $('#bg_preview_bg_color').css('background-color', '');
                    $('#clear_color').hide();
                }
            }
        },

        /** Changes the opacity in the preview */
        updateOpacity : function(force_to, source, target) {
            var opacity = $(source).val(), str_opacity = '100';

            if (force_to) {
                opacity = force_to;
            }

            if (opacity < 10) {
                str_opacity = '.0' + opacity;
            } else  if (opacity < 100) {
                str_opacity = '.' + opacity;
            }

            $(target).css('opacity', str_opacity);
        },

        /** Changes the background opacity */
        updateBackgroundOpacity : function(force_to) {
            myatu_bgm.updateOpacity(force_to, '#background_opacity', '#bg_preview');
        },

        /** Changes the overlay opacity */
        updateOverlayOpacity : function() {
            myatu_bgm.updateOpacity(false, '#overlay_opacity', '#bg_preview_overlay');
        },

        /** Updates the overlay preview */
        updatePreviewOverlay: function() {
            var data = myatu_bgm.GetAjaxData('overlay_data', $('#active_overlay option:selected').val());

            if (data) {
                $('#bg_preview_overlay').css('background', 'url(\'' + data + '\') repeat fixed top left transparent');
            } else {
                $('#bg_preview_overlay').css('background', '');
            }
        },

        /** Updates the image used in the preview, taken from the selected gallery */
        updatePreviewGallery: function() {
            var id = $('#active_gallery option:selected').val(), img = myatu_bgm.GetAjaxData('select_image', {'active_gallery': id, 'prev_img': 'none'});

            if (img) {
                $('#bg_preview').css('background-image', 'url(\'' + img.thumb + '\')');
            } else {
                $('#bg_preview').css('background-image', '');
            }
        },

        /** Updates the preview layout according to the selected settings, ie., tiled, full screen */
        updatePreviewLayout: function() {
            var screen_size = $('input[name="background_size"]:checked').val(),
                position    = $('input[name="background_position"]:checked').val().replace('-', ' '),
                repeat      = $('input[name="background_repeat"]:checked').val(),
                stretch_h   = $('#background_stretch_horizontal').is(':checked'),
                stretch_v   = $('#background_stretch_vertical').is(':checked');

            if (screen_size === 'full') {
                // If full-screen, we emulate the result
                $('#bg_preview').css({
                    'background-size': '100% auto',
                    'background-repeat': 'no-repeat',
                    'background-position': '50% 50%'
                });
            } else {
                // The thumbnail is further resized to 50x50px
                $('#bg_preview').css({
                    'background-size': ((stretch_h) ? '100%' : '50px') + ' ' + ((stretch_v) ? '100%' : '50px'),
                    'background-repeat': repeat,
                    'background-position': position
                });
            }
        },

        /** Resets the color field */
        clearColor: function() {
            $('#background_color').val('#');
            myatu_bgm.updatePreviewColor();

            return false;
        }
    });

    $(document).ready(function($){
        // Color picker
        $('#color_picker').farbtastic(function(color) {
            $('#background_color').val(color);
            myatu_bgm.updatePreviewColor();
        });
        $.farbtastic('#color_picker').setColor($('#background_color').val());

        // Background Color field
        $('#background_color').focusin(function() {
            $('#color_picker').show();
        }).focusout(function() {
            $('#color_picker').hide();
            myatu_bgm.updatePreviewColor();
        }).keyup(function () {
            if (this.value.charAt(0) !== '#') {
                this.value = '#' + this.value;
            }
            $.farbtastic('#color_picker').setColor($('#background_color').val());
            myatu_bgm.updatePreviewColor();
        });

        // Opacity picker
	    $('#opacity_picker').slider({
		    value: $('#background_opacity').val(),
            range: 'min',
		    min: 1,
		    max: 100,
		    slide: function(event, ui) {
			    $("#background_opacity").val(ui.value);
                $("#opacity_picker_val").text(ui.value + '%');
                myatu_bgm.updateBackgroundOpacity();
		    }
	    });

        // Overlay opacity picker
	    $('#ov_opacity_picker').slider({
		    value: $('#overlay_opacity').val(),
            range: 'min',
		    min: 1,
		    max: 100,
		    slide: function(event, ui) {
			    $("#overlay_opacity").val(ui.value);
                $("#ov_opacity_picker_val").text(ui.value + '%');
                myatu_bgm.updateOverlayOpacity();
		    }
	    });

        // Transition Speed picker
	    $('#transition_speed_picker').slider({
		    value: 15100 - $('#transition_speed').val(),
		    min: 100,
		    max: 15000,
            step: 100,
		    slide: function(event, ui) {
			    $("#transition_speed").val(15100 - ui.value);
                myatu_bgm.updateOpacity();
		    }
	    });

        // Set and Pre-set complex events
        myatu_bgm.updatePreviewColor();
        myatu_bgm.updateBackgroundOpacity();
        myatu_bgm.updateOverlayOpacity();

        $('#info_tab').change(myatu_bgm.showHideInfoExtra);                             myatu_bgm.showHideInfoExtra();
        $('#pin_it_btn').change(myatu_bgm.showHidePinItBtnExtra);                       myatu_bgm.showHidePinItBtnExtra();
        $('#bg_track_clicks').change(myatu_bgm.showHideTrackClicksExtra);               myatu_bgm.showHideTrackClicksExtra();
        $('input[name="background_size"]').change(myatu_bgm.showHideLayoutTable);       myatu_bgm.showHideLayoutTable();
        $('#active_gallery').change(myatu_bgm.updatePreviewGallery);                    myatu_bgm.updatePreviewGallery();
        $('#active_overlay').change(myatu_bgm.updatePreviewOverlay);                    myatu_bgm.updatePreviewOverlay();
        $('input[name="background_size"]').change(myatu_bgm.updatePreviewLayout);       myatu_bgm.updatePreviewLayout();
        $('input[name="background_position"]').change(myatu_bgm.updatePreviewLayout);   // ..
        $('input[name="background_repeat"]').change(myatu_bgm.updatePreviewLayout);     // ..
        $('#background_stretch_horizontal').change(myatu_bgm.updatePreviewLayout);      // ..
        $('#background_stretch_vertical').change(myatu_bgm.updatePreviewLayout);        // ..
        $('input[name="change_freq"]').change(myatu_bgm.showHideBackgroundTransition);  // No pre-set (handled by updatePreviewLayout())

        // Button
        $('#clear_color').click(myatu_bgm.clearColor);                                  // No pre-set

        // Simple event
        $('#footer_debug_link').click(function() { $('#footer_debug').toggle(); return false; });

        // Scroll event (to keep the preview in view)
        var preview_pad = 35, preview_div = $('#bg_preview_div'), preview_offset = preview_div.offset().top;

        $(window).scroll(function(){
            var scroll_pos  = $(window).scrollTop() - $('#screen-meta').height(),                   // The current "scroll" position, less height of screen meta
                bottom_edge = $('#submit').offset().top - $('#screen-meta').height() - preview_pad; // Where to stop keeping it in view (bottom edge)

            // If we scrolled past the offset of the preview ...
            if (scroll_pos > preview_offset - preview_pad) {
                // ... But haven't reached the bottom edge yet (prevent covering the "debug" output)
                if (scroll_pos + preview_div.height() + 10 < bottom_edge) {
                    preview_div.css({'box-shadow' : '5px 5px 5px #aaa', 'border' : '1px solid #aaa'});
                    preview_div.stop().animate({'top': (scroll_pos - preview_offset + preview_pad) + 'px'}, 'slow');
                }
            } else {
                preview_div.css({'box-shadow': '', 'border' : ''});
                preview_div.stop().animate({'top': 0});
            }
        });
    });
}(jQuery));
