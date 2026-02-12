jQuery(document).ready(function($) {
    // 新增封鎖日期
    $('#add_blocked_date_btn').on('click', function() {
        var startDate = $('#new_blocked_start_date').val();
        var endDate = $('#new_blocked_end_date').val();
        var note = $('#new_blocked_note').val();
        
        if (!startDate || !endDate) {
            alert('請選擇開始和結束日期');
            return;
        }
        
        $.ajax({
            url: bookingAdminData.ajaxurl,
            type: 'POST',
            data: {
                action: 'add_blocked_date',
                nonce: bookingAdminData.nonce,
                start_date: startDate,
                end_date: endDate,
                note: note
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    
                    // 清空輸入框
                    $('#new_blocked_start_date').val('');
                    $('#new_blocked_end_date').val('');
                    $('#new_blocked_note').val('');
                    
                    // 如果列表是空的,先移除空訊息行
                    if ($('#blocked-dates-list tr').length === 1 && $('#blocked-dates-list tr td').attr('colspan')) {
                        $('#blocked-dates-list').empty();
                    }
                    
                    // 新增到列表
                    var newRow = '<tr data-id="' + response.data.data.id + '">' +
                        '<td><strong>' + response.data.data.start_date + '</strong></td>' +
                        '<td><strong>' + response.data.data.end_date + '</strong></td>' +
                        '<td>' + (response.data.data.note || '<span style="color: #999;">-</span>') + '</td>' +
                        '<td>' + response.data.data.created_at + '</td>' +
                        '<td><button type="button" class="button button-small remove-blocked-date" data-id="' + response.data.data.id + '" style="color: #b32d2e;">刪除</button></td>' +
                        '</tr>';
                    
                    $('#blocked-dates-list').prepend(newRow);
                } else {
                    alert(response.data.message);
                }
            },
            error: function() {
                alert('操作失敗,請稍後再試');
            }
        });
    });
    
    // 刪除封鎖日期
    $(document).on('click', '.remove-blocked-date', function() {
        if (!confirm('確定要刪除此封鎖日期嗎？')) {
            return;
        }
        
        var id = $(this).data('id');
        var row = $(this).closest('tr');
        
        $.ajax({
            url: bookingAdminData.ajaxurl,
            type: 'POST',
            data: {
                action: 'remove_blocked_date',
                nonce: bookingAdminData.nonce,
                id: id
            },
            success: function(response) {
                if (response.success) {
                    row.fadeOut(300, function() {
                        $(this).remove();
                        
                        // 如果列表空了,顯示空訊息
                        if ($('#blocked-dates-list tr').length === 0) {
                            $('#blocked-dates-list').html('<tr><td colspan="5" style="text-align: center; padding: 30px;">目前沒有封鎖日期</td></tr>');
                        }
                    });
                } else {
                    alert(response.data.message);
                }
            },
            error: function() {
                alert('刪除失敗,請稍後再試');
            }
        });
    });
});
