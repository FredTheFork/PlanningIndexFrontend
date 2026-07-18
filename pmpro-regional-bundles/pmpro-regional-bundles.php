<?php
/**
 * Plugin Name: PMPro Regional Bundles
 * Description: Complete multi-step checkout wizard with regional bundle selection, account creation, template preferences, and dynamic pricing. Mirrored from Per-Council system.
 * Version: 3.0.0
 * Author: Planning Index
 */

if (!defined('ABSPATH')) {
    exit;
}

/*--------------------------------------------------------------
  NUCLEAR FIX: Force CSS for logged-out users via EVERY method
--------------------------------------------------------------*/
function pmrb_nuclear_css_injection() {
    if (is_user_logged_in()) return;
    $configured_level = intval(get_option('pmrb_region_level_id', 0));
    if ($configured_level === 0) return;
    $current_level = intval($_REQUEST['level'] ?? $_REQUEST['pmpro_level'] ?? $_GET['pmpro_level'] ?? 0);
    if ($current_level !== $configured_level) return;
    
    $css_url = plugins_url('assets/pmrb-style.css', __FILE__);
    echo '<link rel="stylesheet" id="pmrb-css-nuclear" href="' . esc_url($css_url) . '?v=' . time() . '" type="text/css" media="all" />' . "\n";
    
    // Also inline the CSS
    $css_file = plugin_dir_path(__FILE__) . 'assets/pmrb-style.css';
    if (file_exists($css_file)) {
        echo '<style id="pmrb-css-inline">' . file_get_contents($css_file) . '</style>' . "\n";
    }
}
add_action('wp_head', 'pmrb_nuclear_css_injection', 1);
add_action('wp_head', 'pmrb_nuclear_css_injection', 999);

/*--------------------------------------------------------------
  CONFIGURATION
--------------------------------------------------------------*/
define('PMRB_META_REGION_KEY', 'pmrb_region_bundle');
define('PMRB_META_ALLOWED_KEY', 'pmrb_allowed_councils');
define('PMRB_META_PRICE', 'pmrb_calculated_price');
define('PMRB_META_TEMPLATE', 'pmrb_default_template');
define('PMRB_META_BUSINESS', 'pmrb_business_info');
define('PMRB_OPTION_LEVEL_ID', 'pmrb_region_level_id');
define('PMRB_TOTAL_STEPS', 4);
define('PMRB_SESSION_KEY', 'pmrb_checkout_session');

/*--------------------------------------------------------------
  REGION BUNDLES DATA
--------------------------------------------------------------*/
require_once plugin_dir_path(__FILE__) . 'includes/region-bundles.php';

/*--------------------------------------------------------------
  TEMPLATE DATA
--------------------------------------------------------------*/
function pmrb_get_templates() {
    return [
        'professional' => [
            'name' => 'Professional',
            'description' => 'Clean and formal business layout',
            'icon' => 'file-text'
        ],
        'modern' => [
            'name' => 'Modern',
            'description' => 'Contemporary design with sidebar',
            'icon' => 'layout'
        ],
        'classic' => [
            'name' => 'Classic',
            'description' => 'Traditional formal style',
            'icon' => 'book-open'
        ],
        'minimal' => [
            'name' => 'Minimal',
            'description' => 'Simple and straightforward',
            'icon' => 'minus-square'
        ]
    ];
}

/*--------------------------------------------------------------
  HELPER: Check if current checkout is regional bundles level
--------------------------------------------------------------*/
function pmrb_is_regional_checkout() {
    $configured_level = intval(get_option(PMRB_OPTION_LEVEL_ID, 0));
    
    if ($configured_level === 0) {
        return false;
    }

    $current_level = 0;
    
    if (isset($_REQUEST['level'])) {
        $current_level = intval($_REQUEST['level']);
    } elseif (isset($_REQUEST['pmpro_level'])) {
        $current_level = intval($_REQUEST['pmpro_level']);
    } elseif (isset($_GET['pmpro_level'])) {
        $current_level = intval($_GET['pmpro_level']);
    } elseif (isset($GLOBALS['pmpro_level']->id)) {
        $current_level = intval($GLOBALS['pmpro_level']->id);
    }

    return $current_level === $configured_level;
}

