<?php
/**
 * Plugin Name: 預約系統
 * Description: 完整的預約功能,包含前台預約、後台管理、時段衝突檢查
 * Version: 1.0
 * Author: Your Name
 */

if (!defined('ABSPATH')) exit;

class BookingSystem {
    
    public function __construct() {
        // 註冊 Custom Post Type
        add_action('init', array($this, 'register_booking_post_type'));
        
        // 註冊自訂狀態
        add_action('init', array($this, 'register_booking_statuses'));
        
        // 前台短代碼
        add_shortcode('booking_form', array($this, 'render_booking_form'));
        
        // AJAX 處理
        add_action('wp_ajax_submit_booking', array($this, 'handle_booking_submission'));
        add_action('wp_ajax_nopriv_submit_booking', array($this, 'handle_booking_submission'));
        add_action('wp_ajax_check_availability', array($this, 'check_time_availability'));
        add_action('wp_ajax_nopriv_check_availability', array($this, 'check_time_availability'));
        
        // 載入前台樣式和腳本
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        
        // 後台管理
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_filter('manage_booking_posts_columns', array($this, 'set_custom_columns'));
        add_action('manage_booking_posts_custom_column', array($this, 'custom_column_content'), 10, 2);
        add_action('add_meta_boxes', array($this, 'add_booking_meta_boxes'));
        add_action('save_post_booking', array($this, 'save_booking_meta'), 10, 2);
        
        // 後台腳本
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    // 註冊 Custom Post Type
    public function register_booking_post_type() {
        $labels = array(
            'name' => '預約',
            'singular_name' => '預約',
            'menu_name' => '預約管理',
            'add_new' => '新增預約',
            'add_new_item' => '新增預約',
            'edit_item' => '編輯預約',
            'view_item' => '查看預約',
            'all_items' => '所有預約',
            'search_items' => '搜尋預約',
        );
        
        $args = array(
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'capability_type' => 'post',
            'hierarchical' => false,
            'supports' => array('title', 'editor'),
            'menu_icon' => 'dashicons-calendar-alt',
            'has_archive' => false,
            'rewrite' => false,
        );
        
        register_post_type('booking', $args);
    }
    
    // 註冊自訂預約狀態
    public function register_booking_statuses() {
        register_post_status('pending_booking', array(
            'label' => '待確認',
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('待確認 <span class="count">(%s)</span>', '待確認 <span class="count">(%s)</span>'),
        ));
        
        register_post_status('confirmed', array(
            'label' => '已確認',
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('已確認 <span class="count">(%s)</span>', '已確認 <span class="count">(%s)</span>'),
        ));
        
        register_post_status('cancelled', array(
            'label' => '已取消',
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('已取消 <span class="count">(%s)</span>', '已取消 <span class="count">(%s)</span>'),
        ));
        
        register_post_status('completed', array(
            'label' => '已完成',
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('已完成 <span class="count">(%s)</span>', '已完成 <span class="count">(%s)</span>'),
        ));
    }
    
    // 前台載入腳本
    public function enqueue_frontend_scripts() {
        wp_enqueue_style('booking-style', plugin_dir_url(__FILE__) . 'css/booking-style.css');
        wp_enqueue_script('booking-script', plugin_dir_url(__FILE__) . 'js/booking-script.js', array('jquery'), '1.0', true);
        
        wp_localize_script('booking-script', 'bookingAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('booking_nonce')
        ));
    }
    
