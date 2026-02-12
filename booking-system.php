<?php
/**
 * Plugin Name: 預約系統 by WumetaX
 * Description: 完整的預約功能,包含前台預約、後台管理、時段衝突檢查、信件通知、驗證碼防護 | 由 WumetaX 專業開發
 * Version: 3.1
 * Author: WumetaX
 * Author URI: https://wumetax.com
 * Text Domain: booking-system
 */

if (!defined('ABSPATH')) exit;

class BookingSystem {
    
    private $table_version = '1.2';
    
    public function __construct() {
        add_action('plugins_loaded', array($this, 'check_and_update_table'));
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
        add_action('wp_ajax_get_available_dates', array($this, 'get_available_dates'));
        add_action('wp_ajax_nopriv_get_available_dates', array($this, 'get_available_dates'));
        add_action('wp_ajax_get_booking_details', array($this, 'get_booking_details'));
        add_action('wp_ajax_quick_update_status', array($this, 'quick_update_status'));
        add_action('wp_ajax_add_blocked_date', array($this, 'add_blocked_date'));
        add_action('wp_ajax_remove_blocked_date', array($this, 'remove_blocked_date'));
        add_action('wp_ajax_verify_captcha', array($this, 'verify_captcha'));
        add_action('wp_ajax_nopriv_verify_captcha', array($this, 'verify_captcha'));
        add_action('wp_ajax_delete_email_log', array($this, 'delete_email_log'));
        add_action('wp_ajax_get_email_log_detail', array($this, 'get_email_log_detail'));
        add_action('wp_ajax_send_test_email', array($this, 'send_test_email'));
        
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
        
        register_activation_hook(__FILE__, array($this, 'plugin_activation'));
    }
    
    public function plugin_activation() {
        $this->create_blocked_dates_table();
        $this->create_email_logs_table();
        update_option('booking_blocked_dates_table_version', $this->table_version);
        $this->set_default_email_templates();
    }
    
    private function set_default_email_templates() {
        $templates = get_option('booking_email_templates');
        
        if ($templates && isset($templates['customer_subject'])) {
            return;
        }
        
        $default_templates = array(
            'customer_subject' => '預約確認通知 - {site_name}',
            'customer_body' => "親愛的 {customer_name},您好!\n\n感謝您的預約,以下是您的預約資訊:\n\n預約日期:{booking_date}\n預約時間:{booking_time}\n預約時長:{booking_duration} 分鐘\n聯絡電話:{customer_phone}\n備註說明:{booking_note}\n\n我們已收到您的預約申請,將盡快與您確認。\n如有任何問題,歡迎隨時與我們聯繫。\n\n此信件為系統自動發送,請勿直接回覆。\n\n{site_name}\n{site_url}",
            'admin_subject' => '新預約通知 - {customer_name}',
            'admin_body' => "收到新的預約申請\n\n客戶資訊:\n姓名:{customer_name}\nEmail:{customer_email}\n電話:{customer_phone}\n\n預約資訊:\n日期:{booking_date}\n時間:{booking_time}\n時長:{booking_duration} 分鐘\n備註:{booking_note}\n\n預約編號:#{booking_id}\n建立時間:{created_time}\n\n請至後台查看詳細資訊:\n{admin_url}"
        );
        
        update_option('booking_email_templates', $default_templates);
    }
    
    public function check_and_update_table() {
        $current_version = get_option('booking_blocked_dates_table_version', '0');
        
        if (version_compare($current_version, $this->table_version, '<')) {
            $this->create_blocked_dates_table();
            $this->create_email_logs_table();
            update_option('booking_blocked_dates_table_version', $this->table_version);
        }
    }
    
    public function create_blocked_dates_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'booking_blocked_dates';
        $charset_collate = $wpdb->get_charset_collate();
        
        $existing_columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name", ARRAY_A);
        $has_correct_structure = false;
        
        if (!empty($existing_columns)) {
            $column_names = array_column($existing_columns, 'Field');
            if (in_array('start_date', $column_names) && in_array('end_date', $column_names)) {
                $has_correct_structure = true;
            }
        }
        
