/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */
(function($){
    $.extend(myatu_bgm, {
        showHideLayoutTable: function(val) {
            if ((val) == 'full') {
                $('.bg_extra_layout').hide();
            } else {
                $('.bg_extra_layout').show();
            }
        }
    });

    $(document).ready(function($){
        myatu_bgm.showHideLayoutTable($('input[name="background_size"]:checked').attr('value'));

        $('#background_color').focusin(function() { $('#color_picker').show(); }).focusout(function() { $('#color_picker').hide() }).keyup(function () { if (this.value.charAt(0) != '#') this.value = '#' + this.value; });

        $('input[name="background_size"]').change(function() { myatu_bgm.showHideLayoutTable(this.value); });

        $('#color_picker').farbtastic('#background_color');
    });

})(jQuery);
