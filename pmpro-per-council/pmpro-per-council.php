<?php
/**
 * Plugin Name: PMPro Per Council Selector
 * Description: Complete multi-step checkout wizard with council selection, account creation, template preferences, and dynamic Stripe pricing.
 * Version: 2.2.0
 * Author: Planning Index
 */

if (!defined('ABSPATH')) {
    exit;
}

/*--------------------------------------------------------------
  NUCLEAR FIX: Force CSS for logged-out users via EVERY method
--------------------------------------------------------------*/
function pmpc_nuclear_css_injection() {
    if (is_user_logged_in()) return;
    $configured_level = intval(get_option('pmpc_per_council_level_id', 0));
    if ($configured_level === 0) return;
    $current_level = intval($_REQUEST['level'] ?? $_REQUEST['pmpro_level'] ?? $_GET['pmpro_level'] ?? 0);
    if ($current_level !== $configured_level) return;
    
    $css_url = plugins_url('assets/pmpc-style.css', __FILE__);
    echo '<link rel="stylesheet" id="pmpc-css-nuclear" href="' . esc_url($css_url) . '?v=' . time() . '" type="text/css" media="all" />' . "\n";
    
    // Also inline the CSS
    $css_file = plugin_dir_path(__FILE__) . 'assets/pmpc-style.css';
    if (file_exists($css_file)) {
        echo '<style id="pmpc-css-inline">' . file_get_contents($css_file) . '</style>' . "\n";
    }
}
add_action('wp_head', 'pmpc_nuclear_css_injection', 1);
add_action('wp_head', 'pmpc_nuclear_css_injection', 999);

/*--------------------------------------------------------------
  CRITICAL: SETTINGS PRIORITY CHECK
  If user has saved Settings, those take precedence over checkout
--------------------------------------------------------------*/
function pmpc_should_use_settings($user_id) {
    $settings_data = get_user_meta($user_id, '_pi_business_info', true);
    $settings_timestamp = $settings_data['settings_updated_at'] ?? '';
    return !empty($settings_timestamp);
}

function pmpc_get_effective_business_info($user_id) {
    // If Settings have been saved, use them exclusively
    if (pmpc_should_use_settings($user_id)) {
        $settings = get_user_meta($user_id, '_pi_business_info', true);
        error_log("[PMPC] Using Settings data for user #$user_id (saved: " . ($settings['settings_updated_at'] ?? 'unknown') . ")");
        return [
            'source' => 'settings',
            'data' => $settings,
            'template' => $settings['default_template'] ?? 'basic'
        ];
    }
    
    // Otherwise, use checkout data
    error_log("[PMPC] No Settings found for user #$user_id, will use checkout data");
    return [
        'source' => 'checkout',
        'data' => null,
        'template' => null
    ];
}
/*--------------------------------------------------------------
  CONFIGURATION
--------------------------------------------------------------*/
define('PMPC_UNIT_PRICE', 3);
define('PMPC_MIN_SELECTION', 3);
define('PMPC_META_KEY', 'pmpc_selected_councils');
define('PMPC_META_PRICE', 'pmpc_calculated_price');
define('PMPC_META_TEMPLATE', 'pmpc_default_template');
define('PMPC_META_BUSINESS', 'pmpc_business_info');
define('PMPC_TOTAL_STEPS', 4);
define('PMPC_SESSION_KEY', 'pmpc_checkout_session');

