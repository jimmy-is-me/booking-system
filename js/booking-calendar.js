jQuery(document).ready(function($) {
    if (typeof FullCalendar !== 'undefined') {
        var calendarEl = document.getElementById('booking-calendar');
        
        if (calendarEl) {
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'zh-tw',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                buttonText: {
                    today: '今天',
                    month: '月',
                    week: '週',
                    day: '日'
                },
                events: bookingCalendarData.bookings,
                eventClick: function(info) {
                    info.jsEvent.preventDefault();
                    
                    var bookingId = info.event.extendedProps.bookingId;
                    
                    // 顯示彈出視窗
                    showBookingModal(bookingId);
                },
                eventTimeFormat: {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                }
            });
            
            calendar.render();
        }
    }
    
    // 顯示預約詳情彈出視窗
    function showBookingModal(bookingId) {
        var modal = $('#booking-modal');
        var modalBody = $('#booking-modal-body');
        
        modalBody.html('<p>載入中...</p>');
        modal.show();
        
        $.ajax({
            url: bookingCalendarData.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_booking_details',
                nonce: bookingCalendarData.nonce,
                booking_id: bookingId
            },
            success: function(response) {
                if (response.success) {
                    modalBody.html(response.data.html);
                    $('#booking-edit-link').attr('href', response.data.edit_url);
                } else {
                    modalBody.html('<p class="error">' + response.data.message + '</p>');
                }
            },
            error: function() {
                modalBody.html('<p class="error">載入失敗</p>');
            }
        });
    }
    
    // 關閉彈出視窗
    $('.booking-modal-close, #booking-modal-close-btn').on('click', function() {
        $('#booking-modal').hide();
    });
    
    // 點擊背景關閉
    $('#booking-modal').on('click', function(e) {
        if (e.target === this) {
            $(this).hide();
        }
    });
});
