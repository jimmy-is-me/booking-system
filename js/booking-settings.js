jQuery(document).ready(function($) {
    // 新增封鎖日期
    $('#add_blocked_date_btn').on('click', function() {
        var date = $('#new_blocked_date').val();
        var note = $('#new_blocked_note').val();
        
        if (!date) {
            alert('請選擇日期');
            return;
        }
        
        $.ajax({
            url: bookingAdminData.ajaxurl,
            type: 'POST',
            data: {
                action: 'add_blocked_date',
                nonce: bookingAdminData.nonce,
                date: date,
                note: note
            },
            beforeSend: function() {
                $('#add_blocked_date_btn').prop('disabled', true).text('新增中...');
            },
            success: function(response) {
                if (response.success) {
                    // 清空輸入
                    $('#new_blocked_date').val('');
                    $('#new_blocked_note').val('');
                    
                    // 新增到列表
                    var row = response.data.data;
                    var newRow = '<tr data-id="' + row.id + '">' +
                        '<td><strong>' + row.blocked_date + '</strong></td>' +
                        '<td>' + (row.note ? row.note : '-') + '</td>' +
                        '<td>' + new Date(row.created_at).toLocaleString('zh-TW') + '</td>' +
                        '<td><button type="button" class="button button-small remove-blocked-date" data-id="' + row.id + '" data-date="' + row.blocked_date + '">刪除</button></td>' +
                        '</tr>';
                    
                    var tbody = $('#blocked-dates-list');
                    if (tbody.find('td[colspan="4"]').length > 0) {
                        tbody.html(newRow);
                    } else {
                        tbody.append(newRow);
                    }
                    
                    alert('封鎖日期已新增');
                } else {
                    alert('新增失敗：' + response.data.message);
                }
            },
            error: function() {
                alert('發生錯誤，請重試');
            },
            complete: function() {
                $('#add_blocked_date_btn').prop('disabled', false).text('新增封鎖日期');
            }
        });
    });
    
    // 刪除封鎖日期
    $(document).on('click', '.remove-blocked-date', function() {
        var btn = $(this);
        var id = btn.data('id');
        var date = btn.data('date');
        
        if (!confirm('確定要刪除 ' + date + ' 的封鎖設定嗎？')) {
            return;
        }
        
        $.ajax({
            url: bookingAdminData.ajaxurl,
            type: 'POST',
            data: {
                action: 'remove_blocked_date',
                nonce: bookingAdminData.nonce,
                id: id
            },
            beforeSend: function() {
                btn.prop('disabled', true).text('刪除中...');
            },
            success: function(response) {
                if (response.success) {
                    btn.closest('tr').fadeOut(300, function() {
                        $(this).remove();
                        
                        // 如果沒有資料了，顯示空狀態
                        if ($('#blocked-dates-list tr').length === 0) {
                            $('#blocked-dates-list').html('<tr><td colspan="4" style="text-align: center; padding: 30px;">目前沒有封鎖日期</td></tr>');
                        }
                    });
                    alert('已刪除');
                } else {
                    alert('刪除失敗：' + response.data.message);
                    btn.prop('disabled', false).text('刪除');
                }
            },
            error: function() {
                alert('發生錯誤，請重試');
                btn.prop('disabled', false).text('刪除');
            }
        });
    });
});