        if (!$has_correct_structure && $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
            $wpdb->query("DROP TABLE IF EXISTS $table_name");
        }
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            start_date date NOT NULL,
            end_date date NOT NULL,
            note text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY start_date (start_date),
            KEY end_date (end_date)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public function create_email_logs_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'booking_email_logs';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            booking_id bigint(20) NOT NULL,
            recipient_email varchar(255) NOT NULL,
            recipient_name varchar(255),
            recipient_type varchar(50) NOT NULL,
            subject text NOT NULL,
            message longtext NOT NULL,
            status varchar(20) NOT NULL,
            sent_at datetime DEFAULT CURRENT_TIMESTAMP,
            error_message text,
            PRIMARY KEY  (id),
            KEY booking_id (booking_id),
            KEY recipient_email (recipient_email),
            KEY sent_at (sent_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public function admin_footer_text($text) {
        $screen = get_current_screen();
        if ($screen && (strpos($screen->id, 'booking') !== false)) {
            $text = '<span style="color: #666;">預約系統 by <a href="https://wumetax.com" target="_blank" style="color: #0073aa; text-decoration: none; font-weight: 600;">WumetaX</a> | 版本 3.1</span>';
        }
        return $text;
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
        wp_enqueue_style('booking-style', plugin_dir_url(__FILE__) . 'css/booking-style.css', array(), '3.1');
        wp_enqueue_script('booking-script', plugin_dir_url(__FILE__) . 'js/booking-script.js', array('jquery'), '3.1', true);
        
        $settings = $this->get_booking_settings();
        
        wp_localize_script('booking-script', 'bookingAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('booking_nonce'),
            'availableDays' => $settings['available_days'],
            'blockedDates' => $this->get_all_blocked_dates_for_js(),
            'startTime' => $settings['start_time'],
            'endTime' => $settings['end_time'],
            'timeInterval' => $settings['time_slot_interval'],
            'durations' => $settings['available_durations'],
            'messages' => array(
                'required' => '此欄位為必填',
                'invalid_email' => '請輸入有效的 Email',
                'invalid_phone' => '請輸入有效的電話號碼',
                'select_time' => '請選擇預約時間',
                'captcha_required' => '請完成驗證碼驗證',
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
        
        $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'start_date'");
        if (empty($columns)) {
            return array();
        }
        
        $results = $wpdb->get_results("SELECT start_date, end_date FROM $table_name ORDER BY start_date", ARRAY_A);
        
        if ($wpdb->last_error) {
            return array();
        }
        
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
        
        $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'start_date'");
        if (empty($columns)) {
            return false;
        }
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE %s BETWEEN start_date AND end_date",
            $date
        ));
        
        return $count > 0;
    }
    
public function get_available_dates() {
    check_ajax_referer('booking_nonce', 'nonce');
    
    $year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');
    $month = isset($_POST['month']) ? intval($_POST['month']) : date('m');
    
    $settings = $this->get_booking_settings();
    $available_days = $settings['available_days'];
    
    // 確保月份是兩位數格式
    $month_str = str_pad($month, 2, '0', STR_PAD_LEFT);
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    
    $dates = array();
    $today = date('Y-m-d');
    
    for ($day = 1; $day <= $days_in_month; $day++) {
        $day_str = str_pad($day, 2, '0', STR_PAD_LEFT);
        $dateStr = $year . '-' . $month_str . '-' . $day_str;
        
        // 跳過過去的日期
        if ($dateStr < $today) {
            continue;
        }
        
        $timestamp = strtotime($dateStr);
        if ($timestamp === false) {
            continue;
        }
        
        $dayOfWeek = date('N', $timestamp);
        
        // 檢查是否為可預約星期
        if (!in_array($dayOfWeek, $available_days)) {
            continue;
        }
        
        // 檢查是否為封鎖日期
        if ($this->is_date_blocked($dateStr)) {
            continue;
        }
        
        $dates[] = array(
            'date' => $dateStr,
            'display' => date('m/d', $timestamp) . ' (' . $this->get_weekday_name($dayOfWeek) . ')'
        );
    }
    
    wp_send_json_success(array('dates' => $dates));
}
    
    private function get_weekday_name($day) {
        $names = array(
            '1' => '週一',
            '2' => '週二',
            '3' => '週三',
            '4' => '週四',
            '5' => '週五',
            '6' => '週六',
            '7' => '週日'
        );
        return isset($names[$day]) ? $names[$day] : '';
    }
    
    public function get_available_times() {
        if (!check_ajax_referer('booking_nonce', 'nonce', false)) {
            error_log('Booking System: Nonce verification failed');
            wp_send_json_error(array('message' => '安全驗證失敗,請重新整理頁面'));
            return;
        }
        
        if (!isset($_POST['date']) || !isset($_POST['duration'])) {
            error_log('Booking System: Missing required parameters');
            wp_send_json_error(array('message' => '缺少必要參數'));
            return;
        }
        
        $date = sanitize_text_field($_POST['date']);
        $duration = intval($_POST['duration']);
        
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
                $available_times[] = array(
                    'value' => $time_str,
                    'display' => $time_str
                );
            }
            
            $current_time += ($interval * 60);
        }
        
