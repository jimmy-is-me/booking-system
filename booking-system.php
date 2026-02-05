<?php
/**
 * Plugin Name: 預約系統
 * Description: 完整的預約功能,包含前台預約、後台管理、時段衝突檢查
 * Version: 2.2
 * Author: wumetax
 * Author URI: https://wumetax.com
 * Text Domain: booking-system
 */

if (!defined('ABSPATH')) exit;

class BookingSystem {
    
    public function __construct() {
        // 設定字元編碼
        add_action('init', array($this, 'set_charset'));
        
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
        add_action('wp_ajax_get_available_times', array($this, 'get_available_times'));
        add_action('wp_ajax_nopriv_get_available_times', array($this, 'get_available_times'));
        add_action('wp_ajax_get_booking_details', array($this, 'get_booking_details'));
        add_action('wp_ajax_quick_update_status', array($this, 'quick_update_status'));
        
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
        
        // 停用古騰堡編輯器
        add_filter('use_block_editor_for_post_type', array($this, 'disable_gutenberg_for_booking'), 10, 2);
        
        // 翻譯文字狀態標籤
        add_filter('display_post_states', array($this, 'display_booking_states'), 10, 2);
        
        // 移除文章屬性 Meta Box
        add_action('admin_menu', array($this, 'remove_post_attributes'));
    }
    
    // 設定字元編碼
    public function set_charset() {
        if (!is_admin()) {
            header('Content-Type: text/html; charset=UTF-8');
        }
    }
    
    // 停用古騰堡編輯器用於預約
    public function disable_gutenberg_for_booking($use_block_editor, $post_type) {
        if ($post_type === 'booking') {
            return false;
        }
        return $use_block_editor;
    }
    
    // 移除文章屬性 Meta Box
    public function remove_post_attributes() {
        remove_meta_box('pageparentdiv', 'booking', 'side');
    }
    
    // 顯示預約狀態中文標籤
    public function display_booking_states($states, $post) {
        if ($post->post_type === 'booking') {
            $status = get_post_status($post);
            $status_labels = array(
                'pending_booking' => '待確認',
                'confirmed' => '已確認',
                'cancelled' => '已取消',
                'completed' => '已完成',
            );
            
            if (isset($status_labels[$status])) {
                $states = array($status_labels[$status]);
            }
        }
        return $states;
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
            'new_item' => '新預約',
            'view_item' => '查看預約',
            'all_items' => '所有預約',
            'search_items' => '搜尋預約',
            'not_found' => '找不到預約',
            'not_found_in_trash' => '垃圾桶中沒有預約',
        );
        
