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
                    // 更新顏色
                    var colors = {
                        'pending_booking': '#ff9800',
                        'confirmed': '#4caf50',
                        'cancelled': '#f44336',
                        'completed': '#2196f3'
                    };
                    
                    var icons = {
                        'pending_booking': '🟠',
                        'confirmed': '🟢',
                        'cancelled': '🔴',
                        'completed': '🔵'
                    };
                    
                    selectElement.css({
                        'border-color': colors[newStatus],
                        'color': colors[newStatus]
                    });
                    
                    selectElement.siblings('span').text(icons[newStatus]);
                    
                    // 顯示成功訊息
                    var messageDiv = $('<div class="notice notice-success is-dismissible" style="position: fixed; top: 32px; right: 20px; z-index: 9999; width: 300px;"><p>狀態已更新</p></div>');
                    $('body').append(messageDiv);
                    
                    setTimeout(function() {
                        messageDiv.fadeOut(300, function() {
                            $(this).remove();
                        });
                    }, 2000);
                } else {
                    alert('更新失敗: ' + response.data.message);
                }
            },
            error: function() {
                alert('更新失敗,請稍後再試');
            }
        });
    });
});
