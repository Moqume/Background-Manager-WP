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

/** Adds naturalWidth and naturalHeight support to images in jQuery. Props to Jack Moore <jack@colorpowered.com> */
(function($){
    var props = ['Width', 'Height']
    ,prop;

    while (prop = props.pop()) {
        (function (natural, prop) {
            $.fn[natural] = (natural in new Image()) ? function () {
                return this[0][natural];
            } : function () {
                var node = this[0]
                    , img
                    , value;

                if (node.tagName.toLowerCase() === 'img') {
                    img     = new Image();
                    img.src = node.src,
                    value   = img[prop];
                }

                return value;
            };
        }('natural' + prop, prop.toLowerCase()));
    }
}(jQuery));

/** Background Manager */
(function($){
    $.extend(myatu_bgm, {
        // Previous background data
        previous_background : {},

        // Flux transitions
        flux_transitions : ['bars','zip','blinds','swipe','blocks','blocks2','concentric','warp'],

        // Holder for pre-loaded image @see loadImage()
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
         * Reports a Google Analytics event
         *
         * @param string what The event to report
         * @param string label The event label (current background link by default)
         */
        doGAEvent : function(what, label) {
            var category  = myatu_bgm.bg_track_clicks_category;

            // We must at least have an event action
            if (typeof what === "undefined") {
                return;
            }

            // Provide a label if it isn't specified
            if (typeof label === "undefined") {
                label = myatu_bgm.current_background.bg_link;
            }

            // Provide a category if it's empty
            if (!category) {
                category = 'Background Manager';
            }

            if (label && myatu_bgm.bg_track_clicks === 'true' && typeof _gaq !== "undefined" && $.isFunction(_gaq.push)) {
                _gaq.push(['_trackEvent', category, what, label]);
            }
        },

        /**
         * Event called when the background is clicked
         */
        onBackgroundClick: function(e) {
            var link = myatu_bgm.current_background.bg_link, popup;

            if (e.target === this || $(e.target).hasClass('myatu_bgm_fs')) {
                // Fire custom event function(event, url)
                $(document).trigger('myatu_bgm_background_click', [link]);

                // Event tracking for Google Analytics
                myatu_bgm.doGAEvent('Click');

                if (myatu_bgm.bg_click_new_window === 'true') {
                    // Open the link in a new window
                    window.open(link);
                } else {
                    // Open the link in the same window
                    setTimeout(function() {
                        window.location.assign(link);
                    }, 500);
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
        setBackgroundLink: function() {
            var b = $('body');

            // Unbind our prior hover and click functions, and reset the mouse pointer
            b.unbind('click', myatu_bgm.onBackgroundClick).unbind('mouseover', myatu_bgm.onBackgroundHover).css('cursor', 'auto');

            // Re-bind if we have a non-empty URL
            if (myatu_bgm.current_background.bg_link !== '' && myatu_bgm.current_background.bg_link !== '#') {
                b.bind('click', myatu_bgm.onBackgroundClick).bind('mouseover', myatu_bgm.onBackgroundHover);
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
        animateSlide: function(scroll_in_from, duration, cover) {
            var new_img      = $('#myatu_bgm_top'),
                old_img      = $('#myatu_bgm_prev'),
                old_offset   = old_img.offset(),
                css          = { 'top'  : new_img.css('top'), 'left' : new_img.css('left') },
                start_position, dir, pos;

            // Determine starting position for new image, and ending position for old image
            switch (scroll_in_from) {
                case 'top'    : dir = 'top';  start_position = '-' + (new_img.height() - old_offset.top) + 'px'; break;
                case 'bottom' : dir = 'top';  start_position =       (old_img.height() + old_offset.top) + 'px'; break;
                case 'left'   : dir = 'left'; start_position = '-' + (new_img.width() - old_offset.left) + 'px'; break;
                case 'right'  : dir = 'left'; start_position =       (old_img.width() + old_offset.left) + 'px'; break;
            }

            new_img.css(dir, start_position); // Move the new image to one of the edges of the old image
            new_img.show();

            // Slide
            if (typeof cover === 'undefined' || cover === false) {
                new_img.animate(css, {
                    'duration': duration,
                    'complete': myatu_bgm.onAnimationCompleted,
                    'step': function(now,fx) {
                        if (fx.prop === dir) {
                            // Keep old image "sticking" to edge of new image
                            switch (scroll_in_from) {
                                case 'top'    : old_img.css(dir, (new_img.height() + now) + 'px'); break;
                                case 'bottom' : old_img.css(dir, (now - old_img.height()) + 'px'); break;
                                case 'left'   : old_img.css(dir, (new_img.width() + now) + 'px'); break;
                                case 'right'  : old_img.css(dir, (now - old_img.width()) + 'px'); break;
                            }
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
            var centered             = (myatu_bgm.fs_center === 'true')
                , css                = {'left' : 0,'top' : 0}
                , img_natural_width  = $(img).naturalWidth()
                , img_natural_height = $(img).naturalHeight()
                , ratio              = img_natural_width / img_natural_height
                , win_height         = window.innerHeight || $(window).height()
                , win_width          = window.innerWidth || $(window).width()
                , bg_width           = win_width
                , bg_height          = bg_width / ratio
                , bg_offset;

            if (myatu_bgm.is_fullsize !== 'true') {
                return false; // This can only be done on full-size images
            }

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
            if (myatu_bgm.image_holder === null) {
                // Create an image element (held as fragment)
                myatu_bgm.image_holder = $('<img />');
            }

            // Pre-load the image
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
         * Tests if the browser is a mobile browser
         *
         * Courtesy Chad Smith (http://detectmobilebrowsers.com/)
         *
         * @return bool
         */
        isMobile: function() {
            var ua = (navigator.userAgent || navigator.vendor || window.opera);

            return (/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i.test(ua) || /1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i.test(ua.substr(0, 4)));
        },

        isDisabled: function() {
            var display_on_mobile = (myatu_bgm.display_on_mobile === 'true');

            return (myatu_bgm.isMobile() && !display_on_mobile);
        },

        /**
         * Adds a new top image
         *
         * This will be either a <div> or <img> element, depending on browser support
         *
         * @param string style The style of the previous image (to preserve any custom styling, such as opacity)
         * @param mixed callback The callback to perform when the image has been loaded and added
         */
        addTopImage: function(style, callback) {
            var new_image
                , src = myatu_bgm.current_background.url
                , alt = myatu_bgm.current_background.alt
                , css = {'display' : 'none'};

            // Don't add the top image if it has been disabled (ie., mobile browser and set not to show on mobile browsers)
            if (myatu_bgm.isDisabled())
                return;

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

                // Event tracking for Google Analytics
                myatu_bgm.doGAEvent('Display');

                // Perform callback
                if (typeof callback === "function") {
                    callback.call(new_image);
                }
            });
        },

        /**
         * Event called by the timer, switched the background
         */
        switchBackground: function(override_selection) {
            var is_fullsize  = (myatu_bgm.is_fullsize === 'true')
                , is_preview    = (myatu_bgm.is_preview  === 'true')
                , info_tab      = $('#myatu_bgm_info_tab')
                , cover         = false
                , transition_speed, active_transition, image_selection, flux_instance;

            // Ensure the timer is cleared (for manual calls)
            if (myatu_bgm.timer) {
                clearTimeout(myatu_bgm.timer);
            }

            // Determine if the top image is actually "visible". If not, we simply reset the timer
            if ((is_fullsize && !$('#myatu_bgm_top').is(':visible'))) {
                myatu_bgm.setTimer();
                return;
            }

            // Fire custom event
            $(document).trigger('myatu_bgm_switch_background');

            // Override the method for selecting an image in the preview (Theme Customizer)
            if (is_preview) {
                image_selection = myatu_bgm.image_selection;
            }

            // Allow 'overide_selection' to change the image selection method
            override_selection = override_selection || false;
            if (override_selection) {
                image_selection = override_selection;
            }

            // Async call
            myatu_bgm.GetAjaxData('select_image', {'prev_img' : myatu_bgm.current_background.url, 'selector' : image_selection, 'active_gallery': myatu_bgm.active_gallery}, function(new_image) {
                if (!new_image || new_image.url === myatu_bgm.current_background.url) {
                    return; // Yikes!
                }

                // Set current_background and previous_background vars
                myatu_bgm.previous_background = myatu_bgm.current_background;
                myatu_bgm.current_background  = $.extend(myatu_bgm.current_background, new_image);

                if (is_fullsize) {
                    if (is_preview) {
                        // We're in a Theme Customizer preview, and the transition speed/method specified there
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

                    // Switch image IDs so 'top' becomes 'prev'
                    $('#myatu_bgm_top').attr('id', 'myatu_bgm_prev');

                    // Create a new top image and perform callback when done loading
                    myatu_bgm.addTopImage($('#myatu_bgm_prev').attr('style'), function() {
                        // Check if Flux can be used
                        if (myatu_bgm.Modernizr.backgroundsize) {
                            if (typeof flux_instance === "undefined") {
                                // Flux instance
                                flux_instance = new myatu_bgm_flux.slider('#myatu_bgm_img_group', {
                                    pagination : false,
                                    autoplay   : false
                                });

                                $('#myatu_bgm_img_group').bind('fluxTransitionEnd', myatu_bgm.onAnimationCompleted);
                            }
                        } else if (active_transition !== 'none' && $.inArray(active_transition, myatu_bgm.flux_transitions) > -1) {
                            // Flux cannot be used due to browser, use the default transition instead
                            active_transition = '';
                        }

                        // Reduce transition_speed for Flux transitions
                        if ($.inArray(active_transition, myatu_bgm.flux_transitions) > -1) {
                            transition_speed = transition_speed / 50;
                        }

                        // Update current_background with updated transition data
                        $.extend(myatu_bgm.current_background, {
                           'transition'       : active_transition,
                           'transition_speed' : transition_speed
                        });

                        // Fire custom event - function(event, active_transition, transition_speed, current_background)
                        $(document).trigger('myatu_bgm_start_transition', [active_transition, transition_speed, myatu_bgm.current_background]);

                        switch (active_transition) {
                            // No transition
                            case 'none' :
                                $(this).show();
                                myatu_bgm.onAnimationCompleted();
                                break;

                            case 'coverdown' : cover = true; // Cover instead of slide. Remember nobreak!
                            case 'slidedown' : myatu_bgm.animateSlide('top', transition_speed, cover); break;

                            case 'coverup'   : cover = true; // nobreak!
                            case 'slideup'   : myatu_bgm.animateSlide('bottom', transition_speed, cover); break;

                            case 'coverright': cover = true; // nobreak!
                            case 'slideright': myatu_bgm.animateSlide('left', transition_speed, cover); break;

                            case 'coverleft' : cover = true; // nobreak!
                            case 'slideleft' : myatu_bgm.animateSlide('right', transition_speed, cover); break;

                            case 'bars'   : // nobreak!
                            case 'blinds' :
                            case 'zip'    :
                            case 'blocks' :
                                flux_instance.next(active_transition, {'delayBetweenBars' : transition_speed});
                                break;

                            case 'blocks2' :
                                flux_instance.next('blocks2', {'delayBetweenDiagnols' : transition_speed});
                                break;

                            case 'concentric' :
                            case 'warp'       :
                                flux_instance.next(active_transition, {'delay' : transition_speed * 2 });
                                break;

                            case 'swipe' :
                                // Fixed speed
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
                    // Simply replace the body background - note the use of attr, due to priority flag
                    $('body').attr('style', 'background-image: url("' + new_image.url + '") !important');
                    myatu_bgm.setTimer();
                }

                // Replace/remove background link
                myatu_bgm.setBackgroundLink();

                 // Close the balloon tip, if it is showing.
                if (info_tab.length) {
                    if ($.isFunction(info_tab.qtip)) {
                        info_tab.qtip('api').hide();
                    }
                }

                // "Pin it" button
                if ($('#myatu_bgm_pin_it_btn').length) {
                    var pin_it       = $('#myatu_bgm_pin_it_btn a')
                        , pin_it_src = pin_it.attr('href')
                        , clean_desc = new_image.desc.replace(/(<([^>]+)>)/ig,'');

                    // Set the new button source
                    pin_it_src = myatu_bgm.urlReplaceQueryArgVal(pin_it_src, 'media', new_image.url);       // Replace image URL
                    pin_it_src = myatu_bgm.urlReplaceQueryArgVal(pin_it_src, 'description', clean_desc);    // Replace description

                    pin_it.attr('href', pin_it_src);
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

            // Attach qTip to the info_tab
            info_tab.qtip({
                content: {
                    text: function(api) {
                        var thumb    = $('<img />').attr({'src' : myatu_bgm.current_background.thumb, 'alt' : myatu_bgm.current_background.alt})
                            , result = $('<div></div>').addClass('myatu_bgm_info_tab_content')
                            , desc   = myatu_bgm.current_background.desc;

                        // Add the thumbnail, if needed
                        if (myatu_bgm.info_tab_thumb === 'true') {
                            result.append(thumb);
                        }

                        if (desc) {
                            // Reduce the size of the thumbnail a bit
                            thumb.css({'width':'100px', 'height':'100px'});
                            result.append(desc);
                        } else {
                            // There's no description, so just remove the margin on the thumbnail
                            thumb.css('margin', '5px 0');
                        }

                        return result;
                    },
                    title: {
                        text: function(api) {
                            return myatu_bgm.current_background.caption;
                        },
                        button: true
                    }
                },
                style: {
                    classes: 'ui-tooltip-bootstrap ui-tooltip-shadow ui-tooltip-rounded'
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
        var bg_link = $('#myatu_bgm_bg_link')
            , display_on_mobile ;

        // If it has been disabled, hide some things and then we're done.
        if (myatu_bgm.isDisabled()) {
            $('body').removeClass('myatu_bgm_body');    // Removes body overrides (image, color, etc).
            $('.pin-it-button').hide();                 // Removes 'Pin It' non-js link
            $('#myatu_bgm_info').hide();                // Removes '[+]' info button details
            $('#myatu_bgm_overlay').hide();             // Removes overlay
            return;
        }

        // Set the background link
        if (bg_link.length) {
            myatu_bgm.setBackgroundLink();

            bg_link.remove(); // Remove fall-back background link (prefer the Javascript method)
        }

        // Set the window resize event
        $(window).resize(myatu_bgm.onWindowResize);

        // Initialize the info tab
        myatu_bgm.initInfoTab();

        // Initialize the timer
        myatu_bgm.setTimer();


    });
}(jQuery));
