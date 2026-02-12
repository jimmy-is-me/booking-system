jQuery(document).ready(function($) {
    var captchaVerified = false;
    
    // 當選擇年份時,載入該年度的可用月份
    $('#booking_year').on('change', function() {
        var year = $(this).val();
        
        if (!year) {
            $('#month-group').hide();
            $('#date-group').hide();
            $('#duration-group').hide();
            $('#time-group').hide();
            $('#booking_month').prop('disabled', true).html('<option value="">請先選擇年份</option>');
            return;
        }
        
        loadAvailableMonths(year);
    });
    
    function loadAvailableMonths(year) {
        var currentYear = new Date().getFullYear();
        var currentMonth = new Date().getMonth() + 1;
        
        var monthSelect = $('#booking_month');
        monthSelect.html('<option value="">請選擇月份</option>');
        
        var startMonth = (year == currentYear) ? currentMonth : 1;
        var endMonth = 12;
        
        var monthNames = ['一月', '二月', '三月', '四月', '五月', '六月', 
                         '七月', '八月', '九月', '十月', '十一月', '十二月'];
        
        for (var m = startMonth; m <= endMonth; m++) {
            var monthStr = m < 10 ? '0' + m : '' + m;
            monthSelect.append('<option value="' + monthStr + '">' + m + '月 (' + monthNames[m-1] + ')</option>');
        }
        
        monthSelect.prop('disabled', false);
        $('#month-group').slideDown();
        
        // 重置後續選項
        $('#date-group').hide();
        $('#duration-group').hide();
        $('#time-group').hide();
        $('#booking_date').prop('disabled', true).html('<option value="">請先選擇月份</option>');
    }
    
    // 當選擇月份時,載入該月的可預約日期
    $('#booking_month').on('change', function() {
        var year = $('#booking_year').val();
        var month = $(this).val();
        
        if (!year || !month) {
            $('#date-group').hide();
            $('#duration-group').hide();
            $('#time-group').hide();
            return;
        }
        
        loadAvailableDates(year, month);
    });
    
    function loadAvailableDates(year, month) {
        $('#booking_date').prop('disabled', true).html('<option value="">載入中...</option>');
        
        $.ajax({
            url: bookingAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_available_dates',
                nonce: bookingAjax.nonce,
                year: year,
                month: month
            },
            success: function(response) {
                console.log('回應:', response);
                
                var dateSelect = $('#booking_date');
                dateSelect.html('<option value="">請選擇預約日期</option>');
                
                // 支援兩種回應格式
                var dates = response.data && response.data.dates ? response.data.dates : response.dates;
                
                if (dates && dates.length > 0) {
                    $.each(dates, function(index, dateObj) {
                        dateSelect.append('<option value="' + dateObj.date + '">' + dateObj.display + '</option>');
                    });
                    dateSelect.prop('disabled', false);
                    $('#date-group').slideDown();
                    $('#duration-group').slideDown();
                    $('#booking_duration').prop('disabled', false);
                } else {
                    dateSelect.html('<option value="">此月份無可預約日期</option>');
                    $('#date-group').slideDown();
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX 錯誤:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    error: error,
                    response: xhr.responseText
                });
                
                $('#booking_date').html('<option value="">載入失敗,請重新整理</option>');
                $('#date-group').slideDown();
            }
        });
    }
    
    // 當選擇日期或時長時,載入可用時間
    $('#booking_date, #booking_duration').on('change', function() {
        loadAvailableTimes();
    });
    
    function loadAvailableTimes() {
        var date = $('#booking_date').val();
        var duration = $('#booking_duration').val();
        
        if (!date || !duration) {
            $('#time-group').hide();
            $('#booking_time').prop('disabled', true).html('<option value="">請先選擇日期和時長</option>');
            return;
        }
        
        $('#booking_time').prop('disabled', true).html('<option value="">載入中...</option>');
        $('#time-group').slideDown();
        
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
                } else {
                    timeSelect.html('<option value="">此日期無可用時段</option>');
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
        
        isValid = validateField($('#booking_year'), 'error_year', function(val) {
            return val.length > 0;
        }, bookingAjax.messages.required) && isValid;
        
        isValid = validateField($('#booking_month'), 'error_month', function(val) {
            return val.length > 0;
        }, bookingAjax.messages.required) && isValid;
        
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
                    captchaVerified = false;
                    
                    // 重置表單顯示狀態
                    $('#month-group, #date-group, #duration-group, #time-group').hide();
                    $('#booking_month, #booking_date, #booking_duration, #booking_time').prop('disabled', true);
                    
                    $('html, body').animate({
                        scrollTop: responseDiv.offset().top - 100
                    }, 500);
                } else {
                    if (response.data.errors) {
                        $.each(response.data.errors, function(field, message) {
                            $('#error_' + field).text(message).css('color', '#d63638').show();
                            $('#booking_' + field).addClass('error');
                        });
                        responseDiv.html('<div class="error-message">' + response.data.message + '</div>');
                    } else {
                        responseDiv.html('<div class="error-message">' + response.data.message + '</div>');
                    }
                    
                    $('html, body').animate({
                        scrollTop: responseDiv.offset().top - 100
                    }, 300);
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
