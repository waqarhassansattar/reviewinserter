/**
 * Officers Academy Reviews - Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // 1. Fill Sample JSON into textarea
        $('#oa-paste-sample-btn').on('click', function() {
            var sampleText = $('#oa-sample-json-content').text();
            $('#reviews_json').val(sampleText);
            // Default target course if not selected
            if (!$('#target_course').val()) {
                $('#target_course').val('PMA Long Course');
            }
        });

        // 2. Copy Sample JSON to Clipboard
        $('#oa-copy-sample-btn').on('click', function() {
            var sampleText = $('#oa-sample-json-content').text();
            var $btn = $(this);
            navigator.clipboard.writeText(sampleText).then(function() {
                var oldText = $btn.text();
                $btn.text('Copied! ✓');
                setTimeout(function() {
                    $btn.text(oldText);
                }, 2000);
            });
        });

        // 3. Copy AI Prompt to Clipboard
        $('#oa-copy-prompt-btn').on('click', function() {
            var promptText = $('#oa-ai-prompt-text').val();
            var $btn = $(this);
            navigator.clipboard.writeText(promptText).then(function() {
                var oldText = $btn.text();
                $btn.text('Copied! ✓');
                setTimeout(function() {
                    $btn.text(oldText);
                }, 2000);
            });
        });

        // 4. Select All Checkboxes in Reviews Table
        $('#oa-select-all-reviews').on('change', function() {
            var isChecked = $(this).prop('checked');
            $('.oa-review-cb').prop('checked', isChecked);
        });
    });

})(jQuery);
