/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

(function($){
    $.extend(myatu_bgm, {
        /** Holds selected images */
        image_selection: new Object(),
       
        /** Gets the image count [Ajax] */
        getImageCount: function() { return (myatu_bgm.GetAjaxData('image_count', $('#edit_id').val())); },

        /** Gets the hash of the current images [Ajax] */
        getImagesHash: function() { return (myatu_bgm.GetAjaxData('images_hash', $('#edit_id').val())); },

        /** Gets all the ids of the images [Ajax] */
        getImageIds: function() { return (myatu_bgm.GetAjaxData('image_ids', $('#edit_id').val())); },

        /** Removes the image iframe overlay and restores iframe visibility */
        removeImagesOverlay: function() { $('#images_iframe').fadeIn('fast', function() { $('#image_iframe_overlay').hide(); }); },

        /** Displays or hides the "Edit Bar" (containing buttons related to selected items) */
        showHideEditBar: function(getIds) {
            if (getIds == true) {
                // Check if a selected ID no longer exist in getImageIds(), and delete from image_selection if so.
                var ids = myatu_bgm.getImageIds();

                for (key in myatu_bgm.image_selection) {
                    var id = key.replace('image_', '');

                    if (ids[id] == undefined)
                        delete myatu_bgm.image_selection[key];
                }
            }

            // Show or hide the edit bar based on the image_selection object count
            var edit_bar = $('#quicktags'), selected_count = $('#selected-count'), count = myatu_bgm.GetObjSize(myatu_bgm.image_selection);

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

            if (hash != false && current_hash != hash) {
                if (setNewHash == true)
                    $('#images_hash').val(hash);

                return true;
            }

            return false;
        },

        /** Loads a URL in the image iframe */
        loadImagesIframe: function(dest) {
            var overlay = $('#image_iframe_overlay'), loader = $('#loader', overlay);

            if (dest == undefined)
                dest = $('#images_iframe').attr("src"); // Default action is to reload
               
            // Display the overlay on top of the images iframe
            overlay.show();

            // Hide the image buttons, if shown.
            $('#image_buttons').hide()

            // Center the loader image
            loader.css('top', ((overlay.height() - loader.outerHeight()) / 2) + overlay.scrollTop() + 'px');
            loader.css('left', ((overlay.width() - loader.outerWidth()) / 2) + overlay.scrollLeft() + 'px');
            
            // Fade out the iframe
            $('#images_iframe').attr("src", dest).fadeOut('fast');
        },

        /** Shows (or hides) the (single image) edit/delete buttons on the highlighted item */
        showHideImageButtons: function(highlighted) {
            var image_buttons = $('#images_iframe').contents().find('#image_buttons');

            if (highlighted == undefined)
                highlighted = $('#images_iframe').contents().find('.highlighted:first');

            // If nothing is highlighted, then we hide the edit buttons instead.
            if (!$(highlighted).length) {
                image_buttons.hide();
                return;
            }

            var image_img = $('img', highlighted), overlay = $('#image_iframe_overlay'), loader = $('#loader', overlay);

            // Align edit buttons within the top-left corner of the image tag
            image_buttons.css('top', image_img.offset().top - overlay.scrollTop() + 'px');
            image_buttons.css('left', image_img.offset().left - overlay.scrollLeft() +  'px');
            image_buttons.show();

            // Set the correct href for the image `edit` and `delete` button
            $('#image_edit_button', image_buttons).attr("href", $('#image_iframe_edit_base').val() + '&id=' + $(highlighted).attr('id').replace('image_', '') + '&TB_iframe=true');
            $('#image_del_button', image_buttons).attr("href", '#' + $(highlighted).attr('id').replace('image_', ''));
        },

        /** Event triggered when the iframe has finished loading */
        onImagesIframeFinish: function(current_page) {
            var image_body = $('#images_iframe').contents().find('html,body'), image_container = $('#image_container', image_body);
            var pagination_links = myatu_bgm.GetAjaxData('paginate_links', { id: $('#edit_id').val(), base: $('#images_iframe_base').val(), pp: $('#images_per_page').val(), current: current_page });

            // Display pagination links, if any were returned by Ajax call
            if (pagination_links != false) {
                $('.tablenav-pages').html(pagination_links);
                $('.tablenav-pages a').click(myatu_bgm.onPaginationClick);
            }

            // Display image count
            $('#wp-word-count #image-count').html(myatu_bgm.getImageCount());
            
            // Iterate the images displayed, binding click and re-highlighting if previously selected
            $('.image', image_container).each(function(index) {
                $(this).dblclick(myatu_bgm.onImageDoubleClick);
                $(this).click(myatu_bgm.onImageClick);

                if (myatu_bgm.image_selection[$(this).attr('id')] == true)
                    $(this).addClass('selected');
            });

            // Add a additional click events
            image_body.click(myatu_bgm.onEmptyImageAreaClick);
            $('#image_edit_button', image_body).click(myatu_bgm.onImageEditButtonClick);
            $('#image_del_button', image_body).click(myatu_bgm.onImageDeleteButtonClick);

            // Attach keyboard events
            image_body.keydown(myatu_bgm.onIframeKeyDown);

            // Remove the overlay, we're done.
            myatu_bgm.removeImagesOverlay();
        },

        /** Event triggered when `Delete Selected` is clicked */
        onDeleteSelected: function(event) {
            if ($('#image_del_is_perm').val() == '1' && confirm(bgmL10n.warn_delete_all_images) == false)
                return false;

            var key, ids = '';

            for (key in myatu_bgm.image_selection)
                ids += key.replace('image_', '') + ',';

            // Delete the images from the DB
            myatu_bgm.GetAjaxData('delete_images', ids);

            myatu_bgm.showHideEditBar(true);
            
            if (myatu_bgm.haveImagesChanged(true))
                myatu_bgm.loadImagesIframe();

            return false;
        },

        /** Event triggered when `Clear` is clicked */
        onClearSelected: function(event) {
            var image_container = $('#images_iframe').contents().find('#image_container');

            // Clear image_selection
            myatu_bgm.image_selection = new Object();

            // Remove any selected classes displayed
            $('.image', image_container).each(function(index) {
                if ($(this).hasClass('selected'))
                    $(this).removeClass('selected');
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
            if ($('#image_del_is_perm').val() == '1' && confirm(bgmL10n.warn_delete_image) == false)
                return false;

            // Delete the image from the DB
            myatu_bgm.GetAjaxData('delete_images', $(this).attr('href').replace('#', ''));

            myatu_bgm.showHideEditBar(true);
            
            if (myatu_bgm.haveImagesChanged(true))
                myatu_bgm.loadImagesIframe();
            
            return false;
        },

        /** Event tiggered when a key is pressed inside the iframe, to assist with selecting items by keyboard */
        onIframeKeyDown: function(event) {
            var image_body = $('#images_iframe').contents().find('html,body'), image_container = $('#image_container', image_body), highlighted = $('.image.highlighted', image_container);

            // Internal function to ensure a highlighted item, and then scroll to the highlighted item
            var doScroll = function(which) { 
                if (!highlighted.length && which != undefined) 
                    highlighted = $('.image:'+which, image_container).addClass('highlighted');

                // Add any image buttons, if needed
                myatu_bgm.showHideImageButtons();

                // Scroll to the item.
                if (image_body.length && highlighted.length)
                    image_body.scrollTo(highlighted);

            };

            // Internal function to get position, width and height details
            var pos = function(obj) { return { x: $(obj).offset().left, y: $(obj).offset().top, w: $(obj).outerWidth(), h: $(obj).outerHeight() }; }

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

        }
  
    });

    /** "Ready" event */
    $(document).ready(function($){
        // Override send_to_editor(html):
        mainWin.send_to_editor = function(send_id) {
            tb_remove(); // All we need to do is close the ThickBox window
        }

        // Override tb_remove()
        mainWin.tb_remove = function() {
            $("#TB_imageOff").unbind("click");
            $("#TB_closeWindowButton").unbind("click");
            $("#TB_window").fadeOut("fast",function(){$('#TB_window,#TB_overlay,#TB_HideSelect').trigger("unload").unbind().remove();});
            $("#TB_load").remove();

            if (typeof document.body.style.maxHeight == "undefined") {//if IE 6
                $("body","html").css({height: "auto", width: "auto"});
                $("html").css("overflow","");
            }

            document.onkeydown = "";
            document.onkeyup = "";

            if (myatu_bgm.haveImagesChanged(true))
               myatu_bgm.loadImagesIframe();

            return false;
        }

        // Attach 'click' events
        $('#ed_delete_selected').click(myatu_bgm.onDeleteSelected);
        $('#ed_clear_selected').click(myatu_bgm.onClearSelected);
    });

})(jQuery);
