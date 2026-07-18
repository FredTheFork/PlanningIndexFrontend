<?php
/**
 * Plugin Name: PMPro Enterprise Access
 * Description: Provides an Enterprise-level membership option granting access to all UK councils. Multi-step wizard checkout with template selection, account creation, and business info.
 * Version: 2.0
 * Author: Planning Index
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* --------------------------------------------
   NUCLEAR FIX: Force CSS for logged-out users
-------------------------------------------- */
function pmpe_nuclear_css_injection() {
    if (is_user_logged_in()) return;
    $configured = intval(get_option('pmpe_enterprise_checkout_level_id', 0));
    if (!$configured) return;
    $current = intval($_REQUEST['level'] ?? $_REQUEST['pmpro_level'] ?? 0);
    if ($current !== $configured) return;
    
    $css_url = plugins_url('assets/pmpe-checkout.css', __FILE__);
    echo '<link rel="stylesheet" id="pmpe-css-nuclear" href="' . esc_url($css_url) . '?v=' . time() . '" type="text/css" media="all" />' . "\n";
    
    // Also inline the CSS
    $css_file = plugin_dir_path(__FILE__) . 'assets/pmpe-checkout.css';
    if (file_exists($css_file)) {
        echo '<style id="pmpe-css-inline">' . file_get_contents($css_file) . '</style>' . "\n";
    }
}
add_action('wp_head', 'pmpe_nuclear_css_injection', 1);
add_action('wp_head', 'pmpe_nuclear_css_injection', 999);

/* --------------------------------------------
   CONFIGURATION
-------------------------------------------- */
define( 'PMPE_ENTERPRISE_LEVEL_OPTION', 'pmpe_enterprise_checkout_level_id' );
define( 'PMPE_META_REGION_KEY', 'pmrb_region_bundle' );
define( 'PMPE_META_ALLOWED_KEY', 'pmrb_allowed_councils' );
define( 'PMPE_META_SELECTED_KEY', 'pmpc_selected_councils' );
define( 'PMPE_META_TEMPLATE', 'pmpe_default_template' );
define( 'PMPE_META_BUSINESS', 'pmpe_business_info' );
define( 'PMPE_TOTAL_STEPS', 4 );
define( 'PMPE_SESSION_KEY', 'pmpe_checkout_session' );

/* --------------------------------------------
   GET ALL COUNCILS (Enterprise gets all)
-------------------------------------------- */
function pmpe_get_all_councils() {
    // Use the per-council list if available
    if (function_exists('pmpc_get_all_councils')) {
        return pmpc_get_all_councils();
    }
    
    // Fallback list
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
/**
 * Grant full Enterprise council access to a user
 * Replicates the council meta saving logic
 */
function pmpe_grant_enterprise_access($user_id) {
    if (!$user_id) return;

    $councils = pmpe_get_all_councils();

    update_user_meta($user_id, PMPE_META_REGION_KEY, 'Enterprise Access');
    update_user_meta($user_id, PMPE_META_ALLOWED_KEY, $councils);
    update_user_meta($user_id, PMPE_META_SELECTED_KEY, $councils);

    // Optional: also sync template if you want a default for new users
    $default_template = 'professional'; // or pull from owner if you want
    update_user_meta($user_id, PMPE_META_TEMPLATE, $default_template);

    error_log("[PMPE] Granted full Enterprise council access to user #{$user_id} (" . count($councils) . " councils)");
}
/* --------------------------------------------
   HELPER: Check if current checkout is enterprise level
-------------------------------------------- */
function pmpe_is_enterprise_checkout() {
    $configured = intval(get_option(PMPE_ENTERPRISE_LEVEL_OPTION, 0));
    if (!$configured) return false;

    $current = 0;
    if (isset($_REQUEST['level'])) $current = intval($_REQUEST['level']);
    elseif (isset($_REQUEST['pmpro_level'])) $current = intval($_REQUEST['pmpro_level']);
    elseif (isset($GLOBALS['pmpro_level']->id)) $current = intval($GLOBALS['pmpro_level']->id);

    return $current === $configured;
}

/* --------------------------------------------
   ADMIN SETTINGS
-------------------------------------------- */
add_action( 'admin_menu', function() {
    add_submenu_page(
        'pmpro-dashboard',
        'Enterprise Checkout',
        'Enterprise Checkout',
        'manage_options',
        'pmpe-enterprise-checkout',
        'pmpe_enterprise_checkout_admin_page'
    );
});

function pmpe_enterprise_checkout_admin_page() {
    if ( isset($_POST['pmpe_save']) && check_admin_referer('pmpe-save-checkout') ) {
        update_option( PMPE_ENTERPRISE_LEVEL_OPTION, intval($_POST['pmpe_level_id']) );
        echo '<div class="updated"><p>Settings saved.</p></div>';
    }

    $levels = function_exists('pmpro_getAllLevels') ? pmpro_getAllLevels(true, true) : [];
    $current = intval( get_option(PMPE_ENTERPRISE_LEVEL_OPTION, 0) );
    ?>
    <div class="wrap">
        <h1>Enterprise Checkout Settings</h1>
        <p>Configure the enterprise (all councils) checkout wizard.</p>
        <form method="post">
            <?php wp_nonce_field('pmpe-save-checkout'); ?>
            <table class="form-table">
                <tr>
                    <th>Select Enterprise Level</th>
                    <td>
                        <select name="pmpe_level_id">
                            <option value="0">-- Select Level --</option>
                            <?php foreach ( $levels as $l ) : ?>
                                <option value="<?php echo intval($l->id); ?>" <?php selected($current, $l->id); ?>>
                                    <?php echo esc_html($l->name . " (ID {$l->id})"); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">This will be the membership level that grants full UK-wide authority access with the multi-step wizard checkout.</p>
                    </td>
                </tr>
                <tr>
                    <th>Configuration</th>
                    <td>
                        <p><strong>Total Steps:</strong> <?php echo PMPE_TOTAL_STEPS; ?> (Benefits → Template → Account → Payment)</p>
                        <p><strong>Total Councils:</strong> <?php echo count(pmpe_get_all_councils()); ?> (All UK)</p>
                    </td>
                </tr>
            </table>
            <p><input type="submit" class="button button-primary" name="pmpe_save" value="Save Settings"></p>
        </form>
    </div>
    <?php
}

/* --------------------------------------------
   FRONTEND SCRIPTS & STYLES
-------------------------------------------- */
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
    
    if (!$is_checkout_page || !pmpe_is_enterprise_checkout()) {
        return;
    }

    // First load the per-council base CSS (shared styles)
    if (file_exists(WP_PLUGIN_DIR . '/pmpro-per-council/assets/pmpc-style.css')) {
        wp_enqueue_style(
            'pmpc-checkout-css',
            plugins_url('pmpro-per-council/assets/pmpc-style.css'),
            [],
            '2.2.0'
        );
    }

    // Then load enterprise-specific CSS overrides
    wp_enqueue_style(
        'pmpe-checkout-css',
        plugin_dir_url(__FILE__) . 'assets/pmpe-checkout.css',
        ['pmpc-checkout-css'],
        '2.0.0'
    );

    // Load enterprise JS
    wp_enqueue_script(
        'pmpe-checkout-js',
        plugin_dir_url(__FILE__) . 'assets/pmpe-checkout.js',
        ['jquery'],
        '2.0.0',
        true
    );

    // Get enterprise level price
    $enterprise_level_id = intval(get_option(PMPE_ENTERPRISE_LEVEL_OPTION, 0));
    $enterprise_price = 0;
    if ($enterprise_level_id && function_exists('pmpro_getLevel')) {
        $level = pmpro_getLevel($enterprise_level_id);
        if ($level) {
            $enterprise_price = floatval($level->initial_payment);
        }
    }

    // Get user's current template from business settings (if logged in)
    $user_current_template = 'basic';
    if (is_user_logged_in()) {
        $business_info = get_user_meta(get_current_user_id(), '_pi_business_info', true) ?: [];
        $user_current_template = $business_info['default_template'] ?? 'basic';
    }

    wp_localize_script('pmpe-checkout-js', 'pmpeConfig', [
        'totalSteps' => PMPE_TOTAL_STEPS,
        'checkoutUrl' => pmpro_url('checkout'),
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'restUrl' => rest_url('pi/v1'),
        'restNonce' => wp_create_nonce('wp_rest'),
        'nonce' => wp_create_nonce('pmpe_checkout_nonce'),
        'isLoggedIn' => is_user_logged_in(),
        'enterprisePrice' => $enterprise_price,
        'userCurrentTemplate' => $user_current_template,
        'strings' => [
            'usernameRequired' => 'Please enter a username.',
            'passwordRequired' => 'Please enter a password with at least 8 characters.',
            'passwordMismatch' => 'Passwords do not match.',
            'emailRequired' => 'Please enter a valid email address.',
            'emailMismatch' => 'Email addresses do not match.',
            'processing' => 'Processing your subscription...',
            'continue' => 'Continue',
            'completeSubscription' => 'Complete Enterprise Subscription',
            'perMonth' => '/month'
        ]
    ]);
});