    // 前台預約表單短代碼
    public function render_booking_form() {
        ob_start();
        ?>
        <div class="booking-form-container">
            <h3>線上預約</h3>
            <form id="booking-form" class="booking-form">
                <div class="form-group">
                    <label for="booking_name">姓名 *</label>
                    <input type="text" id="booking_name" name="booking_name" required>
                </div>
                
                <div class="form-group">
                    <label for="booking_email">Email *</label>
                    <input type="email" id="booking_email" name="booking_email" required>
                </div>
                
                <div class="form-group">
                    <label for="booking_phone">電話 *</label>
                    <input type="tel" id="booking_phone" name="booking_phone" required>
                </div>
                
                <div class="form-group">
                    <label for="booking_date">預約日期 *</label>
                    <input type="date" id="booking_date" name="booking_date" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="booking_time">預約時間 *</label>
                    <select id="booking_time" name="booking_time" required>
                        <option value="">請選擇時間</option>
                        <?php
                        for ($hour = 9; $hour <= 17; $hour++) {
                            for ($min = 0; $min < 60; $min += 30) {
                                $time = sprintf('%02d:%02d', $hour, $min);
                                echo "<option value='{$time}'>{$time}</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="booking_duration">預約時長</label>
                    <select id="booking_duration" name="booking_duration">
                        <option value="30">30分鐘</option>
                        <option value="60" selected>1小時</option>
                        <option value="90">1.5小時</option>
                        <option value="120">2小時</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="booking_note">備註</label>
                    <textarea id="booking_note" name="booking_note" rows="4"></textarea>
                </div>
                
                <div id="availability-message" class="availability-message"></div>
                
                <button type="submit" class="submit-booking-btn">送出預約</button>
            </form>
            
            <div id="booking-response" class="booking-response"></div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    // 檢查時段可用性
    public function check_time_availability() {
        check_ajax_referer('booking_nonce', 'nonce');
        
        $date = sanitize_text_field($_POST['date']);
        $time = sanitize_text_field($_POST['time']);
        $duration = intval($_POST['duration']);
        
        $is_available = $this->is_time_slot_available($date, $time, $duration);
        
        wp_send_json(array(
            'available' => $is_available,
            'message' => $is_available ? '此時段可預約' : '此時段已被預約,請選擇其他時間'
        ));
    }
    
    // 檢查時段是否可用的邏輯
    private function is_time_slot_available($date, $time, $duration, $exclude_booking_id = 0) {
        $start_datetime = $date . ' ' . $time;
        $end_datetime = date('Y-m-d H:i:s', strtotime($start_datetime) + ($duration * 60));
        
        $args = array(
            'post_type' => 'booking',
            'post_status' => array('pending_booking', 'confirmed'),
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_booking_date',
                    'value' => $date,
                    'compare' => '='
                )
            )
        );
        
        if ($exclude_booking_id > 0) {
            $args['post__not_in'] = array($exclude_booking_id);
        }
        
        $bookings = get_posts($args);
        
        foreach ($bookings as $booking) {
            $existing_time = get_post_meta($booking->ID, '_booking_time', true);
            $existing_duration = get_post_meta($booking->ID, '_booking_duration', true);
            
            $existing_start = strtotime($date . ' ' . $existing_time);
            $existing_end = $existing_start + ($existing_duration * 60);
            
            $new_start = strtotime($start_datetime);
            $new_end = strtotime($end_datetime);
            
            // 檢查時段是否重疊
            if (($new_start >= $existing_start && $new_start < $existing_end) ||
                ($new_end > $existing_start && $new_end <= $existing_end) ||
                ($new_start <= $existing_start && $new_end >= $existing_end)) {
                return false;
            }
        }
        
        return true;
    }
    
    // 處理預約提交
    public function handle_booking_submission() {
        check_ajax_referer('booking_nonce', 'nonce');
        
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        $date = sanitize_text_field($_POST['date']);
        $time = sanitize_text_field($_POST['time']);
        $duration = intval($_POST['duration']);
        $note = sanitize_textarea_field($_POST['note']);
        
        // 再次檢查時段可用性
        if (!$this->is_time_slot_available($date, $time, $duration)) {
            wp_send_json_error(array('message' => '此時段已被預約,請重新選擇'));
        }
        
        // 建立預約
        $post_data = array(
            'post_title' => $name . ' - ' . $date . ' ' . $time,
            'post_type' => 'booking',
            'post_status' => 'pending_booking',
            'post_content' => $note,
        );
        
        $booking_id = wp_insert_post($post_data);
        
        if ($booking_id) {
            // 儲存預約資料
            update_post_meta($booking_id, '_booking_name', $name);
            update_post_meta($booking_id, '_booking_email', $email);
            update_post_meta($booking_id, '_booking_phone', $phone);
            update_post_meta($booking_id, '_booking_date', $date);
            update_post_meta($booking_id, '_booking_time', $time);
            update_post_meta($booking_id, '_booking_duration', $duration);
            
            // 發送通知郵件給管理員
            $admin_email = get_option('admin_email');
            $subject = '新的預約通知';
            $message = "收到新的預約:\n\n";
            $message .= "姓名: {$name}\n";
            $message .= "Email: {$email}\n";
            $message .= "電話: {$phone}\n";
            $message .= "日期: {$date}\n";
            $message .= "時間: {$time}\n";
            $message .= "時長: {$duration}分鐘\n";
            $message .= "備註: {$note}\n";
            
            wp_mail($admin_email, $subject, $message);
            
            // 發送確認郵件給訪客
            $customer_subject = '預約確認通知';
            $customer_message = "您好 {$name},\n\n";
            $customer_message .= "您的預約已收到,詳細資訊如下:\n\n";
            $customer_message .= "日期: {$date}\n";
            $customer_message .= "時間: {$time}\n";
            $customer_message .= "時長: {$duration}分鐘\n\n";
            $customer_message .= "我們會盡快確認您的預約。\n";
            
            wp_mail($email, $customer_subject, $customer_message);
            
            wp_send_json_success(array('message' => '預約成功!我們會�儘快與您確認。'));
        } else {
            wp_send_json_error(array('message' => '預約失敗,請稍後再試'));
        }
    }
    
    // 後台自訂欄位
    public function set_custom_columns($columns) {
        $new_columns = array();
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = '預約標題';
        $new_columns['booking_name'] = '姓名';
        $new_columns['booking_contact'] = '聯絡資訊';
        $new_columns['booking_datetime'] = '預約時間';
        $new_columns['booking_duration'] = '時長';
        $new_columns['booking_status'] = '狀態';
        $new_columns['date'] = '建立時間';
        
        return $new_columns;
    }
    
    // 自訂欄位內容
    public function custom_column_content($column, $post_id) {
        switch ($column) {
            case 'booking_name':
                echo esc_html(get_post_meta($post_id, '_booking_name', true));
                break;
            case 'booking_contact':
                $email = get_post_meta($post_id, '_booking_email', true);
                $phone = get_post_meta($post_id, '_booking_phone', true);
                echo esc_html($phone) . '<br>' . esc_html($email);
                break;
            case 'booking_datetime':
                $date = get_post_meta($post_id, '_booking_date', true);
                $time = get_post_meta($post_id, '_booking_time', true);
                echo esc_html($date . ' ' . $time);
                break;
            case 'booking_duration':
                echo esc_html(get_post_meta($post_id, '_booking_duration', true)) . '分鐘';
                break;
            case 'booking_status':
                $status = get_post_status($post_id);
                $status_labels = array(
                    'pending_booking' => '<span style="color: orange;">待確認</span>',
                    'confirmed' => '<span style="color: green;">已確認</span>',
                    'cancelled' => '<span style="color: red;">已取消</span>',
                    'completed' => '<span style="color: blue;">已完成</span>',
                );
                echo isset($status_labels[$status]) ? $status_labels[$status] : $status;
                break;
        }
    }
    
    // 新增Meta Boxes
    public function add_booking_meta_boxes() {
        add_meta_box(
            'booking_details',
            '預約詳細資訊',
            array($this, 'render_booking_meta_box'),
            'booking',
            'normal',
            'high'
        );
    }
    
    // 渲染Meta Box
    public function render_booking_meta_box($post) {
        wp_nonce_field('booking_meta_box', 'booking_meta_box_nonce');
        
        $name = get_post_meta($post->ID, '_booking_name', true);
        $email = get_post_meta($post->ID, '_booking_email', true);
        $phone = get_post_meta($post->ID, '_booking_phone', true);
        $date = get_post_meta($post->ID, '_booking_date', true);
        $time = get_post_meta($post->ID, '_booking_time', true);
        $duration = get_post_meta($post->ID, '_booking_duration', true);
        $status = get_post_status($post->ID);
        ?>
        <table class="form-table">
            <tr>
                <th><label for="booking_name">姓名</label></th>
                <td><input type="text" id="booking_name" name="booking_name" value="<?php echo esc_attr($name); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="booking_email">Email</label></th>
                <td><input type="email" id="booking_email" name="booking_email" value="<?php echo esc_attr($email); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="booking_phone">電話</label></th>
                <td><input type="tel" id="booking_phone" name="booking_phone" value="<?php echo esc_attr($phone); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="booking_date">預約日期</label></th>
                <td><input type="date" id="booking_date" name="booking_date" value="<?php echo esc_attr($date); ?>"></td>
            </tr>
            <tr>
                <th><label for="booking_time">預約時間</label></th>
                <td><input type="time" id="booking_time" name="booking_time" value="<?php echo esc_attr($time); ?>"></td>
            </tr>
            <tr>
                <th><label for="booking_duration">時長(分鐘)</label></th>
                <td>
                    <select id="booking_duration" name="booking_duration">
                        <option value="30" <?php selected($duration, 30); ?>>30分鐘</option>
                        <option value="60" <?php selected($duration, 60); ?>>60分鐘</option>
                        <option value="90" <?php selected($duration, 90); ?>>90分鐘</option>
                        <option value="120" <?php selected($duration, 120); ?>>120分鐘</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="booking_status">預約狀態</label></th>
                <td>
                    <select id="booking_status" name="booking_status">
                        <option value="pending_booking" <?php selected($status, 'pending_booking'); ?>>待確認</option>
                        <option value="confirmed" <?php selected($status, 'confirmed'); ?>>已確認</option>
                        <option value="cancelled" <?php selected($status, 'cancelled'); ?>>已取消</option>
                        <option value="completed" <?php selected($status, 'completed'); ?>>已完成</option>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }
    
    // 儲存Meta資料
    public function save_booking_meta($post_id, $post) {
        if (!isset($_POST['booking_meta_box_nonce']) || !wp_verify_nonce($_POST['booking_meta_box_nonce'], 'booking_meta_box')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        if (isset($_POST['booking_name'])) {
            update_post_meta($post_id, '_booking_name', sanitize_text_field($_POST['booking_name']));
        }
        
        if (isset($_POST['booking_email'])) {
            update_post_meta($post_id, '_booking_email', sanitize_email($_POST['booking_email']));
        }
        
        if (isset($_POST['booking_phone'])) {
            update_post_meta($post_id, '_booking_phone', sanitize_text_field($_POST['booking_phone']));
        }
        
        if (isset($_POST['booking_date'])) {
            update_post_meta($post_id, '_booking_date', sanitize_text_field($_POST['booking_date']));
        }
        
        if (isset($_POST['booking_time'])) {
            update_post_meta($post_id, '_booking_time', sanitize_text_field($_POST['booking_time']));
        }
        
        if (isset($_POST['booking_duration'])) {
            update_post_meta($post_id, '_booking_duration', intval($_POST['booking_duration']));
        }
        
        // 更新文章狀態
        if (isset($_POST['booking_status']) && $_POST['booking_status'] != $post->post_status) {
            wp_update_post(array(
                'ID' => $post_id,
                'post_status' => sanitize_text_field($_POST['booking_status'])
            ));
        }
    }
    
    // 新增後台選單
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=booking',
            '日曆檢視',
            '日曆檢視',
            'manage_options',
            'booking-calendar',
            array($this, 'render_calendar_page')
        );
    }
    