        $args = array(
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'capability_type' => 'post',
            'hierarchical' => false,
            'supports' => array('title'),
            'menu_icon' => 'dashicons-calendar-alt',
            'has_archive' => false,
            'rewrite' => false,
            'show_in_rest' => false,
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
        wp_enqueue_style('booking-style', plugin_dir_url(__FILE__) . 'css/booking-style.css', array(), '2.2');
        wp_enqueue_script('booking-script', plugin_dir_url(__FILE__) . 'js/booking-script.js', array('jquery'), '2.2', true);
        
        wp_localize_script('booking-script', 'bookingAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('booking_nonce'),
            'messages' => array(
                'required' => '此欄位為必填',
                'invalid_email' => '請輸入有效的 Email',
                'invalid_phone' => '請輸入有效的電話號碼',
                'select_time' => '請選擇預約時間',
            )
        ));
    }
    
    // 取得管理員設定
    private function get_booking_settings() {
        $defaults = array(
            'available_days' => array('1', '2', '3', '4', '5'),
            'start_time' => '09:00',
            'end_time' => '18:00',
            'time_slot_interval' => '30',
            'available_durations' => array('30', '60', '90', '120'),
            'default_duration' => '60',
            'blocked_dates' => array(),
        );
        
        $settings = get_option('booking_system_settings', $defaults);
        return wp_parse_args($settings, $defaults);
    }
    
    // 檢查日期是否被封鎖
    private function is_date_blocked($date) {
        $settings = $this->get_booking_settings();
        return in_array($date, $settings['blocked_dates']);
    }
    
    // 取得可用時段
    public function get_available_times() {
        check_ajax_referer('booking_nonce', 'nonce');
        
        $date = sanitize_text_field($_POST['date']);
        $duration = isset($_POST['duration']) ? intval($_POST['duration']) : 60;
        
        // 檢查日期是否被封鎖
        if ($this->is_date_blocked($date)) {
            wp_send_json(array('times' => array()));
            return;
        }
        
        $settings = $this->get_booking_settings();
        
        $day_of_week = date('N', strtotime($date));
        
        if (!in_array($day_of_week, $settings['available_days'])) {
            wp_send_json(array('times' => array()));
            return;
        }
        
        $start_time = $settings['start_time'];
        $end_time = $settings['end_time'];
        $interval = intval($settings['time_slot_interval']);
        
        $available_times = array();
        $current_time = strtotime($start_time);
        $end_timestamp = strtotime($end_time);
        
        while ($current_time < $end_timestamp) {
            $time_str = date('H:i', $current_time);
            
            if ($this->is_time_slot_available($date, $time_str, $duration)) {
                $available_times[] = $time_str;
            }
            
            $current_time += ($interval * 60);
        }
        
        wp_send_json(array('times' => $available_times));
    }
    
    // 前台預約表單短代碼
    public function render_booking_form() {
        $settings = $this->get_booking_settings();
        
        ob_start();
        ?>
        <div class="booking-form-container">
            <h3>線上預約</h3>
            <form id="booking-form" class="booking-form" novalidate>
                <div class="form-group">
                    <label for="booking_name">姓名 <span class="required">*</span></label>
                    <input type="text" id="booking_name" name="booking_name" required>
                    <span class="error-message" id="error_name"></span>
                </div>
                
                <div class="form-group">
                    <label for="booking_email">Email <span class="required">*</span></label>
                    <input type="email" id="booking_email" name="booking_email" required>
                    <span class="error-message" id="error_email"></span>
                </div>
                
                <div class="form-group">
                    <label for="booking_phone">電話 <span class="required">*</span></label>
                    <input type="tel" id="booking_phone" name="booking_phone" required pattern="[0-9\-\+\(\)\s]+">
                    <span class="error-message" id="error_phone"></span>
                </div>
                
                <div class="form-group">
                    <label for="booking_date">預約日期 <span class="required">*</span></label>
                    <input type="date" id="booking_date" name="booking_date" required min="<?php echo date('Y-m-d'); ?>">
                    <span class="error-message" id="error_date"></span>
                </div>
                
                <div class="form-group">
                    <label for="booking_duration">預約時長 <span class="required">*</span></label>
                    <select id="booking_duration" name="booking_duration" required>
                        <?php foreach ($settings['available_durations'] as $duration): ?>
                            <option value="<?php echo esc_attr($duration); ?>" <?php selected($duration, $settings['default_duration']); ?>>
                                <?php echo esc_html($duration); ?> 分鐘
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="error-message" id="error_duration"></span>
                </div>
                
                <div class="form-group">
                    <label for="booking_time">預約時間 <span class="required">*</span></label>
                    <select id="booking_time" name="booking_time" required disabled>
                        <option value="">請先選擇日期和時長</option>
                    </select>
                    <span class="error-message" id="error_time"></span>
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
            'message' => $is_available ? '✓ 此時段可預約' : '✗ 此時段已被預約，請選擇其他時間'
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
        
        header('Content-Type: application/json; charset=utf-8');
        
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        $date = sanitize_text_field($_POST['date']);
        $time = sanitize_text_field($_POST['time']);
        $duration = intval($_POST['duration']);
        $note = sanitize_textarea_field($_POST['note']);
        
        $errors = array();
        
        if (empty($name)) {
            $errors['name'] = '請輸入姓名';
        }
        
        if (empty($email) || !is_email($email)) {
            $errors['email'] = '請輸入有效的 Email';
        }
        
        if (empty($phone)) {
            $errors['phone'] = '請輸入電話號碼';
        }
        
        if (empty($date)) {
            $errors['date'] = '請選擇預約日期';
        }
        
        if (empty($time)) {
            $errors['time'] = '請選擇預約時間';
        }
        
        // 檢查日期是否被封鎖
        if ($this->is_date_blocked($date)) {
            $errors['date'] = '此日期不開放預約';
        }
        
        if (!empty($errors)) {
            wp_send_json_error(array(
                'message' => '請修正以下錯誤',
                'errors' => $errors
            ));
            return;
        }
        
        if (!$this->is_time_slot_available($date, $time, $duration)) {
            wp_send_json_error(array('message' => '此時段已被預約，請重新選擇'));
            return;
        }
        
        $post_data = array(
            'post_title' => $name . ' - ' . $date . ' ' . $time,
            'post_type' => 'booking',
            'post_status' => 'pending_booking',
            'post_content' => '',
        );
        
        $booking_id = wp_insert_post($post_data);
        
        if ($booking_id) {
            update_post_meta($booking_id, '_booking_name', $name);
            update_post_meta($booking_id, '_booking_email', $email);
            update_post_meta($booking_id, '_booking_phone', $phone);
            update_post_meta($booking_id, '_booking_date', $date);
            update_post_meta($booking_id, '_booking_time', $time);
            update_post_meta($booking_id, '_booking_duration', $duration);
            update_post_meta($booking_id, '_booking_note', $note);
            
            $admin_email = get_option('admin_email');
            $subject = '新的預約通知';
            $message = "收到新的預約：\n\n";
            $message .= "姓名：{$name}\n";
            $message .= "Email：{$email}\n";
            $message .= "電話：{$phone}\n";
            $message .= "日期：{$date}\n";
            $message .= "時間：{$time}\n";
            $message .= "時長：{$duration}分鐘\n";
            $message .= "備註：{$note}\n";
            
            wp_mail($admin_email, $subject, $message, array('Content-Type: text/plain; charset=UTF-8'));
            
            $customer_subject = '預約確認通知';
            $customer_message = "您好 {$name}，\n\n";
            $customer_message .= "您的預約已收到，詳細資訊如下：\n\n";
            $customer_message .= "日期：{$date}\n";
            $customer_message .= "時間：{$time}\n";
            $customer_message .= "時長：{$duration}分鐘\n\n";
            $customer_message .= "我們會盡快確認您的預約。\n";
            
            wp_mail($email, $customer_subject, $customer_message, array('Content-Type: text/plain; charset=UTF-8'));
            
            wp_send_json_success(array('message' => '預約成功！我們會儘快與您確認。'));
        } else {
            wp_send_json_error(array('message' => '預約失敗，請稍後再試'));
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
                echo esc_html(get_post_meta($post_id, '_booking_duration', true)) . ' 分鐘';
                break;
            case 'booking_status':
                $status = get_post_status($post_id);
                $status_options = array(
                    'pending_booking' => array('label' => '待確認', 'color' => '#ff9800'),
                    'confirmed' => array('label' => '已確認', 'color' => '#4caf50'),
                    'cancelled' => array('label' => '已取消', 'color' => '#f44336'),
                    'completed' => array('label' => '已完成', 'color' => '#2196f3'),
                );
                
                echo '<select class="booking-quick-status" data-booking-id="' . esc_attr($post_id) . '" style="padding: 5px 10px; border-radius: 4px; border: 2px solid ' . $status_options[$status]['color'] . '; background: ' . $status_options[$status]['color'] . '; color: white; font-weight: bold; cursor: pointer;">';
                foreach ($status_options as $status_key => $status_info) {
                    echo '<option value="' . esc_attr($status_key) . '" ' . selected($status, $status_key, false) . ' style="background: white; color: black;">' . esc_html($status_info['label']) . '</option>';
                }
                echo '</select>';
                break;
        }
    }
    
    // 快速更新狀態 (AJAX)
    public function quick_update_status() {
        check_ajax_referer('booking_admin_nonce', 'nonce');
        
        $booking_id = intval($_POST['booking_id']);
        $new_status = sanitize_text_field($_POST['status']);
        
        $allowed_statuses = array('pending_booking', 'confirmed', 'cancelled', 'completed');
        
        if (!in_array($new_status, $allowed_statuses)) {
            wp_send_json_error(array('message' => '無效的狀態'));
            return;
        }
        
        $result = wp_update_post(array(
            'ID' => $booking_id,
            'post_status' => $new_status
        ));
        
        if ($result) {
            wp_send_json_success(array('message' => '狀態已更新'));
        } else {
            wp_send_json_error(array('message' => '更新失敗'));
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
        $note = get_post_meta($post->ID, '_booking_note', true);
        $status = get_post_status($post->ID);
        
        $settings = $this->get_booking_settings();
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
                        <?php foreach ($settings['available_durations'] as $dur): ?>
                            <option value="<?php echo esc_attr($dur); ?>" <?php selected($duration, $dur); ?>><?php echo esc_html($dur); ?>分鐘</option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="booking_status">預約狀態</label></th>
                <td>
                    <select id="booking_status" name="booking_status" style="padding: 8px 12px; font-size: 14px; font-weight: bold;">
                        <option value="pending_booking" <?php selected($status, 'pending_booking'); ?>>🟠 待確認</option>
                        <option value="confirmed" <?php selected($status, 'confirmed'); ?>>🟢 已確認</option>
                        <option value="cancelled" <?php selected($status, 'cancelled'); ?>>🔴 已取消</option>
                        <option value="completed" <?php selected($status, 'completed'); ?>>🔵 已完成</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="booking_note">備註內容</label></th>
                <td>
                    <textarea id="booking_note" name="booking_note" rows="6" class="large-text"><?php echo esc_textarea($note); ?></textarea>
                    <p class="description">客戶填寫的備註資訊</p>
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
        
        if (isset($_POST['booking_note'])) {
            update_post_meta($post_id, '_booking_note', sanitize_textarea_field($_POST['booking_note']));
        }
        
        if (isset($_POST['booking_status']) && $_POST['booking_status'] != $post->post_status) {
            remove_action('save_post_booking', array($this, 'save_booking_meta'), 10);
            wp_update_post(array(
                'ID' => $post_id,
                'post_status' => sanitize_text_field($_POST['booking_status'])
            ));
            add_action('save_post_booking', array($this, 'save_booking_meta'), 10, 2);
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
        
        add_submenu_page(
            'edit.php?post_type=booking',
            '預約設定',
            '預約設定',
            'manage_options',
            'booking-settings',
            array($this, 'render_settings_page')
        );
    }
    
    // 渲染日曆頁面
    public function render_calendar_page() {
        ?>
        <div class="wrap">
            <h1>預約日曆</h1>
            <div id="booking-calendar"></div>
        </div>
        
        <!-- 彈出視窗面板 -->
        <div id="booking-modal-overlay" class="booking-modal-overlay">
            <div class="booking-modal-panel">
                <div class="booking-modal-header">
                    <h2>預約詳情</h2>
                    <span class="booking-modal-close">&times;</span>
                </div>
                <div class="booking-modal-body" id="booking-modal-body">
                    <p>載入中...</p>
                </div>
                <div class="booking-modal-footer">
                    <a href="#" id="booking-edit-link" class="button button-primary" target="_blank">編輯預約</a>
                    <button type="button" class="button" id="booking-modal-close-btn">關閉</button>
                </div>
            </div>
        </div>
        <?php
    }
    
    // 渲染設定頁面
    public function render_settings_page() {
        if (isset($_POST['booking_settings_submit'])) {
            check_admin_referer('booking_settings_action', 'booking_settings_nonce');
            
            // 處理封鎖日期
            $blocked_dates_raw = sanitize_textarea_field($_POST['blocked_dates']);
            $blocked_dates = array();
            if (!empty($blocked_dates_raw)) {
                $dates = explode("\n", $blocked_dates_raw);
                foreach ($dates as $date) {
                    $date = trim($date);
                    if (!empty($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                        $blocked_dates[] = $date;
                    }
                }
            }
            
            $settings = array(
                'available_days' => isset($_POST['available_days']) ? array_map('sanitize_text_field', $_POST['available_days']) : array(),
                'start_time' => sanitize_text_field($_POST['start_time']),
                'end_time' => sanitize_text_field($_POST['end_time']),
                'time_slot_interval' => sanitize_text_field($_POST['time_slot_interval']),
                'available_durations' => isset($_POST['available_durations']) ? array_map('sanitize_text_field', $_POST['available_durations']) : array(),
                'default_duration' => sanitize_text_field($_POST['default_duration']),
                'blocked_dates' => $blocked_dates,
            );
            
            update_option('booking_system_settings', $settings);
            echo '<div class="notice notice-success is-dismissible"><p><strong>設定已儲存！</strong></p></div>';
        }
        
        $settings = $this->get_booking_settings();
        ?>
        <div class="wrap">
            <h1>預約系統設定</h1>
            <p class="description">設定預約系統的運作參數，包含可預約時間、封鎖日期等。</p>
            
            <form method="post" action="" style="max-width: 800px;">
                <?php wp_nonce_field('booking_settings_action', 'booking_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label>可預約星期</label>
                            <p class="description">選擇哪些星期幾開放預約</p>
                        </th>
                        <td>
                            <fieldset>
                                <label style="display: inline-block; margin-right: 15px;">
                                    <input type="checkbox" name="available_days[]" value="1" <?php checked(in_array('1', $settings['available_days'])); ?>> 
                                    <strong>週一</strong>
                                </label>
                                <label style="display: inline-block; margin-right: 15px;">
                                    <input type="checkbox" name="available_days[]" value="2" <?php checked(in_array('2', $settings['available_days'])); ?>> 
                                    <strong>週二</strong>
                                </label>
                                <label style="display: inline-block; margin-right: 15px;">
                                    <input type="checkbox" name="available_days[]" value="3" <?php checked(in_array('3', $settings['available_days'])); ?>> 
                                    <strong>週三</strong>
                                </label>
                                <label style="display: inline-block; margin-right: 15px;">
                                    <input type="checkbox" name="available_days[]" value="4" <?php checked(in_array('4', $settings['available_days'])); ?>> 
                                    <strong>週四</strong>
                                </label>
                                <label style="display: inline-block; margin-right: 15px;">
                                    <input type="checkbox" name="available_days[]" value="5" <?php checked(in_array('5', $settings['available_days'])); ?>> 
                                    <strong>週五</strong>
                                </label>
                                <label style="display: inline-block; margin-right: 15px;">
                                    <input type="checkbox" name="available_days[]" value="6" <?php checked(in_array('6', $settings['available_days'])); ?>> 
                                    <strong>週六</strong>
                                </label>
                                <label style="display: inline-block; margin-right: 15px;">
                                    <input type="checkbox" name="available_days[]" value="7" <?php checked(in_array('7', $settings['available_days'])); ?>> 
                                    <strong>週日</strong>
                                </label>
                                <p class="description">例如：只勾選週一到週五，週末就不會顯示可預約時段</p>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="start_time">營業開始時間</label>
                            <p class="description">每天開始接受預約的時間</p>
                        </th>
                        <td>
                            <input type="time" id="start_time" name="start_time" value="<?php echo esc_attr($settings['start_time']); ?>" style="padding: 8px; font-size: 14px;">
                            <p class="description">例如：09:00 表示早上9點開始營業</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="end_time">營業結束時間</label>
                            <p class="description">每天停止接受預約的時間</p>
                        </th>
                        <td>
                            <input type="time" id="end_time" name="end_time" value="<?php echo esc_attr($settings['end_time']); ?>" style="padding: 8px; font-size: 14px;">
                            <p class="description">例如：18:00 表示下午6點停止營業</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="time_slot_interval">時段間隔</label>
                            <p class="description">每個可預約時段的間隔時間</p>
                        </th>
                        <td>
                            <select id="time_slot_interval" name="time_slot_interval" style="padding: 8px; font-size: 14px;">
                                <option value="15" <?php selected($settings['time_slot_interval'], '15'); ?>>15分鐘</option>
                                <option value="30" <?php selected($settings['time_slot_interval'], '30'); ?>>30分鐘</option>
                                <option value="60" <?php selected($settings['time_slot_interval'], '60'); ?>>60分鐘</option>
                            </select>
                            <p class="description">例如：選擇30分鐘，時段會是 09:00、09:30、10:00...</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label>可選預約時長</label>
                            <p class="description">客戶可以選擇的預約時長選項</p>
                        </th>
                        <td>
                            <fieldset>
                                <label style="display: block; margin-bottom: 8px;">
                                    <input type="checkbox" name="available_durations[]" value="30" <?php checked(in_array('30', $settings['available_durations'])); ?>> 
                                    <strong>30分鐘</strong>
                                </label>
                                <label style="display: block; margin-bottom: 8px;">
                                    <input type="checkbox" name="available_durations[]" value="60" <?php checked(in_array('60', $settings['available_durations'])); ?>> 
                                    <strong>60分鐘 (1小時)</strong>
                                </label>
                                <label style="display: block; margin-bottom: 8px;">
                                    <input type="checkbox" name="available_durations[]" value="90" <?php checked(in_array('90', $settings['available_durations'])); ?>> 
                                    <strong>90分鐘 (1.5小時)</strong>
                                </label>
                                <label style="display: block; margin-bottom: 8px;">
                                    <input type="checkbox" name="available_durations[]" value="120" <?php checked(in_array('120', $settings['available_durations'])); ?>> 
                                    <strong>120分鐘 (2小時)</strong>
                                </label>
                                <p class="description">勾選的選項會出現在前台預約表單中</p>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="default_duration">預設預約時長</label>
                            <p class="description">前台表單預設選擇的時長</p>
                        </th>
                        <td>
                            <select id="default_duration" name="default_duration" style="padding: 8px; font-size: 14px;">
                                <option value="30" <?php selected($settings['default_duration'], '30'); ?>>30分鐘</option>
                                <option value="60" <?php selected($settings['default_duration'], '60'); ?>>60分鐘</option>
                                <option value="90" <?php selected($settings['default_duration'], '90'); ?>>90分鐘</option>
                                <option value="120" <?php selected($settings['default_duration'], '120'); ?>>120分鐘</option>
                            </select>
                            <p class="description">客戶開啟預約表單時預先選擇的時長</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="blocked_dates">封鎖日期 (不可預約)</label>
                            <p class="description">設定特定日期不開放預約<br>例如：年假、國定假日等</p>
                        </th>
                        <td>
                            <textarea id="blocked_dates" name="blocked_dates" rows="8" class="large-text code" placeholder="範例：&#10;2026-02-04&#10;2026-07-01&#10;2026-12-25"><?php echo esc_textarea(implode("\n", $settings['blocked_dates'])); ?></textarea>
                            <p class="description">
                                <strong>格式說明：</strong>每行一個日期，格式為 YYYY-MM-DD<br>
                                <strong>範例：</strong><br>
                                2026-02-04 (農曆春節)<br>
                                2026-07-01 (公司年假)<br>
                                2026-12-25 (聖誕節)<br>
                                <strong>效果：</strong>這些日期在前台不會顯示可預約時段
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <?php submit_button('儲存所有設定', 'primary large', 'booking_settings_submit', false); ?>
                </p>
            </form>
            
            <div style="margin-top: 30px; padding: 20px; background: #f0f0f1; border-left: 4px solid #2271b1;">
                <h3 style="margin-top: 0;">💡 使用提示</h3>
                <ul style="line-height: 1.8;">
                    <li><strong>可預約星期：</strong>控制哪些星期幾開放預約，未勾選的星期不會顯示時段</li>
                    <li><strong>營業時間：</strong>設定每天的營業起訖時間，超過這個範圍不會產生時段</li>
                    <li><strong>時段間隔：</strong>決定可預約時段的密度，間隔越小時段越多</li>
                    <li><strong>封鎖日期：</strong>適用於臨時休假、特殊節日等不營業的日期</li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    // 取得預約詳情 (AJAX)
    public function get_booking_details() {
        check_ajax_referer('booking_admin_nonce', 'nonce');
        
        $booking_id = intval($_POST['booking_id']);
        
        if (!$booking_id) {
            wp_send_json_error(array('message' => '無效的預約ID'));
            return;
        }
        
        $booking = get_post($booking_id);
        
        if (!$booking || $booking->post_type !== 'booking') {
            wp_send_json_error(array('message' => '找不到預約'));
            return;
        }
        
        $name = get_post_meta($booking_id, '_booking_name', true);
        $email = get_post_meta($booking_id, '_booking_email', true);
        $phone = get_post_meta($booking_id, '_booking_phone', true);
        $date = get_post_meta($booking_id, '_booking_date', true);
        $time = get_post_meta($booking_id, '_booking_time', true);
        $duration = get_post_meta($booking_id, '_booking_duration', true);
        $note = get_post_meta($booking_id, '_booking_note', true);
        $status = get_post_status($booking_id);
        
        $status_labels = array(
            'pending_booking' => '🟠 待確認',
            'confirmed' => '🟢 已確認',
            'cancelled' => '🔴 已取消',
            'completed' => '🔵 已完成',
        );
        
        $status_label = isset($status_labels[$status]) ? $status_labels[$status] : $status;
        
        $html = '<table class="booking-details-table">';
        $html .= '<tr><th>姓名：</th><td>' . esc_html($name) . '</td></tr>';
        $html .= '<tr><th>Email：</th><td>' . esc_html($email) . '</td></tr>';
        $html .= '<tr><th>電話：</th><td>' . esc_html($phone) . '</td></tr>';
        $html .= '<tr><th>預約日期：</th><td>' . esc_html($date) . '</td></tr>';
        $html .= '<tr><th>預約時間：</th><td>' . esc_html($time) . '</td></tr>';
        $html .= '<tr><th>預約時長：</th><td>' . esc_html($duration) . ' 分鐘</td></tr>';
        $html .= '<tr><th>狀態：</th><td><strong>' . esc_html($status_label) . '</strong></td></tr>';
        
        if (!empty($note)) {
            $html .= '<tr><th>備註：</th><td>' . nl2br(esc_html($note)) . '</td></tr>';
        }
        
        $html .= '</table>';
        
        wp_send_json_success(array(
            'html' => $html,
            'edit_url' => admin_url('post.php?post=' . $booking_id . '&action=edit')
        ));
    }
    
    // 後台載入腳本
    public function enqueue_admin_scripts($hook) {
        // 所有預約列表頁面
        if ('edit.php' === $hook && isset($_GET['post_type']) && $_GET['post_type'] === 'booking') {
            wp_enqueue_script('booking-admin-list', plugin_dir_url(__FILE__) . 'js/booking-admin-list.js', array('jquery'), '2.2', true);
            wp_localize_script('booking-admin-list', 'bookingAdminData', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('booking_admin_nonce')
            ));
        }
        
        // 日曆頁面
        if ('booking_page_booking-calendar' === $hook) {
            wp_enqueue_style('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css', array(), '6.1.10');
            wp_enqueue_script('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js', array(), '6.1.10', true);
            wp_enqueue_script('fullcalendar-zh', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/locales/zh-tw.global.min.js', array('fullcalendar'), '6.1.10', true);
            
            wp_enqueue_style('booking-admin-style', plugin_dir_url(__FILE__) . 'css/booking-admin.css', array(), '2.2');
            wp_enqueue_script('booking-calendar', plugin_dir_url(__FILE__) . 'js/booking-calendar.js', array('jquery', 'fullcalendar', 'fullcalendar-zh'), '2.2', true);
            
            $bookings = $this->get_all_bookings_for_calendar();
            wp_localize_script('booking-calendar', 'bookingCalendarData', array(
                'bookings' => $bookings,
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('booking_admin_nonce')
            ));
        }
    }
    
    // 獲取所有預約資料供日曆使用
    private function get_all_bookings_for_calendar() {
        $args = array(
            'post_type' => 'booking',
            'post_status' => array('pending_booking', 'confirmed', 'completed', 'cancelled'),
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
            
            if (empty($date) || empty($time)) {
                continue;
            }
            
            $start = $date . 'T' . $time;
            $end = date('Y-m-d\TH:i:s', strtotime($start) + ($duration * 60));
            
            $color = '#3788d8';
            if ($status === 'confirmed') $color = '#4caf50';
            if ($status === 'pending_booking') $color = '#ff9800';
            if ($status === 'completed') $color = '#2196f3';
            if ($status === 'cancelled') $color = '#f44336';
            
            $events[] = array(
                'id' => $booking->ID,
                'title' => $name,
                'start' => $start,
                'end' => $end,
                'color' => $color,
                'extendedProps' => array(
                    'bookingId' => $booking->ID
                )
            );
        }
        
        return $events;
    }
}

// 初始化外掛
new BookingSystem();