/*--------------------------------------------------------------
  FRONTEND SCRIPTS & STYLES
--------------------------------------------------------------*/
add_action('wp_enqueue_scripts', function () {
    // Check if we're on a checkout page - support both PMPro detection and URL fallback for logged-out users
    $is_checkout_page = false;
    
    if (function_exists('pmpro_is_checkout') && pmpro_is_checkout()) {
        $is_checkout_page = true;
    } else {
        // Fallback: Check URL for checkout patterns (for logged-out users where pmpro_is_checkout may fail)
        $request_uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if ($request_uri) {
            $checkout_patterns = ['/membership/checkout', '/checkout', '/register'];
            foreach ($checkout_patterns as $pattern) {
                if (strpos($request_uri, $pattern) !== false) {
                    $is_checkout_page = true;
                    break;
                }
            }
            // Also check for level parameter which indicates checkout
            if (isset($_GET['level']) || isset($_REQUEST['level']) || isset($_REQUEST['pmpro_level'])) {
                $is_checkout_page = true;
            }
        }
    }
    
    if (!$is_checkout_page || !pmrb_is_regional_checkout()) {
        return;
    }

    wp_enqueue_script(
        'pmrb-checkout-js',
        plugin_dir_url(__FILE__) . 'assets/pmrb-checkout.js',
        ['jquery'],
        '3.0.0',
        true
    );

    // Get user's current template from business settings (if logged in)
    $user_current_template = 'basic';
    if (is_user_logged_in()) {
        $business_info = get_user_meta(get_current_user_id(), '_pi_business_info', true) ?: [];
        $user_current_template = $business_info['default_template'] ?? 'basic';
    }

    // Get region bundles with prices
    $bundles = pis_get_region_bundles();
    $regions = [];
    $prices = [];
    foreach ($bundles as $region => $meta) {
        $regions[$region] = [
            'price' => floatval($meta['price']),
            'councils' => $meta['councils'] ?? []
        ];
        $prices[$region] = floatval($meta['price']);
    }

    wp_localize_script('pmrb-checkout-js', 'pmrbConfig', [
        'regions' => $regions,
        'prices' => $prices,
        'templates' => pmrb_get_templates(),
        'totalSteps' => PMRB_TOTAL_STEPS,
        'checkoutUrl' => pmpro_url('checkout'),
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'restUrl' => rest_url('pi/v1'),
        'restNonce' => wp_create_nonce('wp_rest'),
        'nonce' => wp_create_nonce('pmrb_checkout_nonce'),
        'sessionNonce' => wp_create_nonce('pmrb_session_nonce'),
        'isLoggedIn' => is_user_logged_in(),
        'userCurrentTemplate' => $user_current_template,
        'strings' => [
            'selectRegion' => 'Please select a regional bundle to continue.',
            'usernameRequired' => 'Please enter a username.',
            'passwordRequired' => 'Please enter a password with at least 8 characters.',
            'passwordMismatch' => 'Passwords do not match.',
            'emailRequired' => 'Please enter a valid email address.',
            'emailMismatch' => 'Email addresses do not match.',
            'processing' => 'Processing your subscription...',
            'continue' => 'Continue',
            'completeSubscription' => 'Complete Subscription',
            'perMonth' => '/month',
            'loadingTemplates' => 'Loading templates...',
            'templateLoadError' => 'Unable to load templates. Using defaults.'
        ]
    ]);

    wp_enqueue_style(
        'pmrb-checkout-css',
        plugin_dir_url(__FILE__) . 'assets/pmrb-style.css',
        [],
        '3.0.0'
    );

    // Inline PayPal/Stripe sync script (critical)
    $inline_js = <<<'JS'
(function($){
  function pmrb_sync_everything() {
    try {
      var priceVal = $('#pmrb_calculated_price').val();
      if (!priceVal) {
        var label = $('#pmrb_price_display').text();
        if (label) {
          var m = label.match(/([0-9]+(?:\.[0-9]{1,2})?)/);
          if (m) priceVal = m[1];
        }
      }
      priceVal = (priceVal || '0').toString();
      var parsed = parseFloat(priceVal);
      if (!isNaN(parsed)) {
        var formatted = parsed.toFixed(2);
        $('#pmrb_calculated_price').val(formatted);
        try { sessionStorage.setItem('pmrb_calculated_price', formatted); } catch(e){}
      }
      var region = $('#pmrb_region_bundle').val();
      try { sessionStorage.setItem('pmrb_region_selected', region || ''); } catch(e){}
    } catch(e){}
  }

  $(document).on('submit', 'form#pmpro_form, form[name="pmpro_form"]', function(){
    pmrb_sync_everything();
    return true;
  });

  var paypalSelectors = [
    'button#pmpro_paypalexpress',
    'button.pmpro_paypalexpress',
    'a.pmpro_paypalexpress',
    'button[data-gateway="paypalexpress"]',
    'button.pmpro_btn_checkout'
  ];

  $(document).on('click', paypalSelectors.join(','), function(){
    pmrb_sync_everything();
    return true;
  });

  $(function(){
    try {
      var storedPrice = sessionStorage.getItem('pmrb_calculated_price');
      if (storedPrice) $('#pmrb_calculated_price').val(storedPrice);

      var storedRegion = sessionStorage.getItem('pmrb_region_selected');
      if (storedRegion) {
        $('#pmrb_region_bundle').val(storedRegion);
      }
    } catch(e){}
  });
})(jQuery);
JS;

    wp_add_inline_script('pmrb-checkout-js', $inline_js);
});

