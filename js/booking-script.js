jQuery(document).ready(function($) {
    // 監聽日期和時間變更,檢查可用性
    $('#booking_date, #booking_time, #booking_duration').on('change', function() {
        var date = $('#booking_date').val();
        var time = $('#booking_time').val();
        var duration = $('#booking_duration').val();
        
        if (date && time) {
            $.ajax({
                url: bookingAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'check_availability',
                    nonce: bookingAjax.nonce,
                    date: date,
                    time: time,
                    duration: duration
                },
                success: function(response) {
                    var messageDiv = $('#availability-message');
                    if (response.available) {
                        messageDiv.html('<span style="color: green;">✓ ' + response.message + '</span>');
                        $('.submit-booking-btn').prop('disabled', false);
                    } else {
                        messageDiv.html('<span style="color: red;">✗ ' + response.message + '</span>');
                        $('.submit-booking-btn').prop('disabled', true);
                    }
                }
            });
        }
    });
    
    // 提交預約表單
    $('#booking-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = {
            action: 'submit_booking',
            nonce: bookingAjax.nonce,
            name: $('#booking_name').val(),
            email: $('#booking_email').val(),
            phone: $('#booking_phone').val(),
            date: $('#booking_date').val(),
            time: $('#booking_time').val(),
            duration: $('#booking_duration').val(),
            note: $('#booking_note').val()
        };
        
        $.ajax({
            url: bookingAjax.ajaxurl,
            type: 'POST',
            data: formData,
            beforeSend: function() {
                $('.submit-booking-btn').prop('disabled', true).text('送出中...');
            },
            success: function(response) {
                var responseDiv = $('#booking-response');
                if (response.success) {
                    responseDiv.html('<div class="success-message">' + response.data.message + '</div>');
                    $('#booking-form')[0].reset();
                    $('#availability-message').html('');
                } else {
                    responseDiv.html('<div class="error-message">' + response.data.message + '</div>');
                }
                $('.submit-booking-btn').prop('disabled', false).text('送出預約');
            },
            error: function() {
                $('#booking-response').html('<div class="error-message">發生錯誤,請稍後再試</div>');
                $('.submit-booking-btn').prop('disabled', false).text('送出預約');
            }
        });
    });
});
