/**
 * Flux Slider modified for Background Manager
 * Copyright 2011, Joe Lambert <http://www.joelambert.co.uk/flux>
 * Copyright 2012, Mike Green <myatus@gmail.com>
 *
 * Free to use under the MIT license.
 * http://www.opensource.org/licenses/mit-license.php
 */

// Flux namespace for Background Manager
window.myatu_bgm_flux = {
    version: '1.4.4'
};

(function($){
    myatu_bgm_flux.slider = function(elem, opts) {
        var this_ = this, fx, newTrans = [];

        this.element = $(elem);

        // Make a list of all available transitions
        this.transitions = [];
        for (fx in myatu_bgm_flux.transitions) {
            if (myatu_bgm_flux.transitions.hasOwnProperty(fx)) {
                this.transitions.push(fx);
            }
        }

        this.options = $.extend({
            autoplay: false,
            transitions: this.transitions,
            delay: 4000,
            width: null,
            height: null,
            onTransitionEnd: null
        }, opts);

        // Set the height/width if given [EXPERIMENTAL!]
        this.height = this.options.height || null;
        this.width  = this.options.width || null;

        // Filter out non compatible transitions
        $(this.options.transitions).each(function(index, tran){
            var t = new myatu_bgm_flux.transitions[tran](this), compatible = true;

            if(t.options.requires3d && !myatu_bgm_flux.browser.supports3d) {
                compatible = false;
            }

            if(t.options.compatibilityCheck) {
                compatible = t.options.compatibilityCheck();
            }

            if(compatible) {
                newTrans.push(tran);
            }
        });

        this.options.transitions = newTrans;
        this.playing             = false;
        this.imageContainer      = $('#myatu_bgm_img_group');
        this.image1              = $('#myatu_bgm_prev');
        this.image2              = $('#myatu_bgm_top');

        // Catch when a transition has finished
        this.element.bind('fluxTransitionEnd', function(event, data) {
            // If the slider is currently playing then set the timeout for the next transition
            // if(this_.isPlaying())
            //     this_.start();

            // Are we using a callback instead of events for notifying about transition ends?
            if(this_.options.onTransitionEnd) {
                event.preventDefault();
                this_.options.onTransitionEnd(data);
            }
        });

        // Should we auto start the slider?
        if(this.options.autoplay) {
            this.start();
        }

        // Handle swipes
        this.element.bind('swipeLeft', function(event){
            this_.next(null, {direction: 'left'});
        }).bind('swipeRight', function(event){
            this_.prev(null, {direction: 'right'});
        });

        // Under FF7 autoplay breaks when the current tab loses focus
        setTimeout(function(){
            $(window).focus(function(){
                if(this_.isPlaying()) {
                    this_.next();
                }
            });
        }, 100);
    };

    myatu_bgm_flux.slider.prototype = {
        constructor: myatu_bgm_flux.slider,
        playing: false,
        start: function() {
            var this_ = this;
            this.playing = true;
            this.interval = setInterval(function() {
                this_.transition();
            }, this.options.delay);
        },
        stop: function() {
            this.playing = false;
            clearInterval(this.interval);
            this.interval = null;
        },
        isPlaying: function() {
            return this.playing;
        },
        next: function(trans, opts) {
            opts = opts || {};
            opts.direction = 'left';
            this.showImage(trans, opts);
        },
        prev: function(trans, opts) {
            opts = opts || {};
            opts.direction = 'right';
            this.showImage(trans, opts);
        },
        showImage: function(trans, opts) {
            this.transition(trans, opts);
        },
        transition: function(transition, opts) {
            var tran = null, index;

            // Swap z-index
            this.image1.css('z-index', -1998);
            this.image2.css('z-index', -1999);

            // Show new top image
            this.image2.show();

            // Allow a transition to be picked from ALL available transitions (not just the reduced set)
            if(typeof transition === "undefined" || !myatu_bgm_flux.transitions[transition])
            {
                // Pick a transition at random from the (possibly reduced set of) transitions
                index = Math.floor(Math.random()*(this.options.transitions.length));
                transition = this.options.transitions[index];
            }

            try {
                tran = new myatu_bgm_flux.transitions[transition](this, $.extend(this.options[transition] || {}, opts));
            }
            catch(e) {
                // If an invalid transition has been provided then use the fallback (default is to just switch the image)
                tran = new myatu_bgm_flux.transition(this, {fallback: true});
            }

            tran.run();
        }
    };
}(jQuery));

/**
 * Helper object to determine support for various CSS3 functions
 * @author Joe Lambert
 */

