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
                    var data = response.data.data;
                    var noteDisplay = data.note ? data.note : '<span style="color: #999;">-</span>';
                    
                    var row = '<tr data-id="' + data.id + '">' +
                        '<td><strong>' + data.start_date + '</strong></td>' +
                        '<td><strong>' + data.end_date + '</strong></td>' +
                        '<td>' + noteDisplay + '</td>' +
                        '<td>' + data.created_at + '</td>' +
                        '<td><button type="button" class="button button-small remove-blocked-date" data-id="' + data.id + '" style="color: #b32d2e;">刪除</button></td>' +
                        '</tr>';
                    
                    if ($('#blocked-dates-list tr td[colspan="5"]').length > 0) {
                        $('#blocked-dates-list').html(row);
                    } else {
                        $('#blocked-dates-list').prepend(row);
                    }
                    
                    $('#new_blocked_start_date').val('');
                    $('#new_blocked_end_date').val('');
                    $('#new_blocked_note').val('');
                    
                    alert('封鎖日期已新增');
                } else {
                    alert(response.data.message);
                }
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
                        
                        if ($('#blocked-dates-list tr').length === 0) {
                            $('#blocked-dates-list').html('<tr><td colspan="5" style="text-align: center; padding: 30px;">目前沒有封鎖日期</td></tr>');
                        }
                    });
                } else {
                    alert('刪除失敗');
                }
            }
        });
    });
});
