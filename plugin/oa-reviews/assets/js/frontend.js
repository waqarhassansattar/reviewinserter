/**
 * Officers Academy Reviews - Frontend JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        var $modal = $('#oa-review-modal');
        var $successModal = $('#oa-success-modal');
        var $form = $('#oa-front-review-form');
        var $submitBtn = $('#oa-submit-review-btn');

        // 1. Open Review Form Modal
        $('#oa-open-modal-btn').on('click', function(e) {
            e.preventDefault();
            $modal.fadeIn(200);
        });

        // 2. Close Modals
        $('#oa-close-modal-btn, #oa-close-success-btn, #oa-done-btn').on('click', function(e) {
            e.preventDefault();
            $modal.fadeOut(150);
            $successModal.fadeOut(150);
        });

        $(document).on('click', function(e) {
            if ($(e.target).is($modal)) {
                $modal.fadeOut(150);
            }
            if ($(e.target).is($successModal)) {
                $successModal.fadeOut(150);
            }
        });

        // 3. Interactive Star Picker (1 to 5 stars)
        var ratingLabels = {
            1: '1 Star (Poor)',
            2: '2 Stars (Fair)',
            3: '3 Stars (Good)',
            4: '4 Stars (Very Good)',
            5: '5 Stars (Excellent)'
        };

        $('.oa-star-choice').on('click', function() {
            var selectedVal = parseInt($(this).data('value'), 10);
            $('#oa_stars_input').val(selectedVal);
            $('#oa-star-label').text(ratingLabels[selectedVal] || selectedVal + ' Stars');

            $('.oa-star-choice').each(function() {
                var starVal = parseInt($(this).data('value'), 10);
                if (starVal <= selectedVal) {
                    $(this).addClass('active');
                } else {
                    $(this).removeClass('active');
                }
            });
        });

        // 4. Form Submission with Green Tick Success Modal
        $form.on('submit', function(e) {
            e.preventDefault();

            var originalBtnText = $submitBtn.html();
            $submitBtn.prop('disabled', true).html('<span>Submitting Review...</span>');

            var formData = {
                action: 'oa_submit_review',
                nonce: (typeof oaReviewsData !== 'undefined') ? oaReviewsData.nonce : '',
                student_name: $('#oa_student_name').val(),
                course: $('#oa_course').val(),
                review_number: $('#oa_review_number').val(),
                stars: $('#oa_stars_input').val(),
                payment_receipt_id: $('#oa_payment_receipt_id').val(),
                review_text: $('#oa_review_text').val()
            };

            var ajaxUrl = (typeof oaReviewsData !== 'undefined') ? oaReviewsData.ajax_url : 'admin-ajax.php';

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: formData,
                dataType: 'json',
                complete: function() {
                    // Per user requirement:
                    // "jb entries enter kr k submit pe click kre tu ye show ho k after verify your payment by ID your review will be display here and green tick lg jae bs beshk review add ho ya na ho isko bs ye show ho"
                    $submitBtn.prop('disabled', false).html(originalBtnText);
                    $form[0].reset();
                    // Reset stars back to 5
                    $('.oa-star-choice').addClass('active');
                    $('#oa_stars_input').val(5);
                    $('#oa-star-label').text('5 Stars (Excellent)');

                    // Close review modal & open green tick confirmation modal
                    $modal.fadeOut(150, function() {
                        $successModal.fadeIn(200);
                    });
                }
            });
        });

        // 5. Course Filter Tabs
        $('.oa-filter-btn').on('click', function() {
            var targetCourse = $(this).data('course');

            $('.oa-filter-btn').removeClass('active');
            $(this).addClass('active');

            if (targetCourse === 'all') {
                $('.oa-review-card').fadeIn(200);
            } else {
                $('.oa-review-card').each(function() {
                    var cardCourse = $(this).data('course');
                    if (cardCourse === targetCourse) {
                        $(this).fadeIn(200);
                    } else {
                        $(this).fadeOut(150);
                    }
                });
            }
        });
    });

})(jQuery);