/*--------------------------------------------------------------
  COUNCIL DATA
--------------------------------------------------------------*/
function pmpc_get_all_councils() {
    return [
        'Aberdeen', 'Aberdeenshire', 'Adur', 'Allerdale', 'Amber Valley', 'Angus',
        'Antrim and Newtownabbey', 'Argyll and Bute', 'Armagh Banbridge and Craigavon',
        'Arun', 'Ashfield', 'Ashford', 'Babergh and Mid Suffolk',
        'Barking and Dagenham', 'Barnet', 'Barnsley', 'Basildon', 'Basingstoke and Deane',
        'Bassetlaw', 'Bath and North East Somerset', 'Bedford', 'Belfast', 'Bexley',
        'Birmingham', 'Blaby', 'Blackburn with Darwen', 'Blackpool', 'Blaenau Gwent',
        'Bolsover', 'Bolton', 'Boston', 'Bournemouth Christchurch and Poole',
        'Bracknell Forest', 'Bradford', 'Braintree', 'Breckland', 'Brent', 'Brentwood',
        'Bridgend', 'Brighton and Hove', 'Bristol', 'Broadland', 'Bromley',
        'Bromsgrove and Redditch', 'Broxbourne', 'Broxtowe', 'Buckinghamshire', 'Burnley', 'Bury',
        'Caerphilly', 'Calderdale', 'Cambridge', 'Cambridgeshire', 'Camden', 'Cannock Chase',
        'Canterbury', 'Cardiff', 'Carlisle', 'Carmarthenshire', 'Castle Point',
        'Central Bedfordshire', 'Ceredigion', 'Charnwood', 'Chelmsford', 'Cheltenham',
        'Cherwell', 'Cheshire East', 'Cheshire West and Chester', 'Chesterfield',
        'Chichester', 'Chorley', 'Clackmannanshire',
        'Colchester', 'Comhairle nan Eilean Siar', 'Conwy', 'Copeland',
        'Cornwall', 'Cotswold', 'Coventry', 'Crawley', 'Croydon', 'Dacorum',
        'Darlington', 'Dartford', 'Denbighshire', 'Derby', 'Derbyshire Dales',
        'Derry City and Strabane', 'Doncaster', 'Dorset', 'Dover', 'Dudley', 'Dundee',
        'Durham', 'Ealing', 'East Ayrshire', 'East Cambridgeshire', 'East Devon',
        'East Dunbartonshire', 'East Hampshire', 'East Hertfordshire', 'East Lindsey',
        'East Lothian', 'East Northamptonshire', 'East Renfrewshire', 'East Riding of Yorkshire',
        'East Staffordshire', 'East Suffolk', 'Eastbourne', 'Eastleigh', 'Edinburgh',
        'Elmbridge', 'Enfield', 'Epping Forest', 'Epsom and Ewell', 'Erewash', 'Exeter',
        'Falkirk', 'Fareham', 'Fenland', 'Fermanagh and Omagh', 'Fife', 'Flintshire',
        'Folkestone and Hythe', 'Forest of Dean', 'Fylde', 'Gateshead', 'Gedling', 'Glasgow',
        'Gloucester', 'Gosport', 'Gravesham', 'Great Yarmouth', 'Greater Manchester',
        'Greenwich', 'Guildford', 'Gwynedd', 'Hackney', 'Halton', 'Hambleton',
        'Hammersmith and Fulham', 'Harborough', 'Haringey', 'Harlow', 'Harrow', 'Harrogate',
        'Hart', 'Hartlepool', 'Hastings', 'Havant', 'Havering', 'Herefordshire', 'Hertsmere',
        'High Peak', 'Highland', 'Hillingdon', 'Hinckley and Bosworth', 'Horsham', 'Hounslow',
        'Huntingdonshire', 'Hyndburn', 'Inverclyde', 'Ipswich', 'Isle of Anglesey',
        'Isle of Wight', 'Isles of Scilly', 'Islington', 'Kensington and Chelsea',
        'Kings Lynn and West Norfolk', 'Kingston-upon-Hull', 'Kingston upon Thames',
        'Kirklees', 'Knowsley', 'Lambeth', 'Lancaster', 'Leeds', 'Leicester', 'Lewes',
        'Lewisham', 'Lichfield', 'Lincoln', 'Liverpool', 'London', 'Luton', 'Maidstone',
        'Maldon', 'Malvern Hills', 'Manchester', 'Mansfield', 'Medway', 'Melton', 'Mendip',
        'Merthyr Tydfil', 'Merton', 'Mid and East Antrim', 'Mid Devon', 'Mid Sussex',
        'Mid Ulster', 'Middlesbrough', 'Midlothian', 'Milton Keynes', 'Mole Valley',
        'Monmouthshire', 'Moray', 'Neath Port Talbot', 'New Forest', 'Newark and Sherwood',
        'Newcastle', 'Newcastle-under-Lyme', 'Newham', 'Newport', 'Newry Mourne and Down',
        'North Ayrshire', 'North Devon', 'North Down and Ards', 'North East Derbyshire',
        'North East Lincolnshire', 'North Hertfordshire', 'North Kesteven', 'North Lanarkshire',
        'North Lincolnshire', 'North Norfolk', 'North Northamptonshire', 'North Somerset',
        'North Tyneside', 'North Warwickshire', 'North West Leicestershire', 'North Yorkshire', 'Northumberland',
        'Norwich', 'Nottingham', 'Nuneaton and Bedworth', 'Oadby and Wigston', 'Oldham',
        'Orkney Islands', 'Oxford', 'Pembrokeshire', 'Pendle', 'Perth and Kinross',
        'Peterborough', 'Plymouth', 'Portsmouth', 'Powys', 'Preston', 'Reading', 'Redbridge',
        'Redcar and Cleveland', 'Reigate and Banstead', 'Renfrewshire', 'Rhondda-Cynon Taff',
        'Ribble Valley', 'Richmond', 'Richmondshire', 'Rochdale', 'Rochford', 'Rossendale',
        'Rother', 'Rotherham', 'Rugby', 'Runnymede', 'Rushcliffe', 'Rushmoor',
        'Rutland', 'Ryedale', 'Salford', 'Sandwell', 'Scottish Borders',
        'Sedgemoor', 'Sefton', 'Selby', 'Sevenoaks', 'Sheffield', 'Shetland Islands',
        'Shropshire', 'Slough', 'Solihull', 'Somerset West and Taunton', 'South Ayrshire',
        'South Cambridgeshire', 'South Derbyshire', 'South Gloucestershire', 'South Hams',
        'South Holland', 'South Kesteven', 'South Lanarkshire', 'South Norfolk',
        'South Oxfordshire', 'South Ribble', 'South Somerset', 'South Staffordshire',
        'South Tyneside', 'Southampton', 'Southend-on-Sea', 'Southwark', 'Spelthorne',
        'St Albans', 'St Helens', 'Stafford', 'Staffordshire Moorlands', 'Stevenage',
        'Stirling', 'Stockport', 'Stockton-on-Tees', 'Stoke-on-Trent', 'Stratford on Avon',
        'Stroud', 'Sunderland', 'Surrey Heath', 'Sutton', 'Swale', 'Swansea', 'Swindon',
        'Tameside', 'Tamworth', 'Tandridge', 'Teignbridge', 'Telford and Wrekin', 'Tendring',
        'Test Valley', 'Tewkesbury', 'Thanet', 'Three Rivers', 'Thurrock', 'Tonbridge and Malling',
        'Torbay', 'Torfaen', 'Torridge', 'Tower Hamlets', 'Trafford', 'Tunbridge Wells',
        'Uttlesford', 'Vale of Glamorgan', 'Vale of White Horse', 'Wakefield', 'Walsall',
        'Waltham Forest', 'Wandsworth', 'Warrington', 'Warwick', 'Watford', 'Waverley',
        'Wealden', 'Wellingborough', 'Welwyn Hatfield', 'West Berkshire', 'West Devon',
        'West Dunbartonshire', 'West Lancashire', 'West Lindsey', 'West Lothian',
        'West Northamptonshire', 'West Oxfordshire', 'West Suffolk', 'Westminster',
        'Westmorland and Furness', 'Wigan', 'Wiltshire', 'Winchester', 'Windsor and Maidenhead',
        'Wirral', 'Woking', 'Wokingham', 'Wolverhampton', 'Worcester', 'Worthing', 'Wrexham',
        'Wychavon', 'Wycombe', 'Wyre', 'Wyre Forest', 'York'
    ];
}

