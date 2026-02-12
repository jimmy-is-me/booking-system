jQuery(document).ready(function($) {
    // 快速更新狀態
    $('.booking-quick-status').on('change', function() {
        var bookingId = $(this).data('booking-id');
        var newStatus = $(this).val();
        var selectElement = $(this);
        
        $.ajax({
            url: bookingAdminData.ajaxurl,
            type: 'POST',
            data: {
                action: 'quick_update_status',
                nonce: bookingAdminData.nonce,
                booking_id: bookingId,
                status: newStatus
            },
            success: function(response) {
                if (response.success) {
                    // 更新樣式
                    var statusColors = {
                        'pending_booking': '#ff9800',
                        'confirmed': '#4caf50',
                        'cancelled': '#f44336',
                        'completed': '#2196f3'
                    };
                    
                    selectElement.css({
                        'border-color': statusColors[newStatus],
                        'color': statusColors[newStatus]
                    });
                    
                    // 顯示提示
                    var originalText = selectElement.parent().html();
                    selectElement.parent().append('<span class="status-updated" style="color: #4caf50; margin-left: 10px;">✓</span>');
                    
                    setTimeout(function() {
                        $('.status-updated').fadeOut(300, function() {
                            $(this).remove();
                        });
                    }, 2000);
                } else {
                    alert('更新失敗: ' + response.data.message);
                }
            }
        });
    });
});