(function($) {
    myatu_bgm_flux.browser = {
        init: function() {
            // Have we already been initialised?
            if(typeof myatu_bgm_flux.browser.supportsTransitions !== "undefined") {
                return;
            }

            var // div = document.createElement('div'),
                prefixes = ['-webkit', '-moz', '-o', '-ms'],
                //domPrefixes = ['Webkit', 'Moz', 'O', 'Ms'],
                div3D, mq;

            // Does the current browser support CSS Transitions?
            if(typeof window.Modernizr !== "undefined" && Modernizr.csstransitions) {
                myatu_bgm_flux.browser.supportsTransitions = Modernizr.csstransitions;
            } else {
                myatu_bgm_flux.browser.supportsTransitions = this.supportsCSSProperty('Transition');
            }

            // Does the current browser support 3D CSS Transforms?
            if(typeof window.Modernizr !== "undefined" && Modernizr.csstransforms3d) {
                myatu_bgm_flux.browser.supports3d = Modernizr.csstransforms3d;
            } else {
                // Custom detection when Modernizr isn't available
                myatu_bgm_flux.browser.supports3d = this.supportsCSSProperty("Perspective");

                if (myatu_bgm_flux.browser.supports3d && $('body').get(0).style.hasOwnProperty('webkitPerspective')) {
                    // Double check with a media query (similar to how Modernizr does this)
                    div3D = $('<div id="csstransform3d"></div>');
                    mq = $('<style media="(transform-3d), ('+prefixes.join('-transform-3d),(')+'-transform-3d)">div#csstransform3d { position: absolute; left: 9px }</style>');

                    $('body').append(div3D);
                    $('head').append(mq);

                    myatu_bgm_flux.browser.supports3d = (div3D.get(0).offsetLeft === 9);

                    div3D.remove();
                    mq.remove();
                }
            }
        },
        supportsCSSProperty: function(prop) {
            var div = document.createElement('div'),
                //prefixes = ['-webkit', '-moz', '-o', '-ms'],
                domPrefixes = ['Webkit', 'Moz', 'O', 'Ms'],
                support = false, i;

            for(i=0; i<domPrefixes.length; i++) {
                if (domPrefixes[i]+prop in div.style) {
                    support = support || true;
                }
            }

            return support;
        },
        translate: function(x, y, z) {
            x = (typeof x !== "undefined") ? x : 0;
            y = (typeof y !== "undefined") ? y : 0;
            z = (typeof z !== "undefined") ? z : 0;

            return 'translate' + (myatu_bgm_flux.browser.supports3d ? '3d(' : '(') + x + 'px,' + y + (myatu_bgm_flux.browser.supports3d ? 'px,' + z + 'px)' : 'px)');
        },

        rotateX: function(deg) {
            return myatu_bgm_flux.browser.rotate('x', deg);
        },

        rotateY: function(deg) {
            return myatu_bgm_flux.browser.rotate('y', deg);
        },

        rotateZ: function(deg) {
            return myatu_bgm_flux.browser.rotate('z', deg);
        },

        rotate: function(axis, deg) {
            var result = '';

            if ($.inArray(axis, ['x','y','z']) < 0) {
                axis = 'z';
            }

            deg = (typeof deg !== "undefined") ? deg : 0;

            if(myatu_bgm_flux.browser.supports3d) {
                result = 'rotate3d(' + (axis === 'x' ? '1' : '0') + ', ' + (axis === 'y' ? '1' : '0') + ', ' + (axis === 'z' ? '1' : '0') + ', ' + deg + 'deg)';
            } else if(axis === 'z') {
                result = 'rotate(' + deg +'deg)';
            }

            return result;
        }
    };

    $(function(){
        // To continue to work with legacy code, ensure that myatu_bgm_flux.browser is initialised on document ready at the latest
        myatu_bgm_flux.browser.init();
    });
}(jQuery));

