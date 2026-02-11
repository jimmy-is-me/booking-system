jQuery(document).ready(function($) {
    var currentDate = '';
    var currentDuration = '';
    
    // 添加: 設定日期選擇器禁用不可預約的星期
    function setupDatePicker() {
        var dateInput = $('#booking_date');
        
        // 監聽日期選擇器的輸入事件
        dateInput.on('input change', function() {
            validateSelectedDate($(this));
        });
        
        // 添加自訂屬性提示不可預約的星期
        if (bookingAjax.availableDays && bookingAjax.availableDays.length > 0) {
            var unavailableDays = [];
            var dayNames = ['日', '一', '二', '三', '四', '五', '六'];
            
            for (var i = 0; i <= 7; i++) {
                var dayNum = i === 0 ? 7 : i;
                if (bookingAjax.availableDays.indexOf(dayNum.toString()) === -1) {
                    unavailableDays.push(dayNames[i]);
                }
            }
            
            if (unavailableDays.length > 0) {
                var hint = '不可預約: 週' + unavailableDays.join('、週');
                dateInput.attr('title', hint);
                
                // 在日期欄位下方顯示提示訊息
                if ($('#date-availability-hint').length === 0) {
                    dateInput.after('<p id="date-availability-hint" style="color: #666; font-size: 13px; margin-top: 5px;">📅 可預約日期: 週' + getAvailableDayNames() + '</p>');
                }
            }
        }
    }
    
    // 添加: 取得可預約星期的名稱
    function getAvailableDayNames() {
        var dayNames = ['日', '一', '二', '三', '四', '五', '六'];
        var availableNames = [];
        
        if (bookingAjax.availableDays) {
            bookingAjax.availableDays.forEach(function(dayNum) {
                var index = dayNum == 7 ? 0 : parseInt(dayNum);
                availableNames.push(dayNames[index]);
            });
        }
        
        return availableNames.join('、週');
    }
    
    // 修改: 增強日期驗證
    function validateSelectedDate(dateInput) {
        var dateValue = dateInput.val();
        if (!dateValue) return false;
        
        var selectedDate = new Date(dateValue);
        var dayOfWeek = selectedDate.getDay();
        var dayNumber = dayOfWeek === 0 ? 7 : dayOfWeek;
        
        // 檢查星期是否可預約
        if (bookingAjax.availableDays.indexOf(dayNumber.toString()) === -1) {
            $('#error_date').text('此星期不開放預約，請選擇其他日期').show();
            dateInput.addClass('error');
            hideDurationAndTime();
            return false;
        }
        
        // 檢查是否為封鎖日期
        if (bookingAjax.blockedDates && bookingAjax.blockedDates.indexOf(dateValue) !== -1) {
            $('#error_date').text('此日期不開放預約，請選擇其他日期').show();
            dateInput.addClass('error');
            hideDurationAndTime();
            return false;
        }
        
        $('#error_date').text('').hide();
        dateInput.removeClass('error');
        return true;
    }
    
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
    
    function isValidEmail(email) {
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    
    function isValidPhone(phone) {
        return phone.length >= 8;
    }
    
    function hideDurationAndTime() {
        $('#duration-group').hide();
        $('#time-group').hide();
        $('#booking_time').prop('disabled', true).html('<option value="">請先選擇日期和時長</option>');
    }
    
    function showDurationAndTime() {
        $('#duration-group').show();
        $('#time-group').show();
    }
    
    $('#booking_date').on('change', function() {
        if (!validateSelectedDate($(this))) {
            return;
        }
        
        showDurationAndTime();
        loadAvailableTimes();
    });
    
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
                
                if (response.success === false) {
                    timeSelect.html('<option value="">載入失敗: ' + (response.data ? response.data.message : '未知錯誤') + '</option>');
                    console.error('載入時段失敗:', response);
                    return;
                }
                
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
            error: function(xhr, status, error) {
                console.error('AJAX 錯誤:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    error: error,
                    response: xhr.responseText
                });
                
                var errorMsg = '載入失敗';
                if (xhr.status === 403) {
                    errorMsg = '安全驗證失敗，請重新整理頁面';
                }
                
                $('#booking_time').html('<option value="">' + errorMsg + '</option>');
            }
        });
    }
    
    $('#booking_duration').on('change', function() {
        if ($('#booking_date').val()) {
            loadAvailableTimes();
        }
    });
    
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
                    hideDurationAndTime();
                    
                    $('html, body').animate({
                        scrollTop: responseDiv.offset().top - 100
                    }, 500);
                } else {
                    if (response.data.errors) {
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
                $('#booking-response').html('<div class="error-message">發生錯誤,請稍後再試</div>');
                $('.submit-booking-btn').prop('disabled', false).text('送出預約');
            }
        });
    });
    
    // 初始化
    hideDurationAndTime();
    setupDatePicker();
});
