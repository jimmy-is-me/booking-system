jQuery(document).ready(function($) {
    var currentDate = '';
    var currentDuration = '';
    
    // 表單驗證函數
    function validateField(field, errorId, validationFunc, errorMessage) {
        var value = field.val().trim();
        var errorElement = $('#' + errorId);
        
        if (!validationFunc(value)) {
            errorElement.text(errorMessage).show();
            field.addClass('error');
            return false;
        } else {
            errorElement.text('').hide();
            field.removeClass('error');
            return true;
        }
    }
    
    // 驗證Email
    function isValidEmail(email) {
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    
    // 驗證電話
    function isValidPhone(phone) {
        return phone.length >= 8;
    }
    
    // 日期選擇變更時檢查
    $('#booking_date').on('change', function() {
        var selectedDate = new Date($(this).val());
        var dayOfWeek = selectedDate.getDay();
        var dayNumber = dayOfWeek === 0 ? 7 : dayOfWeek;
        var dateString = $(this).val();
        
        // 檢查星期
        if (bookingAjax.availableDays.indexOf(dayNumber.toString()) === -1) {
            $('#error_date').text('此星期不開放預約，請選擇其他日期').show();
            $(this).addClass('error');
            $(this).val('');
            $('#booking_time').prop('disabled', true).html('<option value="">請先選擇日期和時長</option>');
            return;
        }
        
        // 檢查封鎖日期
        if (bookingAjax.blockedDates.indexOf(dateString) !== -1) {
            $('#error_date').text('此日期不開放預約，請選擇其他日期').show();
            $(this).addClass('error');
            $(this).val('');
            $('#booking_time').prop('disabled', true).html('<option value="">請先選擇日期和時長</option>');
            return;
        }
        
        $('#error_date').text('').hide();
        $(this).removeClass('error');
        
        // 載入時段
        loadAvailableTimes();
    });
    
    // 載入可用時段
    function loadAvailableTimes() {
        var date = $('#booking_date').val();
        var duration = $('#booking_duration').val();
        
        if (!date || !duration) {
            return;
        }
        
        currentDate = date;
        currentDuration = duration;
        
        $('#booking_time').prop('disabled', true).html('<option value="">載入中...</option>');
        
        $.ajax({
            url: bookingAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_available_times',
                nonce: bookingAjax.nonce,
                date: date,
                duration: duration
            },
            success: function(response) {
                var timeSelect = $('#booking_time');
                timeSelect.html('<option value="">請選擇時間</option>');
                
                if (response.times && response.times.length > 0) {
                    $.each(response.times, function(index, time) {
                        timeSelect.append('<option value="' + time + '">' + time + '</option>');
                    });
                    timeSelect.prop('disabled', false);
                } else {
                    timeSelect.html('<option value="">此日期無可用時段</option>');
                }
            },
            error: function() {
                $('#booking_time').html('<option value="">載入失敗</option>');
            }
        });
    }
    
    // 監聽時長變更
    $('#booking_duration').on('change', function() {
        if ($('#booking_date').val()) {
            loadAvailableTimes();
        }
    });
    
    // 提交預約表單
    $('#booking-form').on('submit', function(e) {
        e.preventDefault();
        
        // 清除之前的錯誤訊息
        $('.error-message').text('').hide();
        $('.form-group input, .form-group select').removeClass('error');
        
        // 驗證所有必填欄位
        var isValid = true;
        
        isValid = validateField($('#booking_name'), 'error_name', function(val) {
            return val.length > 0;
        }, bookingAjax.messages.required) && isValid;
        
        isValid = validateField($('#booking_email'), 'error_email', isValidEmail, bookingAjax.messages.invalid_email) && isValid;
        
        isValid = validateField($('#booking_phone'), 'error_phone', isValidPhone, bookingAjax.messages.invalid_phone) && isValid;
        
        isValid = validateField($('#booking_date'), 'error_date', function(val) {
            return val.length > 0;
        }, bookingAjax.messages.required) && isValid;
        
        isValid = validateField($('#booking_time'), 'error_time', function(val) {
            return val.length > 0;
        }, bookingAjax.messages.select_time) && isValid;
        
        if (!isValid) {
            $('#booking-response').html('<div class="error-message">請修正標示的錯誤欄位</div>');
            return;
        }
        
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
                $('#booking-response').html('');
            },
            success: function(response) {
                var responseDiv = $('#booking-response');
                if (response.success) {
                    responseDiv.html('<div class="success-message">' + response.data.message + '</div>');
                    $('#booking-form')[0].reset();
                    $('#booking_time').prop('disabled', true).html('<option value="">請先選擇日期和時長</option>');
                    
                    // 滾動到成功訊息
                    $('html, body').animate({
                        scrollTop: responseDiv.offset().top - 100
                    }, 500);
                } else {
                    if (response.data.errors) {
                        // 顯示各欄位錯誤
                        $.each(response.data.errors, function(field, message) {
                            $('#error_' + field).text(message).show();
                            $('#booking_' + field).addClass('error');
                        });
                        responseDiv.html('<div class="error-message">' + response.data.message + '</div>');
                    } else {
                        responseDiv.html('<div class="error-message">' + response.data.message + '</div>');
                    }
                }
                $('.submit-booking-btn').prop('disabled', false).text('送出預約');
            },
            error: function() {
                $('#booking-response').html('<div class="error-message">發生錯誤，請稍後再試</div>');
                $('.submit-booking-btn').prop('disabled', false).text('送出預約');
            }
        });
    });
});
