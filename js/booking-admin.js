/* 預約系統後台腳本 v4.0 */

jQuery(document).ready(function($) {
    
    // 快速更新預約狀態
    $(document).on('change', '.booking-quick-status', function() {
        const select = $(this);
        const bookingId = select.data('booking-id');
        const newStatus = select.val();
        const originalStatus = select.find('option:selected').data('original') || select.val();
        
        if (!confirm('確定要更改此預約的狀態嗎？')) {
            select.val(originalStatus);
            return;
        }
        
        $.ajax({
            url: bookingAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'quick_update_status',
                nonce: bookingAdmin.nonce,
                booking_id: bookingId,
                status: newStatus
            },
            success: function(response) {
                if (response.success) {
                    // 更新顏色
                    const colors = {
                        'pending_booking': '#ff9800',
                        'confirmed': '#4caf50',
                        'cancelled': '#f44336',
                        'completed': '#2196f3'
                    };
                    select.css({
                        'border-color': colors[newStatus],
                        'color': colors[newStatus]
                    });
                    
                    showAdminNotice('success', response.data.message);
                } else {
                    select.val(originalStatus);
                    showAdminNotice('error', response.data.message);
                }
            },
            error: function() {
                select.val(originalStatus);
                showAdminNotice('error', '更新失敗，請重試');
            }
        });
    });
    
    // 新增封鎖日期
    $('#add_blocked_date_btn').on('click', function() {
        const startDate = $('#new_blocked_start_date').val();
        const endDate = $('#new_blocked_end_date').val();
        const note = $('#new_blocked_note').val();
        
        if (!startDate || !endDate) {
            alert('請選擇開始和結束日期');
            return;
        }
        
        if (new Date(startDate) > new Date(endDate)) {
            alert('開始日期不能晚於結束日期');
            return;
        }
        
        const button = $(this);
        button.prop('disabled', true).text('新增中...');
        
        $.ajax({
            url: bookingAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'add_blocked_date',
                nonce: bookingAdmin.nonce,
                start_date: startDate,
                end_date: endDate,
                note: note
            },
            success: function(response) {
                if (response.success) {
                    // 新增到列表
                    const data = response.data.data;
                    const noteDisplay = data.note ? data.note : '<span style="color: #999;">-</span>';
                    
                    const newRow = `
                        <tr data-id="${data.id}">
                            <td><strong>${data.start_date}</strong></td>
                            <td><strong>${data.end_date}</strong></td>
                            <td>${noteDisplay}</td>
                            <td>${data.created_at}</td>
                            <td>
                                <button type="button" class="button button-small remove-blocked-date" data-id="${data.id}" style="color: #b32d2e;">
                                    刪除
                                </button>
                            </td>
                        </tr>
                    `;
                    
                    if ($('#blocked-dates-list tr td[colspan="5"]').length > 0) {
                        $('#blocked-dates-list').html(newRow);
                    } else {
                        $('#blocked-dates-list').prepend(newRow);
                    }
                    
                    // 清空輸入
                    $('#new_blocked_start_date, #new_blocked_end_date, #new_blocked_note').val('');
                    
                    showAdminNotice('success', response.data.message);
                } else {
                    alert(response.data.message);
                }
            },
            error: function() {
                alert('新增失敗，請重試');
            },
            complete: function() {
                button.prop('disabled', false).text('新增封鎖日期');
            }
        });
    });
    
    // 刪除封鎖日期
    $(document).on('click', '.remove-blocked-date', function() {
        if (!confirm('確定要刪除此封鎖日期嗎？')) {
            return;
        }
        
        const button = $(this);
        const id = button.data('id');
        const row = button.closest('tr');
        
        button.prop('disabled', true).text('刪除中...');
        
        $.ajax({
            url: bookingAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'remove_blocked_date',
                nonce: bookingAdmin.nonce,
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
                    
                    showAdminNotice('success', response.data.message);
                } else {
                    alert(response.data.message);
                    button.prop('disabled', false).text('刪除');
                }
            },
            error: function() {
                alert('刪除失敗，請重試');
                button.prop('disabled', false).text('刪除');
            }
        });
    });
    
    // 發送測試客戶信件
    $('#send_test_customer_email').on('click', function() {
        const email = $('#test_customer_email').val().trim();
        
        if (!email) {
            alert('請輸入測試 Email 地址');
            return;
        }
        
        if (!isValidEmail(email)) {
            alert('請輸入有效的 Email 地址');
            return;
        }
        
        const button = $(this);
        const resultSpan = $('#test_customer_result');
        
        button.prop('disabled', true).text('發送中...');
        resultSpan.html('<span style="color: #999;">⏳ 發送中...</span>');
        
        $.ajax({
            url: bookingAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'send_test_email',
                nonce: bookingAdmin.nonce,
                test_email: email,
                email_type: 'customer'
            },
            success: function(response) {
                if (response.success) {
                    resultSpan.html('<span style="color: #46b450;">✓ ' + response.data.message + '</span>');
                } else {
                    resultSpan.html('<span style="color: #dc3232;">✗ ' + response.data.message + '</span>');
                }
            },
            error: function() {
                resultSpan.html('<span style="color: #dc3232;">✗ 發送失敗</span>');
            },
            complete: function() {
                button.prop('disabled', false).text('發送測試信件');
                setTimeout(function() {
                    resultSpan.html('');
                }, 5000);
            }
        });
    });
    
    // 發送測試管理員信件
    $('#send_test_admin_email').on('click', function() {
        const email = $('#test_admin_email').val().trim();
        
        if (!email) {
            alert('請輸入測試 Email 地址');
            return;
        }
        
        if (!isValidEmail(email)) {
            alert('請輸入有效的 Email 地址');
            return;
        }
        
        const button = $(this);
        const resultSpan = $('#test_admin_result');
        
        button.prop('disabled', true).text('發送中...');
        resultSpan.html('<span style="color: #999;">⏳ 發送中...</span>');
        
        $.ajax({
            url: bookingAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'send_test_email',
                nonce: bookingAdmin.nonce,
                test_email: email,
                email_type: 'admin'
            },
            success: function(response) {
                if (response.success) {
                    resultSpan.html('<span style="color: #46b450;">✓ ' + response.data.message + '</span>');
                } else {
                    resultSpan.html('<span style="color: #dc3232;">✗ ' + response.data.message + '</span>');
                }
            },
            error: function() {
                resultSpan.html('<span style="color: #dc3232;">✗ 發送失敗</span>');
            },
            complete: function() {
                button.prop('disabled', false).text('發送測試信件');
                setTimeout(function() {
                    resultSpan.html('');
                }, 5000);
            }
        });
    });
    
    // 查看信件紀錄詳情
    $(document).on('click', '.view-email-log', function() {
        const id = $(this).data('id');
        
        $.ajax({
            url: bookingAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_email_log_detail',
                nonce: bookingAdmin.nonce,
                id: id
            },
            success: function(response) {
                if (response.success) {
                    const log = response.data.data;
                    
                    const statusBadge = log.status === 'sent' 
                        ? '<span style="color: #46b450; font-weight: bold;">✓ 發送成功</span>'
                        : '<span style="color: #dc3232; font-weight: bold;">✗ 發送失敗</span>';
                    
                    const typeBadge = log.recipient_type === 'customer'
                        ? '<span style="color: #2196f3;">👤 客戶通知</span>'
                        : '<span style="color: #ff9800;">⚙️ 管理員通知</span>';
                    
                    const errorSection = log.error_message 
                        ? `<tr>
                            <th style="width: 120px; text-align: left; padding: 12px; background: #f9f9f9; vertical-align: top;">錯誤訊息:</th>
                            <td style="padding: 12px; color: #dc3232;">${escapeHtml(log.error_message)}</td>
                          </tr>`
                        : '';
                    
                    const html = `
                        <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                            <tr>
                                <th style="width: 120px; text-align: left; padding: 12px; background: #f9f9f9;">狀態:</th>
                                <td style="padding: 12px;">${statusBadge}</td>
                            </tr>
                            <tr>
                                <th style="width: 120px; text-align: left; padding: 12px; background: #f9f9f9;">類型:</th>
                                <td style="padding: 12px;">${typeBadge}</td>
                            </tr>
                            <tr>
                                <th style="width: 120px; text-align: left; padding: 12px; background: #f9f9f9;">收件人:</th>
                                <td style="padding: 12px;">
                                    <strong>${escapeHtml(log.recipient_name)}</strong><br>
                                    <span style="color: #666;">${escapeHtml(log.recipient_email)}</span>
                                </td>
                            </tr>
                            <tr>
                                <th style="width: 120px; text-align: left; padding: 12px; background: #f9f9f9;">預約編號:</th>
                                <td style="padding: 12px;">
                                    <a href="post.php?post=${log.booking_id}&action=edit" target="_blank">#${log.booking_id}</a>
                                </td>
                            </tr>
                            <tr>
                                <th style="width: 120px; text-align: left; padding: 12px; background: #f9f9f9;">發送時間:</th>
                                <td style="padding: 12px;">${log.sent_at}</td>
                            </tr>
                            <tr>
                                <th style="width: 120px; text-align: left; padding: 12px; background: #f9f9f9; vertical-align: top;">信件主旨:</th>
                                <td style="padding: 12px;"><strong>${escapeHtml(log.subject)}</strong></td>
                            </tr>
                            ${errorSection}
                            <tr>
                                <th style="width: 120px; text-align: left; padding: 12px; background: #f9f9f9; vertical-align: top;">信件內容:</th>
                                <td style="padding: 12px;">
                                    <div style="background: #f5f5f5; padding: 15px; border-radius: 4px; white-space: pre-wrap; font-family: monospace; max-height: 400px; overflow-y: auto;">${escapeHtml(log.message)}</div>
                                </td>
                            </tr>
                        </table>
                    `;
                    
                    $('#email-log-content').html(html);
                    $('#email-log-modal').fadeIn(200);
                } else {
                    alert(response.data.message);
                }
            },
            error: function() {
                alert('載入失敗，請重試');
            }
        });
    });
    
    // 關閉信件詳情模態框
    $('#close-email-modal, #email-log-modal').on('click', function(e) {
        if (e.target === this) {
            $('#email-log-modal').fadeOut(200);
        }
    });
    
    // 刪除信件紀錄
    $(document).on('click', '.delete-email-log', function() {
        if (!confirm('確定要刪除此信件紀錄嗎？')) {
            return;
        }
        
        const button = $(this);
        const id = button.data('id');
        const row = button.closest('tr');
        
        button.prop('disabled', true).text('刪除中...');
        
        $.ajax({
            url: bookingAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'delete_email_log',
                nonce: bookingAdmin.nonce,
                id: id
            },
            success: function(response) {
                if (response.success) {
                    row.fadeOut(300, function() {
                        $(this).remove();
                        
                        if ($('table tbody tr').length === 0) {
                            $('table tbody').html('<tr><td colspan="6" style="text-align: center; padding: 30px;">目前沒有發信紀錄</td></tr>');
                        }
                    });
                    
                    showAdminNotice('success', response.data.message);
                } else {
                    alert(response.data.message);
                    button.prop('disabled', false).text('刪除');
                }
            },
            error: function() {
                alert('刪除失敗，請重試');
                button.prop('disabled', false).text('刪除');
            }
        });
    });
    
    // 顯示管理後台通知
    function showAdminNotice(type, message) {
        const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        const notice = $(`
            <div class="notice ${noticeClass} is-dismissible" style="position: relative;">
                <p><strong>${message}</strong></p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">關閉此通知</span>
                </button>
            </div>
        `);
        
        $('.wrap > h1').after(notice);
        
        notice.find('.notice-dismiss').on('click', function() {
            notice.fadeOut(200, function() {
                $(this).remove();
            });
        });
        
        setTimeout(function() {
            notice.fadeOut(200, function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // Email 驗證
    function isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    
    // HTML 轉義
    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
});
