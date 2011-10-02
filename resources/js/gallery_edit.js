(function($){
    getPhotoCount = function() { return getAjaxData('photo_count', $('#edit_id').val()); }

    getPhotosHash = function() { return getAjaxData('photos_hash', $('#edit_id').val()); }

    removePhotosOverlay = function() { $('#photo_iframe_overlay').fadeOut('slow'); }

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

    loadPhotosIframe = function(dest) {
        if (dest == undefined)
            dest = $('#photos_iframe').attr("src"); // Default action is to reload

        $('#photo_iframe_overlay').show();
        $('#photos_iframe').attr("src", dest);
    }

    onPaginationClick = function(obj) {
        loadPhotosIframe($(this).attr('href'));

        return false;
   }

    onPhotosOverlayFinish = function(current_page) {
        removePhotosOverlay();

        var pagination_links = getAjaxData('paginate_links', { id: $('#edit_id').val(), base: $('#photos_iframe_base').val(), current: current_page });

        if (pagination_links != false) {
            $('.tablenav-pages').html(pagination_links);
            $('.tablenav-pages a').click(onPaginationClick);
        }

        $('.word-count').html(getPhotoCount());
    }

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
    });

})(jQuery);
