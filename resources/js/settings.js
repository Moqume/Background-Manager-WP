/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */
(function($){
    $.extend(myatu_bgm, {
        /** Shows additional layouts if 'Fullscreen' is not selected, hides otherwise. */
        showHideLayoutTable: function(e) {
            if ((typeof e === 'string' && e == 'full') || this.value == 'full') {
                $('.bg_extra_layout').hide();
            } else {
                $('.bg_extra_layout').show();
            }
        },

        /** Hides or shows additional settings for Background Information */
        showHideInfoExtra: function() {
            if ($('#info_tab:checked').length) {
                $('.info_tab_extra').show();
            } else {
                $('.info_tab_extra').hide();
            }
        },

        /** Changes the preview background color according to the selection */
        updatePreviewColor: function() {
            var color = $('#background_color').val();

            if (color && color.charAt(0) == '#') {
                if (color.length > 1) {
                    $('#bg_preview').css('background-color', color);
                    $('#clear_color').show();
                } else {
                    $('#bg_preview').css('background-color', '');
                    $('#clear_color').hide();
                }
            }
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
            var id = $('#active_gallery option:selected').val(), img = myatu_bgm.GetAjaxData('random_image', {'active_gallery': id, 'prev_img': 'none'});

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
                stretch_h   = ($('#background_stretch_horizontal:checked').length == 1),
                stretch_v   = ($('#background_stretch_vertical:checked').length == 1);

            if (screen_size == 'full') {
                // If full-screen, we emulate the result
                $('#bg_preview').css({
                    'background-size': '100% auto',
                    'background-repeat': 'no-repeat',
                    'background-position': 'top left',
                });
            } else {
                // The thumbnail is further resized to 50x50px
                $('#bg_preview').css({
                    'background-size': ((stretch_h) ? '100%' : '50px') + ' ' + ((stretch_v) ? '100%' : '50px'),
                    'background-repeat': repeat,
                    'background-position': position,
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
        // Pre-set values
        myatu_bgm.updatePreviewColor();
        myatu_bgm.updatePreviewGallery();
        myatu_bgm.updatePreviewLayout();
        myatu_bgm.updatePreviewOverlay();
        myatu_bgm.showHideInfoExtra();
        myatu_bgm.showHideLayoutTable($('input[name="background_size"]:checked').val());

        // Background Color field
        $('#background_color').focusin(function() { 
            $('#color_picker').show(); 
        }).focusout(function() { 
            $('#color_picker').hide(); 
            myatu_bgm.updatePreviewColor();
        }).keyup(function () { 
            if (this.value.charAt(0) != '#') this.value = '#' + this.value; 
            $.farbtastic('#color_picker').setColor($('#background_color').val()); 
            myatu_bgm.updatePreviewColor();
        });

        // Color picker
        $('#color_picker').farbtastic(function(color) { $('#background_color').attr('value', color); $('#bg_preview').css('background-color', color) });
        $.farbtastic('#color_picker').setColor($('#background_color').val());

        // Set events
        $('input[name="background_size"]').change(myatu_bgm.showHideLayoutTable);
        $('#active_gallery').change(myatu_bgm.updatePreviewGallery);
        $('#active_overlay').change(myatu_bgm.updatePreviewOverlay);
        $('input[name="background_size"]').change(myatu_bgm.updatePreviewLayout);
        $('input[name="background_position"]').change(myatu_bgm.updatePreviewLayout);
        $('input[name="background_repeat"]').change(myatu_bgm.updatePreviewLayout);
        $('#background_stretch_horizontal').click(myatu_bgm.updatePreviewLayout);
        $('#background_stretch_vertical').click(myatu_bgm.updatePreviewLayout);
        $('#info_tab').click(myatu_bgm.showHideInfoExtra);
        $('#clear_color').click(myatu_bgm.clearColor);
    });
})(jQuery);
