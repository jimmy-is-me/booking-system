jQuery(document).ready(function($) {
    // 這是一個簡易的日曆顯示
    // 您可以後續整合 FullCalendar 或其他日曆插件
    
    $('#booking-calendar').html('<div style="padding: 40px; text-align: center; background: #f5f5f5; border-radius: 8px;"><h3>日曆功能</h3><p>此功能可整合 FullCalendar 或其他日曆插件來顯示預約日曆視圖</p><p>目前可以在「所有預約」頁面查看和管理預約</p></div>');
    
    // 關閉彈窗
    $('.booking-modal-close, #booking-modal-close-btn').on('click', function() {
        $('#booking-modal-overlay').hide();
    });
});
