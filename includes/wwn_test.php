<?php
if (!defined('ABSPATH')) exit;

echo "<pre>Starting WWN Plugin Test...\n";

// -------------------------------------
// Step 0: Load classes if not autoloaded
// -------------------------------------
require_once plugin_dir_path(__FILE__) . 'class-db-handler.php';
require_once plugin_dir_path(__FILE__) . 'class-send-message.php';
require_once plugin_dir_path(__FILE__) . 'class-order-hooks.php';
require_once plugin_dir_path(__FILE__) . 'class-cron-handler.php';

WWN_Send_Message::boot();

// -------------------------------------
// Step 1: Set dummy options
// -------------------------------------
update_option('WWN_OPTION_KEY', [
    'phone_number_id' => '1234567890',
    'access_token'    => 'TEST_ACCESS_TOKEN',
    'brand_name'      => 'TestBrand',
    'admin_phone'     => '+911234567890',
    'enable_logs'     => true,
    'abandon_delay_m' => 1
]);

update_option('WWN_CART_OPTION', [
    'test_cart_1' => [
        'user_id' => 1,
        'last_ts' => time() - 120, // 2 mins ago
        'notified' => 0,
        'items' => [
            ['name' => 'Test Product', 'quantity' => 1]
        ]
    ]
]);

echo "✅ Dummy options set.\n";

// -------------------------------------
// Step 2: Test DB Handler
// -------------------------------------
$order_id = 101;
WWN_DB_Handler::upsert_order($order_id, 'pending', '912345678900', 'Test message', 'John', 'john@test.com');
$row = WWN_DB_Handler::get_order($order_id);

echo "DB Handler Test Result:\n";
var_dump($row);

WWN_DB_Handler::update_status($order_id, 'completed');
WWN_DB_Handler::update_phone($order_id, '919876543210');
WWN_DB_Handler::insert_log($order_id, 'Test log entry');

echo "✅ DB Handler update & log done.\n";

// -------------------------------------
// Step 3: Test Send Message Class
// -------------------------------------
echo "Testing Send Message (Text)...\n";
$result = WWN_Send_Message::send_text('+911234567890', 'Hello from test!');
var_dump($result);

echo "Testing Send Message (Buttons)...\n";
WWN_Send_Message::send_buttons('+911234567890', 'Header', 'Body text', ['Yes', 'No', 'Maybe']);

echo "✅ Send Message test completed.\n";

// -------------------------------------
// Step 4: Test Order Hooks
// -------------------------------------
class WC_Order_Mock {
    public function get_billing_phone() { return '+911234567890'; }
    public function get_billing_first_name() { return 'John'; }
    public function get_billing_email() { return 'john@test.com'; }
    public function get_total() { return 1234.50; }
}

$order = new WC_Order_Mock();
$hooks = new WWN_Order_Hooks();

$hooks->on_order_status_changed(101, 'pending', 'completed', $order);
$hooks->on_order_notify_any_status(101, 'pending', 'completed', $order);

echo "✅ Order Hooks test completed.\n";

// -------------------------------------
// Step 5: Test Cron Handler
// -------------------------------------
$cron = new WWN_Cron_Handler();
$cron->process_abandoned_carts();

$store = get_option('WWN_CART_OPTION');
echo "Abandoned Cart Cron Test:\n";
var_dump($store);

echo "✅ Cron Handler test completed.\n";

echo "WWN Plugin Test Finished.\n</pre>";