/*--------------------------------------------------------------
  AJAX: VALIDATE USERNAME/EMAIL AVAILABILITY
--------------------------------------------------------------*/
add_action('wp_ajax_nopriv_pmrb_check_user', 'pmrb_ajax_check_user');
add_action('wp_ajax_pmrb_check_user', 'pmrb_ajax_check_user');

function pmrb_ajax_check_user() {
    check_ajax_referer('pmrb_checkout_nonce', 'nonce');
    
    $username = isset($_POST['username']) ? sanitize_user($_POST['username']) : '';
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    
    $response = ['valid' => true, 'errors' => []];
    
    if ($username && username_exists($username)) {
        $response['valid'] = false;
        $response['errors']['username'] = 'This username is already taken.';
    }
    
    if ($email && email_exists($email)) {
        $response['valid'] = false;
        $response['errors']['email'] = 'This email is already registered.';
    }
    
    wp_send_json($response);
}

/*--------------------------------------------------------------
  VALIDATION FILTER
--------------------------------------------------------------*/
add_filter('pmpro_registration_checks', function ($ok) {
    $configured_level = intval(get_option(PMRB_OPTION_LEVEL_ID, 0));
    $chosen_level = isset($_REQUEST['level']) ? intval($_REQUEST['level']) : 0;
    
    if ($chosen_level === 0 && isset($_REQUEST['pmpro_level'])) {
        $chosen_level = intval($_REQUEST['pmpro_level']);
    }

    if ($configured_level === 0 || $chosen_level !== $configured_level) {
        return $ok;
    }

    if (empty($_POST['pmrb_region_bundle'])) {
        pmpro_setMessage('Please select a regional bundle before continuing.', 'pmpro_error');
        return false;
    }

    $region = sanitize_text_field($_POST['pmrb_region_bundle']);
    $bundles = pis_get_region_bundles();

    if (!isset($bundles[$region])) {
        pmpro_setMessage('Invalid region selected.', 'pmpro_error');
        return false;
    }

    $expected_price = floatval($bundles[$region]['price']);
    $posted_price = isset($_POST['pmrb_calculated_price']) ? floatval($_POST['pmrb_calculated_price']) : 0;

    if (abs($posted_price - $expected_price) > 0.01) {
        pmpro_setMessage('Price validation failed. Please reselect your region and try again.', 'pmpro_error');
        return false;
    }

    $_REQUEST['pmrb_region_bundle'] = $region;
    $_REQUEST['pmrb_calculated_price'] = number_format($expected_price, 2, '.', '');

    return $ok;
});