(function($){
    /**
     * Helper function for cross-browser CSS3 support, prepends all possible prefixes to all properties passed in
     * @param {Object} props Ker/value pairs of CSS3 properties
     */
    $.fn.css3 = function(props) {
        var css = {}, prefixes = ['webkit', 'moz', 'ms', 'o'], i, prop;

        for(prop in props)
        {
            if (props.hasOwnProperty(prop)) {
                // Add the vendor specific versions
                for (i=0; i<prefixes.length; i++) {
                    css['-'+prefixes[i]+'-'+prop] = props[prop];
                }

                // Add the actual version
                css[prop] = props[prop];
            }
        }

        this.css(css);
        return this;
    };

    /**
     * Helper function to bind to the correct transition end event
     * @param {function} callback The function to call when the event fires
     */
    $.fn.transitionEnd = function(callback) {
        var events = ['webkitTransitionEnd', 'transitionend', 'oTransitionEnd'],
            i, j,
            trans_end_event = function(event){
                // Automatically stop listening for the event
                for ( j=0; j<events.length; j++) {
                    $(this).unbind(events[j]);
                }

                // Perform the callback function
                if (callback) {
                    callback.call(this, event);
                }
            };

        for (i=0; i < events.length; i++) {
            this.bind(events[i], trans_end_event);
        }

        return this;
    };

    myatu_bgm_flux.transition = function(fluxslider, opts) {
        this.options = $.extend({
            requires3d: false,
            after: function() {
                // Default callback for after the transition has completed
            }
        }, opts);

        this.slider = fluxslider;

        // We need to ensure transitions degrade gracefully if the transition is unsupported or not loaded
        if((this.options.requires3d && !myatu_bgm_flux.browser.supports3d) || !myatu_bgm_flux.browser.supportsTransitions || this.options.fallback === true)
        {
            var this_ = this;

            this.options.after = undefined;

            this.options.setup = function() {
                this_.fallbackSetup();
            };

            this.options.execute = function() {
                this_.fallbackExecute();
            };
        }
    };

    myatu_bgm_flux.transition.prototype = {
        constructor: myatu_bgm_flux.transition,
        hasFinished: false, // This is a lock to ensure that the fluxTransitionEnd event is only fired once per tansition
        run: function() {
            var this_ = this;

            // do something
            if(typeof this.options.setup !== "undefined") {
                this.options.setup.call(this);
            }

            // Remove the background image from the top image
            this.slider.image1.css({
                'background-image': 'none'
            });

            this.slider.imageContainer.css('overflow', this.options.requires3d ? 'visible' : 'hidden');

            // For some of the 3D effects using Zepto we need to delay the transitions for some reason
            setTimeout(function(){
                if(typeof this_.options.execute !== "undefined") {
                    this_.options.execute.call(this_);
                }
            }, 5);
        },
        finished: function() {
            if(this.hasFinished) {
                return;
            }

            this.hasFinished = true;

            if(this.options.after) {
                this.options.after.call(this);
            }

            this.slider.imageContainer.css('overflow', 'hidden');

            // Trigger an event to signal the end of a transition
            this.slider.element.trigger('fluxTransitionEnd', {
                //currentImage: this.slider.getImage(this.slider.currentImageIndex)
            });
        },
        fallbackSetup: function() {
        },
        fallbackExecute: function() {
            this.finished();
        }
    };

    myatu_bgm_flux.transitions = {};

    // Flux grid transition
    myatu_bgm_flux.transition_grid = function(fluxslider, opts) {
        return new myatu_bgm_flux.transition(fluxslider, $.extend({
            columns: 18,
            rows: 18,
            forceSquare: false,
            setup: function() {
                var imgWidth         = this.slider.image1.width(),
                    imgHeight        = this.slider.image1.height(),
                    colWidth         = Math.floor(imgWidth / this.options.columns),
                    rowHeight        = (this.options.forceSquare) ? Math.floor(imgHeight / this.options.rows) : colWidth,
                    rows             = (this.options.forceSquare) ? Math.floor(imgHeight / rowHeight) : this.options.rows,
                    //delayBetweenBars = 150,
                    totalLeft        = 0,
                    totalTop         = 0,
                    fragment         = document.createDocumentFragment(),
                    colRemainder     = imgWidth - (this.options.columns * colWidth),
                    colAddPerLoop    = Math.ceil(colRemainder / this.options.columns),
                    rowRemainder     = imgHeight - (rows * rowHeight),
                    rowAddPerLoop    = Math.ceil(rowRemainder / rows),
                    //height           = this.slider.image1.height(),
                    thisColWidth, thisRowHeight, thisRowRemainder, i, j, add, tile;

                if(this.options.forceSquare) {
                    this.options.rows = rows;
                }

                for (i=0; i<this.options.columns; i++) {
                    thisColWidth = colWidth;
                    totalTop = 0;

                    if(colRemainder > 0)
                    {
                        add = colRemainder >= colAddPerLoop ? colAddPerLoop : colRemainder;
                        thisColWidth += add;
                        colRemainder -= add;
                    }

                    for(j=0; j<this.options.rows; j++) {
                        thisRowHeight = rowHeight;
                        thisRowRemainder = rowRemainder;

                        if(thisRowRemainder > 0) {
                            add = thisRowRemainder >= rowAddPerLoop ? rowAddPerLoop : thisRowRemainder;
                            thisRowHeight += add;
                            thisRowRemainder -= add;
                        }

                        tile = $('<div class="tile tile-'+i+'-'+j+'"></div>').css({
                            width: thisColWidth+'px',
                            height: thisRowHeight+'px',
                            position: 'absolute',
                            top: totalTop+'px',
                            left: totalLeft+'px'
                        });

                        this.options.renderTile.call(this, tile, i, j, thisColWidth, thisRowHeight, totalLeft, totalTop);

                        // Background image adjustment for tile
                        tile.css('background-size', this.slider.image1.css('background-size'));

                        fragment.appendChild(tile.get(0));

                        totalTop += thisRowHeight;
                    }

                    totalLeft += thisColWidth;
                }

                // Append the fragement to the surface
                this.slider.image1.get(0).appendChild(fragment);
            },
            execute: function() {
                var this_ = this,
                    height = this.slider.image1.height(),
                    bars = this.slider.image1.find('div.barcontainer');

                this.slider.image2.hide();

                // Get notified when the last transition has completed
                bars.last().transitionEnd(function(){
                    this_.slider.image2.show();

                    this_.finished();
                });

                bars.css3({
                    'transform': myatu_bgm_flux.browser.rotateX(-90) + ' ' + myatu_bgm_flux.browser.translate(0, height/2, height/2)
                });
            },
            renderTile: function(elem, colIndex, rowIndex, colWidth, rowHeight, leftOffset, topOffset) {
            }
        }, opts));
    };
}(jQuery));

