<?php
/**
 * Plugin Name: 預約系統
 * Description: 完整的預約功能,包含前台預約、後台管理、時段衝突檢查
 * Version: 2.1
 * Author: Your Name
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
        
        // 修改新增預約後的重定向
        add_filter('redirect_post_location', array($this, 'redirect_after_new_booking'), 10, 2);
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
    
    // 新增預約後重定向到列表頁
    public function redirect_after_new_booking($location, $post_id) {
        $post = get_post($post_id);
        
        if ($post && $post->post_type === 'booking' && isset($_POST['save']) && $_POST['save'] === '發佈') {
            // 如果是新增預約,重定向到所有預約列表
            return admin_url('edit.php?post_type=booking');
        }
        
        return $location;
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
            'supports' => array('title', 'editor'),
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
        wp_enqueue_style('booking-style', plugin_dir_url(__FILE__) . 'css/booking-style.css', array(), '2.1');
        wp_enqueue_script('booking-script', plugin_dir_url(__FILE__) . 'js/booking-script.js', array('jquery'), '2.1', true);
        
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
        );
        
        $settings = get_option('booking_system_settings', $defaults);
        return wp_parse_args($settings, $defaults);
    }
    
    // 取得可用時段
    public function get_available_times() {
        check_ajax_referer('booking_nonce', 'nonce');
        
        $date = sanitize_text_field($_POST['date']);
        $duration = isset($_POST['duration']) ? intval($_POST['duration']) : 60;
        
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
            'post_content' => $note,
        );
        
        $booking_id = wp_insert_post($post_data);
        
        if ($booking_id) {
            update_post_meta($booking_id, '_booking_name', $name);
            update_post_meta($booking_id, '_booking_email', $email);
            update_post_meta($booking_id, '_booking_phone', $phone);
            update_post_meta($booking_id, '_booking_date', $date);
            update_post_meta($booking_id, '_booking_time', $time);
            update_post_meta($booking_id, '_booking_duration', $duration);
            
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
                $status_labels = array(
                    'pending_booking' => '<span style="color: orange;">●</span> 待確認',
                    'confirmed' => '<span style="color: green;">●</span> 已確認',
                    'cancelled' => '<span style="color: red;">●</span> 已取消',
                    'completed' => '<span style="color: blue;">●</span> 已完成',
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
                    <select id="booking_status" name="booking_status">
                        <option value="pending_booking" <?php selected($status, 'pending_booking'); ?>>待確認</option>
                        <option value="confirmed" <?php selected($status, 'confirmed'); ?>>已確認</option>
                        <option value="cancelled" <?php selected($status, 'cancelled'); ?>>已取消</option>
                        <option value="completed" <?php selected($status, 'completed'); ?>>已完成</option>
                    </select>
                </td>
            </tr>
        </table>
        
        <p><strong>備註內容：</strong></p>
        <p>請在下方編輯器中編輯備註內容</p>
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
            
            $settings = array(
                'available_days' => isset($_POST['available_days']) ? array_map('sanitize_text_field', $_POST['available_days']) : array(),
                'start_time' => sanitize_text_field($_POST['start_time']),
                'end_time' => sanitize_text_field($_POST['end_time']),
                'time_slot_interval' => sanitize_text_field($_POST['time_slot_interval']),
                'available_durations' => isset($_POST['available_durations']) ? array_map('sanitize_text_field', $_POST['available_durations']) : array(),
                'default_duration' => sanitize_text_field($_POST['default_duration']),
            );
            
            update_option('booking_system_settings', $settings);
            echo '<div class="notice notice-success"><p>設定已儲存</p></div>';
        }
        
        $settings = $this->get_booking_settings();
        ?>
        <div class="wrap">
            <h1>預約系統設定</h1>
            <form method="post" action="">
                <?php wp_nonce_field('booking_settings_action', 'booking_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">可預約星期</th>
                        <td>
                            <fieldset>
                                <label><input type="checkbox" name="available_days[]" value="1" <?php checked(in_array('1', $settings['available_days'])); ?>> 週一</label><br>
                                <label><input type="checkbox" name="available_days[]" value="2" <?php checked(in_array('2', $settings['available_days'])); ?>> 週二</label><br>
                                <label><input type="checkbox" name="available_days[]" value="3" <?php checked(in_array('3', $settings['available_days'])); ?>> 週三</label><br>
                                <label><input type="checkbox" name="available_days[]" value="4" <?php checked(in_array('4', $settings['available_days'])); ?>> 週四</label><br>
                                <label><input type="checkbox" name="available_days[]" value="5" <?php checked(in_array('5', $settings['available_days'])); ?>> 週五</label><br>
                                <label><input type="checkbox" name="available_days[]" value="6" <?php checked(in_array('6', $settings['available_days'])); ?>> 週六</label><br>
                                <label><input type="checkbox" name="available_days[]" value="7" <?php checked(in_array('7', $settings['available_days'])); ?>> 週日</label>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="start_time">開始時間</label></th>
                        <td>
                            <input type="time" id="start_time" name="start_time" value="<?php echo esc_attr($settings['start_time']); ?>">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="end_time">結束時間</label></th>
                        <td>
                            <input type="time" id="end_time" name="end_time" value="<?php echo esc_attr($settings['end_time']); ?>">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="time_slot_interval">時段間隔(分鐘)</label></th>
                        <td>
                            <select id="time_slot_interval" name="time_slot_interval">
                                <option value="15" <?php selected($settings['time_slot_interval'], '15'); ?>>15分鐘</option>
                                <option value="30" <?php selected($settings['time_slot_interval'], '30'); ?>>30分鐘</option>
                                <option value="60" <?php selected($settings['time_slot_interval'], '60'); ?>>60分鐘</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">可選預約時長</th>
                        <td>
                            <fieldset>
                                <label><input type="checkbox" name="available_durations[]" value="30" <?php checked(in_array('30', $settings['available_durations'])); ?>> 30分鐘</label><br>
                                <label><input type="checkbox" name="available_durations[]" value="60" <?php checked(in_array('60', $settings['available_durations'])); ?>> 60分鐘</label><br>
                                <label><input type="checkbox" name="available_durations[]" value="90" <?php checked(in_array('90', $settings['available_durations'])); ?>> 90分鐘</label><br>
                                <label><input type="checkbox" name="available_durations[]" value="120" <?php checked(in_array('120', $settings['available_durations'])); ?>> 120分鐘</label>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="default_duration">預設預約時長</label></th>
                        <td>
                            <select id="default_duration" name="default_duration">
                                <option value="30" <?php selected($settings['default_duration'], '30'); ?>>30分鐘</option>
                                <option value="60" <?php selected($settings['default_duration'], '60'); ?>>60分鐘</option>
                                <option value="90" <?php selected($settings['default_duration'], '90'); ?>>90分鐘</option>
                                <option value="120" <?php selected($settings['default_duration'], '120'); ?>>120分鐘</option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('儲存設定', 'primary', 'booking_settings_submit'); ?>
            </form>
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
        $status = get_post_status($booking_id);
        
        $status_labels = array(
            'pending_booking' => '待確認',
            'confirmed' => '已確認',
            'cancelled' => '已取消',
            'completed' => '已完成',
        );
        
        $status_label = isset($status_labels[$status]) ? $status_labels[$status] : $status;
        
        $html = '<table class="booking-details-table">';
        $html .= '<tr><th>姓名：</th><td>' . esc_html($name) . '</td></tr>';
        $html .= '<tr><th>Email：</th><td>' . esc_html($email) . '</td></tr>';
        $html .= '<tr><th>電話：</th><td>' . esc_html($phone) . '</td></tr>';
        $html .= '<tr><th>預約日期：</th><td>' . esc_html($date) . '</td></tr>';
        $html .= '<tr><th>預約時間：</th><td>' . esc_html($time) . '</td></tr>';
        $html .= '<tr><th>預約時長：</th><td>' . esc_html($duration) . ' 分鐘</td></tr>';
        $html .= '<tr><th>狀態：</th><td>' . esc_html($status_label) . '</td></tr>';
        
        if (!empty($booking->post_content)) {
            $html .= '<tr><th>備註：</th><td>' . nl2br(esc_html($booking->post_content)) . '</td></tr>';
        }
        
        $html .= '</table>';
        
        wp_send_json_success(array(
            'html' => $html,
            'edit_url' => admin_url('post.php?post=' . $booking_id . '&action=edit')
        ));
    }
    
    // 後台載入腳本
    public function enqueue_admin_scripts($hook) {
        if ('booking_page_booking-calendar' === $hook) {
            wp_enqueue_style('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css', array(), '6.1.10');
            wp_enqueue_script('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js', array(), '6.1.10', true);
            wp_enqueue_script('fullcalendar-zh', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/locales/zh-tw.global.min.js', array('fullcalendar'), '6.1.10', true);
            
            wp_enqueue_style('booking-admin-style', plugin_dir_url(__FILE__) . 'css/booking-admin.css', array(), '2.1');
            wp_enqueue_script('booking-calendar', plugin_dir_url(__FILE__) . 'js/booking-calendar.js', array('jquery', 'fullcalendar', 'fullcalendar-zh'), '2.1', true);
            
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
            if ($status === 'confirmed') $color = '#28a745';
            if ($status === 'pending_booking') $color = '#ffc107';
            if ($status === 'completed') $color = '#6c757d';
            if ($status === 'cancelled') $color = '#dc3545';
            
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
