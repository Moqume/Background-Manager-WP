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

            if (myatu_bgm.timer) clearTimeout(myatu_bgm.timer);

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

            // Close the balloon tip, if it is showing.
            $('#myatu_bgm_info_tab').btOff();

            // Set info tab content and link
            $('.myatu_bgm_info_tab a').attr('href', new_image.link);
            $('.myatu_bgm_info_tab_content img').attr('src', new_image.thumb);
            $('.myatu_bgm_info_tab_content h3').text(new_image.caption);
            $('.myatu_bgm_info_tab_desc').html(new_image.desc);
        }
    });

    $(document).ready(function($){
        myatu_bgm.SetTimer();

        if ($.isFunction($('#myatu_bgm_info_tab').bt)) {
            $('#myatu_bgm_info_tab').bt({
                contentSelector: "$('.myatu_bgm_info_tab_content')",
                killTitle: false,
                trigger: ['mouseover focus', 'mouseout blur'],
                //trigger: ['mouseover', 'blur'],
                positions: ['right', 'left'],
                fill: '#333',
                strokeStyle: '#666', 
                spikeLength: 20,
                spikeGirth: 20,
                overlap: 0,
                shrinkToFit: true,
                width: '450px',
                textzIndex: 19999,
                boxzIndex: 19998,
                wrapperzIndex: 19997,
                windowMargin: 20,
                cssStyles: {
                    fontFamily: '"Lucida Grande",Helvetica,Arial,Verdana,sans-serif', 
                    fontSize: '12px',
                    padding: '14px 4px 9px 14px',
                    color: '#eee'
                },
                shadow: true,
                shadowColor: 'rgba(0,0,0,.5)',
                shadowBlur: 8,
                shadowOffsetX: 4,
                shadowOffsetY: 4,
                showTip: function(box) {
                    // Only show the tip if there's something to show. As content is dynamic, we use this callback for that.
                    if (!$('.myatu_bgm_info_tab_content img').attr('src') &&
                        !$('.myatu_bgm_info_tab_desc').text() &&
                        !$('.myatu_bgm_info_tab_content h3').text())
                        return;

                    // Only set to width to 'auto' if there's no description. This maintains the
                    // width (and float of image) if there's something present and prevents overflow if shrunk.
                    if ($('.myatu_bgm_info_tab_desc').text() == '')
                        $(box).css('width', 'auto');

                    $(box).show();
                },
            });
        }
    });
})(jQuery);