(function($) {
    myatu_bgm_flux.transitions.bars = function(fluxslider, opts) {
        return new myatu_bgm_flux.transition_grid(fluxslider, $.extend({
            columns: 20,
            rows: 1,
            delayBetweenBars: 40,
            renderTile: function(elem, colIndex, rowIndex, colWidth, rowHeight, leftOffset, topOffset) {
                $(elem).css({
                    'background-image': this.slider.image1.css('background-image'),
                    'background-position': '-'+leftOffset+'px 0px'
                }).css3({
                    'transition-duration': '400ms',
                    'transition-timing-function': 'ease-in',
                    'transition-property': 'all',
                    'transition-delay': (colIndex*this.options.delayBetweenBars)+'ms'
                });
            },
            execute: function() {
                var this_ = this,
                    height = this.slider.image1.height(),
                    bars = this.slider.image1.find('div.tile');

                // Get notified when the last transition has completed
                $(bars[bars.length-1]).transitionEnd(function(){
                    this_.finished();
                });

                setTimeout(function(){
                    bars.css({
                        'opacity': '0.5'
                    }).css3({
                        'transform': myatu_bgm_flux.browser.translate(0, height)
                    });
                }, 150);

            }
        }, opts));
    };
}(jQuery));

(function($) {
    myatu_bgm_flux.transitions.bars3d = function(fluxslider, opts) {
        return new myatu_bgm_flux.transition_grid(fluxslider, $.extend({
            requires3d: true,
            columns: 7,
            rows: 1,
            delayBetweenBars: 150,
            perspective: 1000,
            renderTile: function(elem, colIndex, rowIndex, colWidth, rowHeight, leftOffset, topOffset) {
                var bar = $('<div class="bar-'+colIndex+'"></div>').css({
                    width: colWidth+'px',
                    height: '100%',
                    position: 'absolute',
                    top: '0px',
                    left: '0px',
                    'z-index': 200,

                    'background-image': this.slider.image1.css('background-image'),
                    'background-position': '-'+leftOffset+'px 0px',
                    'background-repeat': 'no-repeat'
                }).css3({
                    'backface-visibility': 'hidden'
                }),

                bar2 = $(bar.get(0).cloneNode(false)).css({
                    'background-image': this.slider.image2.css('background-image')
                }).css3({
                    'transform': myatu_bgm_flux.browser.rotateX(90) + ' ' + myatu_bgm_flux.browser.translate(0, -rowHeight/2, rowHeight/2)
                }),

                left = $('<div class="side bar-'+colIndex+'"></div>').css({
                    width: rowHeight+'px',
                    height: rowHeight+'px',
                    position: 'absolute',
                    top: '0px',
                    left: '0px',
                    background: '#222',
                    'z-index': 190
                }).css3({
                    'transform': myatu_bgm_flux.browser.rotateY(90) + ' ' + myatu_bgm_flux.browser.translate(rowHeight/2, 0, -rowHeight/2) + ' ' + myatu_bgm_flux.browser.rotateY(180),
                    'backface-visibility': 'hidden'
                }),

                right = $(left.get(0).cloneNode(false)).css3({
                    'transform': myatu_bgm_flux.browser.rotateY(90) + ' ' + myatu_bgm_flux.browser.translate(rowHeight/2, 0, colWidth-rowHeight/2)
                });

                $(elem).css({
                    width: colWidth+'px',
                    height: '100%',
                    position: 'absolute',
                    top: '0px',
                    left: leftOffset+'px',
                    'z-index': colIndex > this.options.columns/2 ? 1000-colIndex : 1000 // Fix for Chrome to ensure that the z-index layering is correct!
                }).css3({
                    'transition-duration': '800ms',
                    'transition-timing-function': 'linear',
                    'transition-property': 'all',
                    'transition-delay': (colIndex*this.options.delayBetweenBars)+'ms',
                    'transform-style': 'preserve-3d'
                }).append(bar).append(bar2).append(left).append(right);
            },
            execute: function() {
                this.slider.image1.css3({
                    'perspective': this.options.perspective,
                    'perspective-origin': '50% 50%'
                }).css({
                    '-moz-transform': 'perspective('+this.options.perspective+'px)',
                    '-moz-perspective': 'none',
                    '-moz-transform-style': 'preserve-3d'
                });

                var this_ = this,
                    height = this.slider.image1.height(),
                    bars = this.slider.image1.find('div.tile');

                this.slider.image2.hide();

                // Get notified when the last transition has completed
                bars.last().transitionEnd(function(event){
                    this_.slider.image1.css3({
                        'transform-style': 'flat'
                    });

                    this_.slider.image2.show();

                    this_.finished();
                });

                setTimeout(function(){
                    bars.css3({
                        'transform': myatu_bgm_flux.browser.rotateX(-90) + ' ' + myatu_bgm_flux.browser.translate(0, height/2, height/2)
                    });
                }, 50);
            }
        }, opts));
    };
}(jQuery));

(function($) {
    myatu_bgm_flux.transitions.blinds = function(fluxslider, opts) {
        return new myatu_bgm_flux.transitions.bars(fluxslider, $.extend({
            execute: function() {
                var this_ = this,
                    //height = this.slider.image1.height(),
                    bars = this.slider.image1.find('div.tile');

                // Get notified when the last transition has completed
                $(bars[bars.length-1]).transitionEnd(function(){
                    this_.finished();
                });

                setTimeout(function(){
                    bars.css({
                        'opacity': '0.5'
                    }).css3({
                        'transform': 'scalex(0.0001)'
                    });
                }, 50);
            }
        }, opts));
    };
}(jQuery));

(function($) {
    myatu_bgm_flux.transitions.blinds3d = function(fluxslider, opts) {
        return new myatu_bgm_flux.transitions.tiles3d(fluxslider, $.extend({
            forceSquare: false,
            rows: 1,
            columns: 6
        }, opts));
    };
}(jQuery));

