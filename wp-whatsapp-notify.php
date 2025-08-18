<?php
/*
Plugin Name: WP WhatsApp Notify (Meta Cloud API)
Description: WhatsApp notifications for WooCommerce: order complete, abandoned cart (4h), and status-based broadcasts via Meta WhatsApp Cloud API.
Version: 1.0.1
Author: Santosh Gautam
License: GPLv2 or later
Text Domain: wp-whatsapp-notify
*/

if (!defined('ABSPATH')) exit;

define('WWN_VERSION', '1.0.1');
define('WWN_PLUGIN_FILE', __FILE__);
define('WWN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WWN_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WWN_OPTION_KEY', 'wwn_settings'); 
define('WWN_CART_OPTION', 'wwn_abandoned_carts'); 
define('WWN_TABLE_NAME', 'wp_wwn_notifications'); // custom table

// Includes
require_once WWN_PLUGIN_DIR . 'includes/class-settings-page.php';
require_once WWN_PLUGIN_DIR . 'includes/class-send-message.php';
require_once WWN_PLUGIN_DIR . 'includes/class-order-hooks.php';
require_once WWN_PLUGIN_DIR . 'includes/class-cron-handler.php';
require_once WWN_PLUGIN_DIR . 'includes/class-db-handler.php';

// Activation: create table + schedule cron
// register_activation_hook(__FILE__, function () {
//     global $wpdb;

//     // Default settings container
//     if (!get_option(WWN_OPTION_KEY)) {
//         add_option(WWN_OPTION_KEY, array(
//             'app_id'           => '',
//             'phone_number_id'  => '',
//             'access_token'     => '',
//             'admin_phone'      => '',  
//             'brand_name'       => 'Your Brand', 
//             'abandon_delay_m'  => 240, 
//             'enable_logs'      => 1,
//         ));
//     }

//     // Cart store
//     if (!get_option(WWN_CART_OPTION)) {
//         add_option(WWN_CART_OPTION, array()); 
//     }

//     // Create custom notifications table
//     $table_name = $wpdb->prefix . "wwn_notifications";
//     $charset_collate = $wpdb->get_charset_collate();

//     $sql = "CREATE TABLE IF NOT EXISTS $table_name (
//         id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
//         order_id BIGINT(20) UNSIGNED NOT NULL,
//         phone_number VARCHAR(20) NOT NULL,
//         status VARCHAR(20) NOT NULL DEFAULT 'pending', -- pending, completed, failed
//         message LONGTEXT NULL,
//         created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
//         updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
//         PRIMARY KEY (id),
//         KEY order_id (order_id),
//         KEY status (status)
//     ) $charset_collate;";

//     require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
//     dbDelta($sql);

//     // Schedule cron
//     if (!wp_next_scheduled('wwn_abandoned_cart_cron')) {
//         wp_schedule_event(time() + 300, 'hourly', 'wwn_abandoned_cart_cron');
//     }
// });
// Activation: create table + schedule cron
register_activation_hook(__FILE__, function () {
    global $wpdb;

    // Default settings container
    if (!get_option(WWN_OPTION_KEY)) {
        add_option(WWN_OPTION_KEY, array(
            'app_id'           => '',
            'phone_number_id'  => '',
            'access_token'     => '',
            'admin_phone'      => '',  
            'brand_name'       => 'Your Brand', 
            'abandon_delay_m'  => 240, 
            'enable_logs'      => 1,
        ));
    }

    // Cart store
    if (!get_option(WWN_CART_OPTION)) {
        add_option(WWN_CART_OPTION, array()); 
    }

    $charset_collate = $wpdb->get_charset_collate();

    // 1️⃣ Update notifications table to include user name and email
    $notifications_table = $wpdb->prefix . "wwn_notifications";
    $sql_notifications = "CREATE TABLE IF NOT EXISTS $notifications_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        order_id BIGINT(20) UNSIGNED NOT NULL,
        user_name VARCHAR(255) NOT NULL,
        user_email VARCHAR(255) NOT NULL,
        phone_number VARCHAR(20) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        message LONGTEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY order_id (order_id),
        KEY status (status)
    ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_notifications);

    // 2️⃣ Create a new logs table
    $logs_table = $wpdb->prefix . "wwn_logs";
    $sql_logs = "CREATE TABLE IF NOT EXISTS $logs_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        order_id BIGINT(20) UNSIGNED NOT NULL,
        log LONGTEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY order_id (order_id)
    ) $charset_collate;";
    dbDelta($sql_logs);

    // Schedule cron
    if (!wp_next_scheduled('wwn_abandoned_cart_cron')) {
        wp_schedule_event(time() + 300, 'hourly', 'wwn_abandoned_cart_cron');
    }
});

// Deactivation: clear cron
register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('wwn_abandoned_cart_cron');
});

// Load plugin after WooCommerce is ready
add_action('woocommerce_loaded', function () {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>WP WhatsApp Notify</strong> requires WooCommerce to be installed & active.</p></div>';
        });
        return;
    }

    // Boot components
    new WWN_Settings_Page();
    WWN_Send_Message::boot(); 
    new WWN_Order_Hooks();
    new WWN_Cron_Handler();

    error_log("WWN DEBUG: Plugin fully initialized after WooCommerce loaded");
});
