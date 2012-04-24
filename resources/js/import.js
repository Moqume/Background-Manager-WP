/*!
 * Copyright (c) 2011-2012 Mike Green <myatus@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */
if(myatu_bgm===undefined){var myatu_bgm={}}(function(b){mainWin.doImportProgress=function(a){b("#import_progress").text(a+"%");b("#import_progress_bar").css("width",a+"%")};b.extend(myatu_bgm,{updateDescription:function(){var a=b("#importer option:selected").val(),d=b("#"+a+"_desc").val();if(a!=""){b("#importer_desc").text(d)}else{b("#importer_desc").text("")}}});b(document).ready(function(a){myatu_bgm.updateDescription();a("#import_progress_bar_container").css("display","inline-block");a("#importer").change(myatu_bgm.updateDescription)})})(jQuery);