(function($) {
    myatu_bgm_flux.transitions.zip = function(fluxslider, opts) {
        return new myatu_bgm_flux.transitions.bars(fluxslider, $.extend({
            execute: function() {
                var this_ = this,
                    height = this.slider.image1.height(),
                    bars = this.slider.image1.find('div.tile');

                // Get notified when the last transition has completed
                $(bars[bars.length-1]).transitionEnd(function(){
                    this_.finished();
                });

                setTimeout(function(){
                    bars.each(function(index, bar){
                        $(bar).css({
                            'opacity': '0.3'
                        }).css3({
                            'transform': myatu_bgm_flux.browser.translate(0, (index%2 ? '-'+(2*height) : height))
                        });
                    });
                }, 20);
            }
        }, opts));
    };
}(jQuery));

(function($) {
    myatu_bgm_flux.transitions.blocks = function(fluxslider, opts) {
        return new myatu_bgm_flux.transition_grid(fluxslider, $.extend({
            forceSquare: true,
            delayBetweenBars: 100,
            renderTile: function(elem, colIndex, rowIndex, colWidth, rowHeight, leftOffset, topOffset) {
                var delay = Math.floor(Math.random()*10*this.options.delayBetweenBars);

                $(elem).css({
                    'background-image': this.slider.image1.css('background-image'),
                    'background-position': '-'+leftOffset+'px -'+topOffset+'px'
                }).css3({
                    'transition-duration': '350ms',
                    'transition-timing-function': 'ease-in',
                    'transition-property': 'all',
                    'transition-delay': delay+'ms'
                });

                // Keep track of the last elem to fire
                if(typeof this.maxDelay === "undefined") {
                    this.maxDelay = 0;
                }

                if(delay > this.maxDelay) {
                    this.maxDelay = delay;
                    this.maxDelayTile = elem;
                }
            },
            execute: function() {
                var this_ = this, blocks = this.slider.image1.find('div.tile');

                // Get notified when the last transition has completed
                this.maxDelayTile.transitionEnd(function(){
                    this_.finished();
                });

                setTimeout(function(){
                    blocks.each(function(index, block){
                        $(block).css({
                            'opacity': '0'
                        }).css3({
                            'transform': 'scale(0.8)'
                        });
                    });
                }, 50);
            }
        }, opts));
    };
}(jQuery));

(function($) {
    myatu_bgm_flux.transitions.blocks2 = function(fluxslider, opts) {
        return new myatu_bgm_flux.transition_grid(fluxslider, $.extend({
            cols: 12,
            forceSquare: true,
            delayBetweenDiagnols: 150,
            renderTile: function(elem, colIndex, rowIndex, colWidth, rowHeight, leftOffset, topOffset) {
                //var delay = Math.floor(Math.random()*10*this.options.delayBetweenBars);

                $(elem).css({
                    'background-image': this.slider.image1.css('background-image'),
                    'background-position': '-'+leftOffset+'px -'+topOffset+'px'
                }).css3({
                    'transition-duration': '350ms',
                    'transition-timing-function': 'ease-in',
                    'transition-property': 'all',
                    'transition-delay': (colIndex+rowIndex)*this.options.delayBetweenDiagnols+'ms',
                    'backface-visibility': 'hidden' // trigger hardware acceleration
                });
            },
            execute: function() {
                var this_ = this, blocks = this.slider.image1.find('div.tile');

                // Get notified when the last transition has completed
                blocks.last().transitionEnd(function(){
                    this_.finished();
                });

                setTimeout(function(){
                    blocks.each(function(index, block){
                        $(block).css({
                            'opacity': '0'
                        }).css3({
                            'transform': 'scale(0.8)'
                        });
                    });
                }, 50);
            }
        }, opts));
    };
}(jQuery));

(function($) {
    myatu_bgm_flux.transitions.concentric = function(fluxslider, opts) {
        return new myatu_bgm_flux.transition(fluxslider, $.extend({
            blockSize: 60,
            delay: 150,
            alternate: false,
            setup: function() {
                var w = this.slider.image1.width(),
                    h = this.slider.image1.height(),
                    largestLength = Math.sqrt(w*w + h*h), // Largest length is the diagonal

                    // How many blocks do we need?
                    blockCount = Math.ceil(((largestLength-this.options.blockSize)/2) / this.options.blockSize) + 1, // 1 extra to account for the round border
                    fragment = document.createDocumentFragment(),
                    thisBlockSize, block, i;

                for(i=0; i<blockCount; i++)
                {
                    thisBlockSize = (2*i*this.options.blockSize)+this.options.blockSize;

                    block = $('<div></div>').attr('class', 'block block-'+i).css({
                        width: thisBlockSize+'px',
                        height: thisBlockSize+'px',
                        position: 'absolute',
                        top: ((h-thisBlockSize)/2)+'px',
                        left: ((w-thisBlockSize)/2)+'px',

                        'z-index': 100+(blockCount-i),

                        'background-image': this.slider.image1.css('background-image'),
                        'background-size': this.slider.image1.css('background-size'),
                        'background-position': 'center center'
                    }).css3({
                        'border-radius': thisBlockSize+'px',
                        'transition-duration': '800ms',
                        'transition-timing-function': 'linear',
                        'transition-property': 'all',
                        'transition-delay': ((blockCount-i)*this.options.delay)+'ms'
                    });

                    fragment.appendChild(block.get(0));
                }

                //this.slider.image1.append($(fragment));
                this.slider.image1.get(0).appendChild(fragment);
            },
            execute: function() {
                var this_ = this, blocks = this.slider.image1.find('div.block');

                // Get notified when the last transition has completed
                $(blocks[0]).transitionEnd(function(){
                    this_.finished();
                });

                setTimeout(function(){
                    blocks.each(function(index, block){
                        $(block).css({
                            'opacity': '0'
                        }).css3({
                            'transform': myatu_bgm_flux.browser.rotateZ((!this_.options.alternate || index%2 ? '' : '-')+'90')
                        });
                    });
                }, 50);
            }
        }, opts));
    };
}(jQuery));