/* --------------------------------------------
   SAVE USER DATA AFTER CHECKOUT
-------------------------------------------- */
add_action('pmpro_after_checkout', 'pmpe_after_checkout_save', 10, 2);
function pmpe_after_checkout_save($user_id, $morder) {
    $level_id = intval(get_option(PMPE_ENTERPRISE_LEVEL_OPTION, 0));
    if (!$level_id) return;

    $membership = pmpro_getMembershipLevelForUser($user_id);
    if (!$membership || $membership->id != $level_id) return;

    // Team seats — always save (subscription-specific)
    $seats = isset($_REQUEST['pmpe_team_seats']) ? max(1, min(3, intval($_REQUEST['pmpe_team_seats']))) : 1;
    update_user_meta($user_id, 'pmpe_is_team_owner', 'yes');
    update_user_meta($user_id, 'pmpe_team_seats', $seats);
    update_user_meta($user_id, 'pmpe_team_members', [$user_id]);

    // Grant full access — always save (subscription-specific)
    pmpe_grant_enterprise_access($user_id);

    // ══════════════════════════════════════════════════════════════════
    // CHECK: If Settings page was EVER saved, do NOT overwrite business info / template
    // ══════════════════════════════════════════════════════════════════
    $existing_info = get_user_meta($user_id, '_pi_business_info', true);
    $settings_saved = is_array($existing_info) && !empty($existing_info['settings_updated_at']);

    if ($settings_saved) {
        error_log("[PMPE] Settings exist for user #$user_id — skipping business info/template override from checkout");
    } else {
        // No settings saved yet — checkout data becomes the primary source
        if (!empty($_REQUEST['pmpe_default_template'])) {
            $template = sanitize_text_field($_REQUEST['pmpe_default_template']);
            update_user_meta($user_id, PMPE_META_TEMPLATE, $template);

            $business = get_user_meta($user_id, '_pi_business_info', true) ?: [];
            $business['default_template'] = $template;
            $business['source'] = 'checkout';
            update_user_meta($user_id, '_pi_business_info', $business);
        }

        // Business info from checkout form
        $checkout_business_info = [];
        $business_fields = ['pmpe_company_name', 'pmpe_business_email', 'pmpe_business_phone', 'pmpe_company_address', 'pmpe_website', 'pmpe_vat_number'];
        
        foreach ($business_fields as $field) {
            if (!empty($_REQUEST[$field])) {
                $checkout_business_info[$field] = sanitize_text_field($_REQUEST[$field]);
            }
        }
        
        if (!empty($checkout_business_info)) {
            update_user_meta($user_id, PMPE_META_BUSINESS, $checkout_business_info);
            
            $business_info = get_user_meta($user_id, '_pi_business_info', true) ?: [];
            
            $field_map = [
                'pmpe_company_name' => 'company_name',
                'pmpe_business_email' => 'email',
                'pmpe_business_phone' => 'phone',
                'pmpe_company_address' => 'company_address',
                'pmpe_website' => 'website',
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
    if (is_object($morder)) {
        $councils = pmpe_get_all_councils();
        $summary = wp_json_encode([
            'region' => 'Enterprise Access',
            'councils' => $councils,
            'template' => $_REQUEST['pmpe_default_template'] ?? 'basic'
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (isset($morder->notes)) {
            $morder->notes .= "\nEnterpriseBundle: {$summary}";
        } else {
            $morder->notes = "EnterpriseBundle: {$summary}";
        }

        if (method_exists($morder, 'save')) {
            $morder->save();
        }
    }

    // Clear session
    if (session_id()) {
        unset($_SESSION[PMPE_SESSION_KEY]);
    }

    error_log("[PMPE] Enterprise checkout complete - User #{$user_id} granted {$seats} seats");
}

/* --------------------------------------------
   MULTI-STEP SERVER-SIDE HANDLER
-------------------------------------------- */
add_action('pmpro_checkout_preheader', 'pmpe_multi_step_handler');
function pmpe_multi_step_handler() {
    if (!pmpe_is_enterprise_checkout()) {
        return;
    }

    if (!session_id()) {
        session_start();
    }

    // Restore session data on every load
    $data = isset($_SESSION[PMPE_SESSION_KEY]) ? (array) $_SESSION[PMPE_SESSION_KEY] : [];
    if (!empty($data)) {
        $_REQUEST = array_merge($_REQUEST, $data);
    }

    // Handle final checkout submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
        (isset($_POST['submit-checkout']) || isset($_POST['pmpro_submit']) || isset($_POST['javascriptok']))) {
        // Final submit: merge session → request and clear
        if (!empty($data)) {
            if (isset($data['template']))     $_REQUEST['pmpe_default_template'] = $data['template'];
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
        unset($_SESSION[PMPE_SESSION_KEY]);
        return; // let PMPro handle real checkout
    }

    // Handle AJAX step saves (steps 1–3)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pmpe_save_step') {
        check_ajax_referer('pmpe_checkout_nonce', 'nonce');

        $step = isset($_POST['step']) ? intval($_POST['step']) : 0;
        if (!$step || $step >= 4) {
            wp_send_json_error(['message' => 'Invalid step']);
        }

        switch ($step) {
            case 1:
                // Enterprise step 1 is just benefits display - nothing to save
                break;

            case 2:
                $data['template'] = sanitize_text_field($_POST['template'] ?? 'professional');
                $business = [];
                $fields = ['pmpe_company_name', 'pmpe_business_email', 'pmpe_business_phone',
                           'pmpe_company_address', 'pmpe_website', 'pmpe_vat_number'];
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

        $_SESSION[PMPE_SESSION_KEY] = $data;
        wp_send_json_success(['step' => $step + 1]);
    }
}

/* --------------------------------------------
   FORCE CUSTOM CHECKOUT TEMPLATE FOR ENTERPRISE
-------------------------------------------- */

add_action('pmpro_checkout_preheader', function() {
    global $pmpro_pages;

    // Bail early if not checkout page or not enterprise level
    if (!is_page($pmpro_pages['checkout']) || !pmpe_is_enterprise_checkout()) {
        return;
    }

    // Skip on final checkout submission
    $is_final_submit = (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        (isset($_POST['submit-checkout']) || isset($_POST['pmpro_submit']))
    );

    if ($is_final_submit) {
        return;
    }

    // Also skip if this looks like a confirmation redirect
    if (isset($_GET['confirm']) || isset($_GET['review'])) {
        return;
    }

    // Look for the enterprise checkout template
    $custom_file = get_stylesheet_directory() . '/paid-memberships-pro/pages/checkoutent.php';

    if (!file_exists($custom_file)) {
        return;
    }

    // Clear conflicting filters
    remove_all_filters('pmpro_get_template');
    remove_all_filters('pmpro_pages_custom_template_path');

    // ENQUEUE ASSETS HERE (before get_header ensures they load even with early exit)
    // This is critical for logged-out users where wp_enqueue_scripts hasn't fired yet
    // First load the per-council base CSS (shared styles)
    if (file_exists(WP_PLUGIN_DIR . '/pmpro-per-council/assets/pmpc-style.css')) {
        wp_enqueue_style(
            'pmpc-checkout-css',
            plugins_url('pmpro-per-council/assets/pmpc-style.css'),
            [],
            '2.2.0'
        );
    }

    // Then load enterprise-specific CSS overrides
    wp_enqueue_style(
        'pmpe-checkout-css',
        plugin_dir_url(__FILE__) . 'assets/pmpe-checkout.css',
        ['pmpc-checkout-css'],
        '2.0.0'
    );

    // Load enterprise JS
    wp_enqueue_script(
        'pmpe-checkout-js',
        plugin_dir_url(__FILE__) . 'assets/pmpe-checkout.js',
        ['jquery'],
        '2.0.0',
        true
    );

    // Get enterprise level price
    $enterprise_level_id = intval(get_option(PMPE_ENTERPRISE_LEVEL_OPTION, 0));
    $enterprise_price = 0;
    if ($enterprise_level_id && function_exists('pmpro_getLevel')) {
        $level = pmpro_getLevel($enterprise_level_id);
        if ($level) {
            $enterprise_price = floatval($level->initial_payment);
        }
    }

    // Get user's current template from business settings (if logged in)
    $user_current_template = 'basic';
    if (is_user_logged_in()) {
        $business_info = get_user_meta(get_current_user_id(), '_pi_business_info', true) ?: [];
        $user_current_template = $business_info['default_template'] ?? 'basic';
    }

    wp_localize_script('pmpe-checkout-js', 'pmpeConfig', [
        'totalSteps' => PMPE_TOTAL_STEPS,
        'checkoutUrl' => pmpro_url('checkout'),
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'restUrl' => rest_url('pi/v1'),
        'restNonce' => wp_create_nonce('wp_rest'),
        'nonce' => wp_create_nonce('pmpe_checkout_nonce'),
        'isLoggedIn' => is_user_logged_in(),
        'enterprisePrice' => $enterprise_price,
        'userCurrentTemplate' => $user_current_template,
        'strings' => [
            'usernameRequired' => 'Please enter a username.',
            'passwordRequired' => 'Please enter a password with at least 8 characters.',
            'passwordMismatch' => 'Passwords do not match.',
            'emailRequired' => 'Please enter a valid email address.',
            'emailMismatch' => 'Email addresses do not match.',
            'processing' => 'Processing your subscription...',
            'continue' => 'Continue',
            'completeSubscription' => 'Complete Enterprise Subscription',
            'perMonth' => '/month'
        ]
    ]);

    // CRITICAL FIX: Ensure jQuery is enqueued
    wp_enqueue_script('jquery');

    // Buffer + full theme structure
    ob_start();
    get_header();
    
    // CRITICAL FIX: For logged-out users, always manually output CSS directly
    // This bypasses any WordPress hooks that might be preventing proper enqueue
    if (!is_user_logged_in()) {
        $css_url = plugin_dir_url(__FILE__) . 'assets/pmpe-checkout.css';
        echo "\n<!-- PMPE ENTERPRISE: Direct CSS injection for logged-out users -->\n";
        echo '<link rel="stylesheet" id="pmpe-checkout-css-direct" href="' . esc_url($css_url) . '?v=2.0.0" type="text/css" media="all" />' . "\n";
    }
    
    include $custom_file;
    get_footer();

    $output = ob_get_clean();
    echo $output;

    // Exit — prevent PMPro from rendering anything else
    exit;

}, 5);

/* --------------------------------------------
   FRONTEND SHORTCODE — [enterprise_checkout]
-------------------------------------------- */
add_shortcode('enterprise_checkout', function() {
    if ( ! function_exists('pmpro_getLevel') ) {
        return '<p>Paid Memberships Pro must be active.</p>';
    }

    $level_id = intval( get_option( PMPE_ENTERPRISE_LEVEL_OPTION, 0 ) );
    if ( ! $level_id ) {
        return '<p><em>Enterprise level not set in settings.</em></p>';
    }

    $level = pmpro_getLevel( $level_id );
    if ( ! $level ) {
        return '<p><em>Enterprise membership level not found.</em></p>';
    }

    ob_start(); ?>
    <section class="enterprise-checkout-wrapper">
        <div class="enterprise-checkout-card">
            <div class="enterprise-header">
                <h2>Enterprise Access</h2>
                <p>Unlimited access to all UK councils across the Planning Index platform.</p>
            </div>

            <div class="enterprise-plan">
                <h3><?php echo esc_html( $level->name ); ?></h3>
                <p class="price">
                    <?php
                    echo pmpro_formatPrice( $level->initial_payment );
                    if ( $level->billing_amount > 0 ) {
                        echo ' / ' . esc_html( $level->cycle_number . ' ' . $level->cycle_period );
                    }
                    ?>
                </p>
            </div>

            <div class="enterprise-action">
                <a href="<?php echo esc_url( pmpro_url( 'checkout', '?level=' . $level_id ) ); ?>" class="enterprise-button">
                    Proceed to Checkout
                </a>
            </div>

            <div class="enterprise-footer">
                <p>Includes full nationwide data coverage, API access, and enterprise-level reporting tools.</p>
            </div>
        </div>
    </section>
    <?php
    return ob_get_clean();
});
/* ============================================
   MY TEAM PAGE — FULL DASHBOARD
============================================ */

// Enqueue team dashboard assets
add_action('wp_enqueue_scripts', function() {
    global $post;
    if (!$post || !has_shortcode($post->post_content, 'pmpro_my_team')) return;

    wp_enqueue_style('google-fonts-inter', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap', [], null);
    wp_enqueue_style('pmpe-team-dashboard-css', plugin_dir_url(__FILE__) . 'assets/pmpe-team-dashboard.css', [], '1.0.0');
    wp_enqueue_script('pmpe-team-dashboard-js', plugin_dir_url(__FILE__) . 'assets/pmpe-team-dashboard.js', ['jquery'], '1.0.0', true);

    $user_id = get_current_user_id();
    wp_localize_script('pmpe-team-dashboard-js', 'pmpeTeamConfig', [
        'restUrl'      => rest_url('pi/v1'),
        'ajaxUrl'      => admin_url('admin-ajax.php'),
        'restNonce'    => wp_create_nonce('wp_rest'),
        'inviteNonce'  => wp_create_nonce('pmpe_invite_nonce'),
        'isOwner'      => get_user_meta($user_id, 'pmpe_is_team_owner', true) === 'yes',
        'currentUserId'=> $user_id,
    ]);
});

// REST API: Team Overview
add_action('rest_api_init', function() {
    register_rest_route('pi/v1', '/team/overview', [
        'methods' => 'GET',
        'permission_callback' => function() {
            if (!is_user_logged_in()) return false;
            $uid = get_current_user_id();
            return pmpro_hasMembershipLevel(get_option(PMPE_ENTERPRISE_LEVEL_OPTION), $uid);
        },
        'callback' => function() {
            $user_id = get_current_user_id();
            $is_owner = get_user_meta($user_id, 'pmpe_is_team_owner', true) === 'yes';

            // Get team members
            if ($is_owner) {
                $member_ids = get_user_meta($user_id, 'pmpe_team_members', true) ?: [$user_id];
            } else {
                $owner_id = get_user_meta($user_id, 'pmpe_team_owner_id', true);
                $member_ids = $owner_id ? (get_user_meta($owner_id, 'pmpe_team_members', true) ?: [$owner_id]) : [$user_id];
            }

            $total_leads = 0; $total_value = 0; $won_value = 0;
            $total_tasks = 0; $pending_tasks = 0; $overdue_tasks = 0;
            $total_proposals = 0;
            $pipeline_breakdown = ['new_lead'=>0,'proposal_sent'=>0,'contacted'=>0,'negotiation'=>0,'won'=>0];
            $members_data = [];

            foreach ($member_ids as $mid) {
                $u = get_user_by('id', $mid);
                if (!$u) continue;

                // Get leads
                $leads = get_posts([
                    'post_type' => 'pi_lead',
                    'meta_key' => '_pi_lead_owner_user_id',
                    'meta_value' => strval($mid),
                    'posts_per_page' => -1,
                    'post_status' => 'any',
                ]);

                $m_leads = count($leads);
                $m_value = 0; $m_won = 0; $m_won_value = 0;
                $m_pipeline = ['new_lead'=>0,'proposal_sent'=>0,'contacted'=>0,'negotiation'=>0,'won'=>0];

                foreach ($leads as $lead) {
                    $lid = $lead->ID;
                    $stage = get_post_meta($lid, '_pi_lead_stage', true) ?: 'new_lead';
                    
                    // Calculate value from pricing_details (matches lead page calculation)
                    // Falls back to estimated_value if no pricing_details
                    $pricing_details = get_post_meta($lid, '_pi_lead_pricing_details', true);
                    if (is_string($pricing_details)) $pricing_details = maybe_unserialize($pricing_details);
                    
                    $est = 0;
                    if (is_array($pricing_details) && !empty($pricing_details)) {
                        $subtotal = 0;
                        foreach ($pricing_details as $item) {
                            $price = floatval($item['price'] ?? 0);
                            $qty = intval($item['qty'] ?? 1);
                            $subtotal += ($price * $qty);
                        }
                        $est = $subtotal + ($subtotal * 0.20); // Include VAT like lead page
                    } else {
                        $est = floatval(get_post_meta($lid, '_pi_lead_estimated_value', true));
                    }

                    if (isset($m_pipeline[$stage])) $m_pipeline[$stage]++;
                    if (isset($pipeline_breakdown[$stage])) $pipeline_breakdown[$stage]++;

                    $m_value += $est;
                    $total_value += $est;

                    if ($stage === 'won') { $m_won++; $m_won_value += $est; $won_value += $est; }

                }

                $total_leads += $m_leads;

                // Tasks
                $tasks_meta = get_user_meta($mid, '_pi_tasks', true) ?: [];
                $m_tasks = count($tasks_meta);
                $m_pending = 0; $m_overdue = 0;
                foreach ($tasks_meta as $t) {
                    if (empty($t['completed'])) {
                        $m_pending++;
                        if (!empty($t['due']) && strtotime($t['due']) < time()) $m_overdue++;
                    }
                }
                $total_tasks += $m_tasks;
                $pending_tasks += $m_pending;
                $overdue_tasks += $m_overdue;

                // Proposals
                $invoices = get_user_meta($mid, '_pi_invoices', true) ?: [];
                $m_proposals = count($invoices);
                $total_proposals += $m_proposals;

                $members_data[] = [
                    'user_id' => $mid,
                    'username' => $u->user_login,
                    'display_name' => $u->display_name ?: $u->user_login,
                    'email' => $u->user_email,
                    'is_owner' => get_user_meta($mid, 'pmpe_is_team_owner', true) === 'yes',
                    'total_leads' => $m_leads,
                    'total_value' => $m_value,
                    'won_leads' => $m_won,
                    'won_value' => $m_won_value,
                    'total_tasks' => $m_tasks,
                    'pending_tasks' => $m_pending,
                    'total_proposals' => $m_proposals,
                    'pipeline_breakdown' => $m_pipeline,
                ];
            }

            // Get team activity from structured log
            $owner_id = get_user_meta($user_id, 'pmpe_is_team_owner', true) === 'yes'
                ? $user_id
                : (intval(get_user_meta($user_id, 'pmpe_team_owner_id', true)) ?: $user_id);

            $team_activity = get_user_meta($owner_id, '_pmpe_team_activity', true) ?: [];

            // If no structured activity yet, migrate from status_history
            if (empty($team_activity)) {
                $raw_activity = [];
                foreach ($members_data as $md) {
                    $m_leads = get_posts([
                        'post_type' => 'pi_lead',
                        'meta_key' => '_pi_lead_owner_user_id',
                        'meta_value' => strval($md['user_id']),
                        'posts_per_page' => -1,
                        'post_status' => 'any',
                    ]);
                    foreach ($m_leads as $ml) {
                        $hist = get_post_meta($ml->ID, '_pi_lead_status_history', true) ?: [];
                        if (!is_array($hist)) continue;
                        $lead_code = get_post_meta($ml->ID, '_pi_lead_lead_code', true) ?: '';
                        foreach ($hist as $h) {
                            if (!preg_match('/Lead created|Moved to/i', $h)) continue;
                            if (preg_match('/^(\d{4}-\d{2}-\d{2}\s[\d:]+):\s*(.+)/', $h, $hm)) {
                                $desc = trim($hm[2]);
                                if ($lead_code) $desc .= " ({$lead_code})";
                                $raw_activity[] = [
                                    'user_id' => $md['user_id'],
                                    'user_name' => $md['display_name'],
                                    'type' => stripos($hm[2], 'Lead created') !== false ? 'lead_created' : 'stage_changed',
                                    'type_label' => stripos($hm[2], 'Lead created') !== false ? 'New Lead' : 'Pipeline',
                                    'description' => $desc,
                                    'lead_code' => $lead_code,
                                    'timestamp' => strtotime($hm[1]),
                                    'time_ago' => human_time_diff(strtotime($hm[1]), time()) . ' ago',
                                ];
                            }
                        }
                    }
                }
                usort($raw_activity, fn($a, $b) => ($b['timestamp'] ?? 0) - ($a['timestamp'] ?? 0));
                $team_activity = array_slice($raw_activity, 0, 30);
                // Save migrated activity
                if (!empty($team_activity)) {
                    update_user_meta($owner_id, '_pmpe_team_activity', $team_activity);
                }
            } else {
                // Add time_ago to structured entries
                foreach ($team_activity as &$ta) {
                    $ta['time_ago'] = human_time_diff($ta['timestamp'], time()) . ' ago';
                    $ta['type_label'] = $ta['type'] === 'lead_created' ? 'New Lead' : 'Pipeline';
                }
                unset($ta);
            }

            return rest_ensure_response([
                'members' => $members_data,
                'total_leads' => $total_leads,
                'total_value' => $total_value,
                'won_value' => $won_value,
                'total_tasks' => $total_tasks,
                'pending_tasks' => $pending_tasks,
                'overdue_tasks' => $overdue_tasks,
                'total_proposals' => $total_proposals,
                'pipeline_breakdown' => $pipeline_breakdown,
                'activity' => array_slice($team_activity, 0, 30),
            ]);
        }
    ]);

    // REST API: Remove team member
    register_rest_route('pi/v1', '/team/remove-member', [
        'methods' => 'POST',
        'permission_callback' => function() {
            if (!is_user_logged_in()) return false;
            return get_user_meta(get_current_user_id(), 'pmpe_is_team_owner', true) === 'yes';
        },
        'callback' => function(WP_REST_Request $req) {
            $owner_id = get_current_user_id();
            $remove_id = intval($req['user_id']);
            if ($remove_id === $owner_id) return new WP_Error('invalid', 'Cannot remove yourself', ['status' => 400]);

            $team = get_user_meta($owner_id, 'pmpe_team_members', true) ?: [];
            $team = array_values(array_filter($team, fn($id) => $id != $remove_id));
            update_user_meta($owner_id, 'pmpe_team_members', $team);

			// Remove membership using proper PMPro 3.0+ cancellation
			if (function_exists('pmpro_cancelMembershipLevel')) {
				// Get the user's current level to cancel specifically
				$current_level = pmpro_getMembershipLevelForUser($remove_id);
				if ($current_level && !empty($current_level->ID)) {
					pmpro_cancelMembershipLevel($current_level->ID, $remove_id, 'inactive');
				} else {
					// Fallback: cancel all levels
					$levels = pmpro_getMembershipLevelsForUser($remove_id);
					foreach ($levels as $level) {
						pmpro_cancelMembershipLevel($level->id, $remove_id, 'inactive');
					}
				}
			} elseif (function_exists('pmpro_changeMembershipLevel')) {
				// Legacy fallback for older PMPro versions
				pmpro_changeMembershipLevel(0, $remove_id);
			}
            delete_user_meta($remove_id, 'pmpe_team_owner_id');

            return rest_ensure_response(['removed' => true]);
        }
    ]);
});

/* --------------------------------------------
   TEAM ACTIVITY LOGGING
-------------------------------------------- */
function pmpe_log_team_activity($user_id, $type, $description, $lead_id = 0) {
    // Find team owner
    $owner_id = $user_id;
    $is_owner = get_user_meta($user_id, 'pmpe_is_team_owner', true) === 'yes';
    if (!$is_owner) {
        $owner_id = intval(get_user_meta($user_id, 'pmpe_team_owner_id', true));
        if (!$owner_id) return;
    }

    $members = get_user_meta($owner_id, 'pmpe_team_members', true) ?: [];
    if (!in_array($user_id, $members) && $user_id != $owner_id) return;

    $user = get_user_by('id', $user_id);
    $activity = get_user_meta($owner_id, '_pmpe_team_activity', true) ?: [];

    $lead_code = '';
    if ($lead_id) {
        $lead_code = get_post_meta($lead_id, '_pi_lead_lead_code', true) ?: '';
    }

    $entry = [
        'user_id' => $user_id,
        'user_name' => $user ? ($user->display_name ?: $user->user_login) : 'Unknown',
        'type' => $type,
        'description' => $lead_code ? "{$description} ({$lead_code})" : $description,
        'lead_id' => $lead_id,
        'lead_code' => $lead_code,
        'timestamp' => time(),
    ];

    array_unshift($activity, $entry);
    $activity = array_slice($activity, 0, 30);

    update_user_meta($owner_id, '_pmpe_team_activity', $activity);
}

// Hook: Lead created
add_action('pi_lead_created', function($lead_id, $user_id) {
    pmpe_log_team_activity($user_id, 'lead_created', 'Added a new lead', $lead_id);
}, 10, 2);

// Hook: Lead stage changed
add_action('pi_lead_stage_changed', function($lead_id, $user_id, $old_stage, $new_stage) {
    $labels = [
        'new_lead' => 'New Lead', 'proposal_sent' => 'Proposal Sent',
        'contacted' => 'Contacted', 'negotiation' => 'Negotiation', 'won' => 'Won'
    ];
    $label = $labels[$new_stage] ?? $new_stage;
    pmpe_log_team_activity($user_id, 'stage_changed', "Moved a lead to {$label}", $lead_id);
}, 10, 4);

add_shortcode('pmpro_my_team', 'pmpe_my_team_page');
function pmpe_my_team_page() {
    if (!is_user_logged_in()) {
        return '<div class="td-dashboard"><div class="td-auth-required">
            <div class="td-auth-icon"><svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>
            <h2>Sign in to manage your team</h2>
            <p>You need to be logged in to access your Enterprise team dashboard.</p>
            <a href="' . wp_login_url(get_permalink()) . '" class="td-btn-primary">Log In</a>
        </div></div>';
    }

    $user_id = get_current_user_id();
    if (!pmpro_hasMembershipLevel(get_option(PMPE_ENTERPRISE_LEVEL_OPTION), $user_id)) {
        return '<div class="td-dashboard"><div class="td-auth-required">
            <div class="td-auth-icon" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.5"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
            </div>
            <h2>Enterprise Members Only</h2>
            <p>Team management is available exclusively for Enterprise plan subscribers.</p>
            <a href="' . esc_url(pmpro_url('levels')) . '" class="td-btn-primary">View Plans</a>
        </div></div>';
    }

    $is_owner = get_user_meta($user_id, 'pmpe_is_team_owner', true) === 'yes';
    $seats = intval(get_user_meta($user_id, 'pmpe_team_seats', true)) ?: 1;
    $members = get_user_meta($user_id, 'pmpe_team_members', true) ?: [$user_id];
    $used = count($members);
    $available = max(0, $seats - $used);
    $usage_percent = ($seats > 0) ? round(($used / $seats) * 100) : 100;

    ob_start(); ?>
    <div class="td-dashboard">
        <!-- Hero Header -->
        <div class="td-hero">
            <div class="td-hero-top">
                <div class="td-hero-badge">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                    Enterprise Team
                </div>
                <div class="td-hero-actions">
                    <a href="<?= esc_url(home_url('/workspace/')) ?>" class="td-hero-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                        My Pipeline
                    </a>
                </div>
            </div>
            <h1>Team Dashboard</h1>
            <p>Manage your Enterprise team, track performance, and monitor activity across all members.</p>
        </div>

        <!-- Capacity Bar -->
        <div class="td-capacity">
            <div class="td-capacity-header">
                <span>Team Capacity</span>
                <small><?= $used ?> of <?= $seats ?> seats used</small>
            </div>
            <div class="td-progress-track">
                <div class="td-progress-fill <?= $available === 0 ? 'full' : '' ?>" style="width:<?= $usage_percent ?>%;"></div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="td-tabs">
            <button class="td-tab active" data-tab="overview">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                Overview
            </button>
            <button class="td-tab" data-tab="members">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Members
            </button>
            <button class="td-tab" data-tab="activity">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                Activity
            </button>
            <button class="td-tab" data-tab="settings">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c.26.604.852.997 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                Settings
            </button>
        </div>

        <!-- Tab Panels -->
        <div id="td-panel-overview" class="td-panel active">
            <div id="td-overview-content">
                <div class="td-loading"><div class="td-spinner"></div><p>Loading team dashboard...</p></div>
            </div>
        </div>

        <div id="td-panel-members" class="td-panel">
            <div id="td-members-content">
                <div class="td-loading"><div class="td-spinner"></div><p>Loading members...</p></div>
            </div>
        </div>

        <div id="td-panel-activity" class="td-panel">
            <div id="td-activity-content">
                <div class="td-loading"><div class="td-spinner"></div><p>Loading activity...</p></div>
            </div>
        </div>

        <div id="td-panel-settings" class="td-panel">
            <?php if ($is_owner && $available > 0) : ?>
            <div class="td-invite-section">
                <h3 class="td-invite-title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                    Invite a Team Member
                </h3>
                <p class="td-invite-desc">Enter their email address and we'll create an account with full Enterprise access and send them login details.</p>
                <form id="td-invite-form" class="td-invite-form">
                    <div class="td-invite-input-wrap">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        <input type="email" id="td-invite-email" class="td-invite-input" placeholder="colleague@company.com" required>
                    </div>
                    <button type="submit" class="td-invite-btn" id="td-invite-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                        Send Invite
                    </button>
                </form>
                <div id="td-invite-msg" class="td-invite-msg"></div>
            </div>
            <?php elseif ($is_owner) : ?>
            <div class="td-card" style="margin-bottom:24px;">
                <div class="td-card-body" style="display:flex;align-items:center;gap:14px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;color:var(--td-text-muted);"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                    <p style="margin:0;font-size:14px;color:var(--td-text-secondary);"><strong>All seats are filled.</strong> You've reached your maximum of <?= $seats ?> team members. Contact support if you need additional seats.</p>
                </div>
            </div>
            <?php else : ?>
            <div class="td-card" style="margin-bottom:24px;">
                <div class="td-card-body" style="display:flex;align-items:center;gap:14px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;color:var(--td-accent);"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                    <p style="margin:0;font-size:14px;">You're a team member. Contact your account owner to manage team settings.</p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Team Info -->
            <div class="td-card">
                <div class="td-card-header">
                    <div class="td-card-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33"/></svg>
                        Team Settings
                    </div>
                </div>
                <div class="td-card-body">
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;">
                        <div class="td-member-stat">
                            <div class="td-member-stat-value"><?= $seats ?></div>
                            <div class="td-member-stat-label">Total Seats</div>
                        </div>
                        <div class="td-member-stat">
                            <div class="td-member-stat-value" style="color:var(--td-accent);"><?= $used ?></div>
                            <div class="td-member-stat-label">Used</div>
                        </div>
                        <div class="td-member-stat">
                            <div class="td-member-stat-value" style="color:var(--td-success);"><?= $available ?></div>
                            <div class="td-member-stat-label">Available</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}


add_action('wp_ajax_pmpe_send_team_invite', 'pmpe_send_team_invite_handler');
function pmpe_send_team_invite_handler() {
    check_ajax_referer('pmpe_invite_nonce', 'nonce');

    $owner_id = get_current_user_id();
    $email = sanitize_email($_POST['email'] ?? '');

    if (!$email || !is_email($email)) {
        wp_send_json_error('Invalid email address');
    }

    if (!pmpro_hasMembershipLevel(get_option(PMPE_ENTERPRISE_LEVEL_OPTION), $owner_id)) {
        wp_send_json_error('Not authorized');
    }

    // Check seat limit first
    $team = get_user_meta($owner_id, 'pmpe_team_members', true) ?: [$owner_id];
    $seats = intval(get_user_meta($owner_id, 'pmpe_team_seats', true)) ?: 1;

    if (count($team) >= $seats) {
        wp_send_json_error('You have reached the maximum number of team members');
    }

    // Create user
    $base_username = sanitize_user(strstr($email, '@', true));
    $username = $base_username;
    $counter = 1;
    while (username_exists($username)) {
        $username = $base_username . $counter++;
    }

    $password = wp_generate_password(12, false);
    $new_user_id = wp_create_user($username, $password, $email);

    if (is_wp_error($new_user_id)) {
        wp_send_json_error($new_user_id->get_error_message());
    }

    // Assign membership level
    $enterprise_level_id = get_option(PMPE_ENTERPRISE_LEVEL_OPTION);
    if (function_exists('pmpro_changeMembershipLevel')) {
        pmpro_changeMembershipLevel($enterprise_level_id, $new_user_id);
    }
    // Grant full council access (THIS WAS MISSING)
    pmpe_grant_enterprise_access($new_user_id);

    // Link to owner
    update_user_meta($new_user_id, 'pmpe_team_owner_id', $owner_id);

    // Add to team list
    $team[] = $new_user_id;
    update_user_meta($owner_id, 'pmpe_team_members', array_unique($team));

    // Send email with direct login link
    // === STUNNING HTML INVITE EMAIL ===
    $login_url = wp_login_url();

    $subject = "You've been invited to Planning Index Enterprise";

    $html_message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>You\'ve been invited to Planning Index Enterprise</title>
        <style>
            body { margin:0; padding:0; background:#f4f4f4; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .email-container { max-width: 620px; margin: 40px auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 15px 35px rgba(0,0,0,0.08); }
            .header { 
                background: linear-gradient(135deg, #1b2534 0%, #2a3a50 100%); 
                padding: 50px 40px; 
                text-align: center; 
                color: white; 
            }
            .header h1 { 
                margin: 0; 
                font-size: 28px; 
                font-weight: 800; 
            }
            .content { 
                padding: 50px 45px; 
                color: #1b2534; 
                line-height: 1.65; 
            }
            .content h2 { 
                font-size: 24px; 
                margin: 0 0 20px; 
            }
            .credentials-box {
                background: #f8fafc;
                border-left: 5px solid #ec5c0d;
                padding: 22px 26px;
                margin: 30px 0;
                border-radius: 8px;
                font-family: monospace;
                font-size: 15.5px;
            }
            .login-button {
                display: inline-block;
                background: linear-gradient(135deg, #ec5c0d, #ff7a33);
                color: white !important;
                padding: 16px 38px;
                font-size: 17px;
                font-weight: 600;
                text-decoration: none;
                border-radius: 50px;
                margin: 25px 0;
                box-shadow: 0 8px 25px rgba(236, 92, 13, 0.35);
                transition: all 0.3s;
            }
            .login-button:hover {
                transform: translateY(-3px);
                box-shadow: 0 12px 30px rgba(236, 92, 13, 0.45);
            }
            .footer {
                text-align: center;
                padding: 35px 40px;
                background: #f8f9fb;
                font-size: 13px;
                color: #64748b;
            }
            .footer a { color: #ec5c0d; text-decoration: none; }
        </style>
    </head>
    <body>
        <div class="email-container">
            <div class="header">
                <h1>PlanningIndex</h1>
                <p style="margin:12px 0 0; opacity:0.9; font-size:15px;">Enterprise Team Invitation</p>
            </div>
            
            <div class="content">
                <h2>Welcome to the team!</h2>
                <p>You have been invited to join <strong>' . get_bloginfo('name') . '</strong> under the <strong>Enterprise Plan</strong>.</p>
                
                <p>You now have full access to planning applications from every council across the UK.</p>
                
                <div class="credentials-box">
                    <strong>Username:</strong> ' . esc_html($username) . '<br>
                    <strong>Temporary Password:</strong> ' . esc_html($password) . '
                </div>
                
                <p style="text-align:center;">
                    <a href="' . esc_url($login_url) . '" class="login-button">Log In Now →</a>
                </p>
                
                <p><strong>Security Note:</strong> Please change your password immediately after your first login.</p>
            </div>
            
            <div class="footer">
                Planning Index • Enterprise Access<br>
                <a href="' . home_url() . '">planningindex.co.uk</a>
            </div>
        </div>
    </body>
    </html>
    ';

    // Send as HTML email
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: Planning Index <no-reply@' . parse_url(home_url(), PHP_URL_HOST) . '>'
    );

    wp_mail($email, $subject, $html_message, $headers);

    wp_send_json_success('Invitation sent successfully');
}
/* --------------------------------------------
   ENQUEUE SHORTCODE STYLES (for [enterprise_checkout])
-------------------------------------------- */
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style( 'pmpe-enterprise-style', plugin_dir_url(__FILE__) . 'assets/pmpe-checkout.css', [], '2.1' );
});

/* --------------------------------------------
   AJAX HANDLERS
-------------------------------------------- */
add_action('wp_ajax_pmpe_check_user', 'pmpe_ajax_check_user');
add_action('wp_ajax_nopriv_pmpe_check_user', 'pmpe_ajax_check_user');

function pmpe_ajax_check_user() {
    check_ajax_referer('pmpe_checkout_nonce', 'nonce');
    
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

/* --------------------------------------------
   FOOTER SCRIPT FOR WIZARD CHECK
-------------------------------------------- */
add_action('wp_footer', function() {
    if (pmpe_is_enterprise_checkout()) {
        ?>
        <script>
            jQuery(function($) {
                if ($('.pmpe-wizard-checkout').length > 0) {
                    console.log('PMPE: Enterprise Wizard loaded');
                } else {
                console.log('PMPE: Enterprise Wizard NOT loaded');
                }
            });
        </script>
        <?php
    }
});

/* ============================================
   TEAM PREVIEW SHORTCODE (Account Page Widget)
============================================ */
add_shortcode('pmpe_team_preview', 'pmpe_team_preview_shortcode');
function pmpe_team_preview_shortcode() {
    if (!is_user_logged_in()) return '';

    $user_id = get_current_user_id();
    $enterprise_level = intval(get_option(PMPE_ENTERPRISE_LEVEL_OPTION, 0));

    // Only show for enterprise members
    if (!$enterprise_level || !function_exists('pmpro_hasMembershipLevel')) return '';
    if (!pmpro_hasMembershipLevel($enterprise_level, $user_id)) return '';

    $is_owner  = get_user_meta($user_id, 'pmpe_is_team_owner', true) === 'yes';
    $seats     = intval(get_user_meta($user_id, 'pmpe_team_seats', true)) ?: 1;
    $members   = get_user_meta($user_id, 'pmpe_team_members', true) ?: [$user_id];
    $used      = count($members);
    $available = max(0, $seats - $used);
    $team_url  = 'https://planningindex.co.uk/membership-account/myteam/';

    // Build member avatars (show up to 3)
    $avatar_html = '';
    $show_count = min($used, 3);
    for ($i = 0; $i < $show_count; $i++) {
        $member = get_user_by('id', $members[$i]);
        if ($member) {
            $initials = strtoupper(substr($member->display_name, 0, 1));
            $avatar_html .= '<div class="pmpe-tp-avatar" title="' . esc_attr($member->display_name) . '">' . esc_html($initials) . '</div>';
        }
    }
    if ($used > 3) {
        $avatar_html .= '<div class="pmpe-tp-avatar pmpe-tp-avatar-more">+' . ($used - 3) . '</div>';
    }

    ob_start(); ?>
    <div class="pmpe-team-preview">
        <div class="pmpe-tp-header">
            <div class="pmpe-tp-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div class="pmpe-tp-title">
                <h3>My Team</h3>
                <span class="pmpe-tp-badge"><?php echo $is_owner ? 'Owner' : 'Member'; ?></span>
            </div>
        </div>

        <div class="pmpe-tp-stats">
            <div class="pmpe-tp-stat">
                <span class="pmpe-tp-stat-value"><?php echo $used; ?></span>
                <span class="pmpe-tp-stat-label">Active</span>
            </div>
            <div class="pmpe-tp-stat-divider"></div>
            <div class="pmpe-tp-stat">
                <span class="pmpe-tp-stat-value"><?php echo $seats; ?></span>
                <span class="pmpe-tp-stat-label">Total Seats</span>
            </div>
            <div class="pmpe-tp-stat-divider"></div>
            <div class="pmpe-tp-stat">
                <span class="pmpe-tp-stat-value"><?php echo $available; ?></span>
                <span class="pmpe-tp-stat-label">Available</span>
            </div>
        </div>

        <?php if ($avatar_html) : ?>
        <div class="pmpe-tp-members">
            <span class="pmpe-tp-members-label">Team members</span>
            <div class="pmpe-tp-avatars"><?php echo $avatar_html; ?></div>
        </div>
        <?php endif; ?>

        <a href="<?php echo esc_url($team_url); ?>" class="pmpe-tp-link">
            Manage Team
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </a>
    </div>
    <?php
    return ob_get_clean();
}
