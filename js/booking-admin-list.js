jQuery(document).ready(function($) {
    // 快速更新預約狀態
    $(document).on('change', '.booking-quick-status', function() {
        var select = $(this);
        var bookingId = select.data('booking-id');
        var newStatus = select.val();
        
        $.ajax({
            url: bookingAdminData.ajaxurl,
            type: 'POST',
            data: {
                action: 'quick_update_status',
                nonce: bookingAdminData.nonce,
                booking_id: bookingId,
                status: newStatus
            },
            beforeSend: function() {
                select.prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    // 更新選單顏色
                    var colors = {
                        'pending_booking': '#ff9800',
                        'confirmed': '#4caf50',
                        'cancelled': '#f44336',
                        'completed': '#2196f3'
                    };
                    
                    select.css({
                        'border-color': colors[newStatus],
                        'background': colors[newStatus]
                    });
                    
                    // 顯示成功提示
                    var notice = $('<div class="notice notice-success is-dismissible" style="position: fixed; top: 32px; right: 20px; z-index: 9999;"><p>狀態已更新</p></div>');
                    $('body').append(notice);
                    setTimeout(function() {
                        notice.fadeOut(function() {
                            $(this).remove();
                        });
                    }, 2000);
                } else {
                    alert('更新失敗：' + response.data.message);
                    location.reload();
                }
            },
            error: function() {
                alert('發生錯誤，請重新整理頁面');
                location.reload();
            },
            complete: function() {
                select.prop('disabled', false);
            }
        });
    });
});
