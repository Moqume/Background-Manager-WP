(function($){
    /** Holds selected photos */
    photo_selection = new Object();

    /** Gets the count of named properties */
    getObjSize = function(obj) {
        var size = 0, key;

        for (key in obj)
            if (obj.hasOwnProperty(key))
                size++;

        return size;
    }

    /** Gets the photo count [Ajax] */
    getPhotoCount = function() { return getAjaxData('photo_count', $('#edit_id').val()); }

    /** Gets the hash of the current photos [Ajax] */
    getPhotosHash = function() { return getAjaxData('photos_hash', $('#edit_id').val()); }

    /** Gets all the ids of the photos [Ajax] */
    getPhotoIds = function() { return getAjaxData('photo_ids', $('#edit_id').val()); }

    /** Removes the photo iframe overlay */
    removePhotosOverlay = function() { $('#photo_iframe_overlay').fadeOut('slow'); }

    /** Displays the photo iframe overlay */
    showPhotosOverlay = function() {
        var overlay = $('#photo_iframe_overlay');
        var loader  = $('#loader', overlay);
        
        // Display the overlay on top of the photos iframe
        overlay.show();

        // Center the loader image
        loader.css("top", ((overlay.height() - loader.outerHeight()) / 2) + overlay.scrollTop() + "px");
        loader.css("left", ((overlay.width() - loader.outerWidth()) / 2) + overlay.scrollLeft() + "px");
    }

    /** Displayes or hides the "Edit Bar" (containing buttons related to selected items) */
    showHideEditBar = function() {
        var edit_bar = $('#quicktags'), selected_count = $('#selected-count');
        var count = getObjSize(photo_selection);

        if (count > 0) {
            edit_bar.slideDown();
            selected_count.show();
            $('#select-count', selected_count).html(count);
        } else {
            edit_bar.slideUp();
            selected_count.hide();
            $('#select-count', selected_count).html('0');
        }
    }

    /** Returns whether photos have been changed (based on the hash) */
    havePhotosChanged = function(setNewHash) {
        var current_hash = $('#photos_hash').val();
        var hash = getPhotosHash();

        if (hash != false && current_hash != hash) {
            if (setNewHash == true)
                $('#photos_hash').val(hash);

            return true;
        }

        return false;
    }

    /** Loads a URL in the photo iframe */
    loadPhotosIframe = function(dest) {
        if (dest == undefined)
            dest = $('#photos_iframe').attr("src"); // Default action is to reload
        
        showPhotosOverlay();

        $('#photos_iframe').attr("src", dest);
    }

    /** Event triggered when `Delete Selected` is clicked */
    onDeleteSelected = function(obj) {
        var key, ids = '';

        for (key in photo_selection)
            ids += key.substring(6) + ','; // Note: substring(6) removes "photo_" from key name

        var all_deleted = getAjaxData('delete_photos', ids);

        // Check if a selected ID no longer exist in getPhotoIds(), and delete if so.
        ids = getPhotoIds();

        for (key in photo_selection) {
            id = key.substring(6);

            if (ids[id] == undefined)
                delete photo_selection[key];
        }

        showHideEditBar();
        
        if (havePhotosChanged(true))
            loadPhotosIframe();
    }

    /** Event triggered when one of the pagination buttons have been clicked */
    onPaginationClick = function(obj) {
        loadPhotosIframe($(this).attr('href'));

        return false;
   }

    /** Event triggered when the iframe has finished loading */
    onPhotosIframeFinish = function(current_page) {
        var pagination_links = getAjaxData('paginate_links', { id: $('#edit_id').val(), base: $('#photos_iframe_base').val(), pp: $('#photos_per_page').val(), current: current_page });

        if (pagination_links != false) {
            $('.tablenav-pages').html(pagination_links);
            $('.tablenav-pages a').click(onPaginationClick);
        }

        $('#wp-word-count #photo-count').html(getPhotoCount());

        // Grab the photo container inside the iframe (this is a same-domain thing)
        var photo_container = $('#photos_iframe').contents().find('#photo_container');
        
        // Iterate the photos displayed, binding click and highlighting if previously selected
        $('.photo', photo_container).each(function(index) {
            $(this).click(onPhotoClick);

            if (photo_selection[$(this).attr('id')] == true)
                $(this).addClass('selected');
        });

        removePhotosOverlay();
    }

    /** Event triggered when a photo (inside the iframe) has been clicked */
    onPhotoClick = function(obj) {
        $(this).toggleClass('selected');

        var id = $(this).attr('id');

        if ($(this).hasClass('selected')) {
            photo_selection[id] = true;
        } else {
            delete photo_selection[id];
        }

        showHideEditBar();
    }

    /** "Read" event */
    $(document).ready(function($){
        // Override send_to_editor(html):
        mainWin.send_to_editor = function(html) {
            tb_remove(); // Close ThickBox window

            var img_token = $('img',html);
            if ( img_token.length == 0 ) {
                // this *is* the img token

                img_token = $(html);
            }

            if ( img_token.length == 0 ) return; // If we still have no image token, then give up trying.

            var img_url = img_token.attr('src');

            // Build data for adding photo
    //        alert('Woohoo! Got '+img_token.attr('title'));
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

            if (havePhotosChanged(true))
               loadPhotosIframe();

            return false;
        }

        // Attach 'click' events
        $('#ed_delete_selected').click(onDeleteSelected);
    });

})(jQuery);
