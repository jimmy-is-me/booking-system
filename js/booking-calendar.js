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
                events: bookingCalendarData.bookings,
                eventClick: function(info) {
                    info.jsEvent.preventDefault();
                    if (info.event.url) {
                        window.open(info.event.url, '_blank');
                    }
                }
            });
            
            calendar.render();
        }
    }
});
