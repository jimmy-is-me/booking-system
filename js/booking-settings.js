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
        
        if (new Date(endDate) < new Date(startDate)) {
            alert('結束日期不能早於開始日期');
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
            beforeSend: function() {
                $('#add_blocked_date_btn').prop('disabled', true).text('新增中...');
            },
            success: function(response) {
                if (response.success) {
                    // 清空輸入
                    $('#new_blocked_start_date').val('');
                    $('#new_blocked_end_date').val('');
                    $('#new_blocked_note').val('');
                    
                    // 新增到列表
                    var row = response.data;
                    var noteDisplay = row.note ? '<span class="dashicons dashicons-info" style="color: #2271b1;"></span> ' + row.note : '<span style="color: #999;">-</span>';
                    
                    var createdDate = new Date(row.created_at.replace(' ', 'T'));
                    var formattedDate = createdDate.getFullYear() + '-' + 
                                      String(createdDate.getMonth() + 1).padStart(2, '0') + '-' + 
                                      String(createdDate.getDate()).padStart(2, '0') + ' ' + 
                                      String(createdDate.getHours()).padStart(2, '0') + ':' + 
                                      String(createdDate.getMinutes()).padStart(2, '0');
                    
                    var newRow = '<tr data-id="' + row.id + '">' +
                        '<td><strong>' + row.start_date + '</strong></td>' +
                        '<td><strong>' + row.end_date + '</strong></td>' +
                        '<td>' + noteDisplay + '</td>' +
                        '<td>' + formattedDate + '</td>' +
                        '<td><button type="button" class="button button-small remove-blocked-date" data-id="' + row.id + '" style="color: #b32d2e;">刪除</button></td>' +
                        '</tr>';
                    
                    var tbody = $('#blocked-dates-list');
                    if (tbody.find('td[colspan="5"]').length > 0) {
                        tbody.html(newRow);
                    } else {
                        tbody.prepend(newRow);
                    }
                    
                    // 顯示成功提示
                    var message = row.start_date === row.end_date ? 
                        '已新增封鎖日期：' + row.start_date : 
                        '已新增日期區間：' + row.start_date + ' 至 ' + row.end_date;
                    alert(message);
                } else {
                    alert('新增失敗：' + (response.data && response.data.message ? response.data.message : '未知錯誤'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                console.error('Response:', xhr.responseText);
                alert('發生錯誤，請重試。錯誤訊息：' + error);
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
        var row = btn.closest('tr');
        var startDate = row.find('td:first strong').text();
        var endDate = row.find('td:eq(1) strong').text();
        
        var confirmMsg = startDate === endDate ? 
            '確定要刪除 ' + startDate + ' 的封鎖設定嗎？' : 
            '確定要刪除 ' + startDate + ' 至 ' + endDate + ' 的封鎖區間嗎？';
        
        if (!confirm(confirmMsg)) {
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
                    row.fadeOut(300, function() {
                        $(this).remove();
                        
                        // 如果沒有資料了，顯示空狀態
                        if ($('#blocked-dates-list tr').length === 0) {
                            $('#blocked-dates-list').html('<tr><td colspan="5" style="text-align: center; padding: 30px; color: #666;">目前沒有封鎖日期<br><small style="color: #999;">您可以在上方新增需要封鎖的日期或日期區間</small></td></tr>');
                        }
                    });
                    alert('已成功刪除封鎖日期');
                } else {
                    alert('刪除失敗：' + (response.data && response.data.message ? response.data.message : '未知錯誤'));
                    btn.prop('disabled', false).text('刪除');
                }
            },
            error: function() {
                alert('發生錯誤，請重試');
                btn.prop('disabled', false).text('刪除');
            }
        });
    });
    
    // 自動同步開始和結束日期(單一日期使用)
    $('#new_blocked_start_date').on('change', function() {
        var endDateInput = $('#new_blocked_end_date');
        if (!endDateInput.val()) {
            endDateInput.val($(this).val());
        }
    });
});
