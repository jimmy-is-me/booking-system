<?php
/**
 * Plugin Name: 預約系統 by WumetaX
 * Description: 完整的預約功能,包含前台預約、後台管理、時段衝突檢查 | 由 WumetaX 專業開發
 * Version: 2.5
 * Author: WumetaX
 * Author URI: https://wumetax.com
 * Text Domain: booking-system
 */

if (!defined('ABSPATH')) exit;

class BookingSystem {
    
    public function __construct() {
        add_action('init', array($this, 'set_charset'));
        add_action('init', array($this, 'register_booking_post_type'));
        add_action('init', array($this, 'register_booking_statuses'));
        
        add_shortcode('booking_form', array($this, 'render_booking_form'));
        
        add_action('wp_ajax_submit_booking', array($this, 'handle_booking_submission'));
        add_action('wp_ajax_nopriv_submit_booking', array($this, 'handle_booking_submission'));
        add_action('wp_ajax_check_availability', array($this, 'check_time_availability'));
        add_action('wp_ajax_nopriv_check_availability', array($this, 'check_time_availability'));
        add_action('wp_ajax_get_available_times', array($this, 'get_available_times'));
        add_action('wp_ajax_nopriv_get_available_times', array($this, 'get_available_times'));
        add_action('wp_ajax_get_booking_details', array($this, 'get_booking_details'));
        add_action('wp_ajax_quick_update_status', array($this, 'quick_update_status'));
        add_action('wp_ajax_add_blocked_date', array($this, 'add_blocked_date'));
        add_action('wp_ajax_remove_blocked_date', array($this, 'remove_blocked_date'));
        
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_filter('manage_booking_posts_columns', array($this, 'set_custom_columns'));
        add_action('manage_booking_posts_custom_column', array($this, 'custom_column_content'), 10, 2);
        add_action('add_meta_boxes', array($this, 'add_booking_meta_boxes'));
        add_action('save_post_booking', array($this, 'save_booking_meta'), 10, 2);
        
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        add_filter('use_block_editor_for_post_type', array($this, 'disable_gutenberg_for_booking'), 10, 2);
        add_filter('display_post_states', array($this, 'display_booking_states'), 10, 2);
        add_filter('admin_footer_text', array($this, 'admin_footer_text'));
        
        add_action('admin_menu', array($this, 'remove_post_attributes'));
        
        register_activation_hook(__FILE__, array($this, 'create_blocked_dates_table'));
    }
    
    public function admin_footer_text($text) {
        $screen = get_current_screen();
        if ($screen && (strpos($screen->id, 'booking') !== false)) {
            $text = '<span style="color: #666;">預約系統 by <a href="https://wumetax.com" target="_blank" style="color: #0073aa; text-decoration: none; font-weight: 600;">WumetaX</a> | 版本 2.5</span>';
        }
        return $text;
    }
    
    public function create_blocked_dates_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'booking_blocked_dates';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            start_date date NOT NULL,
            end_date date NOT NULL,
            note text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY start_date (start_date),
            KEY end_date (end_date)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public function set_charset() {
        if (!is_admin()) {
            header('Content-Type: text/html; charset=UTF-8');
        }
    }
    
    public function disable_gutenberg_for_booking($use_block_editor, $post_type) {
        if ($post_type === 'booking') {
            return false;
        }
        return $use_block_editor;
    }
    
    public function remove_post_attributes() {
        remove_meta_box('pageparentdiv', 'booking', 'side');
    }
    
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
    