(function($) {
    myatu_bgm_flux.transitions.warp = function(fluxslider, opts) {
        return new myatu_bgm_flux.transitions.concentric(fluxslider, $.extend({
            delay: 30,
            alternate: true
        }, opts));
    };
}(jQuery));

(function($) {
    myatu_bgm_flux.transitions.cube = function(fluxslider, opts) {
        return new myatu_bgm_flux.transition(fluxslider, $.extend({
            requires3d: true,
            barWidth: 100,
            direction: 'left',
            perspective: 1000,
            setup: function() {
                var width = this.slider.image1.width(),
                    height = this.slider.image1.height(),
                    css, currentFace, nextFace;

                // Setup the container to allow 3D perspective

                this.slider.image1.css3({
                    'perspective': this.options.perspective,
                    'perspective-origin': '50% 50%'
                });

                this.cubeContainer = $('<div class="cube"></div>').css({
                    width: width+'px',
                    height: height+'px',
                    position: 'relative'
                }).css3({
                    'transition-duration': '800ms',
                    'transition-timing-function': 'linear',
                    'transition-property': 'all',
                    'transform-style': 'preserve-3d'
                });

                css = {
                    height: '100%',
                    width: '100%',
                    position: 'absolute',
                    top: '0px',
                    left: '0px'
                };

                currentFace = $('<div class="face current"></div>').css($.extend(css, {
                    'background' : this.slider.image1.css('background-image'),
                    'background-size': this.slider.image1.css('background-size')
                })).css3({
                    'backface-visibility': 'hidden'
                });

                this.cubeContainer.append(currentFace);

                nextFace = $('<div class="face next"></div>').css($.extend(css, {
                    background: this.slider.image2.css('background-image')
                })).css3({
                    'transform' : this.options.transitionStrings.call(this, this.options.direction, 'nextFace'),
                    'backface-visibility': 'hidden'
                });

                this.cubeContainer.append(nextFace);

                this.slider.image1.append(this.cubeContainer);
            },
            execute: function() {
                var this_ = this;
                    //width = this.slider.image1.width(),
                    //height = this.slider.image1.height();

                this.slider.image2.hide();
                this.cubeContainer.transitionEnd(function(){
                    this_.slider.image2.show();

                    this_.finished();
                });

                setTimeout(function(){
                    this_.cubeContainer.css3({
                        'transform' : this_.options.transitionStrings.call(this_, this_.options.direction, 'container')
                    });
                }, 50);
            },
            transitionStrings: function(direction, elem) {
                var width = this.slider.image1.width(),
                    height = this.slider.image1.height(),
                    t = {
                    'up' : {
                        'nextFace': myatu_bgm_flux.browser.rotateX(-90) + ' ' + myatu_bgm_flux.browser.translate(0, height/2, height/2),
                        'container': myatu_bgm_flux.browser.rotateX(90) + ' ' + myatu_bgm_flux.browser.translate(0, -height/2, height/2)
                    },
                    'down' : {
                        'nextFace': myatu_bgm_flux.browser.rotateX(90) + ' ' + myatu_bgm_flux.browser.translate(0, -height/2, height/2),
                        'container': myatu_bgm_flux.browser.rotateX(-90) + ' ' + myatu_bgm_flux.browser.translate(0, height/2, height/2)
                    },
                    'left' : {
                        'nextFace': myatu_bgm_flux.browser.rotateY(90) + ' ' + myatu_bgm_flux.browser.translate(width/2, 0, width/2),
                        'container': myatu_bgm_flux.browser.rotateY(-90) + ' ' + myatu_bgm_flux.browser.translate(-width/2, 0, width/2)
                    },
                    'right' : {
                        'nextFace': myatu_bgm_flux.browser.rotateY(-90) + ' ' + myatu_bgm_flux.browser.translate(-width/2, 0, width/2),
                        'container': myatu_bgm_flux.browser.rotateY(90) + ' ' + myatu_bgm_flux.browser.translate(width/2, 0, width/2)
                    }
                };

                return (t[direction] && t[direction][elem]) ? t[direction][elem] : false;
            }
        }, opts));
    };
}(jQuery));