/*--------------------------------------------------------------
  CORE: Override PMPro checkout level object
--------------------------------------------------------------*/
add_filter('pmpro_checkout_level', function ($level) {
    $configured_level = intval(get_option(PMRB_OPTION_LEVEL_ID, 0));
    
    if (empty($configured_level) || intval($level->id) !== $configured_level) {
        return $level;
    }

    $dynamic_price = pmrb_get_price_from_request();
    
    if ($dynamic_price <= 0) {
        return $level;
    }

    $level->initial_payment = $dynamic_price;
    $level->billing_amount = $dynamic_price;
    
    if (property_exists($level, 'cycle_number') && $level->cycle_number > 0) {
        $level->billing_amount = $dynamic_price;
    }

    return $level;
}, 20);

/*--------------------------------------------------------------
  Helper: Get price from request
--------------------------------------------------------------*/
function pmrb_get_price_from_request() {
    if (is_user_logged_in()) {
        $stored = get_user_meta(get_current_user_id(), PMRB_META_PRICE, true);
        if ($stored && floatval($stored) > 0) {
            return floatval($stored);
        }
    }

    if (!empty($_REQUEST['pmrb_calculated_price'])) {
        return floatval(sanitize_text_field(wp_unslash($_REQUEST['pmrb_calculated_price'])));
    }

    // Try to get from region bundle
    if (!empty($_REQUEST['pmrb_region_bundle'])) {
        $bundles = pis_get_region_bundles();
        $region = sanitize_text_field($_REQUEST['pmrb_region_bundle']);
        if (isset($bundles[$region])) {
            return floatval($bundles[$region]['price']);
        }
    }

    return 0;
}

/*--------------------------------------------------------------
  ORDER PRICE OVERRIDE (safety net)
--------------------------------------------------------------*/
add_action('pmpro_checkout_before_processing', function () {
    $price = pmrb_get_price_from_request();
    if ($price <= 0) return;

    $_REQUEST['initial_payment'] = $price;
    $_REQUEST['amount'] = $price;
    $_REQUEST['payment_amount'] = $price;
});

add_action('pmpro_checkout_before_payment', function ($morder) {
    $price = pmrb_get_price_from_request();
    if ($price <= 0) return;

    $morder->initial_payment = $price;
    $morder->payment_amount = $price;
    $morder->subtotal = $price;
    $morder->total = $price;
    $morder->billing_amount = $price;

    if (!empty($morder->membership_id) && empty($morder->membership_level)) {
        if (function_exists('pmpro_getLevel')) {
            $lvl = pmpro_getLevel($morder->membership_id);
            if ($lvl && is_object($lvl)) {
                $morder->membership_level = $lvl;
            }
        }
    }

    $configured_level = intval(get_option(PMRB_OPTION_LEVEL_ID, 0));
    if (!empty($configured_level) && !empty($morder->membership_level) && is_object($morder->membership_level) && intval($morder->membership_level->id) === $configured_level) {
        $morder->membership_level->initial_payment = $price;
        $morder->membership_level->billing_amount = $price;
    }
});

