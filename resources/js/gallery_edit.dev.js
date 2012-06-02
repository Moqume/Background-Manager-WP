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
        /** Gets the count of named properties */
        GetObjSize: function(obj) {
            var size = 0, key;

            for (key in obj) {
                if (obj.hasOwnProperty(key)) {
                    size++;
                }
            }

            return size;
        },

        /** Creates a cookie */
        createCookie : function(name, value, days) {
            var date, expires;

            if (days) {
                date = new Date();
                date.setTime(date.getTime() + (days*24*60*60*1000));
                expires = '; expires=' + date.toGMTString();
            } else {
                expires = '';
            }
            document.cookie = name + '=' + value + expires + '; path=/';
        },

        /** Reads a cookie */
        readCookie : function(name) {
            var nameEQ = name + '=',
                ca = document.cookie.split(';'),
                c, i;

            for (i=0; i < ca.length; i++) {
                c = ca[i];
                while (c.charAt(0) === ' ') {
                    c = c.substring(1, c.length);
                }

                if (c.indexOf(nameEQ) === 0) {
                    return c.substring(nameEQ.length, c.length);
                }
            }

            return null;
        },

        /** Deletes a cookie */
        deleteCookie : function(name) {
            myatu_bgm.createCookie(name, "", -1);
        },

        /** Holds selected images */
        image_selection: {},

        /** Gets the image count [Ajax] */
        getImageCount: function() { return (myatu_bgm.GetAjaxData('image_count', $('#edit_id').val())); },

        /** Gets the hash of the current images [Ajax] */
        getImagesHash: function() { return (myatu_bgm.GetAjaxData('images_hash', $('#edit_id').val())); },

        /** Gets all the ids of the images [Ajax] */
        getImageIds: function() { return (myatu_bgm.GetAjaxData('image_ids', $('#edit_id').val())); },

        /** Removes the image iframe overlay and restores iframe visibility */
        removeImagesOverlay: function() { $('#images_iframe').fadeIn('fast', function() { $('#image_iframe_overlay').hide(); }); },

        /** Helper to remove or delete images */
        doDeleteRemoveImages : function(do_delete, id) {
            var key, ids = '', func = (typeof do_delete === 'undefined' || !do_delete) ? 'remove_images' : 'delete_images';

            if (typeof id === 'undefined') {
                // Determine the selected images
                for (key in myatu_bgm.image_selection) {
                    if (myatu_bgm.image_selection.hasOwnProperty(key)) {
                        ids += key.replace('image_', '') + ',';
                    }
                }
            } else {
                // Delete or remove specified image
                ids = id;
            }

            // Delete or remove the images
            myatu_bgm.GetAjaxData(func, ids);

            myatu_bgm.showHideEditBar(true);

            if (myatu_bgm.haveImagesChanged(true)) {
                myatu_bgm.loadImagesIframe();
            }
        },

        /** Helper to move images */
        doMoveImages: function(right, id) {
            var key, ids = '', inc = (right) ? 1 : 0;

            if (typeof id === 'undefined') {
                // Determine the selected images
                for (key in myatu_bgm.image_selection) {
                    if (myatu_bgm.image_selection.hasOwnProperty(key)) {
                        ids += key.replace('image_', '') + ',';
                    }
                }
            } else {
                // Move a specified image
                ids = id;
            }

            // Peform AJAX magic for moving the image
            myatu_bgm.GetAjaxData('change_order', {'ids' : ids, 'inc' : inc });

            myatu_bgm.showHideEditBar(true);

            if (myatu_bgm.haveImagesChanged(true)) {
                myatu_bgm.loadImagesIframe();
            }
        },

        /** Displays or hides the "Edit Bar" (containing buttons related to selected items) */
        showHideEditBar: function(getIds) {
            var edit_bar      = $('#quicktags'),
                selected_count = $('#selected-count'),
                count, ids, id, key;

            if (getIds === true) {
                // Check if a selected ID no longer exist in getImageIds(), and delete from image_selection if so.
                ids = myatu_bgm.getImageIds();

                for (key in myatu_bgm.image_selection) {
                    if (myatu_bgm.image_selection.hasOwnProperty(key)) {
                        id = key.replace('image_', '');

                        if (typeof ids[id] === 'undefined') {
                            delete myatu_bgm.image_selection[key];
                        }
                    }
                }
            }

            // Obtain the count of selected images (after removal)
            count = myatu_bgm.GetObjSize(myatu_bgm.image_selection);

            // Show or hide the edit bar based on the image_selection object count
            if (count > 0) {
                edit_bar.slideDown();
                selected_count.show();
                $('#select-count', selected_count).html(count);
            } else {
                edit_bar.slideUp();
                selected_count.hide();
                $('#select-count', selected_count).html('0');
            }
        },

        /** Returns whether images have been changed (based on the hash) */
        haveImagesChanged: function(setNewHash) {
            var current_hash = $('#images_hash').val(), hash = myatu_bgm.getImagesHash();

            if (hash !== false && current_hash !== hash) {
                if (setNewHash === true) {
                    $('#images_hash').val(hash);
                }

                return true;
            }

            return false;
        },

        /** Loads a URL in the image iframe */
        loadImagesIframe: function(dest) {
            var overlay = $('#image_iframe_overlay'), loader = $('#loader', overlay);

            if (dest === undefined) {
                dest = $('#images_iframe').attr("src"); // Default action is to reload
            }

            // Display the overlay on top of the images iframe
            overlay.show();

            // Hide the image buttons, if shown.
            $('#image_buttons').hide();

            // Center the loader image
            loader.css('top', ((overlay.height() - loader.outerHeight()) / 2) + overlay.scrollTop() + 'px');
            loader.css('left', ((overlay.width() - loader.outerWidth()) / 2) + overlay.scrollLeft() + 'px');

            // Fade out the iframe
            $('#images_iframe').attr("src", dest).fadeOut('fast');
        },

        /** Shows (or hides) the (single image) buttons on the highlighted item */
        showHideImageButtons: function(highlighted) {
            var image_iframe    = $('#images_iframe').contents(),
                image_buttons    = $('#image_buttons',                  image_iframe),
                image_r_button_h = $('#image_move_right_button_holder', image_iframe),
                image_l_button_h = $('#image_move_left_button_holder',  image_iframe),
                image_img_bottom, image_img, overlay, image_id;

            // If nothing is highlighted, then we hide the buttons instead.
            if (!$(highlighted).length) {
                image_buttons.hide();
                image_r_button_h.hide();
                image_l_button_h.hide();
                return;
            }

            image_img = $('img', highlighted);
            overlay   = $('#image_iframe_overlay');
            image_id  = $(highlighted).attr('id').replace('image_', '');

            // Align edit buttons within the top-left corner of the image
            image_buttons.css('top',  image_img.offset().top  - overlay.scrollTop()  + 'px');
            image_buttons.css('left', image_img.offset().left - overlay.scrollLeft() + 'px');
            image_buttons.show();

            image_img_bottom = (image_img.height() + image_img.offset().top - 30) - overlay.scrollTop();
            // Align the 'move left' button with the bottom-left corner of the image
            image_l_button_h.css('top',  image_img_bottom + 'px');
            image_l_button_h.css('left', image_img.offset().left - overlay.scrollLeft() + 'px');
            image_l_button_h.show();

            // Align the 'move right' button with the bottom-right corner of the image
            image_r_button_h.css('top',  image_img_bottom + 'px');
            image_r_button_h.css('left', (image_img.width() + image_img.offset().left - 30) - overlay.scrollLeft() + 'px');
            image_r_button_h.show();

            // Set the correct href for the buttons
            $('.image_button', image_iframe).each(function() {
                if ($(this).attr('id') === 'image_edit_button') {
                    $(this).attr('href', $('#image_iframe_edit_base').val() + '&id=' + image_id + '&TB_iframe=true');
                } else {
                    $(this).attr('href', '#' + image_id);
                }
            });
        },

        /** Event triggered when the iframe has finished loading */
        onImagesIframeFinish: function(current_page) {
            var image_body       = $('#images_iframe').contents().find('html,body'),
                image_container  = $('#image_container', image_body),
                pagination_links = myatu_bgm.GetAjaxData('paginate_links', {
                    id:      $('#edit_id').val(),
                    base:    $('#images_iframe_base').val(),
                    pp:      $('#images_per_page').val(),
                    current: current_page
                });

            // Display pagination links, if any were returned by Ajax call
            if (pagination_links !== false) {
                $('.tablenav-pages').html(pagination_links);
                $('.tablenav-pages a').click(myatu_bgm.onPaginationClick);
            }

            // Display image count
            $('#wp-word-count #image-count').html(myatu_bgm.getImageCount());

            // Iterate the images displayed, binding click and re-highlighting if previously selected
            $('.image', image_container).each(function(index) {
                $(this).dblclick(myatu_bgm.onImageDoubleClick);
                $(this).click(myatu_bgm.onImageClick);

                if (myatu_bgm.image_selection[$(this).attr('id')] === true) {
                    $(this).addClass('selected');
                }
            });

            // Add a additional click events
            image_body.click(myatu_bgm.onEmptyImageAreaClick);
            $('#image_edit_button', image_body).click(myatu_bgm.onImageEditButtonClick);
            $('#image_del_button', image_body).click(myatu_bgm.onImageDeleteButtonClick);
            $('#image_remove_button', image_body).click(myatu_bgm.onImageRemoveButtonClick);
            $('#image_move_right_button', image_body).click(myatu_bgm.onImageMoveRightButtonClick);
            $('#image_move_left_button', image_body).click(myatu_bgm.onImageMoveLeftButtonClick);

            // Attach keyboard events
            image_body.keydown(myatu_bgm.onIframeKeyDown);

            // Remove the overlay, we're done.
            myatu_bgm.removeImagesOverlay();
        },

        /** Event triggered when `Delete Selected` is clicked */
        onDeleteSelected: function(event) {
            if ($('#image_del_is_perm').val() === '1' && confirm(bgmL10n.warn_delete_all_images) === false) {
                return false;
            }

            myatu_bgm.doDeleteRemoveImages(true);

            return false;
        },

        /** Event triggered when `Remove Selected` is clicked */
        onRemoveSelected: function(event) {
            myatu_bgm.doDeleteRemoveImages(false);

            return false;
        },

        /** Event triggered when 'Move Selected Left' is clicked */
        onMoveLeftSelected: function(event) {
            myatu_bgm.doMoveImages(false);

            return false;
        },

        /** Event triggered when 'Move Selected Right' is clicked */
        onMoveRightSelected: function(event) {
            myatu_bgm.doMoveImages(true);

            return false;
        },

        /** Event triggered when `Clear` is clicked */
        onClearSelected: function(event) {
            var image_container = $('#images_iframe').contents().find('#image_container');

            // Clear image_selection
            myatu_bgm.image_selection = {};

            // Remove any selected classes displayed
            $('.image', image_container).each(function(index) {
                if ($(this).hasClass('selected')) {
                    $(this).removeClass('selected');
                }
            });

            // Hide the edit bar
            myatu_bgm.showHideEditBar(false);

            return false;
        },

        /** Event triggered when one of the pagination buttons have been clicked */
        onPaginationClick: function(event) {
            myatu_bgm.loadImagesIframe($(this).attr('href'));

            return false;
       },

        /** Event triggered when a image (inside the iframe) has been double clicked */
        onImageDoubleClick: function(event) {
            var id = $(this).attr('id');

            $(this).toggleClass('selected');

            if ($(this).hasClass('selected')) {
                myatu_bgm.image_selection[id] = true;
            } else {
                delete myatu_bgm.image_selection[id];
            }

            myatu_bgm.showHideEditBar(false);

            return false;
        },

        /** Event triggered when a image (inside the iframe) has been clicked */
        onImageClick: function(event) {
            // Only allow a single image to be highlighted
            $('#images_iframe').contents().find('.image').removeClass('highlighted');

            // Highlight the clicked item
            $(this).addClass('highlighted');

            // And show the image buttons
            myatu_bgm.showHideImageButtons(this);

            return false;
        },

        /** Event triggered when no image (empty area) is clicked */
        onEmptyImageAreaClick: function(event) {
            $('#images_iframe').contents().find('.image').removeClass('highlighted');

            myatu_bgm.showHideImageButtons();
        },

        /** Event triggered when the `edit` button is clicked */
        onImageEditButtonClick: function(event) {
            tb_show($(this).attr('title'), $(this).attr('href')); // We do this here instead of using a thickbox class, to ensure it is shown in the parent, not the iframe

            return false;
        },

        /** Event triggered when the `delete` button is clicked */
        onImageDeleteButtonClick: function(event) {
            if ($('#image_del_is_perm').val() === '1' && confirm(bgmL10n.warn_delete_image) === false) {
                return false;
            }

            myatu_bgm.doDeleteRemoveImages(true, $(this).attr('href').replace('#', ''));

            return false;
        },

        /** Event triggered when the `remove` button is clicked */
        onImageRemoveButtonClick: function(event) {
            myatu_bgm.doDeleteRemoveImages(false, $(this).attr('href').replace('#', ''));

            return false;
        },

        /** Event triggered when the `move left` button is clicked */
        onImageMoveLeftButtonClick: function(event) {
            myatu_bgm.doMoveImages(false, $(this).attr('href').replace('#', ''));

            return false;
        },

        /** Event triggered when the `move right` button is clicked */
        onImageMoveRightButtonClick: function(event) {
            myatu_bgm.doMoveImages(true, $(this).attr('href').replace('#', ''));

            return false;
        },


        /** Event tiggered when a key is pressed inside the iframe, to assist with selecting items by keyboard */
        onIframeKeyDown: function(event) {
            var image_body      = $('#images_iframe').contents().find('html,body'),
                image_container = $('#image_container', image_body),
                highlighted     = $('.image.highlighted', image_container),
                doScroll        = function(which) {
                    // Internal function to ensure a highlighted item, and then scroll to the highlighted item
                    if (!highlighted.length && typeof which !== 'undefined') {
                        highlighted = $('.image:'+which, image_container).addClass('highlighted');
                    }

                    // Add any image buttons, if needed
                    myatu_bgm.showHideImageButtons();

                    // Scroll to the item.
                    if (image_body.length && highlighted.length) {
                        image_body.scrollTo(highlighted);
                    }
                };

            // Event is based on specific keys:
            switch (event.keyCode) {
                case 32:
                    // Space
                    highlighted.dblclick();

                    return false;

                case 37:
                    // Left arrow
                    highlighted = highlighted.removeClass('highlighted').prev('.image').addClass('highlighted');

                    doScroll('last');

                    return false;

                case 39:
                    // Right arrow
                    highlighted = highlighted.removeClass('highlighted').next('.image').addClass('highlighted');

                    doScroll('first');

                    return false;
            }

        },

        /**
         * Allows the main Edit window to be resized
         */
        initEditWindowResize : function() {
            var container = $('#editorcontainer'),
                last_seen, pos, new_height;

            if (!container.length) {
                return;
            }

            // Initialize the height of the editor based on a stored cookie
            new_height = myatu_bgm.readCookie('mbgm_editor_height');

            if (new_height && new_height > 150) {
                container.height(new_height);
            }

            // Process mouse movement
            $(mainWin).mousemove(function(event) {
                if (!container.data('active')) {
                    return;
                }

                pos       = event.pageY;
                last_seen = container.data('last_seen');

                if (last_seen) {
                    new_height = container.height() + (pos - last_seen);

                    if (new_height > 150) {
                        container.height(new_height);
                    }
                }

                container.data('last_seen', pos);
            });

            // Allows the window to be resized while the mouse is being moved
            $('#resize_window').mousedown(function(e){
                container.data('active', true);
                e.preventDefault();
            });

            // Stops allowing the window to be resized
            $(mainWin).mouseup(function(){
                container.data('active', false);
                container.data('last_seen', false);

                myatu_bgm.createCookie('mbgm_editor_height', container.height(), 365);
            });



        }
    });

    /** "Ready" event */
    $(document).ready(function($){
        // Override send_to_editor(html):
        if (typeof mainWin !== "undefined") {
            mainWin.send_to_editor = function(send_id) {
                tb_remove(); // All we need to do is close the ThickBox window
            };

            // Override tb_remove()
            mainWin.tb_remove = function() {
                $("#TB_imageOff").unbind("click");
                $("#TB_closeWindowButton").unbind("click");
                $("#TB_window").fadeOut("fast",function(){$('#TB_window,#TB_overlay,#TB_HideSelect').trigger("unload").unbind().remove();});
                $("#TB_load").remove();

                if (typeof document.body.style.maxHeight === "undefined") {//if IE 6
                    $("body","html").css({height: "auto", width: "auto"});
                    $("html").css("overflow","");
                }

                document.onkeydown = "";
                document.onkeyup = "";

                if (myatu_bgm.haveImagesChanged(true)) {
                   myatu_bgm.loadImagesIframe();
                }

                return false;
            };
        }

        // Attach 'click' events
        $('#ed_delete_selected').click(myatu_bgm.onDeleteSelected);
        $('#ed_clear_selected').click(myatu_bgm.onClearSelected);
        $('#ed_remove_selected').click(myatu_bgm.onRemoveSelected);
        $('#ed_move_l_selected').click(myatu_bgm.onMoveLeftSelected);
        $('#ed_move_r_selected').click(myatu_bgm.onMoveRightSelected);

        myatu_bgm.initEditWindowResize();
    });

}(jQuery));
