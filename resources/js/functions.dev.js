mainWin = window.dialogArguments || opener || parent || top;

(function($) {
    getAjaxData = function(ajaxFunc, ajaxData) {
        var resp = false;

        $.ajax({
            type     : 'POST',
            dataType : 'json',
            url      : ajaxurl,
            timeout  : 5000,
            async    : false,
            data     : { action: ajaxaction, func: ajaxFunc, data: ajaxData, _ajax_nonce: ajaxnonce },
            success  : function(ajaxResp) {
                if (ajaxResp.nonce == ajaxnonceresponse && ajaxResp.stat == 'ok')
                    resp = ajaxResp.data;
            }
        });

        return resp;
    }
})(jQuery);
