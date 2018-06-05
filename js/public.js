jQuery(document).ready(function($) {
    /**
     * PS Links
     */
    $("a.pslinks").mouseover(function() {
        jQuery(this).children(".tip").show();
    });
    $("a.pslinks").mouseout(function() {
        jQuery(this).children(".tip").hide();
    });

    /**
     * PS Tag Image
     */
    if ($('.ps_tag_image').length > 0) {
        var taggd_options = {
            edit: false,
            align: {
                y: 'top'
            },
            offset: {
                top: 15
            },
            handlers: {
				click: 'toggle'
            }
        };

        $('.ps_tag_image').each(function() {
            var tag_data = [];
            if($(this).data('tags')){
                tag_data = $.parseJSON($(this).attr('data-tags'));
            }
            $(this).find('img').taggd(taggd_options, tag_data);
        });
    }
});