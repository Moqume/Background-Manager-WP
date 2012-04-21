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
    mainWin.doImportProgress = function(percent) {
        $('#import_progress').text(percent + '%');
        $('#import_progress_bar').css('width', percent + '%');
    }

    $.extend(myatu_bgm, {
        updateDescription : function() {
            var selected_importer = $('#importer option:selected').val(), importer_desc = $('#' + selected_importer + '_desc').val();

            if (selected_importer != '') {
                $('#importer_desc').text(importer_desc);
            } else {
                $('#importer_desc').text('');
            }
        }
    });

    $(document).ready(function($){
        // Initialize
        myatu_bgm.updateDescription();

        // Show progress bar container if JS is enabled
        $('#import_progress_bar_container').css('display', 'inline-block');

        // Set events
        $('#importer').change(myatu_bgm.updateDescription);
    });
})(jQuery);