/*--------------------------------------------------------------
  SAVE USER DATA AFTER CHECKOUT
--------------------------------------------------------------*/
add_action('pmpro_after_checkout', function ($user_id, $morder) {
    // Region — always save (subscription-specific)
    if (!empty($_REQUEST['pmrb_region_bundle'])) {
        $region = sanitize_text_field($_REQUEST['pmrb_region_bundle']);
        update_user_meta($user_id, PMRB_META_REGION_KEY, $region);

        $bundles = pis_get_region_bundles();
        $allowed = $bundles[$region]['councils'] ?? [];
        $allowed = array_map('sanitize_text_field', $allowed);
        update_user_meta($user_id, PMRB_META_ALLOWED_KEY, $allowed);
        update_user_meta($user_id, 'pmpc_selected_councils', $allowed);
    }

    // Price — always save (subscription-specific)
    if (!empty($_REQUEST['pmrb_calculated_price'])) {
        $price = floatval(sanitize_text_field(wp_unslash($_REQUEST['pmrb_calculated_price'])));
        update_user_meta($user_id, PMRB_META_PRICE, number_format($price, 2, '.', ''));
    }

    // ══════════════════════════════════════════════════════════════════
    // CHECK: If Settings page was EVER saved, do NOT overwrite business info / template
    // ══════════════════════════════════════════════════════════════════
    $existing_info = get_user_meta($user_id, '_pi_business_info', true);
    $settings_saved = is_array($existing_info) && !empty($existing_info['settings_updated_at']);

    if ($settings_saved) {
        error_log("[PMRB] Settings exist for user #$user_id — skipping business info/template override from checkout");
        // Still save to plugin-specific meta for reference
        if (!empty($_REQUEST['pmrb_default_template'])) {
            update_user_meta($user_id, PMRB_META_TEMPLATE, sanitize_text_field($_REQUEST['pmrb_default_template']));
        }
    } else {
        // No settings saved yet — checkout data becomes primary
        if (!empty($_REQUEST['pmrb_default_template'])) {
            $template = sanitize_text_field($_REQUEST['pmrb_default_template']);
            update_user_meta($user_id, PMRB_META_TEMPLATE, $template);
            
            $business_info = get_user_meta($user_id, '_pi_business_info', true) ?: [];
            $business_info['default_template'] = $template;
            $business_info['source'] = 'checkout';
            update_user_meta($user_id, '_pi_business_info', $business_info);
        }

        // Business info from checkout form
        $checkout_business_info = [];
        $business_fields = ['pmrb_company_name', 'pmrb_business_email', 'pmrb_business_phone', 'pmrb_company_address', 'pmrb_website', 'pmrb_vat_number'];
        
        foreach ($business_fields as $field) {
            if (!empty($_REQUEST[$field])) {
                $checkout_business_info[$field] = sanitize_text_field($_REQUEST[$field]);
            }
        }
        
        if (!empty($checkout_business_info)) {
            update_user_meta($user_id, PMRB_META_BUSINESS, $checkout_business_info);
            
            $business_info = get_user_meta($user_id, '_pi_business_info', true) ?: [];
            
            $field_map = [
                'pmrb_company_name' => 'company_name',
                'pmrb_business_email' => 'email',
                'pmrb_business_phone' => 'phone',
                'pmrb_company_address' => 'company_address',
                'pmrb_website' => 'website',
            ];
            
            foreach ($field_map as $checkout_key => $settings_key) {
                if (!empty($checkout_business_info[$checkout_key])) {
                    $business_info[$settings_key] = $checkout_business_info[$checkout_key];
                }
            }
            
            $business_info['source'] = 'checkout';
            update_user_meta($user_id, '_pi_business_info', $business_info);
        }
    }

    // Order note
    if (!empty($morder)) {
        $region = isset($_REQUEST['pmrb_region_bundle']) ? $_REQUEST['pmrb_region_bundle'] : '';
        $morder->notes = "RegionalBundleSelected: $region";
        if (method_exists($morder, 'save')) {
            $morder->save();
        }
    }
}, 10, 2);

/*--------------------------------------------------------------
  STRIPE SPECIFIC HOOKS
--------------------------------------------------------------*/
add_filter('pmpro_stripe_create_subscription_array', function ($params, $order) {
    $price = pmrb_get_price_from_request();
    if ($price > 0 && isset($params['items'][0]['price_data']['unit_amount'])) {
        $params['items'][0]['price_data']['unit_amount'] = intval($price * 100);
    }
    return $params;
}, 20, 2);

add_filter('pmpro_stripe_payment_intent_amount', function ($amount, $order) {
    $price = pmrb_get_price_from_request();
    if ($price > 0) {
        return intval($price * 100);
    }
    return $amount;
}, 20, 2);

add_filter('pmpro_stripe_create_payment_intent_array', function ($intent_array, $order) {
    $price = pmrb_get_price_from_request();
    if ($price > 0) {
        $intent_array['amount'] = intval($price * 100);
    }
    return $intent_array;
}, 20, 2);