        wp_send_json(array('times' => $available_times));
    }
    
    public function verify_captcha() {
        check_ajax_referer('booking_nonce', 'nonce');
        
        if (!isset($_POST['answer'])) {
            wp_send_json_error(array('message' => '缺少驗證答案'));
            return;
        }
        
        $answer = intval($_POST['answer']);
        
        if (!isset($_SESSION)) {
            session_start();
        }
        
        if (!isset($_SESSION['booking_captcha_answer'])) {
            wp_send_json_error(array('message' => '驗證碼已過期'));
            return;
        }
        
        if ($answer === $_SESSION['booking_captcha_answer']) {
            $_SESSION['booking_captcha_verified'] = true;
            wp_send_json_success(array('message' => '驗證成功'));
        } else {
            wp_send_json_error(array('message' => '驗證碼錯誤'));
        }
    }
    
    private function generate_captcha() {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        $num1 = rand(1, 10);
        $num2 = rand(1, 10);
        $answer = $num1 + $num2;
        
        $_SESSION['booking_captcha_answer'] = $answer;
        $_SESSION['booking_captcha_verified'] = false;
        
        return array(
            'question' => "{$num1} + {$num2} = ?",
            'num1' => $num1,
            'num2' => $num2
        );
    }
    
public function render_booking_form() {
    $settings = $this->get_booking_settings();
    $captcha = $this->generate_captcha();
    
    $year_month_options = array();
    $current_date = new DateTime();
    
    for ($i = 0; $i < 12; $i++) {
        $year = $current_date->format('Y');
        $month = $current_date->format('m');
        $display = $current_date->format('Y年m月');
        
        $year_month_options[] = array(
            'year' => $year,
            'month' => $month,
            'display' => $display,
            'value' => $year . '-' . $month
        );
        
        $current_date->modify('+1 month');
    }
    
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
                <label for="booking_year_month">選擇年月 <span class="required">*</span></label>
                <select id="booking_year_month" name="booking_year_month" required>
                    <option value="">請選擇年月</option>
                    <?php foreach ($year_month_options as $option): ?>
                        <option value="<?php echo esc_attr($option['value']); ?>" 
                                data-year="<?php echo esc_attr($option['year']); ?>" 
                                data-month="<?php echo esc_attr($option['month']); ?>">
                            <?php echo esc_html($option['display']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="error-message" id="error_year_month"></span>
            </div>
            
            <div class="form-group" id="date-group" style="display: none;">
                <label for="booking_date">預約日期 <span class="required">*</span></label>
                <select id="booking_date" name="booking_date" required disabled>
                    <option value="">請先選擇年月</option>
                </select>
                <span class="error-message" id="error_date"></span>
            </div>
            
            <div class="form-group" id="duration-group" style="display: none;">
                <label for="booking_duration">預約時長 <span class="required">*</span></label>
                <select id="booking_duration" name="booking_duration" required disabled>
                    <?php foreach ($settings['available_durations'] as $duration): ?>
                        <option value="<?php echo esc_attr($duration); ?>" <?php selected($duration, $settings['default_duration']); ?>>
                            <?php echo esc_html($duration); ?> 分鐘
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="error-message" id="error_duration"></span>
            </div>
            
            <div class="form-group" id="time-group" style="display: none;">
                <label for="booking_time">預約時間 <span class="required">*</span></label>
                <select id="booking_time" name="booking_time" required disabled>
                    <option value="">請先選擇日期和時長</option>
                </select>
                <span class="error-message" id="error_time"></span>
            </div>
            
            <div class="form-group">
                <label for="booking_note">備註</label>
                <textarea id="booking_note" name="booking_note" rows="4" placeholder="如有特殊需求請在此註明"></textarea>
            </div>
            
            <div class="form-group captcha-group">
                <label for="captcha_answer">驗證碼 <span class="required">*</span></label>
                <div class="captcha-question">
                    <span class="captcha-text"><?php echo esc_html($captcha['question']); ?></span>
                    <input type="number" id="captcha_answer" name="captcha_answer" required style="width: 100px; display: inline-block;">
                </div>
                <span class="error-message" id="error_captcha"></span>
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
    
    private function send_booking_email($booking_id, $name, $email, $phone, $date, $time, $duration, $note) {
        $templates = get_option('booking_email_templates');
        
        if (!$templates) {
            return false;
        }
        
        $placeholders = array(
            '{site_name}' => get_bloginfo('name'),
            '{site_url}' => get_bloginfo('url'),
            '{customer_name}' => $name,
            '{customer_email}' => $email,
            '{customer_phone}' => $phone,
            '{booking_date}' => $date,
            '{booking_time}' => $time,
            '{booking_duration}' => $duration,
            '{booking_note}' => $note ? $note : '無',
            '{booking_id}' => $booking_id,
            '{created_time}' => current_time('Y-m-d H:i:s'),
            '{admin_url}' => admin_url('post.php?post=' . $booking_id . '&action=edit')
        );
        
        $customer_subject = str_replace(array_keys($placeholders), array_values($placeholders), $templates['customer_subject']);
        $customer_body = str_replace(array_keys($placeholders), array_values($placeholders), $templates['customer_body']);
        
        $customer_sent = wp_mail($email, $customer_subject, $customer_body, array('Content-Type: text/plain; charset=UTF-8'));
        $this->log_email($booking_id, $email, $name, 'customer', $customer_subject, $customer_body, $customer_sent ? 'sent' : 'failed');
        
        $admin_email = get_option('admin_email');
        $admin_subject = str_replace(array_keys($placeholders), array_values($placeholders), $templates['admin_subject']);
        $admin_body = str_replace(array_keys($placeholders), array_values($placeholders), $templates['admin_body']);
        
        $admin_sent = wp_mail($admin_email, $admin_subject, $admin_body, array('Content-Type: text/plain; charset=UTF-8'));
        $this->log_email($booking_id, $admin_email, '管理員', 'admin', $admin_subject, $admin_body, $admin_sent ? 'sent' : 'failed');
        
        return $customer_sent && $admin_sent;
    }
    
    private function log_email($booking_id, $recipient_email, $recipient_name, $recipient_type, $subject, $message, $status, $error = '') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'booking_email_logs';
        
        $wpdb->insert(
            $table_name,
            array(
                'booking_id' => $booking_id,
                'recipient_email' => $recipient_email,
                'recipient_name' => $recipient_name,
                'recipient_type' => $recipient_type,
                'subject' => $subject,
                'message' => $message,
                'status' => $status,
                'error_message' => $error,
                'sent_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    public function send_test_email() {
        check_ajax_referer('booking_admin_nonce', 'nonce');
        
        $test_email = sanitize_email($_POST['test_email']);
        $email_type = sanitize_text_field($_POST['email_type']);
        
        if (!is_email($test_email)) {
            wp_send_json_error(array('message' => '請輸入有效的 Email 地址'));
            return;
        }
        
        $templates = get_option('booking_email_templates');
        
        if (!$templates) {
            wp_send_json_error(array('message' => '找不到信件模板'));
            return;
        }
        
        $placeholders = array(
            '{site_name}' => get_bloginfo('name'),
            '{site_url}' => get_bloginfo('url'),
            '{customer_name}' => '測試客戶',
            '{customer_email}' => 'test@example.com',
            '{customer_phone}' => '0912345678',
            '{booking_date}' => date('Y-m-d'),
            '{booking_time}' => '14:00',
            '{booking_duration}' => '60',
            '{booking_note}' => '這是測試預約的備註內容',
            '{booking_id}' => '999',
            '{created_time}' => current_time('Y-m-d H:i:s'),
            '{admin_url}' => admin_url('edit.php?post_type=booking')
        );
        
        if ($email_type === 'customer') {
            $subject = str_replace(array_keys($placeholders), array_values($placeholders), $templates['customer_subject']);
            $body = str_replace(array_keys($placeholders), array_values($placeholders), $templates['customer_body']);
        } else {
            $subject = str_replace(array_keys($placeholders), array_values($placeholders), $templates['admin_subject']);
            $body = str_replace(array_keys($placeholders), array_values($placeholders), $templates['admin_body']);
        }
        
        $sent = wp_mail($test_email, $subject, $body, array('Content-Type: text/plain; charset=UTF-8'));
        
        if ($sent) {
            wp_send_json_success(array('message' => '測試信件已發送至 ' . $test_email));
        } else {
            wp_send_json_error(array('message' => '信件發送失敗,請檢查您的郵件伺服器設定'));
        }
    }
    
    public function handle_booking_submission() {
        check_ajax_referer('booking_nonce', 'nonce');
        
        header('Content-Type: application/json; charset=utf-8');
        
        if (!isset($_SESSION)) {
            session_start();
        }
        
        if (!isset($_SESSION['booking_captcha_verified']) || !$_SESSION['booking_captcha_verified']) {
            wp_send_json_error(array('message' => '請先完成驗證碼驗證'));
            return;
        }
        
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
            
            $this->send_booking_email($booking_id, $name, $email, $phone, $date, $time, $duration, $note);
            
            unset($_SESSION['booking_captcha_verified']);
            unset($_SESSION['booking_captcha_answer']);
            
            wp_send_json_success(array('message' => '預約成功！我們已寄送確認信件至您的信箱。'));
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
                echo '<select class="booking-quick-status" data-booking-id="' . esc_attr($post_id) . '" style="padding: 2px 10px; border-radius: 4px; border: 2px solid ' . $status_options[$status]['color'] . '; background: white; color: ' . $status_options[$status]['color'] . '; font-weight: bold; cursor: pointer;">';
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
                    <select id="booking_status" name="booking_status">
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
        
        add_submenu_page(
            'edit.php?post_type=booking',
            '信件模板',
            '信件模板',
            'manage_options',
            'booking-email-templates',
            array($this, 'render_email_templates_page')
        );
        
        add_submenu_page(
            'edit.php?post_type=booking',
            '發信紀錄',
            '發信紀錄',
            'manage_options',
            'booking-email-logs',
            array($this, 'render_email_logs_page')
        );
    }
    
    public function render_email_templates_page() {
        if (isset($_POST['save_email_templates'])) {
            check_admin_referer('email_templates_action', 'email_templates_nonce');
            
            $templates = array(
                'customer_subject' => sanitize_text_field($_POST['customer_subject']),
                'customer_body' => sanitize_textarea_field($_POST['customer_body']),
                'admin_subject' => sanitize_text_field($_POST['admin_subject']),
                'admin_body' => sanitize_textarea_field($_POST['admin_body']),
            );
            
            update_option('booking_email_templates', $templates);
            echo '<div class="notice notice-success is-dismissible"><p><strong>信件模板已儲存！</strong></p></div>';
        }
        
        if (isset($_POST['reset_email_templates'])) {
            check_admin_referer('email_templates_action', 'email_templates_nonce');
            
            delete_option('booking_email_templates');
            $this->set_default_email_templates();
            echo '<div class="notice notice-success is-dismissible"><p><strong>信件模板已重置為預設內容！</strong></p></div>';
        }
        
        $templates = get_option('booking_email_templates');
        
        if (!$templates) {
            $this->set_default_email_templates();
            $templates = get_option('booking_email_templates');
        }
        ?>
        <div class="wrap">
            <h1>信件模板設定</h1>
            <p class="description">設定預約成功後寄送給客戶和管理員的通知信件內容</p>
            
            <div style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px;">
                <h3 style="margin-top: 0;">📧 可用變數說明</h3>
                <p style="margin-bottom: 10px;">您可以在信件主旨和內容中使用以下變數，系統會自動替換為實際內容：</p>
                <ul style="list-style: disc; margin-left: 20px; columns: 2;">
                    <li><code>{site_name}</code> - 網站名稱</li>
                    <li><code>{site_url}</code> - 網站網址</li>
                    <li><code>{customer_name}</code> - 客戶姓名</li>
                    <li><code>{customer_email}</code> - 客戶 Email</li>
                    <li><code>{customer_phone}</code> - 客戶電話</li>
                    <li><code>{booking_date}</code> - 預約日期</li>
                    <li><code>{booking_time}</code> - 預約時間</li>
                    <li><code>{booking_duration}</code> - 預約時長</li>
                    <li><code>{booking_note}</code> - 預約備註</li>
                    <li><code>{booking_id}</code> - 預約編號</li>
                    <li><code>{created_time}</code> - 建立時間</li>
                    <li><code>{admin_url}</code> - 後台編輯連結</li>
                </ul>
            </div>
            
            <form method="post" action="">
                <?php wp_nonce_field('email_templates_action', 'email_templates_nonce'); ?>
                
                <div style="background: white; padding: 20px; margin: 20px 0; border: 1px solid #ccc; border-radius: 4px;">
                    <h2>📨 客戶通知信件</h2>
                    <p class="description">預約成功後寄送給填寫者的確認信件</p>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="customer_subject">信件主旨</label></th>
                            <td>
                                <input type="text" id="customer_subject" name="customer_subject" value="<?php echo esc_attr($templates['customer_subject']); ?>" class="large-text">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="customer_body">信件內容</label></th>
                            <td>
                                <textarea id="customer_body" name="customer_body" rows="12" class="large-text" style="font-family: monospace;"><?php echo esc_textarea($templates['customer_body']); ?></textarea>
                            </td>
                        </tr>
                    </table>
                    
                    <div style="margin-top: 15px; padding: 15px; background: #f0f8ff; border: 1px solid #b3d9ff; border-radius: 4px;">
                        <h4 style="margin-top: 0;">🧪 測試客戶信件</h4>
                        <p style="margin-bottom: 10px;">輸入Email地址測試信件發送:</p>
                        <input type="email" id="customer_test_email" placeholder="test@example.com" style="width: 300px; padding: 8px;">
                        <button type="button" class="button" id="send_customer_test">發送測試信件</button>
                        <span id="customer_test_result" style="margin-left: 10px;"></span>
                    </div>
                </div>
                
                <div style="background: white; padding: 20px; margin: 20px 0; border: 1px solid #ccc; border-radius: 4px;">
                    <h2>👨‍💼 管理員通知信件</h2>
                    <p class="description">有新預約時寄送給管理員的通知信件</p>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="admin_subject">信件主旨</label></th>
                            <td>
                                <input type="text" id="admin_subject" name="admin_subject" value="<?php echo esc_attr($templates['admin_subject']); ?>" class="large-text">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="admin_body">信件內容</label></th>
                            <td>
                                <textarea id="admin_body" name="admin_body" rows="12" class="large-text" style="font-family: monospace;"><?php echo esc_textarea($templates['admin_body']); ?></textarea>
                            </td>
                        </tr>
                    </table>
                    
                    <div style="margin-top: 15px; padding: 15px; background: #f0f8ff; border: 1px solid #b3d9ff; border-radius: 4px;">
                        <h4 style="margin-top: 0;">🧪 測試管理員信件</h4>
                        <p style="margin-bottom: 10px;">輸入Email地址測試信件發送:</p>
                        <input type="email" id="admin_test_email" placeholder="admin@example.com" value="<?php echo esc_attr(get_option('admin_email')); ?>" style="width: 300px; padding: 8px;">
                        <button type="button" class="button" id="send_admin_test">發送測試信件</button>
                        <span id="admin_test_result" style="margin-left: 10px;"></span>
                    </div>
                </div>
                
                <p class="submit">
                    <?php submit_button('儲存信件模板', 'primary large', 'save_email_templates', false); ?>
                    <?php submit_button('重置為預設模板', 'secondary', 'reset_email_templates', false, array('onclick' => 'return confirm("確定要重置為預設模板嗎？目前的自訂內容將會遺失！");')); ?>
                </p>
            </form>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            function sendTestEmail(type) {
                var emailInput = type === 'customer' ? $('#customer_test_email') : $('#admin_test_email');
                var resultSpan = type === 'customer' ? $('#customer_test_result') : $('#admin_test_result');
                var testEmail = emailInput.val();
                
                if (!testEmail) {
                    alert('請輸入測試Email地址');
                    return;
                }
                
                resultSpan.html('<span style="color: #999;">發送中...</span>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'send_test_email',
                        nonce: '<?php echo wp_create_nonce('booking_admin_nonce'); ?>',
                        test_email: testEmail,
                        email_type: type
                    },
                    success: function(response) {
                        if (response.success) {
                            resultSpan.html('<span style="color: #4caf50;">✓ ' + response.data.message + '</span>');
                        } else {
                            resultSpan.html('<span style="color: #d63638;">✗ ' + response.data.message + '</span>');
                        }
                        
                        setTimeout(function() {
                            resultSpan.fadeOut(300, function() {
                                $(this).html('').show();
                            });
                        }, 5000);
                    },
                    error: function() {
                        resultSpan.html('<span style="color: #d63638;">✗ 發送失敗</span>');
                    }
                });
            }
            
            $('#send_customer_test').on('click', function() {
                sendTestEmail('customer');
            });
            
            $('#send_admin_test').on('click', function() {
                sendTestEmail('admin');
            });
        });
        </script>
        <?php
    }
    
    public function render_email_logs_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'booking_email_logs';
        
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;
        
        $total_logs = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $total_pages = ceil($total_logs / $per_page);
        
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY sent_at DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));
        ?>
        <div class="wrap">
            <h1>發信紀錄</h1>
            <p class="description">查看所有預約通知信件的發送記錄</p>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 80px;">編號</th>
                        <th style="width: 120px;">預約ID</th>
                        <th>收件人</th>
                        <th style="width: 100px;">類型</th>
                        <th>主旨</th>
                        <th style="width: 80px;">狀態</th>
                        <th style="width: 150px;">發送時間</th>
                        <th style="width: 120px;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 30px;">
                                目前沒有發信紀錄
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo esc_html($log->id); ?></td>
                                <td>
                                    <a href="<?php echo admin_url('post.php?post=' . $log->booking_id . '&action=edit'); ?>" target="_blank">
                                        #<?php echo esc_html($log->booking_id); ?>
                                    </a>
                                </td>
                                <td>
                                    <strong><?php echo esc_html($log->recipient_name); ?></strong><br>
                                    <small><?php echo esc_html($log->recipient_email); ?></small>
                                </td>
                                <td>
                                    <?php if ($log->recipient_type === 'customer'): ?>
                                        <span style="color: #0073aa;">👤 客戶</span>
                                    <?php else: ?>
                                        <span style="color: #d63638;">👨‍💼 管理員</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($log->subject); ?></td>
                                <td>
                                    <?php if ($log->status === 'sent'): ?>
                                        <span style="color: #4caf50; font-weight: bold;">✓ 成功</span>
                                    <?php else: ?>
                                        <span style="color: #f44336; font-weight: bold;">✗ 失敗</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html(date('Y-m-d H:i', strtotime($log->sent_at))); ?></td>
                                <td>
                                    <button type="button" class="button button-small view-email-detail" data-id="<?php echo esc_attr($log->id); ?>">
                                        查看
                                    </button>
                                    <button type="button" class="button button-small delete-email-log" data-id="<?php echo esc_attr($log->id); ?>" style="color: #b32d2e;">
                                        刪除
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if ($total_pages > 1): ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total' => $total_pages,
                            'current' => $current_page
                        ));
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div id="email-detail-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 999999;">
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 8px; max-width: 600px; width: 90%; max-height: 80%; overflow-y: auto;">
                <h2 style="margin-top: 0;">信件內容詳情</h2>
                <div id="email-detail-content"></div>
                <button type="button" class="button button-primary" onclick="jQuery('#email-detail-modal').hide();">關閉</button>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('.view-email-detail').on('click', function() {
                var logId = $(this).data('id');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'get_email_log_detail',
                        log_id: logId,
                        nonce: '<?php echo wp_create_nonce('booking_admin_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#email-detail-content').html(response.data.html);
                            $('#email-detail-modal').show();
                        }
                    }
                });
            });
            
            $('.delete-email-log').on('click', function() {
                if (!confirm('確定要刪除此發信紀錄嗎？')) {
                    return;
                }
                
                var logId = $(this).data('id');
                var row = $(this).closest('tr');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'delete_email_log',
                        log_id: logId,
                        nonce: '<?php echo wp_create_nonce('booking_admin_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            row.fadeOut(300, function() {
                                $(this).remove();
                            });
                        } else {
                            alert('刪除失敗');
                        }
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    public function get_email_log_detail() {
        check_ajax_referer('booking_admin_nonce', 'nonce');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'booking_email_logs';
        
        $log_id = intval($_POST['log_id']);
        
        $log = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $log_id));
        
        if (!$log) {
            wp_send_json_error(array('message' => '找不到信件紀錄'));
            return;
        }
        
        $html = '<div style="margin-bottom: 20px;">';
        $html .= '<p><strong>收件人:</strong> ' . esc_html($log->recipient_name) . ' (' . esc_html($log->recipient_email) . ')</p>';
        $html .= '<p><strong>類型:</strong> ' . ($log->recipient_type === 'customer' ? '客戶信件' : '管理員信件') . '</p>';
        $html .= '<p><strong>主旨:</strong> ' . esc_html($log->subject) . '</p>';
        $html .= '<p><strong>發送時間:</strong> ' . esc_html($log->sent_at) . '</p>';
        $html .= '<p><strong>狀態:</strong> ' . ($log->status === 'sent' ? '<span style="color: #4caf50;">發送成功</span>' : '<span style="color: #f44336;">發送失敗</span>') . '</p>';
        
        if ($log->error_message) {
            $html .= '<p><strong>錯誤訊息:</strong> <span style="color: #d63638;">' . esc_html($log->error_message) . '</span></p>';
        }
        
        $html .= '<hr>';
        $html .= '<h3>信件內容</h3>';
        $html .= '<div style="background: #f5f5f5; padding: 15px; border-radius: 4px; white-space: pre-wrap; font-family: monospace; font-size: 13px;">';
        $html .= esc_html($log->message);
        $html .= '</div>';
        $html .= '</div>';
        
        wp_send_json_success(array('html' => $html));
    }
    
    public function delete_email_log() {
        check_ajax_referer('booking_admin_nonce', 'nonce');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'booking_email_logs';
        
        $log_id = intval($_POST['log_id']);
        
        $result = $wpdb->delete($table_name, array('id' => $log_id), array('%d'));
        
        if ($result) {
            wp_send_json_success(array('message' => '紀錄已刪除'));
        } else {
            wp_send_json_error(array('message' => '刪除失敗'));
        }
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
                                        <strong>功能說明：</strong>選擇您開放預約的星期。只有勾選的星期會在前台顯示為可選日期。
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
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="end_time">營業結束時間</label></th>
                            <td>
                                <input type="time" id="end_time" name="end_time" value="<?php echo esc_attr($settings['end_time']); ?>" style="padding: 8px; font-size: 14px;">
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="time_slot_interval">時段間隔</label></th>
                            <td>
                                <select id="time_slot_interval" name="time_slot_interval">
                                    <option value="15" <?php selected($settings['time_slot_interval'], '15'); ?>>15分鐘</option>
                                    <option value="30" <?php selected($settings['time_slot_interval'], '30'); ?>>30分鐘</option>
                                    <option value="60" <?php selected($settings['time_slot_interval'], '60'); ?>>60分鐘</option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label>可選預約時長</label></th>
                            <td>
                                <fieldset>
                                    <label style="display: block; margin-bottom: 8px;">
                                        <input type="checkbox" name="available_durations[]" value="30" <?php checked(in_array('30', $settings['available_durations'])); ?>> 
                                        <strong>30分鐘</strong>
                                    </label>
                                    <label style="display: block; margin-bottom: 8px;">
                                        <input type="checkbox" name="available_durations[]" value="60" <?php checked(in_array('60', $settings['available_durations'])); ?>> 
                                        <strong>60分鐘</strong>
                                    </label>
                                    <label style="display: block; margin-bottom: 8px;">
                                        <input type="checkbox" name="available_durations[]" value="90" <?php checked(in_array('90', $settings['available_durations'])); ?>> 
                                        <strong>90分鐘</strong>
                                    </label>
                                    <label style="display: block; margin-bottom: 8px;">
                                        <input type="checkbox" name="available_durations[]" value="120" <?php checked(in_array('120', $settings['available_durations'])); ?>> 
                                        <strong>120分鐘</strong>
                                    </label>
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
                    
                    <?php submit_button('儲存設定', 'primary large', 'booking_settings_submit'); ?>
                </form>
            </div>
            
            <div id="blocked-dates" class="tab-content" style="display: none; margin-top: 20px;">
                <div style="max-width: 1100px;">
                    <h3>新增封鎖日期</h3>
                    <p class="description">封鎖特定日期或日期區間,讓客戶無法在這些日期預約。</p>
                    
                    <div style="background: white; padding: 20px; border: 1px solid #ccc; border-radius: 4px; margin: 20px 0;">
                        <table class="form-table">
                            <tr>
                                <th><label for="new_blocked_start_date">開始日期</label></th>
                                <td>
                                    <input type="date" id="new_blocked_start_date" style="padding: 8px; font-size: 14px; width: 200px;">
                                </td>
                            </tr>
                            <tr>
                                <th><label for="new_blocked_end_date">結束日期</label></th>
                                <td>
                                    <input type="date" id="new_blocked_end_date" style="padding: 8px; font-size: 14px; width: 200px;">
                                </td>
                            </tr>
                            <tr>
                                <th><label for="new_blocked_note">備註說明</label></th>
                                <td>
                                    <input type="text" id="new_blocked_note" placeholder="例如：春節假期、公司年假" style="padding: 8px; font-size: 14px; width: 400px;">
                                </td>
                            </tr>
                        </table>
                        <button type="button" id="add_blocked_date_btn" class="button button-primary">新增封鎖日期</button>
                    </div>
                    
                    <h3>已封鎖的日期列表</h3>
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
                                    <td colspan="5" style="text-align: center; padding: 30px;">目前沒有封鎖日期</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($blocked_dates as $blocked): ?>
                                    <tr data-id="<?php echo esc_attr($blocked->id); ?>">
                                        <td><strong><?php echo esc_html($blocked->start_date); ?></strong></td>
                                        <td><strong><?php echo esc_html($blocked->end_date); ?></strong></td>
                                        <td><?php echo $blocked->note ? esc_html($blocked->note) : '<span style="color: #999;">-</span>'; ?></td>
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
        
        <script type="text/javascript">
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
        
        if (strtotime($start_date) > strtotime($end_date)) {
            wp_send_json_error(array('message' => '開始日期不能晚於結束日期'));
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
        
        if ($result) {
            $data = array(
                'id' => $wpdb->insert_id,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'note' => $note,
                'created_at' => current_time('Y-m-d H:i')
            );
            
            wp_send_json_success(array('message' => '封鎖日期已新增', 'data' => $data));
        } else {
            wp_send_json_error(array('message' => '新增失敗'));
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
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'booking') === false) {
            return;
        }
        
        wp_enqueue_style('booking-admin-style', plugin_dir_url(__FILE__) . 'css/booking-admin.css', array(), '3.1');
        
        if (isset($_GET['page'])) {
            if ($_GET['page'] === 'booking-settings') {
                wp_enqueue_script('booking-settings', plugin_dir_url(__FILE__) . 'js/booking-settings.js', array('jquery'), '3.1', true);
            } elseif ($_GET['page'] === 'booking-calendar') {
                wp_enqueue_script('booking-calendar', plugin_dir_url(__FILE__) . 'js/booking-calendar.js', array('jquery'), '3.1', true);
            }
        }
        
        if ($hook === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'booking') {
            wp_enqueue_script('booking-admin-list', plugin_dir_url(__FILE__) . 'js/booking-admin-list.js', array('jquery'), '3.1', true);
        }
        
        wp_localize_script('booking-settings', 'bookingAdminData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('booking_admin_nonce')
        ));
        
        wp_localize_script('booking-admin-list', 'bookingAdminData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('booking_admin_nonce')
        ));
        
        wp_localize_script('booking-calendar', 'bookingAdminData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('booking_admin_nonce')
        ));
    }
}

new BookingSystem();
