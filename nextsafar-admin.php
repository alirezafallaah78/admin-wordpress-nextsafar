<?php
/**
 * Plugin Name: NextSafar Admin
 * Plugin URI:  https://nextsafar.com
 * Description: Custom administration interface for NextSafar.
 * Version:     2.0
 * Author:      NextSafar
 * Text Domain: nextsafar-admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define Constants
define( 'NEXTSAFAR_ADMIN_VERSION', '1.4.0' );
define( 'NEXTSAFAR_ADMIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'NEXTSAFAR_ADMIN_URL', plugin_dir_url( __FILE__ ) );

// Load Helper Functions
require_once NEXTSAFAR_ADMIN_PATH . 'includes/functions-helpers.php';
require_once NEXTSAFAR_ADMIN_PATH . 'includes/class-helpers.php';

// Load Core Classes
require_once NEXTSAFAR_ADMIN_PATH . 'includes/class-icons.php';
require_once NEXTSAFAR_ADMIN_PATH . 'includes/class-assets.php';
require_once NEXTSAFAR_ADMIN_PATH . 'includes/class-admin.php';
require_once NEXTSAFAR_ADMIN_PATH . 'includes/class-content-manager.php';
require_once NEXTSAFAR_ADMIN_PATH . 'includes/class-media-folders.php';
require_once NEXTSAFAR_ADMIN_PATH . 'includes/class-media-modal-enhancer.php';
require_once NEXTSAFAR_ADMIN_PATH . 'includes/class-updates.php';
require_once NEXTSAFAR_ADMIN_PATH . 'includes/class-wp-pages-wrapper.php';
require_once NEXTSAFAR_ADMIN_PATH . 'includes/class-external-menus.php';
require_once NEXTSAFAR_ADMIN_PATH . 'includes/class-categories.php';
require_once NEXTSAFAR_ADMIN_PATH . 'includes/class-login.php';

require_once NEXTSAFAR_ADMIN_PATH . 'includes/class-role-access.php';  // 🆕 اضافه شد
// Load Dashboard & Widgets
require_once NEXTSAFAR_ADMIN_PATH . 'includes/class-widget-base.php';
require_once NEXTSAFAR_ADMIN_PATH . 'includes/class-widgets.php';
require_once NEXTSAFAR_ADMIN_PATH . 'includes/class-dashboard.php';
require_once NEXTSAFAR_ADMIN_PATH . 'includes/widgets/class-widget-stats.php';
require_once NEXTSAFAR_ADMIN_PATH . 'includes/widgets/class-widget-revenue-chart.php';
require_once NEXTSAFAR_ADMIN_PATH . 'includes/widgets/class-widget-recent-bookings.php';
require_once NEXTSAFAR_ADMIN_PATH . 'includes/widgets/class-widget-visa-applications.php';
require_once NEXTSAFAR_ADMIN_PATH . 'includes/widgets/class-widget-yoast-seo.php';
require_once NEXTSAFAR_ADMIN_PATH . 'includes/class-widget-cache.php';

/**
 * 🌟 هسته مرکزی پلاگین (NextSafar Core Container)
 * جایگزین متغیرهای global برای مدیریت یکپارچه Instance ها
 */
class NextSafar_Core {
    private static $instance = null;
    
    // Public properties for easy access: nextsafar()->dashboard
    public $admin;
    public $assets;
    public $content_manager;
    public $categories;
    public $media_folders;
    public $media_modal;
    public $updates;
    public $wp_wrapper;
    public $external_menus;
    public $dashboard;
    public $login;  // ✅ اضافه شد
    public $role_access;  // 🆕 اضافه شد

    private function __construct() {
        $this->init_components();
    }

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function init_components() {
        // Instantiate all components
        $this->admin           = new NextSafar_Admin();
        $this->assets          = new NextSafar_Assets();
        $this->content_manager = new NextSafar_Content_Manager();
        $this->categories      = new NextSafar_Categories();
        $this->media_folders   = new NextSafar_Media_Folders();
        $this->media_modal     = new NextSafar_Media_Modal_Enhancer();
        $this->updates         = new NextSafar_Updates();
        $this->wp_wrapper      = new NextSafar_WP_Pages_Wrapper();
        $this->external_menus  = new NextSafar_External_Menus();
        $this->dashboard       = new NextSafar_Dashboard();
                $this->login = new NextSafar_Login();
        $this->role_access = new NextSafar_Role_Access();  // 🆕 اضافه شد

        // Initialize all components that have an init() method
        foreach ( get_object_vars( $this ) as $component ) {
            if ( is_object( $component ) && method_exists( $component, 'init' ) ) {
                $component->init();
            }
        }

        // 🆕 فعال‌سازی هوک‌های کش ویجت‌ها
        NextSafar_Widget_Cache::init_hooks();
        // 🆕 پاکسازی کش ویجت‌ها هنگام ذخیره پست
        add_action( 'save_post', function() {
            delete_transient( 'nextsafar_widget_recent_content' );
            delete_transient( 'nextsafar_widget_stats_data' );
        }, 20 );

        // 🆕 پاکسازی کش شمارنده‌ها هنگام تغییر کامنت
        add_action( 'wp_insert_comment', function() {
            delete_transient( 'nextsafar_pending_comments_count' );
        } );
        add_action( 'transition_comment_status', function() {
            delete_transient( 'nextsafar_pending_comments_count' );
        } );

        // 🆕 پاکسازی کش آپدیت‌ها هنگام بروزرسانی
        add_action( 'upgrader_process_complete', function() {
            delete_transient( 'nextsafar_pending_updates_count' );
        }, 10, 0 );
    }
}

/**
 * 🌟 تابع Helper برای دسترسی آسان به هسته پلاگین
 * استفاده: nextsafar()->dashboard یا nextsafar()->external_menus
 */
function nextsafar() {
    return NextSafar_Core::get_instance();
}

// Initialize the plugin on plugins_loaded
add_action( 'plugins_loaded', 'nextsafar' );

/**
 * Activation Hook
 */
function nextsafar_admin_activate() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_name      = $wpdb->prefix . 'nextsafar_media_folders';

    // 🚀 ایجاد Full-text Index برای Global Search
    // حذف شود
    if ( class_exists( 'NextSafar_Admin' ) ) {
        NextSafar_Admin::create_fulltext_index();
    }
    // تا اینجا حذف شود 
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        parent_id BIGINT(20) UNSIGNED DEFAULT 0,
        order_index INT(11) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_parent (parent_id),
        KEY idx_order (order_index)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    add_option( 'nextsafar_admin_version', NEXTSAFAR_ADMIN_VERSION );
}
register_activation_hook( __FILE__, 'nextsafar_admin_activate' );

/**
 * Deactivation Hook
 */
function nextsafar_admin_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'nextsafar_admin_deactivate' );