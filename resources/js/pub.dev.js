/*
 * Copyright (c) 2011-2012 Mike Green <myatus@gmail.com>
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

        /** Called when an animation performed by SwitchBackground is completed */
        AnimationCompleted: function() {
            $('#myatu_bgm_top').css({'left' : '', 'top' : ''}); // Restore `left` and `top` to those defined by CSS
            $('#myatu_bgm_prev').remove();                      // Remove old background
            myatu_bgm.SetTimer();                               // Reset timer
        },

        /** Slides a new background image into position */
        AnimateSlide: function(scroll_in_from, duration, cover) {
            var new_img = $('#myatu_bgm_top'), old_img = $('#myatu_bgm_prev'),              // Images selectors
                pos_new_img = '-9000px', pos_old_img = '9000px',                            // Failsafe image positions
                dir   = 'top',                                                              // Default CSS positioning
                css   = new Object,
                ww    = $(window).width(),                                                  // Window width
                wp    = ((new_img.width() - ww) * 100) / ww,                                // Window image overflow %
                pos_p = (150 + wp) + '%',
                neg_p = '-' + (50 + wp) + '%',
                _isp  = function(s) { s = String(s); return (s.charAt(s.length-1) == '%') }; // Macro to check if value is a percentage

            // Determine starting position for new image, and ending position for old image
            switch (scroll_in_from) {
                // Vertical movement - the show/hide is to permit the browser to adjust the image to the window size (CSS styling)
                case 'top'   : new_img.show(); pos_new_img = '-'+new_img.height()+'px'; pos_old_img = new_img.height()+'px'; new_img.hide(); break;
                case 'bottom': pos_new_img = old_img.height()+'px'; pos_old_img = '-'+old_img.height()+'px'; break; //!!

                // Horizontla movement
                case 'left'  : 
                    dir = 'left';

                    var p = _isp(new_img.css(dir));
                    pos_new_img = (p) ? neg_p : '-'+ww+'px';
                    pos_old_img = (p) ? pos_p :     ww+'px';

                    break;

                case 'right' : 
                    dir = 'left';

                    var p = _isp(new_img.css(dir));

                    pos_new_img = (p) ? pos_p :     ww+'px';
                    pos_old_img = (p) ? neg_p : '-'+ww+'px';
                    break;
            }

            // Store original position and move new image out of view
            var pos_orig = new_img.css(dir);
            new_img.css(dir, pos_new_img).show();

            // Slide (scroll) old image out of the way, unless we're covering it
            if (cover == undefined || cover == false) {
                css[dir] = pos_old_img;
                old_img.animate(css, {'duration': duration, 'queue': false});
            }

            // Slide new image into orignal position
            css[dir] = pos_orig;
            new_img.animate(css, {'duration': duration, 'queue': false, 'complete': myatu_bgm.AnimationCompleted});
        },

        /** Event on background click */
        OnBackgroundClick: function(e) {
            if (e.target == this || $(e.target).hasClass('myatu_bgm_fs')) {
                window.location.href = e.data.url;
                return false;
            }
        },

        /** Event on background hover - changes the mouse pointer if the background is click-able */
        OnBackgroundHover: function(e) {
            $(this).css('cursor', (e.target == this || $(e.target).hasClass('myatu_bgm_fs')) ? 'pointer' : 'auto');
        },

        /** Make the background click-able */
        SetBackgroundLink: function(url) {
            var b = $('body');
            
            // Unbind our prior hover and click functions, and reset the mouse pointer 
            b.unbind('click', myatu_bgm.OnBackgroundClick).unbind('mouseover', myatu_bgm.OnBackgroundHover).css('cursor', 'auto');

            // Re-bind if we have a non-empty URL
            if (url != '' && url != '#') {
                b.bind('click', {'url': url}, myatu_bgm.OnBackgroundClick).bind('mouseover', myatu_bgm.OnBackgroundHover);
            }
        },

        /** Switch the background */
        SwitchBackground: function() {
            var is_fullsize = (background_manager_vars.is_fullsize == 'true'),
                prev_img = (is_fullsize) ? $('#myatu_bgm_top').attr('src') : $('body').css('background-image'),
                new_image = myatu_bgm.GetAjaxData('random_image', { 'prev_img' : prev_img, 'active_gallery': background_manager_vars.active_gallery });


            if (!new_image)
                return;

            // Replace/remove background link
            myatu_bgm.SetBackgroundLink(new_image.bg_link);

            if (is_fullsize) {
                // Clone to a 'prev' full-size image
                $('#myatu_bgm_top').clone().attr('id', 'myatu_bgm_prev').appendTo('body');

                // Hide and then set new top image (unbinding previous imgLoaded event)
                $('#myatu_bgm_top').hide().unbind('load').attr({
                    'src'   : new_image.url,
                    'alt'   : new_image.alt
                }).imgLoaded(function() {
                    var s = new_image.transition_speed, c = false;

                    switch (new_image.transition) {
                        // No transition
                        case 'none' :
                            $(this).show();
                            myatu_bgm.AnimationCompleted();
                            break;

                        case 'coverdown' : c = true; // Cover instead of slide. Remember nobreak
                        case 'slidedown' : myatu_bgm.AnimateSlide('top', s, c); break;

                        case 'coverup'   : c = true;
                        case 'slideup'   : myatu_bgm.AnimateSlide('bottom', s, c); break;

                        case 'coverright': c = true;
                        case 'slideright': myatu_bgm.AnimateSlide('left', s, c); break;

                        case 'coverleft' : c = true;
                        case 'slideleft' : myatu_bgm.AnimateSlide('right', s, c); break;

                        // Crossfade is standard transition
                        default:
                            // Fade-out the previous image at the same time the new image is being faded in.
                            $('#myatu_bgm_prev').animate({opacity:0}, {'duration': new_image.transition_speed, 'queue': false});

                            $(this).fadeIn(new_image.transition_speed, myatu_bgm.AnimationCompleted);
                            break;
                    }
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

        // Pre-set background link
        myatu_bgm.SetBackgroundLink($('#myatu_bgm_bg_link').attr('href'));

        // Remove fall-back background link (prefer the Javascript method)
        $('#myatu_bgm_bg_link').remove();

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
