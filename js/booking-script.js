jQuery(document).ready(function($) {
    // 設定日期選擇器的可用日期
    var availableDays = bookingAjax.availableDays;
    var blockedDates = bookingAjax.blockedDates;
    
    console.log('可預約星期:', availableDays);
    console.log('封鎖日期:', blockedDates);
    
    // 禁用封鎖日期和非營業日的功能
    function disableBlockedDates() {
        var dateInput = document.getElementById('booking_date');
        
        if (!dateInput) return;
        
        dateInput.addEventListener('input', function(e) {
            var selectedDate = this.value;
            
            if (!selectedDate) return;
            
            if (blockedDates.includes(selectedDate)) {
                alert('此日期已被封鎖,無法預約\n請選擇其他日期');
                this.value = '';
                return;
            }
            
            var date = new Date(selectedDate);
            var dayOfWeek = date.getDay();
            var dayNumber = dayOfWeek === 0 ? '7' : dayOfWeek.toString();
            
            if (!availableDays.includes(dayNumber)) {
                alert('此日期為非營業日,無法預約\n請選擇其他日期');
                this.value = '';
                return;
            }
        });
    }
    
    disableBlockedDates();
    
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
        
        var date = new Date(selectedDate);
        var dayOfWeek = date.getDay();
        var dayNumber = dayOfWeek === 0 ? '7' : dayOfWeek.toString();
        
        if (!availableDays.includes(dayNumber)) {
            $('#error_date').text('此日期不開放預約(非營業日)').css('color', '#d63638').show();
            $(this).addClass('error');
            $('#duration-group').hide();
            $('#time-group').hide();
            return;
        }
        
        if (blockedDates.includes(selectedDate)) {
            $('#error_date').text('此日期已被封鎖,不開放預約').css('color', '#d63638').show();
            $(this).addClass('error');
            $('#duration-group').hide();
            $('#time-group').hide();
            return;
        }
        
        $('#error_date').text('').hide();
        $(this).removeClass('error');
        $('#duration-group').slideDown();
        $('#booking_duration').prop('disabled', false);
        
        console.log('選擇日期:', selectedDate, '星期:', dayNumber);
        
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
    
    function validateField(field, errorId, validationFunc, errorMessage) {
        var value = field.val();
        
        // 處理 undefined 或 null
        if (value === undefined || value === null) {
            value = '';
        } else {
            value = value.trim();
        }
        
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
        
        console.log('開始提交表單');
        
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
        
        console.log('表單驗證結果:', isValid);
        
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
