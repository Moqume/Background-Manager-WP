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
        /** (Re)sets the timer for loading the next image */
        SetTimer: function() {
            if (background_manager_vars.change_freq <= 0)
                return;

            if (myatu_bgm.timer) clearTimeout(myatu_bgm.timer);

            myatu_bgm.timer = setTimeout('myatu_bgm.SwitchBackground()', background_manager_vars.change_freq * 1000);
        },

        /** Event on background click */
        OnBackgroundClick: function(e) {
            if (e.target == this || $(e.target).hasClass('myatu_bgm_fs')) {
                window.open(e.data.url);
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

        /** Replaces a query argument's value in a URL */
        UrlReplaceQueryArgVal: function(original_url, query_arg, new_val) {
            var pattern = new RegExp('(?![?&])' + query_arg + '=(.*?(?=\\?|\\&(?!amp;)|#|$))', 'ig');

            return original_url.replace(pattern, query_arg + '=' + encodeURIComponent(new_val));
        },

        /** Adjusts the image size to fill the background, based on the work of Scott Robbin (srobbin.com) */
        AdjustImageSize: function() {
            var img        = $('#myatu_bgm_top'),
                centered   = (background_manager_vars.fs_center == 'true'),
                css        = {'left' : 0,'top' : 0},
                win_height = $(window).height(),
                bg_width   = win_width = $(window).width(),
                bg_height, ratio, bg_offset;

            if (background_manager_vars.is_fullsize == 'false' || background_manager_vars.fs_adjust == 'false')
                return; // Adjusting is disabled

            img.css({'width':'','height':''}); // Remove current image width/height, if any, before determining the ratio and bg_height
            ratio     = img.width() / img.height();
            bg_height = bg_width / ratio;

            if (bg_height >= win_height) {
                bg_offset = (bg_height - win_height) / 2;
                if (centered) {
                    $.extend(css, {'top': '-' + bg_offset + 'px'});
                }
            } else {
                bg_height = win_height;
                bg_width  = bg_height * ratio;
                bg_offset = (bg_width - win_width) / 2;
                if (centered)
                    $.extend(css, {'left': '-' + bg_offset + 'px'});
            }

            img.width(bg_width).height(bg_height).css(css);
        },

        /** Called when an animation performed by SwitchBackground is completed */
        AnimationCompleted: function() {
            $('#myatu_bgm_prev').remove();  // Remove old background
            myatu_bgm.SetTimer();           // Reset timer

            $(document).trigger('myatu_bgm_finish_transition');
        },

        /** Slides a new background image into position */
        AnimateSlide: function(scroll_in_from, duration, cover) {
            var new_img      = $('#myatu_bgm_top'),
                old_img      = $('#myatu_bgm_prev'),
                new_offset   = new_img.offset(),
                old_offset   = old_img.offset(),
                css          = {},
                start_position, dir;

            // Determine starting position for new image, and ending position for old image
            switch (scroll_in_from) {
                case 'top'    : dir = 'top';  css[dir] = new_offset.top  + 'px'; pos = new_img.height() - old_offset.top; start_position = '-' + pos + 'px'; break;
                case 'bottom' : dir = 'top';  css[dir] = new_offset.top  + 'px'; pos = old_img.height() + old_offset.top; start_position =       pos + 'px'; break;
                case 'left'   : dir = 'left'; css[dir] = new_offset.left + 'px'; pos = new_img.width() - old_offset.left; start_position = '-' + pos + 'px'; break;
                case 'right'  : dir = 'left'; css[dir] = new_offset.left + 'px'; pos = old_img.width() + old_offset.left; start_position =       pos + 'px'; break;
            }

            new_img.css(dir, start_position);   // Move the new image to one of the edges of the old image
            new_img.css('visibility','');       // Unhide the new image (as it is out of view now) - separate call due execution order

            // Slide
            if (cover === undefined || cover === false) {
                new_img.animate(css, {
                    'duration': duration,
                    'complete': myatu_bgm.AnimationCompleted,
                    'step': function(now,fx) {
                        // Keep old image "sticking" to edge of new image
                        switch (scroll_in_from) {
                            case 'top'    : old_img.css(dir, (new_img.height() + now) + 'px'); break;
                            case 'bottom' : old_img.css(dir, (now - old_img.height()) + 'px'); break;
                            case 'left'   : old_img.css(dir, (new_img.width() + now) + 'px'); break;
                            case 'right'  : old_img.css(dir, (now - old_img.width()) + 'px'); break;
                        }
                    }
                });
            } else {
                // Cover
                new_img.animate(css, {'duration': duration, 'complete': myatu_bgm.AnimationCompleted});
            }
        },

        NewTopImage: function(style, alt, src, callback) {
            // Create new (hidden) image element as the 'top' image
            $('<img>').attr({
                'style' : style,
                'class' : 'myatu_bgm_fs',
                'id'    : 'myatu_bgm_top',
                'alt'   : alt
            }).css({'visibility':'hidden','width':'','height':''}).appendTo('body');

            // Set image source and when done loading, call callback
            $('#myatu_bgm_top').attr('src', src).imgLoaded(function() {
                if (typeof callback == "function")
                    callback.call(this);
            });
        },

        /** Switch the background */
        SwitchBackground: function() {
            var is_fullsize = (background_manager_vars.is_fullsize == 'true'),
                is_preview  = (background_manager_vars.is_preview  == 'true'),
                info_tab    = $('#myatu_bgm_info_tab'),
                prev_img    = (is_fullsize) ? $('#myatu_bgm_top').attr('src') : $('body').css('background-image'),
                prev_style  = '',
                transition_speed, active_transition, image_selection;

            if (is_preview) {
                // Override the method for selecting an image in the preview
                image_selection = background_manager_vars.image_selection;
            }

            // Async call
            myatu_bgm.GetAjaxData('select_image', { 'prev_img' : prev_img, 'selector' : image_selection, 'active_gallery': background_manager_vars.active_gallery }, function(new_image) {
                if (!new_image)
                    return;

                // Replace/remove background link
                myatu_bgm.SetBackgroundLink(new_image.bg_link);

                if (is_fullsize) {
                    // Set transition speed to what's returned by AJAX, or what's specified in preview
                    if (is_preview) {
                        // Preview variables
                        transition_speed  = Number(background_manager_vars.transition_speed);
                        active_transition = background_manager_vars.active_transition;

                        // We do the random picking here when in a preview
                        if (active_transition == 'random') {
                            active_transition = background_manager_vars.transitions[Math.floor(Math.random()*background_manager_vars.transitions.length)];
                        }
                    } else {
                        // AJAX variables
                        transition_speed  = new_image.transition_speed;
                        active_transition = new_image.transition;
                    }

                    // Grab the current style (grabs the opacity)
                    prev_style = $('#myatu_bgm_top').attr('style');

                    // Switch image ID ('top' becomes 'prev')
                    $('#myatu_bgm_top').attr('id', 'myatu_bgm_prev');

                    // Create a new top image and perform callback when done loading
                    myatu_bgm.NewTopImage(prev_style, new_image.alt, new_image.url, function() {
                        var c = false; // Cover or slide?

                        // Resize the image according to the window width/height
                        myatu_bgm.AdjustImageSize();

                        // Force the transition to 'none' if 'myatu_bgm_prev' is missing (failsafe)
                        if (!$('#myatu_bgm_prev').length)
                            active_transition = 'none';

                        // Custom event - function(event, active_transition, transition_speed, new_image_object)
                        $(document).trigger('myatu_bgm_start_transition', [active_transition, transition_speed, new_image]);

                        switch (active_transition) {
                            // No transition
                            case 'none' :
                                $(this).css('visibility','');
                                myatu_bgm.AnimationCompleted();
                                break;

                            case 'coverdown' : c = true; // Cover instead of slide. Remember nobreak
                            case 'slidedown' : myatu_bgm.AnimateSlide('top', transition_speed, c); break;

                            case 'coverup'   : c = true;
                            case 'slideup'   : myatu_bgm.AnimateSlide('bottom', transition_speed, c); break;

                            case 'coverright': c = true;
                            case 'slideright': myatu_bgm.AnimateSlide('left', transition_speed, c); break;

                            case 'coverleft' : c = true;
                            case 'slideleft' : myatu_bgm.AnimateSlide('right', transition_speed, c); break;

                            case 'zoom' :
                                $(this).css({'display':'none','visibility':''});

                                // Fade-out the previous image at the same time the new image is being faded in.
                                $('#myatu_bgm_prev').animate({opacity:0}, {'duration': transition_speed, 'queue': false});

                                // Fade in the image whilst zooming it sightly
                                $(this).animate(
                                    {
                                        'width'   : $(this).width() * 1.05,
                                        'height'  : $(this).height() * 1.05,
                                        'opacity' : 'show'
                                    },
                                    {
                                        'duration': transition_speed,
                                        'complete': myatu_bgm.AnimationCompleted
                                    }
                                );
                                break;

                            // Crossfade is standard transition
                            default:
                                // Swap 'visibility' with 'display'
                                $(this).css({'display':'none','visibility':''});

                                // Fade-out the previous image at the same time the new image is being faded in.
                                $('#myatu_bgm_prev').animate({opacity:0}, {'duration': transition_speed, 'queue': false});

                                $(this).fadeIn(transition_speed, myatu_bgm.AnimationCompleted);
                                break;
                        }
                    });
                } else {
                    // Simply replace the body background
                    $('body').css('background-image', 'url("' + new_image.url + '")');
                    myatu_bgm.SetTimer();
                }

                // Info tab
                if (info_tab.length) {
                    // Close the balloon tip, if it is showing.
                    if ($.isFunction(info_tab.qtip))
                        info_tab.qtip('api').hide();

                    // Set info tab content and link
                    $('.myatu_bgm_info_tab a').attr('href', new_image.link);
                    $('.myatu_bgm_info_tab_content img').attr('src', new_image.thumb);
                    $('.myatu_bgm_info_tab_content h3').text(new_image.caption);
                    $('.myatu_bgm_info_tab_desc').html(new_image.desc);
                }

                // "Pin it" button
                if ($('#myatu_bgm_pin_it_btn').length) {
                    // Replace "Pin it" button's iFrame source
                    var pin_it_src = $('#myatu_bgm_pin_it_btn iframe').attr('src'), clean_desc = new_image.desc.replace(/(<([^>]+)>)/ig,'');

                    pin_it_src = myatu_bgm.UrlReplaceQueryArgVal(pin_it_src, 'media', new_image.url);       // Replace image URL
                    pin_it_src = myatu_bgm.UrlReplaceQueryArgVal(pin_it_src, 'description', clean_desc);    // Replace description

                    $('#myatu_bgm_pin_it_btn iframe').attr('src', pin_it_src)
                }
            });
        }
    });

    $(document).ready(function($){
        var bg_link = $('#myatu_bgm_bg_link'), info_tab = $('#myatu_bgm_info_tab');

        myatu_bgm.SetTimer();

        $(window).resize(function() {
            myatu_bgm.AdjustImageSize();
        });

        if (bg_link.length) {
            // Pre-set background link
            myatu_bgm.SetBackgroundLink(bg_link.attr('href'));

            // Remove fall-back background link (prefer the Javascript method)
            bg_link.remove();
        }

        if ($.isFunction(info_tab.qtip)) {
            info_tab.qtip({
                content: {
                    text: function(api) {
                        var text = $('.myatu_bgm_info_tab_content').clone();

                        $('h3', text).remove(); // Remove title

                        // Remove margin if there's no text to display
                        if ($('.myatu_bgm_info_tab_desc', text).text() === '') {
                            $('img', text).css('margin', 0);
                        } else {
                            $('img', text).css({'width':'100px', 'height':'100px'});
                        }

                        return text;
                    },
                    title: {
                        text: function(api) {
                            return $('.myatu_bgm_info_tab_content:first h3').text();
                        },
                        button: true
                    }
                },
                style: {
                    classes: 'ui-tooltip-dark ui-tooltip-shadow'
                },
                events: {
                    hide: function(event, api) {
                        $('.myatu_bgm_info_tab_content:last').remove();
                    }
                },
                hide: false,
                position: {
                    adjust: {
                        x: -10
                    },
                    viewport: $(window)
                }
            });
        }
    });
})(jQuery);