/*--------------------------------------------------------------
  TEMPLATE DATA
--------------------------------------------------------------*/
function pmpc_get_templates() {
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
  HELPER: Check if current checkout is per-council level
  FIXED: Added aggressive early exits and conditional logging
--------------------------------------------------------------*/
function pmpc_is_per_council_checkout() {
    static $cached_result = null;
    static $cached_level = null;
    
    // Use caching to prevent multiple calls per request
    if ($cached_result !== null) {
        return $cached_result;
    }

    $configured_level = intval(get_option('pmpc_per_council_level_id', 0));
    
    // EARLY EXIT 1: No configured level means this feature is disabled
    if ($configured_level === 0) {
        $cached_result = false;
        return false;
    }

    // EARLY EXIT 2: Not a checkout page (most requests will hit this)
    if (!function_exists('pmpro_is_checkout') || !pmpro_is_checkout()) {
        $cached_result = false;
        return false;
    }

    // EARLY EXIT 3: Must have a level parameter or be on checkout
    if (empty($_REQUEST['level']) && empty($_REQUEST['pmpro_level']) && empty($_GET['pmpro_level'])) {
        // Check global as last resort only if we're definitely on checkout
        if (!isset($GLOBALS['pmpro_level']->id)) {
            $cached_result = false;
            return false;
        }
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

    // EARLY EXIT 4: No level detected
    if ($current_level === 0) {
        $cached_result = false;
        return false;
    }

    $result = ($current_level === $configured_level);
    
    // Only log when debug mode is explicitly enabled, or when result is true
    // This prevents log spam from background requests
    if (defined('PMPC_DEBUG') && PMPC_DEBUG) {
        error_log('PMPC: Detected level ID: ' . $current_level . ', Configured: ' . $configured_level . ', Match: ' . ($result ? 'YES' : 'NO'));
    }
    
    $cached_result = $result;
    return $result;
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
    
    if (!$is_checkout_page || !pmpc_is_per_council_checkout()) {
        return;
    }

    wp_enqueue_script(
        'pmpc-checkout-js',
        plugin_dir_url(__FILE__) . 'assets/pmpc-checkout.js',
        ['jquery'],
        '2.2.0',
        true
    );

    // Get user's current template from business settings (if logged in)
    $user_current_template = 'basic';
    if (is_user_logged_in()) {
        $business_info = get_user_meta(get_current_user_id(), '_pi_business_info', true) ?: [];
        $user_current_template = $business_info['default_template'] ?? 'basic';
    }

    wp_localize_script('pmpc-checkout-js', 'pmpcVars', [
        'unitPrice' => PMPC_UNIT_PRICE,
        'minSelect' => PMPC_MIN_SELECTION,
        'strings' => [
            'chooseAtLeast' => sprintf('Please choose at least %d councils', PMPC_MIN_SELECTION),
            'priceText'     => 'Calculated price: £',
            'cancelText'    => 'Cancel any time'
        ],
    ]);

    wp_localize_script('pmpc-checkout-js', 'pmpcConfig', [
        'unitPrice' => PMPC_UNIT_PRICE,
        'minSelection' => PMPC_MIN_SELECTION,
        'totalSteps' => PMPC_TOTAL_STEPS,
        'councils' => pmpc_get_all_councils(),
        'templates' => pmpc_get_templates(), // Fallback templates
        'checkoutUrl' => pmpro_url('checkout'),
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'restUrl' => rest_url('pi/v1'), // REST API base URL for templates
        'restNonce' => wp_create_nonce('wp_rest'),
        'nonce' => wp_create_nonce('pmpc_checkout_nonce'),
        'sessionNonce' => wp_create_nonce('pmpc_session_nonce'),
        'isLoggedIn' => is_user_logged_in(),
        'userCurrentTemplate' => $user_current_template, // User's saved template
        'strings' => [
            'selectMinCouncils' => sprintf('Please select at least %d councils to continue.', PMPC_MIN_SELECTION),
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
        'pmpc-checkout-css',
        plugin_dir_url(__FILE__) . 'assets/pmpc-style.css',
        [],
        '2.2.0'
    );

    // Inline PayPal/Stripe sync script (critical)
    $inline_js = <<<'JS'
(function($){
  function pmpc_sync_everything() {
    try {
      var priceVal = $('#pmpc_calculated_price').val();
      if (!priceVal) {
        var label = $('#pmpc_price_label').text() || $('#pmpc_price_display').text();
        if (label) {
          var m = label.match(/([0-9]+(?:\.[0-9]{1,2})?)/);
          if (m) priceVal = m[1];
        }
      }
      priceVal = (priceVal || '0').toString();
      var parsed = parseFloat(priceVal);
      if (!isNaN(parsed)) {
        var formatted = parsed.toFixed(2);
        $('#pmpc_calculated_price').val(formatted);
        try { sessionStorage.setItem('pmpc_calculated_price', formatted); } catch(e){}
      }
      var selected = [];
      var sel = $('#pmpc_councils').val();
      if (sel) jQuery.each(sel, function(i,v){ if (v) selected.push(v); });
      try { sessionStorage.setItem('pmpc_selected_councils', JSON.stringify(selected); } catch(e){}
    } catch(e){}
  }

  $(document).on('submit', 'form#pmpro_form, form[name="pmpro_form"]', function(){
    pmpc_sync_everything();
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
    pmpc_sync_everything();
    return true;
  });

  $(function(){
    try {
      var storedPrice = sessionStorage.getItem('pmpc_calculated_price');
      if (storedPrice) $('#pmpc_calculated_price').val(storedPrice);

      var storedSel = sessionStorage.getItem('pmpc_selected_councils');
      if (storedSel) {
        var arr = JSON.parse(storedSel);
        if (Array.isArray(arr) && arr.length) {
          $('#pmpc_councils').val(arr).trigger('change');
        }
      }
    } catch(e){}
  });
})(jQuery);
JS;

    wp_add_inline_script('pmpc-checkout-js', $inline_js);
});

/*--------------------------------------------------------------
  AJAX: VALIDATE USERNAME/EMAIL AVAILABILITY
--------------------------------------------------------------*/
add_action('wp_ajax_nopriv_pmpc_check_user', 'pmpc_ajax_check_user');
add_action('wp_ajax_pmpc_check_user', 'pmpc_ajax_check_user');

function pmpc_ajax_check_user() {
    check_ajax_referer('pmpc_checkout_nonce', 'nonce');
    
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
    $configured_level = intval(get_option('pmpc_per_council_level_id', 0));
    $chosen_level = isset($_REQUEST['level']) ? intval($_REQUEST['level']) : 0;
    
    if ($chosen_level === 0 && isset($_REQUEST['pmpro_level'])) {
        $chosen_level = intval($_REQUEST['pmpro_level']);
    }

    if ($configured_level === 0 || $chosen_level !== $configured_level) {
        return $ok;
    }

    if (empty($_POST['pmpc_councils'])) {
        pmpro_setMessage('Please select at least ' . PMPC_MIN_SELECTION . ' councils.', 'pmpro_error');
        return false;
    }

    $selected = array_map('sanitize_text_field', (array) $_POST['pmpc_councils']);
    $count = count($selected);

    if ($count < PMPC_MIN_SELECTION) {
        pmpro_setMessage('Please select at least ' . PMPC_MIN_SELECTION . ' councils.', 'pmpro_error');
        return false;
    }

    $expected_price = $count * PMPC_UNIT_PRICE;
    $posted_price = isset($_POST['pmpc_calculated_price']) ? floatval($_POST['pmpc_calculated_price']) : 0;

    if (abs($posted_price - $expected_price) > 0.01) {
        pmpro_setMessage('Price validation failed. Please reselect your councils and try again.', 'pmpro_error');
        return false;
    }

    $_REQUEST['pmpc_councils'] = $selected;
    $_REQUEST['pmpc_calculated_price'] = number_format($expected_price, 2, '.', '');

    return $ok;
});

/*--------------------------------------------------------------
  CORE: Override PMPro checkout level object
--------------------------------------------------------------*/
add_filter('pmpro_checkout_level', function ($level) {
    $configured_level = intval(get_option('pmpc_per_council_level_id', 0));
    
    if (empty($configured_level) || intval($level->id) !== $configured_level) {
        return $level;
    }

    $dynamic_price = 0;
    
    if (!empty($_REQUEST['pmpc_calculated_price'])) {
        $dynamic_price = floatval(sanitize_text_field(wp_unslash($_REQUEST['pmpc_calculated_price'])));
    }
    
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
function pmpc_get_price_from_request() {
    if (is_user_logged_in()) {
        $stored = get_user_meta(get_current_user_id(), PMPC_META_PRICE, true);
        if ($stored && floatval($stored) > 0) {
            return floatval($stored);
        }
    }

    if (!empty($_REQUEST['pmpc_calculated_price'])) {
        return floatval(sanitize_text_field(wp_unslash($_REQUEST['pmpc_calculated_price'])));
    }

    return 0;
}

/*--------------------------------------------------------------
  ORDER PRICE OVERRIDE (safety net)
--------------------------------------------------------------*/
add_action('pmpro_checkout_before_processing', function () {
    $price = pmpc_get_price_from_request();
    if ($price <= 0) return;

    $_REQUEST['initial_payment'] = $price;
    $_REQUEST['amount'] = $price;
    $_REQUEST['payment_amount'] = $price;
});

add_action('pmpro_checkout_before_payment', function ($morder) {
    $price = pmpc_get_price_from_request();
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

    $configured_level = intval(get_option('pmpc_per_council_level_id', 0));
    if (!empty($configured_level) && !empty($morder->membership_level) && is_object($morder->membership_level) && intval($morder->membership_level->id) === $configured_level) {
        $morder->membership_level->initial_payment = $price;
        $morder->membership_level->billing_amount = $price;
    }
});

/*--------------------------------------------------------------
  SAVE USER DATA AFTER CHECKOUT
--------------------------------------------------------------*/
/*--------------------------------------------------------------
  SAVE USER DATA AFTER CHECKOUT
--------------------------------------------------------------*/
add_action('pmpro_after_checkout', function ($user_id, $morder) {
    // CHECK: If Settings have been saved, DO NOT override with checkout data
    $settings_check = pmpc_get_effective_business_info($user_id);
    
    if ($settings_check['source'] === 'settings') {
        // Settings exist - only save councils and price, NOT business info or template
        error_log("[PMPC Checkout] Settings detected for user #$user_id - skipping business info/template override");
        
        // Still save councils (that's subscription-specific)
        if (!empty($_REQUEST['pmpc_councils'])) {
            $councils = array_map('sanitize_text_field', (array) $_REQUEST['pmpc_councils']);
            update_user_meta($user_id, PMPC_META_KEY, $councils);
        }

        // Still save price (that's subscription-specific)
        if (!empty($_REQUEST['pmpc_calculated_price'])) {
            $price = floatval(sanitize_text_field(wp_unslash($_REQUEST['pmpc_calculated_price'])));
            update_user_meta($user_id, PMPC_META_PRICE, number_format($price, 2, '.', ''));
        }
        
        // Save checkout template to PMPC meta for reference ONLY
        // DO NOT update _pi_business_info - Settings takes precedence
        if (!empty($_REQUEST['pmpc_default_template'])) {
            $template = sanitize_text_field($_REQUEST['pmpc_default_template']);
            update_user_meta($user_id, PMPC_META_TEMPLATE, $template);
            // NOTE: We intentionally do NOT update _pi_business_info here
        }
        
        // Save checkout business info to PMPC meta for reference only, NOT to _pi_business_info
        $checkout_business_info = [];
        $business_fields = ['pmpc_company_name', 'pmpc_business_email', 'pmpc_business_phone', 'pmpc_company_address', 'pmpc_website', 'pmpc_vat_number'];
        
        foreach ($business_fields as $field) {
            if (!empty($_REQUEST[$field])) {
                $checkout_business_info[$field] = sanitize_text_field($_REQUEST[$field]);
            }
        }
        
        if (!empty($checkout_business_info)) {
            // Save to PMPC meta only - do NOT touch _pi_business_info
            update_user_meta($user_id, PMPC_META_BUSINESS, $checkout_business_info);
        }
        
    } else {
        // NO Settings saved yet - use checkout data as primary source
        error_log("[PMPC Checkout] No Settings for user #$user_id - saving checkout data as primary");
        
        // Councils
        if (!empty($_REQUEST['pmpc_councils'])) {
            $councils = array_map('sanitize_text_field', (array) $_REQUEST['pmpc_councils']);
            update_user_meta($user_id, PMPC_META_KEY, $councils);
        }

        // Price
        if (!empty($_REQUEST['pmpc_calculated_price'])) {
            $price = floatval(sanitize_text_field(wp_unslash($_REQUEST['pmpc_calculated_price'])));
            update_user_meta($user_id, PMPC_META_PRICE, number_format($price, 2, '.', ''));
        }

        // Template - Save to BOTH locations (since no Settings exist yet)
        if (!empty($_REQUEST['pmpc_default_template'])) {
            $template = sanitize_text_field($_REQUEST['pmpc_default_template']);
            
            // Save to PMPC meta
            update_user_meta($user_id, PMPC_META_TEMPLATE, $template);
            
            // Also save to _pi_business_info (since no Settings exist yet)
            $business_info = get_user_meta($user_id, '_pi_business_info', true);
            if (!is_array($business_info)) {
                $business_info = [];
            }
            $business_info['default_template'] = $template;
            // Mark that this came from checkout, not settings
            $business_info['source'] = 'checkout';
            update_user_meta($user_id, '_pi_business_info', $business_info);
        }

        // Business info from checkout form
        $checkout_business_info = [];
        $business_fields = ['pmpc_company_name', 'pmpc_business_email', 'pmpc_business_phone', 'pmpc_company_address', 'pmpc_website', 'pmpc_vat_number'];
        
        foreach ($business_fields as $field) {
            if (!empty($_REQUEST[$field])) {
                $checkout_business_info[$field] = sanitize_text_field($_REQUEST[$field]);
            }
        }
        
        if (!empty($checkout_business_info)) {
            update_user_meta($user_id, PMPC_META_BUSINESS, $checkout_business_info);
            
            // Also merge into _pi_business_info (since no Settings exist yet)
            $business_info = get_user_meta($user_id, '_pi_business_info', true);
            if (!is_array($business_info)) {
                $business_info = [];
            }
            
            $field_map = [
                'pmpc_company_name' => 'company_name',
                'pmpc_business_email' => 'email',
                'pmpc_business_phone' => 'phone',
                'pmpc_company_address' => 'company_address',
                'pmpc_website' => 'website',
            ];
            
            foreach ($field_map as $checkout_key => $settings_key) {
                if (!empty($checkout_business_info[$checkout_key])) {
                    $business_info[$settings_key] = $checkout_business_info[$checkout_key];
                }
            }
            
            // Mark source as checkout
            $business_info['source'] = 'checkout';
            update_user_meta($user_id, '_pi_business_info', $business_info);
        }
    }

    // Order note (always save this)
    if (!empty($morder)) {
        $councils = isset($_REQUEST['pmpc_councils']) ? $_REQUEST['pmpc_councils'] : [];
        $summary = wp_json_encode(['councils' => $councils], JSON_UNESCAPED_UNICODE);
        $morder->notes = "PerCouncilSelected: $summary";
        if (method_exists($morder, 'save')) {
            $morder->save();
        }
    }
}, 10, 2);
/*--------------------------------------------------------------
  STRIPE SPECIFIC HOOKS
--------------------------------------------------------------*/
add_filter('pmpro_stripe_create_subscription_array', function ($params, $order) {
    $price = pmpc_get_price_from_request();
    if ($price > 0 && isset($params['items'][0]['price_data']['unit_amount'])) {
        $params['items'][0]['price_data']['unit_amount'] = intval($price * 100);
    }
    return $params;
}, 20, 2);

add_filter('pmpro_stripe_payment_intent_amount', function ($amount, $order) {
    $price = pmpc_get_price_from_request();
    if ($price > 0) {
        return intval($price * 100);
    }
    return $amount;
}, 20, 2);
add_filter('pmpro_stripe_create_payment_intent_array', function ($intent_array, $order) {
    $price = pmpc_get_price_from_request();
    if ($price > 0) {
        $intent_array['amount'] = intval($price * 100);
    }
    return $intent_array;
}, 20, 2);
/*--------------------------------------------------------------
  MULTI-STEP SERVER-SIDE HANDLER – now AJAX-only for steps 1–3
--------------------------------------------------------------*/
add_action('pmpro_checkout_preheader', 'pmpc_multi_step_handler');
function pmpc_multi_step_handler() {
    if (!pmpc_is_per_council_checkout()) {
        return;
    }

    if (!session_id()) {
        session_start();
    }

    // CRITICAL: Check if Settings exist - if so, don't let session data override them
    $user_id = get_current_user_id();
    if ($user_id > 0 && pmpc_should_use_settings($user_id)) {
        // Clear any session data that might conflict with Settings
        if (isset($_SESSION[PMPC_SESSION_KEY]['business'])) {
            error_log("[PMPC Session] Clearing business data from session - Settings exist for user #$user_id");
            unset($_SESSION[PMPC_SESSION_KEY]['business']);
        }
        if (isset($_SESSION[PMPC_SESSION_KEY]['template'])) {
            error_log("[PMPC Session] Clearing template from session - Settings exist for user #$user_id");
            unset($_SESSION[PMPC_SESSION_KEY]['template']);
        }
    }
    // 1. Restore session data on every load (for GET/reload/back button)
    $data = isset($_SESSION[PMPC_SESSION_KEY]) ? (array) $_SESSION[PMPC_SESSION_KEY] : [];
    if (!empty($data)) {
        $_REQUEST = array_merge($_REQUEST, $data);
    }

    // 2. Skip real checkout processing
    if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
        (isset($_POST['submit-checkout']) || isset($_POST['pmpro_submit']) || isset($_POST['javascriptok']))) {
        // Final submit: merge session → request and clear
        if (!empty($data)) {
            if (isset($data['councils']))     $_REQUEST['pmpc_councils']         = $data['councils'];
            if (isset($data['price']))        $_REQUEST['pmpc_calculated_price'] = $data['price'];
            if (isset($data['template']))     $_REQUEST['pmpc_default_template'] = $data['template'];
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
        unset($_SESSION[PMPC_SESSION_KEY]);
        return; // let PMPro handle real checkout
    }

    // 3. Handle AJAX step saves (steps 1–3)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pmpc_save_step') {
        check_ajax_referer('pmpc_checkout_nonce', 'nonce');

        $step = isset($_POST['step']) ? intval($_POST['step']) : 0;
        if (!$step || $step >= 4) {
            wp_send_json_error(['message' => 'Invalid step']);
        }

        switch ($step) {
            case 1:
                $selected = array_map('sanitize_text_field', (array) ($_POST['councils'] ?? []));
                $count = count($selected);
                if ($count < PMPC_MIN_SELECTION) {
                    wp_send_json_error(['message' => 'Please select at least ' . PMPC_MIN_SELECTION . ' councils.']);
                }
                $data['councils'] = $selected;
                $data['price']    = $count * PMPC_UNIT_PRICE;
                break;

            case 2:
                $data['template'] = sanitize_text_field($_POST['template'] ?? 'professional');
                $business = [];
                $fields = ['pmpc_company_name', 'pmpc_business_email', 'pmpc_business_phone',
                           'pmpc_company_address', 'pmpc_website', 'pmpc_vat_number'];
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

        $_SESSION[PMPC_SESSION_KEY] = $data;
        wp_send_json_success(['step' => $step + 1]);
    }
}

// Keep your existing AJAX update_session if you still use it, but it's optional now
add_action('wp_ajax_pmpc_update_session', 'pmpc_update_session');
add_action('wp_ajax_nopriv_pmpc_update_session', 'pmpc_update_session');

function pmpc_update_session() {
    check_ajax_referer('pmpc_nonce', 'nonce');

    if (!session_id()) session_start();

    $_SESSION[PMPC_SESSION_KEY]['councils'] = json_decode(stripslashes($_POST['councils']), true);
    $_SESSION[PMPC_SESSION_KEY]['calculated_price'] = floatval($_POST['price']);

    wp_send_json_success();
}

/*--------------------------------------------------------------
  FALLBACK RENDER UI (if JS wizard fails to load)
--------------------------------------------------------------*/
// Temporarily disabled to avoid interference
//add_action('pmpro_checkout_boxes', 'pmpc_fallback_render_ui');
function pmpc_fallback_render_ui() {
    if (!pmpc_is_per_council_checkout()) {
        return;
    }

    $councils = pmpc_get_all_councils();
    $selected = !empty($_REQUEST['pmpc_councils']) ? (array) $_REQUEST['pmpc_councils'] : [];
    ?>
    <div class="pmpc-fallback-selector" style="border:1px solid #ddd; padding:20px; margin:20px 0; background:#f9f9f9;">
        <h3 style="margin-top:0;">Select Councils (Fallback)</h3>
        <p style="color:#e74c3c;">JavaScript wizard did not load — using fallback selector.</p>
        <select id="pmpc_councils" name="pmpc_councils[]" multiple style="width:100%; height:200px;">
            <?php foreach ($councils as $c): ?>
                <option value="<?php echo esc_attr($c); ?>" <?php selected(in_array($c, $selected)); ?>>
                    <?php echo esc_html($c); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="hidden" id="pmpc_calculated_price" name="pmpc_calculated_price" value="0">
        <p><em>Please refresh the page or contact support if this message persists.</em></p>
    </div>
    <?php
}

/*--------------------------------------------------------------
  ADMIN SETTINGS PAGE
--------------------------------------------------------------*/
add_action('admin_menu', function () {
    add_submenu_page(
        'pmpro-dashboard',
        'Per Council Settings',
        'Per Council',
        'manage_options',
        'pmpc-settings',
        'pmpc_render_admin_page'
    );
});

function pmpc_render_admin_page() {
    if (isset($_POST['pmpc_save']) && check_admin_referer('pmpc_admin_settings')) {
        update_option('pmpc_per_council_level_id', intval($_POST['pmpc_level_id']));
        echo '<div class="notice notice-success"><p>Settings saved successfully.</p></div>';
    }

    $levels = pmpro_getAllLevels(true, true);
    $current_level = intval(get_option('pmpc_per_council_level_id', 0));
    ?>
    <div class="wrap">
        <h1>Per Council Settings</h1>
        <p>Configure the per-council checkout system for Paid Memberships Pro.</p>
        
        <form method="post">
            <?php wp_nonce_field('pmpc_admin_settings'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="pmpc_level_id">Per Council Level</label>
                    </th>
                    <td>
                        <select name="pmpc_level_id" id="pmpc_level_id">
                            <option value="0">-- Select Level --</option>
                            <?php foreach ($levels as $level): ?>
                                <option value="<?php echo intval($level->id); ?>" <?php selected($current_level, $level->id); ?>>
                                    <?php echo esc_html($level->name) . ' (ID: ' . $level->id . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Select the membership level that uses per-council pricing (£<?php echo PMPC_UNIT_PRICE; ?>/council).</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Configuration</th>
                    <td>
                        <p><strong>Unit Price:</strong> £<?php echo PMPC_UNIT_PRICE; ?> per council</p>
                        <p><strong>Minimum Selection:</strong> <?php echo PMPC_MIN_SELECTION; ?> councils</p>
                        <p><strong>Total Councils Available:</strong> <?php echo count(pmpc_get_all_councils()); ?></p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="pmpc_save" class="button button-primary" value="Save Settings">
            </p>
        </form>
    </div>
    <?php
}

/*--------------------------------------------------------------
  SHORTCODE: Display user's selected councils
--------------------------------------------------------------*/
add_shortcode('pmpc_user_councils', function ($atts) {
    if (!is_user_logged_in()) {
        return '<p>Please log in to view your selected councils.</p>';
    }

    $councils = get_user_meta(get_current_user_id(), PMPC_META_KEY, true);
    
    if (empty($councils) || !is_array($councils)) {
        return '<p>No councils selected yet.</p>';
    }

    $output = '<ul class="pmpc-user-councils">';
    foreach ($councils as $council) {
        $output .= '<li>' . esc_html($council) . '</li>';
    }
    $output .= '</ul>';

    return $output;
});

/*--------------------------------------------------------------
  REST API: Get councils list (future-proof)
--------------------------------------------------------------*/
add_action('rest_api_init', function () {
    register_rest_route('pmpc/v1', '/councils', [
        'methods' => 'GET',
        'callback' => function () {
            return new WP_REST_Response(pmpc_get_all_councils(), 200);
        },
        'permission_callback' => '__return_true'
    ]);
});


/*--------------------------------------------------------------
  FORCE INCLUDE CUSTOM CHECKOUT TEMPLATE
--------------------------------------------------------------*/

add_action('pmpro_checkout_preheader', function() {
    global $pmpro_pages;

    // EARLY EXIT 1: Not a page request
    if (!is_page() || empty($pmpro_pages['checkout'])) {
        return;
    }

    // EARLY EXIT 2: Not the checkout page
    if (!is_page($pmpro_pages['checkout'])) {
        return;
    }

    // EARLY EXIT 3: Must have a level parameter (critical fix!)
    $has_level_param = !empty($_REQUEST['level']) || !empty($_REQUEST['pmpro_level']) || !empty($_GET['pmpro_level']) || !empty($GLOBALS['pmpro_level']->id);
    if (!$has_level_param) {
        return;
    }

    // Now check if it's our specific level - this will use the cached result
    if (!pmpc_is_per_council_checkout()) {
        return;
    }

    // VERY IMPORTANT: Only apply on real GET navigation or form POST
    // Do NOT apply on bfcache / back-button / history navigation
    $is_real_load = (
        $_SERVER['REQUEST_METHOD'] === 'GET' ||
        ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['submit-checkout']))
    );

    if (!$is_real_load) {
        return;
    }

    // Also skip if this looks like a Stripe/PMPro final confirmation redirect
    if (isset($_GET['confirm']) || isset($_GET['review'])) {
        return;
    }

    $custom_file = get_stylesheet_directory() . '/paid-memberships-pro/pages/checkout.php';

    if (!file_exists($custom_file)) {
        return;
    }

    // Clear conflicting filters
    remove_all_filters('pmpro_get_template');
    remove_all_filters('pmpro_pages_custom_template_path');

    // ENQUEUE ASSETS HERE (before get_header ensures they load even with early exit)
    // This is critical for logged-out users where wp_enqueue_scripts hasn't fired yet
    wp_enqueue_script(
        'pmpc-checkout-js',
        plugin_dir_url(__FILE__) . 'assets/pmpc-checkout.js',
        ['jquery'],
        '2.2.0',
        true
    );

    // Get user's current template from business settings (if logged in)
    $user_current_template = 'basic';
    if (is_user_logged_in()) {
        $business_info = get_user_meta(get_current_user_id(), '_pi_business_info', true) ?: [];
        $user_current_template = $business_info['default_template'] ?? 'basic';
    }

    wp_localize_script('pmpc-checkout-js', 'pmpcVars', [
        'unitPrice' => PMPC_UNIT_PRICE,
        'minSelect' => PMPC_MIN_SELECTION,
        'strings' => [
            'chooseAtLeast' => sprintf('Please choose at least %d councils', PMPC_MIN_SELECTION),
            'priceText'     => 'Calculated price: £',
            'cancelText'    => 'Cancel any time'
        ],
    ]);

    wp_localize_script('pmpc-checkout-js', 'pmpcConfig', [
        'unitPrice' => PMPC_UNIT_PRICE,
        'minSelection' => PMPC_MIN_SELECTION,
        'totalSteps' => PMPC_TOTAL_STEPS,
        'councils' => pmpc_get_all_councils(),
        'templates' => pmpc_get_templates(),
        'checkoutUrl' => pmpro_url('checkout'),
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'restUrl' => rest_url('pi/v1'),
        'restNonce' => wp_create_nonce('wp_rest'),
        'nonce' => wp_create_nonce('pmpc_checkout_nonce'),
        'sessionNonce' => wp_create_nonce('pmpc_session_nonce'),
        'isLoggedIn' => is_user_logged_in(),
        'userCurrentTemplate' => $user_current_template,
        'strings' => [
            'selectMinCouncils' => sprintf('Please select at least %d councils to continue.', PMPC_MIN_SELECTION),
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
        'pmpc-checkout-css',
        plugin_dir_url(__FILE__) . 'assets/pmpc-style.css',
        [],
        '2.2.0'
    );

    // Inline PayPal/Stripe sync script (critical)
    $inline_js = <<<'JS'
(function($){
  function pmpc_sync_everything() {
    try {
      var priceVal = $('#pmpc_calculated_price').val();
      if (!priceVal) {
        var label = $('#pmpc_price_label').text() || $('#pmpc_price_display').text();
        if (label) {
          var m = label.match(/([0-9]+(?:\.[0-9]{1,2})?)/);
          if (m) priceVal = m[1];
        }
      }
      priceVal = (priceVal || '0').toString();
      var parsed = parseFloat(priceVal);
      if (!isNaN(parsed)) {
        var formatted = parsed.toFixed(2);
        $('#pmpc_calculated_price').val(formatted);
        try { sessionStorage.setItem('pmpc_calculated_price', formatted); } catch(e){}
      }
      var selected = [];
      var sel = $('#pmpc_councils').val();
      if (sel) jQuery.each(sel, function(i,v){ if (v) selected.push(v); });
      try { sessionStorage.setItem('pmpc_selected_councils', JSON.stringify(selected)); } catch(e){}
    } catch(e){}
  }

  $(document).on('submit', 'form#pmpro_form, form[name="pmpro_form"]', function(){
    pmpc_sync_everything();
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
    pmpc_sync_everything();
    return true;
  });

  $(function(){
    try {
      var storedPrice = sessionStorage.getItem('pmpc_calculated_price');
      if (storedPrice) $('#pmpc_calculated_price').val(storedPrice);

      var storedSel = sessionStorage.getItem('pmpc_selected_councils');
      if (storedSel) {
        var arr = JSON.parse(storedSel);
        if (Array.isArray(arr) && arr.length) {
          $('#pmpc_councils').val(arr).trigger('change');
        }
      }
    } catch(e){}
  });
})(jQuery);
JS;

    wp_add_inline_script('pmpc-checkout-js', $inline_js);
    
    // CRITICAL FIX: Ensure jQuery is enqueued
    wp_enqueue_script('jquery');

    // Buffer + full theme structure
    ob_start();
    get_header();
    
    // CRITICAL FIX: For logged-out users, always manually output CSS directly
    // This bypasses any WordPress hooks that might be preventing proper enqueue
    if (!is_user_logged_in()) {
        $css_url = plugin_dir_url(__FILE__) . 'assets/pmpc-style.css';
        echo "\n<!-- PMPC PER-COUNCIL: Direct CSS injection for logged-out users -->\n";
        echo '<link rel="stylesheet" id="pmpc-style-css-direct" href="' . esc_url($css_url) . '?v=2.0.0" type="text/css" media="all" />' . "\n";
    }
    
    include $custom_file;
    get_footer();

    $output = ob_get_clean();
    echo $output;

    // Exit — prevent PMPro from rendering anything else
    exit;

}, 5);






add_action('wp_footer', function() {
    if (pmpc_is_per_council_checkout()) {
        ?>
        <script>
            jQuery(function($) {
                if ($('.pmpc-wizard-checkout').length > 0) {
                    $('.pmpc-fallback-selector').remove();
                    console.log('PMPC: Wizard loaded - fallback removed');
                } else {
                    console.log('PMPC: Wizard NOT loaded');
                }
            });
        </script>
        <?php
    }
});
