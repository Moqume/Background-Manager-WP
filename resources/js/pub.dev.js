/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */
(function($){
    // Please use the `myatu_bgm` namespace
    $.extend(myatu_bgm, {
        SetTimer: function() {
            if (background_manager_vars.change_freq <= 0)
                return;

            clearTimeout(myatu_bgm.timer);
            myatu_bgm.timer = setTimeout('myatu_bgm.SwitchBackground()', background_manager_vars.change_freq * 1000);
        },

        SwitchBackground: function() {
            var is_fullsize = (background_manager_vars.is_fullsize == 'true'),
                prev_img = (is_fullsize) ? $('#myatu_bgm_top').attr('src') : $('body').css('background-image'),
                new_image = myatu_bgm.GetAjaxData('random_image', { 'prev_img' : prev_img, 'active_gallery': background_manager_vars.active_gallery });

            if (!new_image)
                return;

            if (is_fullsize) {
                // Clone to a 'prev' full-size image
                $('#myatu_bgm_top').clone().attr('id', 'myatu_bgm_prev').appendTo('body');

                // Hide and then set new top image
                $('#myatu_bgm_top').hide().attr({
                    'src'   : new_image.url,
                    'alt'   : new_image.alt
                }).imgLoaded(function() {
                    // Once the image is loaded, fade it in and then remove the underlaying 'prev' image
                    $(this).fadeIn('slow', function() {
                        $('#myatu_bgm_prev').remove();

                        // And repeat later
                        myatu_bgm.SetTimer();
                    });
                });
            } else {
                // Simply replace the body background
                $('body').css('background-image', 'url("' + new_image.url + '")');
                myatu_bgm.SetTimer();
            }
        }
    });

    $(document).ready(function($){
        myatu_bgm.SetTimer();
    });
})(jQuery);