    public function enqueue_frontend_scripts() {
        wp_enqueue_style('booking-style', plugin_dir_url(__FILE__) . 'css/booking-style.css', array(), '2.5');
        wp_enqueue_script('booking-script', plugin_dir_url(__FILE__) . 'js/booking-script.js', array('jquery'), '2.5', true);
        
        $settings = $this->get_booking_settings();
        
        wp_localize_script('booking-script', 'bookingAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('booking_nonce'),
            'availableDays' => $settings['available_days'],
            'blockedDates' => $this->get_all_blocked_dates_for_js(),
            'messages' => array(
                'required' => '此欄位為必填',
                'invalid_email' => '請輸入有效的 Email',
                'invalid_phone' => '請輸入有效的電話號碼',
                'select_time' => '請選擇預約時間',
            )
        ));
    }
    
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
    
    private function get_all_blocked_dates_for_js() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'booking_blocked_dates';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return array();
        }
        
        $results = $wpdb->get_results("SELECT start_date, end_date FROM $table_name ORDER BY start_date", ARRAY_A);
        
        $all_dates = array();
        foreach ($results as $row) {
            $start = new DateTime($row['start_date']);
            $end = new DateTime($row['end_date']);
            $end->modify('+1 day');
            
            $interval = new DateInterval('P1D');
            $period = new DatePeriod($start, $interval, $end);
            
            foreach ($period as $date) {
                $all_dates[] = $date->format('Y-m-d');
            }
        }
        
        return array_unique($all_dates);
    }
    
    private function is_date_blocked($date) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'booking_blocked_dates';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return false;
        }
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE %s BETWEEN start_date AND end_date",
            $date
        ));
        
        return $count > 0;
    }
    
    public function get_available_times() {
        check_ajax_referer('booking_nonce', 'nonce');
        
        $date = sanitize_text_field($_POST['date']);
        $duration = isset($_POST['duration']) ? intval($_POST['duration']) : 60;
        
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
                    <input type="tel" id="booking_phone" name="booking_phone" required>
                    <span class="error-message" id="error_phone"></span>
                </div>
                
                <div class="form-group">
                    <label for="booking_date">預約日期 <span class="required">*</span></label>
                    <input type="date" id="booking_date" name="booking_date" required min="<?php echo date('Y-m-d'); ?>">
                    <span class="error-message" id="error_date"></span>
                </div>
                
                <div class="form-group" id="duration-group">
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
                
                <div class="form-group" id="time-group">
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
                
                <button type="submit" class="submit-booking-btn">送出預約</button>
            </form>
            
            <div id="booking-response" class="booking-response"></div>
        </div>
        <?php
        return ob_get_clean();
    }
    
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
                    'pending_booking' => array('label' => '待確認', 'color' => '#ff9800', 'icon' => '🟠'),
                    'confirmed' => array('label' => '已確認', 'color' => '#4caf50', 'icon' => '🟢'),
                    'cancelled' => array('label' => '已取消', 'color' => '#f44336', 'icon' => '🔴'),
                    'completed' => array('label' => '已完成', 'color' => '#2196f3', 'icon' => '🔵'),
                );
                
                echo '<div style="display: flex; align-items: center; gap: 8px;">';
                echo '<span style="font-size: 18px;">' . $status_options[$status]['icon'] . '</span>';
                echo '<select class="booking-quick-status" data-booking-id="' . esc_attr($post_id) . '" style="padding: 6px 10px; border-radius: 4px; border: 2px solid ' . $status_options[$status]['color'] . '; background: white; color: ' . $status_options[$status]['color'] . '; font-weight: bold; cursor: pointer;">';
                foreach ($status_options as $status_key => $status_info) {
                    echo '<option value="' . esc_attr($status_key) . '" ' . selected($status, $status_key, false) . '>' . esc_html($status_info['label']) . '</option>';
                }
                echo '</select>';
                echo '</div>';
                break;
        }
    }
    
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
    
    public function render_calendar_page() {
        ?>
        <div class="wrap">
            <h1>預約日曆</h1>
            <div id="booking-calendar"></div>
        </div>
        
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
    
    public function render_settings_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'booking_blocked_dates';
        
        $this->create_blocked_dates_table();
        
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
            echo '<div class="notice notice-success is-dismissible"><p><strong>設定已儲存！</strong></p></div>';
        }
        
        $settings = $this->get_booking_settings();
        $blocked_dates = $wpdb->get_results("SELECT * FROM $table_name ORDER BY start_date DESC");
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">預約系統設定</h1>
            <p class="description" style="margin-top: 5px; margin-bottom: 20px;">設定您的預約系統營業時間、可預約時段和封鎖日期</p>
            
            <h2 class="nav-tab-wrapper">
                <a href="#general-settings" class="nav-tab nav-tab-active">一般設定</a>
                <a href="#blocked-dates" class="nav-tab">封鎖日期管理</a>
            </h2>
            
            <!-- 一般設定 -->
            <div id="general-settings" class="tab-content" style="display: block;">
                <form method="post" action="" style="max-width: 900px; margin-top: 20px;">
                    <?php wp_nonce_field('booking_settings_action', 'booking_settings_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label>可預約星期</label>
                            </th>
                            <td>
                                <fieldset>
                                    <legend class="screen-reader-text"><span>可預約星期</span></legend>
                                    <p class="description" style="margin-bottom: 15px;">
                                        <strong>功能說明：</strong>選擇您開放預約的星期。只有勾選的星期會在前台日曆中顯示為可選日期。<br>
                                        <strong>範例：</strong>如果只勾選週一到週五,週六日將不會出現在預約日曆中。
                                    </p>
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
                                </fieldset>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="start_time">營業開始時間</label></th>
                            <td>
                                <input type="time" id="start_time" name="start_time" value="<?php echo esc_attr($settings['start_time']); ?>" style="padding: 8px; font-size: 14px;">
                                <p class="description">
                                    <strong>功能說明：</strong>設定您每天開始接受預約的時間。<br>
                                    <strong>範例：</strong>設定為 09:00,表示早上9點開始可以預約。
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="end_time">營業結束時間</label></th>
                            <td>
                                <input type="time" id="end_time" name="end_time" value="<?php echo esc_attr($settings['end_time']); ?>" style="padding: 8px; font-size: 14px;">
                                <p class="description">
                                    <strong>功能說明：</strong>設定您每天最後一個預約時段的開始時間。<br>
                                    <strong>範例：</strong>設定為 18:00,最後一個預約時段將從下午6點開始。
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="time_slot_interval">時段間隔</label></th>
                            <td>
                                <select id="time_slot_interval" name="time_slot_interval" style="padding: 8px; font-size: 14px;">
                                    <option value="15" <?php selected($settings['time_slot_interval'], '15'); ?>>15分鐘</option>
                                    <option value="30" <?php selected($settings['time_slot_interval'], '30'); ?>>30分鐘</option>
                                    <option value="60" <?php selected($settings['time_slot_interval'], '60'); ?>>60分鐘</option>
                                </select>
                                <p class="description">
                                    <strong>功能說明：</strong>設定每個時段之間的間隔時間。<br>
                                    <strong>範例：</strong>選擇30分鐘,可預約時間將會是 09:00、09:30、10:00、10:30 等。
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label>可選預約時長</label></th>
                            <td>
                                <fieldset>
                                    <legend class="screen-reader-text"><span>可選預約時長</span></legend>
                                    <p class="description" style="margin-bottom: 15px;">
                                        <strong>功能說明：</strong>客戶可以選擇的預約時長選項。<br>
                                        <strong>範例：</strong>勾選 30、60、90 分鐘,客戶就可以在這三個選項中選擇。
                                    </p>
                                    <label style="display: block; margin-bottom: 8px;">
                                        <input type="checkbox" name="available_durations[]" value="30" <?php checked(in_array('30', $settings['available_durations'])); ?>> 
                                        <strong>30分鐘</strong> - 短時間服務
                                    </label>
                                    <label style="display: block; margin-bottom: 8px;">
                                        <input type="checkbox" name="available_durations[]" value="60" <?php checked(in_array('60', $settings['available_durations'])); ?>> 
                                        <strong>60分鐘</strong> - 標準服務時長
                                    </label>
                                    <label style="display: block; margin-bottom: 8px;">
                                        <input type="checkbox" name="available_durations[]" value="90" <?php checked(in_array('90', $settings['available_durations'])); ?>> 
                                        <strong>90分鐘</strong> - 較長時間服務
                                    </label>
                                    <label style="display: block; margin-bottom: 8px;">
                                        <input type="checkbox" name="available_durations[]" value="120" <?php checked(in_array('120', $settings['available_durations'])); ?>> 
                                        <strong>120分鐘</strong> - 完整長時間服務
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="default_duration">預設預約時長</label></th>
                            <td>
                                <select id="default_duration" name="default_duration" style="padding: 8px; font-size: 14px;">
                                    <option value="30" <?php selected($settings['default_duration'], '30'); ?>>30分鐘</option>
                                    <option value="60" <?php selected($settings['default_duration'], '60'); ?>>60分鐘</option>
                                    <option value="90" <?php selected($settings['default_duration'], '90'); ?>>90分鐘</option>
                                    <option value="120" <?php selected($settings['default_duration'], '120'); ?>>120分鐘</option>
                                </select>
                                <p class="description">
                                    <strong>功能說明：</strong>客戶開啟預約表單時,預設選中的時長選項。<br>
                                    <strong>建議：</strong>選擇您最常見的服務時長作為預設值。
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button('儲存設定', 'primary large', 'booking_settings_submit'); ?>
                </form>
            </div>
            
            <!-- 封鎖日期管理 -->
            <div id="blocked-dates" class="tab-content" style="display: none; margin-top: 20px;">
                <div style="max-width: 1100px;">
                    <h3>新增封鎖日期</h3>
                    <p class="description">
                        <strong>功能說明：</strong>封鎖特定日期或日期區間,讓客戶無法在這些日期預約。<br>
                        <strong>使用情境：</strong>適用於國定假日、公司年假、臨時休息等情況。<br>
                        <strong>操作方式：</strong>單一日期請在開始和結束填寫相同日期;日期區間請填寫不同的開始和結束日期。
                    </p>
                    
                    <div style="background: white; padding: 20px; border: 1px solid #ccc; border-radius: 4px; margin: 20px 0;">
                        <table class="form-table">
                            <tr>
                                <th><label for="new_blocked_start_date">開始日期</label></th>
                                <td>
                                    <input type="date" id="new_blocked_start_date" style="padding: 8px; font-size: 14px; width: 200px;">
                                    <p class="description">封鎖的起始日期(單一日期請填寫相同日期)</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="new_blocked_end_date">結束日期</label></th>
                                <td>
                                    <input type="date" id="new_blocked_end_date" style="padding: 8px; font-size: 14px; width: 200px;">
                                    <p class="description">封鎖的結束日期(單一日期請填寫相同日期)</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="new_blocked_note">備註說明</label></th>
                                <td>
                                    <input type="text" id="new_blocked_note" placeholder="例如：春節假期、公司年假、設備維修" style="padding: 8px; font-size: 14px; width: 400px;">
                                    <p class="description">選填,記錄此區間封鎖的原因,方便日後管理</p>
                                </td>
                            </tr>
                        </table>
                        <button type="button" id="add_blocked_date_btn" class="button button-primary">新增封鎖日期</button>
                    </div>
                    
                    <h3>已封鎖的日期列表</h3>
                    <p class="description" style="margin-bottom: 15px;">
                        以下是所有已設定的封鎖日期區間。客戶將無法在這些日期範圍內進行預約。
                    </p>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width: 130px;">開始日期</th>
                                <th style="width: 130px;">結束日期</th>
                                <th>備註說明</th>
                                <th style="width: 150px;">建立時間</th>
                                <th style="width: 100px;">操作</th>
                            </tr>
                        </thead>
                        <tbody id="blocked-dates-list">
                            <?php if (empty($blocked_dates)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 30px; color: #666;">
                                        目前沒有封鎖日期<br>
                                        <small style="color: #999;">您可以在上方新增需要封鎖的日期或日期區間</small>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($blocked_dates as $blocked): ?>
                                    <?php
                                    $is_range = $blocked->start_date !== $blocked->end_date;
                                    $date_display = $is_range ? '區間' : '單日';
                                    ?>
                                    <tr data-id="<?php echo esc_attr($blocked->id); ?>">
                                        <td><strong><?php echo esc_html($blocked->start_date); ?></strong></td>
                                        <td><strong><?php echo esc_html($blocked->end_date); ?></strong></td>
                                        <td>
                                            <?php if ($blocked->note): ?>
                                                <span class="dashicons dashicons-info" style="color: #2271b1;"></span>
                                                <?php echo esc_html($blocked->note); ?>
                                            <?php else: ?>
                                                <span style="color: #999;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html(date('Y-m-d H:i', strtotime($blocked->created_at))); ?></td>
                                        <td>
                                            <button type="button" class="button button-small remove-blocked-date" data-id="<?php echo esc_attr($blocked->id); ?>" style="color: #b32d2e;">
                                                刪除
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                $('.tab-content').hide();
                $($(this).attr('href')).show();
            });
        });
        </script>
        <?php
    }
    
    public function add_blocked_date() {
        check_ajax_referer('booking_admin_nonce', 'nonce');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'booking_blocked_dates';
        
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);
        $note = sanitize_text_field($_POST['note']);
        
        if (empty($start_date) || empty($end_date)) {
            wp_send_json_error(array('message' => '請選擇開始和結束日期'));
            return;
        }
        
        if (strtotime($end_date) < strtotime($start_date)) {
            wp_send_json_error(array('message' => '結束日期不能早於開始日期'));
            return;
        }
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'start_date' => $start_date,
                'end_date' => $end_date,
                'note' => $note,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s')
        );
        
        if ($result !== false) {
            $inserted_id = $wpdb->insert_id;
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $inserted_id), ARRAY_A);
            
            wp_send_json_success(array(
                'message' => '封鎖日期已新增',
                'data' => $row
            ));
        } else {
            wp_send_json_error(array('message' => '新增失敗: ' . $wpdb->last_error));
        }
    }
    
    public function remove_blocked_date() {
        check_ajax_referer('booking_admin_nonce', 'nonce');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'booking_blocked_dates';
        
        $id = intval($_POST['id']);
        
        $result = $wpdb->delete($table_name, array('id' => $id), array('%d'));
        
        if ($result) {
            wp_send_json_success(array('message' => '封鎖日期已刪除'));
        } else {
            wp_send_json_error(array('message' => '刪除失敗'));
        }
    }
    
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
    
    public function enqueue_admin_scripts($hook) {
        if ('edit.php' === $hook && isset($_GET['post_type']) && $_GET['post_type'] === 'booking') {
            wp_enqueue_script('booking-admin-list', plugin_dir_url(__FILE__) . 'js/booking-admin-list.js', array('jquery'), '2.5', true);
            wp_localize_script('booking-admin-list', 'bookingAdminData', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('booking_admin_nonce')
            ));
        }
        
        if ('booking_page_booking-settings' === $hook) {
            wp_enqueue_script('booking-settings', plugin_dir_url(__FILE__) . 'js/booking-settings.js', array('jquery'), '2.5', true);
            wp_localize_script('booking-settings', 'bookingAdminData', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('booking_admin_nonce')
            ));
        }
        
        if ('booking_page_booking-calendar' === $hook) {
            wp_enqueue_style('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css', array(), '6.1.10');
            wp_enqueue_script('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js', array(), '6.1.10', true);
            wp_enqueue_script('fullcalendar-zh', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/locales/zh-tw.global.min.js', array('fullcalendar'), '6.1.10', true);
            
            wp_enqueue_style('booking-admin-style', plugin_dir_url(__FILE__) . 'css/booking-admin.css', array(), '2.5');
            wp_enqueue_script('booking-calendar', plugin_dir_url(__FILE__) . 'js/booking-calendar.js', array('jquery', 'fullcalendar', 'fullcalendar-zh'), '2.5', true);
            
            $bookings = $this->get_all_bookings_for_calendar();
            wp_localize_script('booking-calendar', 'bookingCalendarData', array(
                'bookings' => $bookings,
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('booking_admin_nonce')
            ));
        }
    }
    
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

new BookingSystem();