/*--------------------------------------------------------------
  MULTI-STEP SERVER-SIDE HANDLER
--------------------------------------------------------------*/
add_action('pmpro_checkout_preheader', 'pmrb_multi_step_handler');
function pmrb_multi_step_handler() {
    if (!pmrb_is_regional_checkout()) {
        return;
    }

    if (!session_id()) {
        session_start();
    }

    // 1. Restore session data on every load
    $data = isset($_SESSION[PMRB_SESSION_KEY]) ? (array) $_SESSION[PMRB_SESSION_KEY] : [];
    if (!empty($data)) {
        $_REQUEST = array_merge($_REQUEST, $data);
    }

    // 2. Final submit handling
    if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
        (isset($_POST['submit-checkout']) || isset($_POST['pmpro_submit']) || isset($_POST['javascriptok']))) {
        
        if (!empty($data)) {
            if (isset($data['region']))       $_REQUEST['pmrb_region_bundle']    = $data['region'];
            if (isset($data['price']))        $_REQUEST['pmrb_calculated_price'] = $data['price'];
            if (isset($data['template']))     $_REQUEST['pmrb_default_template'] = $data['template'];
            if (!empty($data['business'])) {
                foreach ($data['business'] as $k => $v) {
                    $_REQUEST[$k] = $v;
                }
            }
            if (!is_user_logged_in() && isset($data['username'])) {
                $_REQUEST['username']       = $data['username'];
                $_REQUEST['password']       = $data['password'];
                $_REQUEST['password2']      = $data['password'];
                $_REQUEST['bemail']         = $data['email'];
                $_REQUEST['bconfirmemail']  = $data['email'];
            }
        }
        unset($_SESSION[PMRB_SESSION_KEY]);
        return;
    }

    // 3. Handle AJAX step saves
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pmrb_save_step') {
        check_ajax_referer('pmrb_checkout_nonce', 'nonce');

        $step = isset($_POST['step']) ? intval($_POST['step']) : 0;
        if (!$step || $step >= 4) {
            wp_send_json_error(['message' => 'Invalid step']);
        }

        switch ($step) {
            case 1:
                $region = sanitize_text_field($_POST['region'] ?? '');
                if (empty($region)) {
                    wp_send_json_error(['message' => 'Please select a regional bundle.']);
                }
                $bundles = pis_get_region_bundles();
                if (!isset($bundles[$region])) {
                    wp_send_json_error(['message' => 'Invalid region selected.']);
                }
                $data['region'] = $region;
                $data['price']  = floatval($bundles[$region]['price']);
                break;

            case 2:
                $data['template'] = sanitize_text_field($_POST['template'] ?? 'professional');
                $business = [];
                $fields = ['pmrb_company_name', 'pmrb_business_email', 'pmrb_business_phone',
                           'pmrb_company_address', 'pmrb_website', 'pmrb_vat_number'];
                foreach ($fields as $f) {
                    if (isset($_POST[$f])) {
                        $business[$f] = sanitize_text_field($_POST[$f]);
                    }
                }
                $data['business'] = $business;
                break;

            case 3:
                if (!is_user_logged_in()) {
                    $data['username'] = sanitize_user($_POST['username'] ?? '');
                    $data['password'] = $_POST['password'] ?? '';
                    $data['email']    = sanitize_email($_POST['bemail'] ?? '');
                }
                break;
        }

        $_SESSION[PMRB_SESSION_KEY] = $data;
        wp_send_json_success(['step' => $step + 1]);
    }
}

/*--------------------------------------------------------------
  ADMIN SETTINGS PAGE
--------------------------------------------------------------*/
add_action('admin_menu', function () {
    add_submenu_page(
        'pmpro-dashboard',
        'Regional Bundles Settings',
        'Regional Bundles',
        'manage_options',
        'pmrb-settings',
        'pmrb_render_admin_page'
    );
});