(function($) {
    myatu_bgm_flux.transitions.tiles3d = function(fluxslider, opts) {
        return new myatu_bgm_flux.transition_grid(fluxslider, $.extend({
            requires3d: true,
            forceSquare: true,
            columns: 5,
            perspective: 600,
            delayBetweenBarsX: 200,
            delayBetweenBarsY: 150,
            renderTile: function(elem, colIndex, rowIndex, colWidth, rowHeight, leftOffset, topOffset) {
                var tile = $('<div></div>').css({
                    width: colWidth+'px',
                    height: rowHeight+'px',
                    position: 'absolute',
                    top: '0px',
                    left: '0px',
                    //'z-index': 200, // Removed to make compatible with FF10 (Chrome bug seems to have been fixed)

                    'background-image': this.slider.image1.css('background-image'),
                    'background-position': '-'+leftOffset+'px -'+topOffset+'px',
                    'background-size': this.slider.image1.css('background-size'),
                    'background-repeat': 'no-repeat',
                    '-moz-transform': 'translateZ(1px)'
                }).css3({
                    'backface-visibility': 'hidden'
                }),
                tile2 = $(tile.get(0).cloneNode(false)).css({
                    'background-image': this.slider.image2.css('background-image')
                    //'z-index': 190 // Removed to make compatible with FF10 (Chrome bug seems to have been fixed)
                }).css3({
                    'transform': myatu_bgm_flux.browser.rotateY(180),
                    'backface-visibility': 'hidden'
                });

                $(elem).css({
                    'z-index': (colIndex > this.options.columns/2 ? 500-colIndex : 500) + (rowIndex > this.options.rows/2 ? 500-rowIndex : 500) // Fix for Chrome to ensure that the z-index layering is correct!
                }).css3({
                    'transition-duration': '800ms',
                    'transition-timing-function': 'ease-out',
                    'transition-property': 'all',
                    'transition-delay': (colIndex*this.options.delayBetweenBarsX+rowIndex*this.options.delayBetweenBarsY)+'ms',
                    'transform-style': 'preserve-3d'
                }).append(tile).append(tile2);
            },
            execute: function() {
                var this_ = this, tiles = this.slider.image1.find('div.tile');

                this.slider.image1.css3({
                    'perspective': this.options.perspective,
                    'perspective-origin': '50% 50%'
                });

                this.slider.image2.hide();

                // Get notified when the last transition has completed
                tiles.last().transitionEnd(function(event){
                    this_.slider.image2.show();

                    this_.finished();
                });

                setTimeout(function(){
                    tiles.css3({
                        'transform': myatu_bgm_flux.browser.rotateY(180)
                    });
                }, 50);
            }
        }, opts));
    };
}(jQuery));

(function($) {
    myatu_bgm_flux.transitions.turn = function(fluxslider, opts) {
        return new myatu_bgm_flux.transition(fluxslider, $.extend({
            requires3d: true,
            perspective: 1300,
            direction: 'left',
            setup: function() {
                var tab = $('<div class="tab"></div>').css({
                        width: '50%',
                        height: '100%',
                        position: 'absolute',
                        top: '0px',
                        left: this.options.direction === 'left' ? '50%' : '0%',
                        'z-index':101
                    }).css3({
                        'transform-style': 'preserve-3d',
                        'transition-duration': '1000ms',
                        'transition-timing-function': 'ease-out',
                        'transition-property': 'all',
                        'transform-origin': this.options.direction === 'left' ? 'left center' : 'right center'
                    }),

                current = $('<div></div>').css({
                    position: 'absolute',
                    top: '0',
                    left: this.options.direction === 'left' ? '0' : '50%',
                    width: '50%',
                    height: '100%',
                    'background-image': this.slider.image1.css('background-image'),
                    'background-size': this.slider.image1.css('background-size'),
                    'background-position': (this.options.direction === 'left' ? 0 : '-'+(this.slider.image1.width()/2))+'px 0',
                    'z-index':100
                }),

                overlay = $('<div class="overlay"></div>').css({
                    position: 'absolute',
                    top: '0',
                    left: this.options.direction === 'left' ? '50%' : '0',
                    width: '50%',
                    height: '100%',
                    background: '#000',
                    opacity: 1
                }).css3({
                    'transition-duration': '800ms',
                    'transition-timing-function': 'linear',
                    'transition-property': 'opacity'
                }),

                container = $('<div></div>').css3({
                    width: '100%',
                    height: '100%'
                }).css3({
                    'perspective': this.options.perspective,
                    'perspective-origin': '50% 50%'
                }).append(tab).append(current).append(overlay);

                /* front = */
                $('<div></div>').appendTo(tab).css({
                    'background-image': this.slider.image1.css('background-image'),
                    'background-size': this.slider.image1.css('background-size'),
                    'background-position': (this.options.direction === 'left' ? '-'+(this.slider.image1.width()/2) : 0)+'px 0',
                    width: '100%',
                    height: '100%',
                    position: 'absolute',
                    top: '0',
                    left: '0',
                    '-moz-transform': 'translateZ(1px)'
                }).css3({
                    'backface-visibility': 'hidden'
                });

                /* back = */
                $('<div></div>').appendTo(tab).css({
                    'background-image': this.slider.image2.css('background-image'),
                    'background-size': this.slider.image2.css('background-size'),
                    'background-position': (this.options.direction === 'left' ? 0 : '-'+(this.slider.image1.width()/2))+'px 0',
                    width: '100%',
                    height: '100%',
                    position: 'absolute',
                    top: '0',
                    left: '0'
                }).css3({
                    transform: myatu_bgm_flux.browser.rotateY(180),
                    'backface-visibility': 'hidden'
                });


                this.slider.image1.append(container);
            },
            execute: function() {
                var this_ = this;

                this.slider.image1.find('div.tab').first().transitionEnd(function(){
                    this_.finished();
                });

                setTimeout(function(){
                    this_.slider.image1.find('div.tab').css3({
                        // 179 not 180 so that the tab rotates the correct way in Firefox
                        transform: myatu_bgm_flux.browser.rotateY(this_.options.direction === 'left' ? -179 : 179)
                    });
                    this_.slider.image1.find('div.overlay').css({
                        opacity: 0
                    });
                }, 50);
            }
        }, opts));
    };
}(jQuery));

