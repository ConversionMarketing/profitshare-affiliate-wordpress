jQuery(document).ready(function($) {
    var mediaUploader,
            attachment,
            upload_img_btn = $('#upload-img'),
            uploaded_img = $('#uploaded-img'),
            uploaded_img_src = $('#uploaded-img-src'),
            uploaded_img_tags = $('#uploaded-img-tags'),
            uploaded_img_clear = $('#uploaded-img-clear'),
            taggd_data = [];

    /**
     * Taggd
     */
    if (uploaded_img_src.val() != '') {
        uploaded_img.attr('src', uploaded_img_src.val());
    }

    if (uploaded_img_tags.val() != '') {
        taggd_data = $.parseJSON(uploaded_img_tags.val());
    }

    var taggd = uploaded_img.taggd({
        edit: true,
        align: {
            y: 'top'
        },
        offset: {
            top: 15
        },
        handlers: {
            click: 'toggle'
        }
    }, taggd_data);

    taggd.on('change', function() {
        uploaded_img_tags.attr('value', JSON.stringify(taggd.data));
    });

    /**
     * Upload image
     */
    upload_img_btn.click(function(e) {
        e.preventDefault();
        // If the uploader object has already been created, reopen the dialog
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }
        // Extend the wp.media object
        mediaUploader = wp.media.frames.file_frame = wp.media({
            title: 'Choose Image',
            button: {
                text: 'Choose Image'
            },
            multiple: false
        });
        // When a file is selected, grab the URL and set it as the text field's value
        mediaUploader.on('select', function() {
            attachment = mediaUploader.state().get('selection').first().toJSON();

            // populate image
            uploaded_img.attr('src', attachment.url);
            uploaded_img.show();
            uploaded_img_src.attr('value', attachment.url);

            // clear tags
            uploaded_img_clear.trigger('click');
        });
        // Open the uploader dialog
        mediaUploader.open();
    });

    /**
     * Clear tags
     */
    uploaded_img_clear.on('click', function() {
        uploaded_img_tags.attr('value', '');

        taggd.setData([]);
        taggd.clear();
    });
});