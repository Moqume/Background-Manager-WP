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
        // Flux transitions
        flux_transitions : ['bars','zip','blinds','swipe','blocks','blocks2','concentric','warp'],

        // Holder for pre-loading the next image
        image_holder : null,

        /** (Re)sets the timer for loading the next image */
        setTimer: function() {
            if (myatu_bgm.change_freq <= 0) {
                return;
            }

            if (myatu_bgm.timer) {
                clearTimeout(myatu_bgm.timer);
            }

            myatu_bgm.timer = setTimeout(myatu_bgm.switchBackground, myatu_bgm.change_freq * 1000);
        },

        /**
         * Event called when the background is clicked
         */
        onBackgroundClick: function(e) {
            if (e.target === this || $(e.target).hasClass('myatu_bgm_fs')) {
                if (myatu_bgm.bg_click_new_window === true) {
                    // Open the link in a new window
                    window.open(e.data.url);
                } else {
                    // Open the link in the same window
                    window.location.assign(e.data.url);
                }
                return false;
            }
        },

        /**
         * Event called when the mouse is over the background
         *
         * This sets the mouse cursor to "pointer" if it's above a clickable area of the background
         */
        onBackgroundHover: function(e) {
            $(this).css('cursor', (e.target === this || $(e.target).hasClass('myatu_bgm_fs')) ? 'pointer' : 'auto');
        },

        /**
         * Sets a clickable link for the background
         */
        setBackgroundLink: function(url) {
            var b = $('body');

            // Unbind our prior hover and click functions, and reset the mouse pointer
            b.unbind('click', myatu_bgm.onBackgroundClick).unbind('mouseover', myatu_bgm.onBackgroundHover).css('cursor', 'auto');

            // Re-bind if we have a non-empty URL
            if (url !== '' && url !== '#') {
                b.bind('click', {'url': url}, myatu_bgm.onBackgroundClick).bind('mouseover', myatu_bgm.onBackgroundHover);
            }
        },

        /**
         * Replaces a query argument value in the URL
         *
         * @param string original_url The URL in which to replace the query argument
         * @param string query_arg The name of the query argument
         * @param string new_val The new value for the query argument
         */
        urlReplaceQueryArgVal: function(original_url, query_arg, new_val) {
            var pattern = new RegExp('(?![?&])' + query_arg + '=(.*?(?=\\?|\\&(?!amp;)|#|$))', 'ig');

            return original_url.replace(pattern, query_arg + '=' + encodeURIComponent(new_val));
        },

        /**
         * Event called when an animation (transition) has been completed
         */
        onAnimationCompleted: function() {
            $('#myatu_bgm_prev').remove();  // Remove old background
            myatu_bgm.setTimer();           // Reset timer

            $(document).trigger('myatu_bgm_finish_transition');
        },

        /**
         * Performs a jQuery based slide animation for the transition
         *
         * @param string scroll_in_from Side from which the image is slid in
         * @param int duration The duration of the slide (in ms)
         * @param bool cover A boolean indicating if the background image should be covered or pushed aside (default)
         */
        AnimateSlide: function(scroll_in_from, duration, cover) {
            var new_img      = $('#myatu_bgm_top'),
                old_img      = $('#myatu_bgm_prev'),
                new_offset   = new_img.offset(),
                old_offset   = old_img.offset(),
                css          = {},
                start_position, dir, pos;

            // Determine starting position for new image, and ending position for old image
            switch (scroll_in_from) {
                case 'top'    : dir = 'top';  css[dir] = new_offset.top  + 'px'; pos = new_img.height() - old_offset.top; start_position = '-' + pos + 'px'; break;
                case 'bottom' : dir = 'top';  css[dir] = new_offset.top  + 'px'; pos = old_img.height() + old_offset.top; start_position =       pos + 'px'; break;
                case 'left'   : dir = 'left'; css[dir] = new_offset.left + 'px'; pos = new_img.width() - old_offset.left; start_position = '-' + pos + 'px'; break;
                case 'right'  : dir = 'left'; css[dir] = new_offset.left + 'px'; pos = old_img.width() + old_offset.left; start_position =       pos + 'px'; break;
            }

            new_img.css(dir, start_position); // Move the new image to one of the edges of the old image
            new_img.show();

            // Slide
            if (typeof cover === 'undefined' || cover === false) {
                new_img.animate(css, {
                    'duration': duration,
                    'complete': myatu_bgm.onAnimationCompleted,
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
                new_img.animate(css, {'duration': duration, 'complete': myatu_bgm.onAnimationCompleted});
            }
        },

        /**
         * Adjust the image size.
         *
         * Based on the work of Scott Robbin (srobbin.com)
         *
         * @param object img The image to adjust
         * @return bool Returns true if the image was adjusted, false otherwise
         */
        adjustImageSize : function(img) {
            var centered    = (myatu_bgm.fs_center === 'true'),
                css         = {'left' : 0,'top' : 0},
                win_height  = $(window).height(),
                win_width   = $(window).width(),
                bg_width    = win_width,
                bg_height, ratio, bg_offset;

            if (myatu_bgm.is_fullsize !== 'true') {
                return false; // This can only be done on full-size images
            }

            ratio     = $(img).width() / $(img).height();
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
                if (centered) {
                    $.extend(css, {'left': '-' + bg_offset + 'px'});
                }
            }

            $(img).width(bg_width).height(bg_height).css(css);

            return true;
        },

        /**
         * Loads an image into image_holder
         *
         * @param string src Image source URI
         * @param mixed callback Callback to performm once the image has been loaded by the browser
         */
        loadImage : function(src, callback) {
            var is_adjusted;

            // Create an image element and attach it to the image group
            if (myatu_bgm.image_holder === null) {
                myatu_bgm.image_holder = $('<img />').css({
                    'position'   : 'absolute',
                    'display'    : 'none'
                }).appendTo('#myatu_bgm_img_group');
            }

            // Reset the width and height
            myatu_bgm.image_holder.css({'width':'','height':''});

            // pre-load the image
            myatu_bgm.image_holder.attr('src', src).imgLoaded(function() {
                myatu_bgm.adjustImageSize(this);

                // Perform callback
                if (typeof callback === "function") {
                    callback();
                }
            });
        },

        /**
         * Event called when the window is resized
         *
         * Re-sizes the top image accordingly
         */
        onWindowResize : function(e) {
            var img = myatu_bgm.image_holder;

            if (img === null) {
                return; // Nothing to do.
            }

            myatu_bgm.adjustImageSize(img);

            // If it's a <div> element, we set the background-size
            $('div#myatu_bgm_top').css('background-size', img.width() + 'px ' + img.height() + 'px');

            $('#myatu_bgm_top').css({
                'left'   : img.css('left'),
                'top'    : img.css('top'),
                'width'  : img.width() + 'px',
                'height' : img.height() + 'px'
            });
        },

        /**
         * Adds a new top image
         *
         * This will be either a <div> or <img> element, depending on browser support
         *
         * @param string style The style of the previous image (to preserve any custom styling, such as opacity)
         * @param string alt The "alt" attribute, used with <img> elements
         * @param string src The source URI of the image
         * @param mixed callback The callback to perform when the image has been loaded and added
         */
        addTopImage: function(style, alt, src, callback) {
            var new_image, css = {'display' : 'none'};

            myatu_bgm.loadImage(src, function() {
                if (myatu_bgm.Modernizr.backgroundsize) {
                    // "background-size" is supported, so use a <div> element
                    new_image = $('<div></div>');

                    // Set the background image
                    $.extend(css, {
                        'background-image'  : 'url(' + src + ')',
                        'background-size'   : myatu_bgm.image_holder.width() + 'px ' + myatu_bgm.image_holder.height() + 'px',
                        'background-repeat' : 'no-repeat'
                    });
                } else {
                    // "background-size" is not supported, use a cloned <img> element instead (MSIE < 9)
                    new_image = myatu_bgm.image_holder.clone();
                    new_image.attr('alt', alt);
                }

                // CSS for the new image
                $.extend(css, {
                    'left'   : myatu_bgm.image_holder.css('left'),
                    'top'    : myatu_bgm.image_holder.css('top'),
                    'width'  : myatu_bgm.image_holder.width() + 'px',
                    'height' : myatu_bgm.image_holder.height() + 'px'
                });

                // Set the id, class, style and css, then append to the image group
                new_image.attr({
                    'id'    : 'myatu_bgm_top',
                    'class' : 'myatu_bgm_fs',
                    'style' : style
                }).css(css).appendTo('#myatu_bgm_img_group');

                // Perform callback
                if (typeof callback === "function") {
                    callback.call(new_image);
                }
            });
        },

        /**
         * Event called by the timer, switched the background
         */
        switchBackground: function() {
            var is_fullsize  = (myatu_bgm.is_fullsize === 'true'),
                is_preview    = (myatu_bgm.is_preview  === 'true'),
                info_tab      = $('#myatu_bgm_info_tab'),
                prev_style    = '',
                prev_img, transition_speed, active_transition, image_selection, flux_instance;

            // Grab the previous image
            prev_img = $((is_fullsize) ? '#myatu_bgm_top' : 'body').css('background-image').replace(/url\(|\)|"|'/g, "");

            if (prev_img === 'none' && is_fullsize) {
                // Using the source URI of the <img> element instead
                prev_img = $('#myatu_bgm_top').attr('src');
            }

            // Determine if the top image is actually "visible". If not, we simply reset the timer
            if ((is_fullsize && !$('#myatu_bgm_top').is(':visible'))) {
                myatu_bgm.setTimer();
                return;
            }

            // Override the method for selecting an image in the preview (Theme Customizer)
            if (is_preview) {
                image_selection = myatu_bgm.image_selection;
            }

            // Async call
            myatu_bgm.GetAjaxData('select_image', { 'prev_img' : prev_img, 'selector' : image_selection, 'active_gallery': myatu_bgm.active_gallery }, function(new_image) {
                if (!new_image || prev_img === new_image.url) {
                    return; // Something didn't go right, do not retry (reset timer)
                }

                // Replace/remove background link
                myatu_bgm.setBackgroundLink(new_image.bg_link);

                if (is_fullsize) {
                    // Set transition speed to what's returned by AJAX, or what's specified in preview (Theme Customizer)
                    if (is_preview) {
                        // Preview variables
                        transition_speed  = Number(myatu_bgm.transition_speed);
                        active_transition = myatu_bgm.active_transition;

                        // We do the random picking here when in a preview
                        if (active_transition === 'random') {
                            active_transition = myatu_bgm.transitions[Math.floor(Math.random()*myatu_bgm.transitions.length)];
                        }
                    } else {
                        // AJAX variables
                        transition_speed  = new_image.transition_speed;
                        active_transition = new_image.transition;
                    }

                    // Grab the current style
                    prev_style = $('#myatu_bgm_top').attr('style');

                    // Switch image ID ('top' becomes 'prev')
                    $('#myatu_bgm_top').attr('id', 'myatu_bgm_prev');

                    // Create a new top image and perform callback when done loading
                    myatu_bgm.addTopImage(prev_style, new_image.alt, new_image.url, function() {
                        var c = false; // Cover or slide?

                        // Force the transition to 'none' if 'myatu_bgm_prev' is missing for some reason (failsafe)
                        if (!$('#myatu_bgm_prev').length) {
                            active_transition = 'none';
                        }

                        // Custom event - function(event, active_transition, transition_speed, new_image_object)
                        $(document).trigger('myatu_bgm_start_transition', [active_transition, transition_speed, new_image]);

                        // Check if Flux can be used
                        if (myatu_bgm.Modernizr.backgroundsize) {
                            if (typeof flux_instance === "undefined") {
                                // Flux instance

                                flux_instance = new myatu_bgm_flux.slider('#myatu_bgm_img_group', {
                                    pagination : false,
                                    autoplay   : false
                                });

                                $('#myatu_bgm_img_group').bind('fluxTransitionEnd', myatu_bgm.onAnimationCompleted);                            }
                        } else if (active_transition !== 'none' && $.inArray(active_transition, myatu_bgm.flux_transitions) > -1) {
                            // Flux cannot be used, use the default transition
                            active_transition = '';
                            console.log('reset active transition');
                        }

                        // Reduce transition_speed for Flux transitions
                        if ($.inArray(active_transition, myatu_bgm.flux_transitions) > -1) {
                            transition_speed = transition_speed / 50;
                        }

                        switch (active_transition) {
                            // No transition
                            case 'none' :
                                $(this).show();
                                myatu_bgm.onAnimationCompleted();
                                break;

                            case 'coverdown' : c = true; // Cover instead of slide. Remember nobreak!
                            case 'slidedown' : myatu_bgm.AnimateSlide('top', transition_speed, c); break;

                            case 'coverup'   : c = true; // nobreak!
                            case 'slideup'   : myatu_bgm.AnimateSlide('bottom', transition_speed, c); break;

                            case 'coverright': c = true; // nobreak!
                            case 'slideright': myatu_bgm.AnimateSlide('left', transition_speed, c); break;

                            case 'coverleft' : c = true; // nobreak!
                            case 'slideleft' : myatu_bgm.AnimateSlide('right', transition_speed, c); break;

                            case 'bars'   : // nobreak!
                            case 'blinds' :
                            case 'zip'    :
                            case 'blocks' :
                                flux_instance.next(active_transition, {'delayBetweenBars' : transition_speed});
                                break;

                            case 'blocks2' :
                                flux_instance.next('blocks2', {'delayBetweenDiagnols' : transition_speed});
                                break;

                            case 'swipe'      :
                            case 'concentric' :
                            case 'warp'       :
                                // Fixed transition speeds
                                flux_instance.next(active_transition);
                                break;

                            case 'zoom' :
                                // Fade-out the previous image at the same time the new image is being faded in.
                                $('#myatu_bgm_prev').animate({opacity:0}, {'duration': transition_speed, 'queue': false});

                                // Fade in the image whilst zooming it sightly
                                $(this).animate({
                                    'width'   : $(this).width() * 1.05,
                                    'height'  : $(this).height() * 1.05,
                                    'opacity' : 'show'
                                }, {
                                    'duration': transition_speed,
                                    'complete': myatu_bgm.onAnimationCompleted
                                });
                                break;

                            // Crossfade is standard transition
                            default:
                                // Fade-out the previous image at the same time the new image is being faded in.
                                $('#myatu_bgm_prev').animate({opacity:0}, {'duration': transition_speed, 'queue': false});

                                $(this).fadeIn(transition_speed, myatu_bgm.onAnimationCompleted);
                                break;
                        }
                    });
                } else {
                    // Simply replace the body background
                    $('body').css('background-image', 'url("' + new_image.url + '")');
                    myatu_bgm.setTimer();
                }

                // Set the info tab details
                if (info_tab.length) {
                    // Close the balloon tip, if it is showing.
                    if ($.isFunction(info_tab.qtip)) {
                        info_tab.qtip('api').hide();
                    }

                    // Set info tab content and link
                    $('.myatu_bgm_info_tab a').attr('href', new_image.link);
                    $('.myatu_bgm_info_tab_content.clonable img').attr('src', new_image.thumb);
                    $('.myatu_bgm_info_tab_content.clonable h3').text(new_image.caption);
                    $('.myatu_bgm_info_tab_content.clonable .myatu_bgm_info_tab_desc').html(new_image.desc);
                }

                // "Pin it" button
                if ($('#myatu_bgm_pin_it_btn').length) {
                    // Replace "Pin it" button's iFrame source
                    var pin_it_src = $('#myatu_bgm_pin_it_btn iframe').attr('src'), clean_desc = new_image.desc.replace(/(<([^>]+)>)/ig,'');

                    pin_it_src = myatu_bgm.urlReplaceQueryArgVal(pin_it_src, 'media', new_image.url);       // Replace image URL
                    pin_it_src = myatu_bgm.urlReplaceQueryArgVal(pin_it_src, 'description', clean_desc);    // Replace description

                    $('#myatu_bgm_pin_it_btn iframe').attr('src', pin_it_src);
                }
            });
        },

        /**
         * Initializes the info tab
         */
        initInfoTab : function() {
            var info_tab = $('#myatu_bgm_info_tab');

            if (!$.isFunction(info_tab.qtip) || !info_tab.length) {
                return; // Nothing to do!
            }
            info_tab.qtip({
                content: {
                    text: function(api) {
                        var text = $('.myatu_bgm_info_tab_content.clonable').clone();

                        // Change class from "clonable" to "shown"
                        text.removeClass("clonable").addClass("shown");

                        $('h3', text).remove(); // Remove title, as this is used in the "title" var

                        if ($('.myatu_bgm_info_tab_desc', text).text() === '') {
                            // Remove margin if there's no text to display
                            $('img', text).css('margin', '5px 0');
                        } else {
                            // Reduce the size of the image a bit
                            $('img', text).css({'width':'100px', 'height':'100px'});

                            // And widen the tip window a bit
                            $(this).qtip('option', 'width', 500);
                        }

                        return text;
                    },
                    title: {
                        text: function(api) {
                            return $('.myatu_bgm_info_tab_content.clonable h3').text();
                        },
                        button: true
                    }
                },
                style: {
                    classes: 'ui-tooltip-bootstrap ui-tooltip-shadow ui-tooltip-rounded'
                },
                events: {
                    hide: function(event, api) {
                        // Delete the clone
                        $('.myatu_bgm_info_tab_content.shown').remove();
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

    $(document).ready(function($){
        var bg_link = $('#myatu_bgm_bg_link');

        // Set the window resize event
        $(window).resize(myatu_bgm.onWindowResize);

        // Set the background link
        if (bg_link.length) {
            myatu_bgm.setBackgroundLink(bg_link.attr('href'));

            bg_link.remove(); // Remove fall-back background link (prefer the Javascript method)
        }

        // Initialize the info tab
        myatu_bgm.initInfoTab();

        // Initialize the timer
        myatu_bgm.setTimer();
    });
}(jQuery));