(function($) {
    myatu_bgm_flux.transitions.slide = function(fluxslider, opts) {
        return new myatu_bgm_flux.transition(fluxslider, $.extend({
            direction: 'left',
            setup: function() {
                var width = this.slider.image1.width(),
                    height = this.slider.image1.height(),

                currentImage = $('<div class="current"></div>').css({
                    height: height+'px',
                    width: width+'px',
                    position: 'absolute',
                    top: '0px',
                    left: '0px',
                    background: this.slider[this.options.direction === 'left' ? 'image1' : 'image2'].css('background-image'),
                    'background-size': this.slider[this.options.direction === 'left' ? 'image1' : 'image2'].css('background-size')
                }).css3({
                    'backface-visibility': 'hidden'
                }),

                nextImage = $('<div class="next"></div>').css({
                    height: height+'px',
                    width: width+'px',
                    position: 'absolute',
                    top: '0px',
                    left: width+'px',
                    background: this.slider[this.options.direction === 'left' ? 'image2' : 'image1'].css('background-image'),
                    'background-size': this.slider[this.options.direction === 'left' ? 'image2' : 'image1'].css('background-size')
                }).css3({
                    'backface-visibility': 'hidden'
                });

                this.slideContainer = $('<div class="slide"></div>').css({
                    width: (2*width)+'px',
                    height: height+'px',
                    position: 'relative',
                    left: this.options.direction === 'left' ? '0px' : -width+'px',
                    'z-index': 101
                }).css3({
                    'transition-duration': '600ms',
                    'transition-timing-function': 'ease-in',
                    'transition-property': 'all'
                });

                this.slideContainer.append(currentImage).append(nextImage);

                this.slider.image1.append(this.slideContainer);
            },
            execute: function() {
                var this_ = this,
                    delta = this.slider.image1.width();

                if(this.options.direction === 'left') {
                    delta = -delta;
                }

                this.slideContainer.transitionEnd(function(){
                    this_.finished();
                });

                setTimeout(function(){
                    this_.slideContainer.css3({
                        'transform' : myatu_bgm_flux.browser.translate(delta)
                    });
                }, 50);
            }
        }, opts));
    };
}(jQuery));

(function($) {
    myatu_bgm_flux.transitions.swipe = function(fluxslider, opts) {
        return new myatu_bgm_flux.transition(fluxslider, $.extend({
            setup: function() {
                var img = $('<div></div>').css({
                    width: '100%',
                    height: '100%',
                    'background-image': this.slider.image1.css('background-image'),
                    'background-size': this.slider.image1.css('background-size')
                }).css3({
                    'transition-duration': '1200ms',
                    'transition-timing-function': 'ease-in',
                    'transition-property': 'all',
                    'mask-image': '-webkit-linear-gradient(left, rgba(0,0,0,0) 0%, rgba(0,0,0,0) 48%, rgba(0,0,0,1) 52%, rgba(0,0,0,1) 100%)',
                    'mask-position': '70%',
                    'mask-size': '300%'
                });

                this.slider.image1.append(img);
            },
            execute: function() {
                var this_ = this,
                    img = this.slider.image1.find('div');

                // Get notified when the last transition has completed
                $(img).transitionEnd(function(){
                    this_.finished();
                });

                setTimeout(function(){
                    $(img).css3({
                        'mask-position': '30%'
                    });
                }, 50);
            },
            compatibilityCheck: function() {
                return myatu_bgm_flux.browser.supportsCSSProperty('MaskImage');
            }
        }, opts));
    };
}(jQuery));

(function($) {
    myatu_bgm_flux.transitions.dissolve = function(fluxslider, opts) {
        return new myatu_bgm_flux.transition(fluxslider, $.extend({
            setup: function() {
                var img = $('<div class="image"></div>').css({
                    width: '100%',
                    height: '100%',
                    'background-image': this.slider.image1.css('background-image'),
                    'background-size': this.slider.image1.css('background-size')
                }).css3({
                    'transition-duration': '600ms',
                    'transition-timing-function': 'ease-in',
                    'transition-property': 'opacity'
                });

                this.slider.image1.append(img);
            },
            execute: function() {
                var this_ = this,
                    img = this.slider.image1.find('div.image');

                // Get notified when the last transition has completed
                $(img).transitionEnd(function(){
                    this_.finished();
                });

                setTimeout(function(){
                    $(img).css({
                        'opacity': '0.0'
                    });
                }, 50);
            }
        }, opts));
    };
}(jQuery));
