/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */
(function($){
    $.extend(myatu_bgm, {
        showHideLayoutTable: function(e) {
            if ((typeof e === 'string' && e == 'full') || this.value == 'full') {
                $('.bg_extra_layout').hide();
            } else {
                $('.bg_extra_layout').show();
            }
        },

        showHideInfoExtra: function() {
            if ($('#info_tab:checked').length) {
                $('.info_tab_extra').show();
            } else {
                $('.info_tab_extra').hide();
            }
        },

        updatePreviewColor: function() {
            var color = $('#background_color').val();

            if (color && color.charAt(0) == '#') {
                if (color.length > 1) {
                    $('#bg_preview').css('background-color', color);
                } else {
                    $('#bg_preview').css('background-color', '');
                }
            }
        },

        updatePreviewOverlay: function() {
            var data = myatu_bgm.GetAjaxData('overlay_data', $('#active_overlay option:selected').val());

            if (data) {
                $('#bg_preview_overlay').css('background', 'url(\'' + data + '\') repeat fixed top left transparent');
            } else {
                $('#bg_preview_overlay').css('background', '');
            }
        },

        updatePreviewGallery: function() {
            var id = $('#active_gallery option:selected').val(), img = myatu_bgm.GetAjaxData('preview_image', id);

            if (img) {
                $('#bg_preview').css('background-image', 'url(\'' + img.url + '\')');
            } else {
                $('#bg_preview').css('background-image', '');
            }
        },

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
    });
})(jQuery);
