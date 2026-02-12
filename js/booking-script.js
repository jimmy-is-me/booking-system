jQuery(document).ready(function($) {
    var captchaVerified = false;
    
    // 設定日期選擇器的可用日期
    var availableDays = bookingAjax.availableDays; // ['1', '2', '3', '4', '5'] 代表週一到週五
    var blockedDates = bookingAjax.blockedDates; // ['2026-02-15', '2026-02-20']
    
    // 當選擇日期時檢查是否可預約
    $('#booking_date').on('change', function() {
        var selectedDate = $(this).val();
        
        if (!selectedDate) {
            $('#duration-group').hide();
            $('#time-group').hide();
            $('#booking_duration').prop('disabled', true);
            $('#booking_time').prop('disabled', true);
            return;
        }
        
        // 檢查是否為可預約的星期
        var date = new Date(selectedDate);
        var dayOfWeek = date.getDay(); // 0=週日, 1=週一, ..., 6=週六
        var dayNumber = dayOfWeek === 0 ? '7' : dayOfWeek.toString(); // 轉換成 1-7
        
        if (!availableDays.includes(dayNumber)) {
            $('#error_date').text('此日期不開放預約(非營業日)').css('color', '#d63638').show();
            $(this).addClass('error');
            $('#duration-group').hide();
            $('#time-group').hide();
            return;
        }
        
        // 檢查是否為封鎖日期
        if (blockedDates.includes(selectedDate)) {
            $('#error_date').text('此日期已被封鎖,不開放預約').css('color', '#d63638').show();
            $(this).addClass('error');
            $('#duration-group').hide();
            $('#time-group').hide();
            return;
        }
        
        // 日期有效,顯示時長選擇
        $('#error_date').text('').hide();
        $(this).removeClass('error');
        $('#duration-group').slideDown();
        $('#booking_duration').prop('disabled', false);
        
        console.log('選擇日期:', selectedDate, '星期:', dayNumber);
        
        // 如果已經選擇時長,直接載入時間
        var duration = $('#booking_duration').val();
        if (duration) {
            loadAvailableTimes(selectedDate, duration);
        }
    });
    
    // 當選擇時長時載入可用時間
    $('#booking_duration').on('change', function() {
        var date = $('#booking_date').val();
        var duration = $(this).val();
        
        if (!date || !duration) {
            $('#time-group').hide();
            return;
        }
        
        loadAvailableTimes(date, duration);
    });
    
    function loadAvailableTimes(date, duration) {
        $('#booking_time').prop('disabled', true).html('<option value="">載入中...</option>');
        $('#time-group').slideDown();
        
        console.log('開始載入時間 - 日期:', date, '時長:', duration);
        
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
                console.log('載入時間成功,原始回應:', response);
                
                var timeSelect = $('#booking_time');
                
                if (response.success === false) {
                    timeSelect.html('<option value="">載入失敗: ' + (response.data ? response.data.message : '未知錯誤') + '</option>');
                    console.error('載入時段失敗:', response);
                    return;
                }
                
                timeSelect.html('<option value="">請選擇時間</option>');
                
                if (response.times && response.times.length > 0) {
                    $.each(response.times, function(index, timeObj) {
                        timeSelect.append('<option value="' + timeObj.value + '">' + timeObj.display + '</option>');
                    });
                    timeSelect.prop('disabled', false);
                    console.log('成功載入 ' + response.times.length + ' 個可預約時段');
                } else {
                    timeSelect.html('<option value="">此日期無可用時段</option>');
                    console.log('此日期沒有可預約時段');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX 錯誤:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    error: error,
                    response: xhr.responseText
                });
                
                var errorMsg = '載入失敗';
                if (xhr.status === 403) {
                    errorMsg = '安全驗證失敗,請重新整理頁面';
                } else if (xhr.status === 500) {
                    errorMsg = '伺服器錯誤,請稍後再試';
                } else if (xhr.status === 0) {
                    errorMsg = '網路連線失敗';
                }
                
                $('#booking_time').html('<option value="">' + errorMsg + '</option>');
            }
        });
    }
    
    // 驗證碼驗證
    $('#captcha_answer').on('blur', function() {
        var answer = $(this).val();
        
        if (!answer) {
            return;
        }
        
        $.ajax({
            url: bookingAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'verify_captcha',
                nonce: bookingAjax.nonce,
                answer: answer
            },
            success: function(response) {
                if (response.success) {
                    $('#error_captcha').text('✓ 驗證成功').css('color', '#4caf50').show();
                    $('#captcha_answer').removeClass('error').css('border-color', '#4caf50');
                    captchaVerified = true;
                } else {
                    $('#error_captcha').text('✗ 驗證碼錯誤').css('color', '#d63638').show();
                    $('#captcha_answer').addClass('error');
                    captchaVerified = false;
                }
            }
        });
    });
    
    function validateField(field, errorId, validationFunc, errorMessage) {
        var value = field.val().trim();
        var errorElement = $('#' + errorId);
        
        if (!validationFunc(value)) {
            errorElement.text(errorMessage).css('color', '#d63638').show();
            field.addClass('error');
            return false;
        } else {
            errorElement.text('').hide();
            field.removeClass('error');
            return true;
        }
    }
    
    function isValidEmail(email) {
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    
    function isValidPhone(phone) {
        return phone.length >= 8;
    }
    
    // 表單提交
    $('#booking-form').on('submit', function(e) {
        e.preventDefault();
        
        $('.error-message').text('').hide();
        $('.form-group input, .form-group select').removeClass('error');
        
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
        
        // 驗證驗證碼
        if (!captchaVerified) {
            $('#error_captcha').text(bookingAjax.messages.captcha_required).css('color', '#d63638').show();
            $('#captcha_answer').addClass('error');
            isValid = false;
        }
        
        if (!isValid) {
            $('#booking-response').html('<div class="error-message">請修正標示的錯誤欄位</div>');
            $('html, body').animate({
                scrollTop: $('.error-message:visible:first').offset().top - 100
            }, 300);
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
        
        console.log('提交表單資料:', formData);
        
        $.ajax({
            url: bookingAjax.ajaxurl,
            type: 'POST',
            data: formData,
            beforeSend: function() {
                $('.submit-booking-btn').prop('disabled', true).text('送出中...');
                $('#booking-response').html('');
            },
            success: function(response) {
                console.log('提交表單回應:', response);
                
                var responseDiv = $('#booking-response');
                if (response.success) {
                    responseDiv.html('<div class="success-message">' + response.data.message + '</div>');
                    $('#booking-form')[0].reset();
                    captchaVerified = false;
                    
                    // 重置表單顯示狀態
                    $('#duration-group, #time-group').hide();
                    $('#booking_duration, #booking_time').prop('disabled', true);
                    
                    $('html, body').animate({
                        scrollTop: responseDiv.offset().top - 100
                    }, 500);
                } else {
                    if (response.data && response.data.errors) {
                        $.each(response.data.errors, function(field, message) {
                            $('#error_' + field).text(message).css('color', '#d63638').show();
                            $('#booking_' + field).addClass('error');
                        });
                        responseDiv.html('<div class="error-message">' + (response.data.message || '預約失敗,請檢查輸入內容') + '</div>');
                    } else {
                        responseDiv.html('<div class="error-message">' + (response.data ? response.data.message : '預約失敗,請稍後再試') + '</div>');
                    }
                    
                    $('html, body').animate({
                        scrollTop: responseDiv.offset().top - 100
                    }, 300);
                }
                $('.submit-booking-btn').prop('disabled', false).text('送出預約');
            },
            error: function(xhr, status, error) {
                console.error('提交表單時發生錯誤:', {
                    status: xhr.status,
                    error: error,
                    response: xhr.responseText
                });
                $('#booking-response').html('<div class="error-message">發生錯誤,請稍後再試</div>');
                $('.submit-booking-btn').prop('disabled', false).text('送出預約');
            }
        });
    });
});
