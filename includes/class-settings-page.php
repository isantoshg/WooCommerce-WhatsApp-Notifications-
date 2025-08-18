<?php
if (!defined('ABSPATH')) exit;

class WWN_Settings_Page {

    public function __construct() {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'register']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
    }

    public function menu() {
        add_menu_page(
            __('WhatsApp Notify', 'wp-whatsapp-notify'),
            __('WhatsApp Notify', 'wp-whatsapp-notify'),
            'manage_options',
            'wwn-settings',
            [$this, 'render'],
            'dashicons-whatsapp',
            56
        );
    }

    public function register() {
        register_setting('wwn_settings_group', WWN_OPTION_KEY, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize'],
        ]);
    }

    public function sanitize($input) {
        $out = get_option(WWN_OPTION_KEY, array());

        // General settings
        $out['app_id']          = sanitize_text_field($input['app_id'] ?? '');
        $out['phone_number_id'] = sanitize_text_field($input['phone_number_id'] ?? '');
        $out['access_token']    = trim($input['access_token'] ?? '');
        $out['brand_name']      = sanitize_text_field($input['brand_name'] ?? 'Your Brand');

        $phone = preg_replace('/\D+/', '', $input['admin_phone'] ?? '');
        if (strlen($phone) < 8) {
            add_settings_error(WWN_OPTION_KEY, 'invalid_phone', __('Invalid phone number. Format: CountryCode+Number.', 'wp-whatsapp-notify'));
        }
        $out['admin_phone'] = $phone;

        $delay = intval($input['abandon_delay_m'] ?? 240);
        if ($delay < 30) {
            add_settings_error(WWN_OPTION_KEY, 'invalid_delay', __('Delay must be at least 30 minutes.', 'wp-whatsapp-notify'));
            $delay = 30;
        }
        $out['abandon_delay_m'] = $delay;

        $out['enable_logs'] = !empty($input['enable_logs']) ? 1 : 0;

        if (empty($out['access_token'])) {
            add_settings_error(WWN_OPTION_KEY, 'missing_token', __('Access Token is required.', 'wp-whatsapp-notify'));
        }

        // Offer notification
        $out['offer_template'] = sanitize_text_field($input['offer_template'] ?? '');
        $out['test_phone']     = preg_replace('/\D+/', '', $input['test_phone'] ?? '');

        return $out;
    }

    public function assets($hook) {
        if ($hook === 'toplevel_page_wwn-settings') {
            wp_enqueue_style('wwn-admin', WWN_PLUGIN_URL . 'assets/css/admin-style.css', [], WWN_VERSION);
            wp_add_inline_script('jquery-core', "
                jQuery(document).ready(function($){
                    // Toggle token
                    $('.toggle-token').on('click', function(){
                        let field = $('#wwn_token_field');
                        if(field.attr('type') === 'password'){
                            field.attr('type','text'); $(this).text('Hide');
                        } else { field.attr('type','password'); $(this).text('Show'); }
                    });

                    // Tab switching with URL hash
                    function showTab(){
                        var hash = window.location.hash || '#tab-general';
                        $('.wwn-tab-nav').removeClass('nav-tab-active');
                        $('.wwn-tab-content').hide();
                        $('.wwn-tab-nav[href=\"'+hash+'\"]').addClass('nav-tab-active');
                        $(hash).show();
                    }
                    showTab();
                    $('.wwn-tab-nav').click(function(e){
                        e.preventDefault();
                        window.location.hash = $(this).attr('href');
                        showTab();
                    });

                    // Send test offer
                    $('#send_test_offer').click(function(){
                        let number = $('#test_phone').val();
                        let template = $('#offer_template').val();
                        if(number && template){
                            $.post(ajaxurl, { action:'wwn_send_offer_test', phone:number, template:template }, function(res){ alert(res.data); });
                        } else { alert('Please enter test number and template name.'); }
                    });

                    // Send offer to all
                    $('#send_offer_all').click(function(){
                        let template = $('#offer_template').val();
                        if(template){
                            $.post(ajaxurl, { action:'wwn_send_offer_all', template:template }, function(res){ alert(res.data); });
                        } else { alert('Please enter template name first.'); }
                    });
                });
            ");
        }
    }

    public function render() {
        if (!current_user_can('manage_options')) return;
        $opt = get_option(WWN_OPTION_KEY, []);
        settings_errors(WWN_OPTION_KEY);
        $v = fn($k,$d='') => esc_attr($opt[$k] ?? $d);
        ?>
        <div class="wrap wwn-wrap">
            <h1>WhatsApp Notify <small style="opacity:0.6;">(Meta Cloud API)</small></h1>

            <h2 class="nav-tab-wrapper">
                <a href="#tab-general" class="nav-tab wwn-tab-nav nav-tab-active">General</a>
                <a href="#tab-offer" class="nav-tab wwn-tab-nav">Offer Notification</a>
            </h2>

            <div id="tab-general" class="wwn-tab-content">
                <form method="post" action="options.php" class="wwn-card">
                    <?php settings_fields('wwn_settings_group'); ?>
                    <table class="form-table">
                        <tr><th><label for="app_id">App ID *</label></th>
                            <td><input type="text" id="app_id" name="<?php echo WWN_OPTION_KEY; ?>[app_id]" value="<?php echo $v('app_id'); ?>" class="regular-text" required /></td></tr>

                        <tr><th><label for="phone_number_id">Phone Number ID *</label></th>
                            <td><input type="text" id="phone_number_id" name="<?php echo WWN_OPTION_KEY; ?>[phone_number_id]" value="<?php echo $v('phone_number_id'); ?>" class="regular-text" required /></td></tr>

                        <tr><th><label for="wwn_token_field">Access Token *</label></th>
                            <td><div class="wwn-token-field">
                                <input type="password" id="wwn_token_field" name="<?php echo WWN_OPTION_KEY; ?>[access_token]" value="<?php echo $v('access_token'); ?>" class="regular-text" required />
                                <button type="button" class="button toggle-token">Show</button>
                            </div></td></tr>

                        <tr><th><label for="admin_phone">Admin Phone *</label></th>
                            <td><input type="text" id="admin_phone" name="<?php echo WWN_OPTION_KEY; ?>[admin_phone]" value="<?php echo $v('admin_phone'); ?>" class="regular-text" placeholder="91XXXXXXXXXX" required /></td></tr>

                        <tr><th><label for="brand_name">Brand Name</label></th>
                            <td><input type="text" id="brand_name" name="<?php echo WWN_OPTION_KEY; ?>[brand_name]" value="<?php echo $v('brand_name','Your Brand'); ?>" class="regular-text" /></td></tr>

                        <tr><th><label for="abandon_delay_m">Abandoned Cart Delay (minutes)</label></th>
                            <td><input type="number" id="abandon_delay_m" min="30" step="15" name="<?php echo WWN_OPTION_KEY; ?>[abandon_delay_m]" value="<?php echo $v('abandon_delay_m',240); ?>" /></td></tr>

                        <tr><th>Enable Logs</th>
                            <td><label><input type="checkbox" name="<?php echo WWN_OPTION_KEY; ?>[enable_logs]" value="1" <?php checked(1,intval($opt['enable_logs']??0)); ?> /> Yes</label></td></tr>
                    </table>
                    <?php submit_button('Save Settings'); ?>
                </form>
            </div>

            <div id="tab-offer" class="wwn-tab-content" style="display:none;">
                <table class="form-table">
                    <tr><th><label for="offer_template">Template Name</label></th>
                        <td><input type="text" id="offer_template" name="<?php echo WWN_OPTION_KEY; ?>[offer_template]" value="<?php echo $v('offer_template'); ?>" class="regular-text" /></td></tr>

                    <tr><th><label for="test_phone">Test Number</label></th>
                        <td><input type="text" id="test_phone" placeholder="91XXXXXXXXXX" class="regular-text" />
                            <button type="button" class="button" id="send_test_offer">Send Test</button></td></tr>

                    <tr><th>Send To All</th>
                        <td><button type="button" class="button button-primary" id="send_offer_all">Send Offer to All Users</button></td></tr>
                </table>
            </div>
        </div>
        <?php
    }
}
