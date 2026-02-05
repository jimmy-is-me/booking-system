jQuery(document).ready(function($) {
    var selectedDate = null;
    var selectedTime = null;
    var currentDuration = $('#booking_duration').val();
    
    // 初始化日曆
    function initCalendar() {
        var today = new Date();
        var currentMonth = today.getMonth();
        var currentYear = today.getFullYear();
        
        renderCalendar(currentYear, currentMonth);
    }
    
    // 渲染日曆
    function renderCalendar(year, month) {
        var firstDay = new Date(year, month, 1);
        var lastDay = new Date(year, month + 1, 0);
        var prevLastDay = new Date(year, month, 0);
        
        var firstDayOfWeek = firstDay.getDay();
        var daysInMonth = lastDay.getDate();
        var prevDaysInMonth = prevLastDay.getDate();
        
        var calendar = '<div class="calendar-widget">';
        
        // 月份導航
        calendar += '<div class="calendar-header">';
        calendar += '<button type="button" class="calendar-nav-btn" data-action="prev">&lt;</button>';
        calendar += '<span class="calendar-month-year">' + year + '年' + (month + 1) + '月</span>';
        calendar += '<button type="button" class="calendar-nav-btn" data-action="next">&gt;</button>';
        calendar += '</div>';
        
        // 星期標題
        calendar += '<div class="calendar-weekdays">';
        var weekdays = ['日', '一', '二', '三', '四', '五', '六'];
        weekdays.forEach(function(day) {
            calendar += '<div class="calendar-weekday">' + day + '</div>';
        });
        calendar += '</div>';
        
        // 日期格子
        calendar += '<div class="calendar-days">';
        
        // 上個月的日期
        for (var i = firstDayOfWeek; i > 0; i--) {
            calendar += '<div class="calendar-day other-month">' + (prevDaysInMonth - i + 1) + '</div>';
        }
        
        // 本月日期
        var today = new Date();
        today.setHours(0, 0, 0, 0);
        
        for (var day = 1; day <= daysInMonth; day++) {
            var currentDate = new Date(year, month, day);
            currentDate.setHours(0, 0, 0, 0);
            
            var dateString = formatDate(currentDate);
            var dayOfWeek = currentDate.getDay() === 0 ? 7 : currentDate.getDay();
            
            var isToday = (currentDate.getTime() === today.getTime());
            var isSelected = (dateString === selectedDate);
            var isPast = currentDate < today;
            var isBlocked = bookingAjax.blockedDates.indexOf(dateString) !== -1;
            var isAvailable = bookingAjax.availableDays.indexOf(dayOfWeek.toString()) !== -1;
            
            var classes = ['calendar-day'];
            if (isToday) classes.push('today');
            if (isSelected) classes.push('selected');
            if (isPast || isBlocked || !isAvailable) classes.push('disabled');
            
            var dataAttr = '';
            if (!isPast && !isBlocked && isAvailable) {
                dataAttr = ' data-date="' + dateString + '"';
            }
            
            calendar += '<div class="' + classes.join(' ') + '"' + dataAttr + '>' + day + '</div>';
        }
        
        // 下個月的日期
        var remainingDays = 42 - (firstDayOfWeek + daysInMonth);
        for (var i = 1; i <= remainingDays; i++) {
            calendar += '<div class="calendar-day other-month">' + i + '</div>';
        }
        
        calendar += '</div></div>';
        
        $('#booking-calendar-picker').html(calendar);
        $('#booking-calendar-picker').data('current-year', year);
        $('#booking-calendar-picker').data('current-month', month);
    }
    
    // 格式化日期為 YYYY-MM-DD
    function formatDate(date) {
        var year = date.getFullYear();
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var day = String(date.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    }
    
    // 格式化顯示日期
    function formatDisplayDate(dateString) {
        var parts = dateString.split('-');
        var date = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
        var weekdays = ['星期日', '星期一', '星期二', '星期三', '星期四', '星期五', '星期六'];
        var year = date.getFullYear();
        var month = date.getMonth() + 1;
        var day = date.getDate();
        var weekday = weekdays[date.getDay()];
        
        return year + '年' + month + '月' + day + '日 ' + weekday;
    }
    
    // 日曆導航
    $(document).on('click', '.calendar-nav-btn', function(e) {
        e.preventDefault();
        var action = $(this).data('action');
        var currentYear = $('#booking-calendar-picker').data('current-year');
        var currentMonth = $('#booking-calendar-picker').data('current-month');
        
        if (action === 'prev') {
            if (currentMonth === 0) {
                currentMonth = 11;
                currentYear--;
            } else {
                currentMonth--;
            }
        } else {
            if (currentMonth === 11) {
                currentMonth = 0;
                currentYear++;
            } else {
                currentMonth++;
            }
        }
        
        renderCalendar(currentYear, currentMonth);
    });
    
    // 點擊日期
    $(document).on('click', '.calendar-day:not(.disabled):not(.other-month)', function(e) {
        e.preventDefault();
        var dateString = $(this).data('date');
        if (!dateString) return;
        
        selectedDate = dateString;
        selectedTime = null;
        
        $('.calendar-day').removeClass('selected');
        $(this).addClass('selected');
        
        $('#booking_date').val(dateString);
        $('#selected-date-display').text(formatDisplayDate(dateString));
        $('#error_date').text('').hide();
        
        loadTimeSlots();
    });
    
    // 載入時段
    function loadTimeSlots() {
        if (!selectedDate || !currentDuration) {
            return;
        }
        
        $('#time-slots-container').show();
        $('#time-slots').html('<div class="loading">載入時段中...</div>');
        
        $.ajax({
            url: bookingAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_available_times',
                nonce: bookingAjax.nonce,
                date: selectedDate,
                duration: currentDuration
            },
            success: function(response) {
                var timeSlotsDiv = $('#time-slots');
                timeSlotsDiv.empty();
                
                if (response.times && response.times.length > 0) {
                    response.times.forEach(function(time) {
                        var btn = $('<button type="button" class="time-slot-btn" data-time="' + time + '">' + time + '</button>');
                        timeSlotsDiv.append(btn);
                    });
                } else {
                    timeSlotsDiv.html('<div class="no-slots">此日期無可用時段</div>');
                }
            },
            error: function() {
                $('#time-slots').html('<div class="error">載入失敗，請重試</div>');
            }
        });
    }
    
    // 點擊時段
    $(document).on('click', '.time-slot-btn', function(e) {
        e.preventDefault();
        selectedTime = $(this).data('time');
        
        $('.time-slot-btn').removeClass('selected');
        $(this).addClass('selected');
        
        $('#booking_time').val(selectedTime);
        $('#error_time').text('').hide();
    });
    
    // 時長變更
    $('#booking_duration').on('change', function() {
        currentDuration = $(this).val();
        if (selectedDate) {
            selectedTime = null;
            $('#booking_time').val('');
            $('.time-slot-btn').removeClass('selected');
            loadTimeSlots();
        }
    });
    
    // 表單驗證
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
    
    // 提交表單
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
        
        if (!selectedDate) {
            $('#error_date').text('請選擇預約日期').show();
            isValid = false;
        }
        
        if (!selectedTime) {
            $('#error_time').text('請選擇預約時間').show();
            isValid = false;
        }
        
        if (!isValid) {
            $('#booking-response').html('<div class="error-message">請修正標示的錯誤欄位</div>');
            var firstError = $('.error-message:visible:first');
            if (firstError.length) {
                $('html, body').animate({
                    scrollTop: firstError.offset().top - 100
                }, 500);
            }
            return;
        }
        
        var formData = {
            action: 'submit_booking',
            nonce: bookingAjax.nonce,
            name: $('#booking_name').val(),
            email: $('#booking_email').val(),
            phone: $('#booking_phone').val(),
            date: selectedDate,
            time: selectedTime,
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
                    
                    // 重置表單
                    $('#booking_name').val('');
                    $('#booking_email').val('');
                    $('#booking_phone').val('');
                    $('#booking_note').val('');
                    selectedDate = null;
                    selectedTime = null;
                    $('#booking_date').val('');
                    $('#booking_time').val('');
                    $('#time-slots-container').hide();
                    $('#selected-date-display').text('');
                    $('.calendar-day').removeClass('selected');
                    $('.time-slot-btn').removeClass('selected');
                    
                    // 重新渲染日曆
                    var today = new Date();
                    renderCalendar(today.getFullYear(), today.getMonth());
                    
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
                $('#booking-response').html('<div class="error-message">發生錯誤，請稍後再試</div>');
                $('.submit-booking-btn').prop('disabled', false).text('送出預約');
            }
        });
    });
    
    // 初始化日曆
    if ($('#booking-calendar-picker').length > 0) {
        initCalendar();
    }
});
