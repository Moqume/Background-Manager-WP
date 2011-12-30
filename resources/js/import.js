/*
 * Copyright (c) 2011 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */
(function(a){mainWin.doImportProgress=function(b){a("#import_progress").text(b+"%");a("#import_progress_bar").css("width",b+"%")};a.extend(myatu_bgm,{updateDescription:function(){var b=a("#importer option:selected").val(),c=a("#"+b+"_desc").val();if(b!=""){a("#importer_desc").text(c)}else{a("#importer_desc").text("")}}});a(document).ready(function(b){myatu_bgm.updateDescription();b("#import_progress_bar_container").css("display","inline-block");b("#importer").change(myatu_bgm.updateDescription)})})(jQuery);