function pmrb_render_admin_page() {
    if (isset($_POST['pmrb_save']) && check_admin_referer('pmrb_admin_settings')) {
        update_option(PMRB_OPTION_LEVEL_ID, intval($_POST['pmrb_level_id']));
        echo '<div class="notice notice-success"><p>Settings saved successfully.</p></div>';
    }

    $levels = function_exists('pmpro_getAllLevels') ? pmpro_getAllLevels(true, true) : [];
    $current_level = intval(get_option(PMRB_OPTION_LEVEL_ID, 0));
    $bundles = pis_get_region_bundles();
    ?>
    <div class="wrap">
        <h1>Regional Bundles Settings</h1>
        <p>Configure the regional bundles checkout system for Paid Memberships Pro.</p>
        
        <form method="post">
            <?php wp_nonce_field('pmrb_admin_settings'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="pmrb_level_id">Regional Bundles Level</label>
                    </th>
                    <td>
                        <select name="pmrb_level_id" id="pmrb_level_id">
                            <option value="0">-- Select Level --</option>
                            <?php foreach ($levels as $level): ?>
                                <option value="<?php echo intval($level->id); ?>" <?php selected($current_level, $level->id); ?>>
                                    <?php echo esc_html($level->name) . ' (ID: ' . $level->id . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Select the membership level that uses regional bundle pricing.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Configuration</th>
                    <td>
                        <p><strong>Total Regions Available:</strong> <?php echo count($bundles); ?></p>
                        <p><strong>Price Range:</strong> £<?php 
                            $prices = array_map(function($b) { return floatval($b['price']); }, $bundles);
                            echo min($prices) . ' - £' . max($prices);
                        ?></p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="pmrb_save" class="button button-primary" value="Save Settings">
            </p>
        </form>
    </div>
    <?php
}

/*--------------------------------------------------------------
  SHORTCODE: Display user's selected region
--------------------------------------------------------------*/
add_shortcode('pmrb_user_region', function ($atts) {
    if (!is_user_logged_in()) {
        return '<p>Please log in to view your selected region.</p>';
    }

    $region = get_user_meta(get_current_user_id(), PMRB_META_REGION_KEY, true);
    
    if (empty($region)) {
        return '<p>No region selected yet.</p>';
    }

    return '<p><strong>Selected Region:</strong> ' . esc_html($region) . '</p>';
});

/*--------------------------------------------------------------
  REST API: Get regions list
--------------------------------------------------------------*/
add_action('rest_api_init', function () {
    register_rest_route('pmrb/v1', '/regions', [
        'methods' => 'GET',
        'callback' => function () {
            return new WP_REST_Response(pis_get_region_bundles(), 200);
        },
        'permission_callback' => '__return_true'
    ]);
});

/*--------------------------------------------------------------
  FORCE INCLUDE CUSTOM TEMPLATE
--------------------------------------------------------------*/

add_action('pmpro_checkout_preheader', function() {
    global $pmpro_pages;

    if (!is_page($pmpro_pages['checkout']) || !pmrb_is_regional_checkout()) {
        return;
    }

    $is_real_load = (
        $_SERVER['REQUEST_METHOD'] === 'GET' ||
        ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['submit-checkout']))
    );

    if (!$is_real_load) {
        return;
    }

    if (isset($_GET['confirm']) || isset($_GET['review'])) {
        return;
    }

    $custom_file = get_stylesheet_directory() . '/paid-memberships-pro/pages/checkoutrb.php';

    if (!file_exists($custom_file)) {
        return;
    }

    remove_all_filters('pmpro_get_template');
    remove_all_filters('pmpro_pages_custom_template_path');

    // ENQUEUE ASSETS HERE (before get_header ensures they load even with early exit)
    // This is critical for logged-out users where wp_enqueue_scripts hasn't fired yet
    wp_enqueue_script(
        'pmrb-checkout-js',
        plugin_dir_url(__FILE__) . 'assets/pmrb-checkout.js',
        ['jquery'],
        '3.0.0',
        true
    );

    // Get user's current template from business settings (if logged in)
    $user_current_template = 'basic';
    if (is_user_logged_in()) {
        $business_info = get_user_meta(get_current_user_id(), '_pi_business_info', true) ?: [];
        $user_current_template = $business_info['default_template'] ?? 'basic';
    }

    // Get region bundles with prices
    $bundles = pis_get_region_bundles();
    $regions = [];
    $prices = [];
    foreach ($bundles as $region => $meta) {
        $regions[$region] = [
            'price' => floatval($meta['price']),
            'councils' => $meta['councils'] ?? []
        ];
        $prices[$region] = floatval($meta['price']);
    }

    wp_localize_script('pmrb-checkout-js', 'pmrbConfig', [
        'regions' => $regions,
        'prices' => $prices,
        'templates' => pmrb_get_templates(),
        'totalSteps' => PMRB_TOTAL_STEPS,
        'checkoutUrl' => pmpro_url('checkout'),
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'restUrl' => rest_url('pi/v1'),
        'restNonce' => wp_create_nonce('wp_rest'),
        'nonce' => wp_create_nonce('pmrb_checkout_nonce'),
        'sessionNonce' => wp_create_nonce('pmrb_session_nonce'),
        'isLoggedIn' => is_user_logged_in(),
        'userCurrentTemplate' => $user_current_template,
        'strings' => [
            'selectRegion' => 'Please select a regional bundle to continue.',
            'usernameRequired' => 'Please enter a username.',
            'passwordRequired' => 'Please enter a password with at least 8 characters.',
            'passwordMismatch' => 'Passwords do not match.',
            'emailRequired' => 'Please enter a valid email address.',
            'emailMismatch' => 'Email addresses do not match.',
            'processing' => 'Processing your subscription...',
            'continue' => 'Continue',
            'completeSubscription' => 'Complete Subscription',
            'perMonth' => '/month',
            'loadingTemplates' => 'Loading templates...',
            'templateLoadError' => 'Unable to load templates. Using defaults.'
        ]
    ]);

    wp_enqueue_style(
        'pmrb-checkout-css',
        plugin_dir_url(__FILE__) . 'assets/pmrb-style.css',
        [],
        '3.0.0'
    );

    // Inline PayPal/Stripe sync script (critical)
    $inline_js = <<<'JS'
(function($){
  function pmrb_sync_everything() {
    try {
      var priceVal = $('#pmrb_calculated_price').val();
      if (!priceVal) {
        var label = $('#pmrb_price_display').text();
        if (label) {
          var m = label.match(/([0-9]+(?:\.[0-9]{1,2})?)/);
          if (m) priceVal = m[1];
        }
      }
      priceVal = (priceVal || '0').toString();
      var parsed = parseFloat(priceVal);
      if (!isNaN(parsed)) {
        var formatted = parsed.toFixed(2);
        $('#pmrb_calculated_price').val(formatted);
        try { sessionStorage.setItem('pmrb_calculated_price', formatted); } catch(e){}
      }
      var region = $('#pmrb_region_bundle').val();
      try { sessionStorage.setItem('pmrb_region_selected', region || ''); } catch(e){}
    } catch(e){}
  }

  $(document).on('submit', 'form#pmpro_form, form[name="pmpro_form"]', function(){
    pmrb_sync_everything();
    return true;
  });

  var paypalSelectors = [
    'button#pmpro_paypalexpress',
    'button.pmpro_paypalexpress',
    'a.pmpro_paypalexpress',
    'button[data-gateway="paypalexpress"]',
    'button.pmpro_btn_checkout'
  ];

  $(document).on('click', paypalSelectors.join(','), function(){
    pmrb_sync_everything();
    return true;
  });

  $(function(){
    try {
      var storedPrice = sessionStorage.getItem('pmrb_calculated_price');
      if (storedPrice) $('#pmrb_calculated_price').val(storedPrice);

      var storedRegion = sessionStorage.getItem('pmrb_region_selected');
      if (storedRegion) {
        $('#pmrb_region_bundle').val(storedRegion);
      }
    } catch(e){}
  });
})(jQuery);
JS;

    wp_add_inline_script('pmrb-checkout-js', $inline_js);
    
    // CRITICAL FIX: Ensure jQuery is enqueued
    wp_enqueue_script('jquery');

    ob_start();
    get_header();
    
    // CRITICAL FIX: For logged-out users, always manually output CSS directly
    // This bypasses any WordPress hooks that might be preventing proper enqueue
    if (!is_user_logged_in()) {
        $css_url = plugin_dir_url(__FILE__) . 'assets/pmrb-style.css';
        echo "\n<!-- PMRB REGIONAL: Direct CSS injection for logged-out users -->\n";
        echo '<link rel="stylesheet" id="pmrb-style-css-direct" href="' . esc_url($css_url) . '?v=2.0.0" type="text/css" media="all" />' . "\n";
    }
    
    include $custom_file;
    get_footer();

    $output = ob_get_clean();
    echo $output;

    exit;
});

/*--------------------------------------------------------------
  HELPER: Get region prices as JSON
--------------------------------------------------------------*/
function pis_get_region_prices_json() {
    $bundles = pis_get_region_bundles();
    $out = [];
    foreach ($bundles as $r => $m) {
        $out[$r] = floatval($m['price']);
    }
    return $out;
}
