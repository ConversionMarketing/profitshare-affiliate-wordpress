jQuery(document).ready(function($) {
    var progressbar = $('#progressbar'),
            progressbarLabel = $('#progressbar .plabel'),
            batch_type = $('.batch_type'),
            batch_count = $('.batch_count'),
            batch_processed = $('.batch_processed'),
            batch_log = $('#processed_items'),
            batch_last;

    /**
     * Progress Bar
     */
    progressbar.progressbar({
        value: 0,
        change: function() {
            progressbarLabel.text(progressbar.progressbar('value') + '%');
            progressbarLabel.css({color: '#FFF'});
        },
        complete: function() {
            progressbarLabel.text('Process is done!');
        }
    });

    /**
     * Update Progress Bar and Log
     */
    var update_progressbar = function(value) {
        progressbar.progressbar('value', Math.ceil(value));
    };

    var update_log = function(data) {
        $.each(data, function(index, value) {
            batch_log.append('<div class="item">Item #' + value + ' was processed.</div>');
        });
    }

    /**
     * AJAX
     */
    var do_ajax = function() {
        $.post(ajaxurl, { action: 'ps_replace_links_batch', type: batch_type.text(), last: batch_last }, function(response) {
            if (response.success) {
                var items_processed = response.data.processed,
                    items_processed_no = items_processed.length,
                    batch_processed_int = parseInt(batch_processed.text()),
                    batch_count_int = parseInt(batch_count.text()),
                    progress_value = ((batch_processed_int + items_processed_no) / batch_count_int) * 100;
                    
                batch_last = response.data.last;

                // update progressbar and log
                update_log(items_processed);
                update_progressbar(progress_value);

                // update dom
                batch_processed.text(batch_processed_int + items_processed_no);

                // do_ajax until finish
                if ((batch_processed_int + items_processed_no) < batch_count_int) {
                    do_ajax();
                }
            } else {
                alert(response.data.message);
            }
        });
    }

    do_ajax();
});