    // 渲染日曆頁面
    public function render_calendar_page() {
        ?>
        <div class="wrap">
            <h1>預約日曆</h1>
            <div id="booking-calendar"></div>
        </div>
        <?php
    }
    
    // 後台載入腳本
    public function enqueue_admin_scripts($hook) {
        if ('booking_page_booking-calendar' === $hook) {
            wp_enqueue_style('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css');
            wp_enqueue_script('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js', array(), '5.11.3', true);
            wp_enqueue_script('booking-calendar', plugin_dir_url(__FILE__) . 'js/booking-calendar.js', array('jquery', 'fullcalendar'), '1.0', true);
            
            // 傳遞預約資料給JavaScript
            $bookings = $this->get_all_bookings_for_calendar();
            wp_localize_script('booking-calendar', 'bookingCalendarData', array(
                'bookings' => $bookings
            ));
        }
    }
    
    // 獲取所有預約資料供日曆使用
    private function get_all_bookings_for_calendar() {
        $args = array(
            'post_type' => 'booking',
            'post_status' => array('pending_booking', 'confirmed', 'completed'),
            'posts_per_page' => -1,
        );
        
        $bookings = get_posts($args);
        $events = array();
        
        foreach ($bookings as $booking) {
            $date = get_post_meta($booking->ID, '_booking_date', true);
            $time = get_post_meta($booking->ID, '_booking_time', true);
            $duration = get_post_meta($booking->ID, '_booking_duration', true);
            $name = get_post_meta($booking->ID, '_booking_name', true);
            $status = get_post_status($booking->ID);
            
            $start = $date . 'T' . $time;
            $end = date('Y-m-d\TH:i:s', strtotime($start) + ($duration * 60));
            
            $color = '#3788d8';
            if ($status === 'confirmed') $color = '#28a745';
            if ($status === 'pending_booking') $color = '#ffc107';
            if ($status === 'completed') $color = '#6c757d';
            
            $events[] = array(
                'title' => $name,
                'start' => $start,
                'end' => $end,
                'color' => $color,
                'url' => admin_url('post.php?post=' . $booking->ID . '&action=edit')
            );
        }
        
        return $events;
    }
}

// 初始化外掛
new BookingSystem();
