<?php

/**
 * Plugin Name: PMPro Trial Checkout
 * Description: Multi-step checkout for free trial membership (level 63, max 5 councils, 2 weeks free).
 * Version: 3.0.0
 * Author: Planning Index
 */

if (!defined('ABSPATH')) exit;

/*--------------------------------------------------------------
  NUCLEAR FIX: Force CSS for logged-out users via EVERY method
--------------------------------------------------------------*/

// Method 1: Early wp_head hook
add_action('wp_head', function() {
    if (!is_user_logged_in() && isset($_GET['pmpro_level']) && $_GET['pmpro_level'] == 63) {
        $css_url = plugins_url('assets/pmpc-trial.css', __FILE__);
        echo '<link rel="stylesheet" id="pmpc-trial-css-method1" href="' . esc_url($css_url) . '?v=' . time() . '" type="text/css" media="all" />' . "\n";
    }
}, 1);

// Method 2: Late wp_head hook
add_action('wp_head', function() {
    if (!is_user_logged_in() && isset($_GET['pmpro_level']) && $_GET['pmpro_level'] == 63) {
        $css_url = plugins_url('assets/pmpc-trial.css', __FILE__);
        echo '<link rel="stylesheet" id="pmpc-trial-css-method2" href="' . esc_url($css_url) . '?v=' . time() . '" type="text/css" media="all" />' . "\n";
    }
}, 999);

// Method 3: Inline CSS if file exists
add_action('wp_head', function() {
    if (!is_user_logged_in() && isset($_GET['pmpro_level']) && $_GET['pmpro_level'] == 63) {
        $css_file = plugin_dir_path(__FILE__) . 'assets/pmpc-trial.css';
        if (file_exists($css_file)) {
            echo '<style id="pmpc-trial-css-inline">' . file_get_contents($css_file) . '</style>' . "\n";
        }
    }
}, 500);

// Method 4: JavaScript fallback to inject CSS
add_action('wp_footer', function() {
    if (!is_user_logged_in() && isset($_GET['pmpro_level']) && $_GET['pmpro_level'] == 63) {
        $css_url = plugins_url('assets/pmpc-trial.css', __FILE__);
        echo '<script>if(!document.getElementById("pmpc-trial-css-js")){var l=document.createElement("link");l.id="pmpc-trial-css-js";l.rel="stylesheet";l.type="text/css";l.href="' . esc_url($css_url) . '?v=' . time() . '";document.head.appendChild(l);}</script>' . "\n";
    }
}, 1);

/*--------------------------------------------------------------
  CONFIGURATION
--------------------------------------------------------------*/

define('PMPC_TRIAL_MAX', 5);
define('PMPC_TRIAL_MIN', 1);
define('PMPC_TRIAL_LEVEL_ID', 63);
define('PMPC_TRIAL_DAYS', 14);
define('PMPC_TRIAL_SESSION_KEY', 'pmpc_trial_session');
define('PMPC_TRIAL_META_KEY', 'pmpc_selected_councils');
define('PMPC_TRIAL_META_TEMPLATE', 'pmpc_default_template');
define('PMPC_TRIAL_META_BUSINESS', 'pmpc_trial_business_info');
define('PMPC_TRIAL_TOTAL_STEPS', 4);

/*--------------------------------------------------------------
  COUNCIL DATA
--------------------------------------------------------------*/

function pmpc_trial_get_all_councils() {
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

function pmpc_trial_get_templates() {
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
  HELPER: Check if current checkout is trial level
--------------------------------------------------------------*/

function pmpc_is_trial_checkout() {
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

    return $current_level === PMPC_TRIAL_LEVEL_ID;
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
    
    if (!$is_checkout_page || !pmpc_is_trial_checkout()) {
        return;
    }

    // Explicitly enqueue jQuery before our script
    wp_enqueue_script('jquery');

    wp_enqueue_script(
        'pmpc-trial-js',
        plugin_dir_url(__FILE__) . 'assets/pmpc-trial.js',
        ['jquery'],
        '3.0.0',
        true
    );

    $user_current_template = 'professional';
    if (is_user_logged_in()) {
        $business_info = get_user_meta(get_current_user_id(), '_pi_business_info', true) ?: [];
        $user_current_template = $business_info['default_template'] ?? 'professional';
    }

    // Detect reduced motion preference server-side
    $is_reduced_motion = false;
    if (isset($_SERVER['HTTP_SEC_CH_PREFERS_REDUCED_MOTION'])) {
        $is_reduced_motion = $_SERVER['HTTP_SEC_CH_PREFERS_REDUCED_MOTION'] === 'reduce';
    }

    wp_localize_script('pmpc-trial-js', 'pmpcTrialConfig', [
        'maxSelection' => PMPC_TRIAL_MAX,
        'minSelection' => PMPC_TRIAL_MIN,
        'totalSteps' => PMPC_TRIAL_TOTAL_STEPS,
        'trialDays' => PMPC_TRIAL_DAYS,
        'councils' => pmpc_trial_get_all_councils(),
        'templates' => pmpc_trial_get_templates(),
        'checkoutUrl' => pmpro_url('checkout'),
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'restUrl' => rest_url('pmpc-trial/v1'),
        'restNonce' => wp_create_nonce('wp_rest'),
        'nonce' => wp_create_nonce('pmpc_trial_checkout_nonce'),
        'isLoggedIn' => is_user_logged_in(),
        'userCurrentTemplate' => $user_current_template,
        'isReducedMotion' => $is_reduced_motion,
        'strings' => [
            'selectMinCouncils' => 'Please select at least 1 council to continue.',
            'maxCouncils' => 'Maximum 5 councils during your free trial.',
            'usernameRequired' => 'Please enter a username.',
            'passwordRequired' => 'Please enter a password with at least 8 characters.',
            'passwordMismatch' => 'Passwords do not match.',
            'emailRequired' => 'Please enter a valid email address.',
            'emailMismatch' => 'Email addresses do not match.',
            'processing' => 'Starting your free trial...',
            'continue' => 'Continue',
            'startTrial' => 'Start 2 Week Free Trial',
            'loadingTemplates' => 'Loading templates...',
            'templateLoadError' => 'Unable to load templates. Using defaults.',
            // New strings for Phase 2/3
            'searchPlaceholder' => 'Search 300+ councils...',
            'noResults' => 'No councils found. Try a different search.',
            'councilLimitReached' => 'Maximum 5 councils reached. Remove one to add another.',
            'passwordWeak' => 'Weak password',
            'passwordMedium' => 'Medium strength',
            'passwordStrong' => 'Strong password',
            'emailsMatch' => 'Emails match',
            'emailsDontMatch' => 'Emails do not match',
            'fieldRequired' => 'This field is required',
            'trialStarted' => 'Your free trial has started!',
            'genericError' => 'Something went wrong. Please try again.'
        ]
    ]);

    wp_enqueue_style(
        'pmpc-trial-css',
        plugin_dir_url(__FILE__) . 'assets/pmpc-trial.css',
        [],
        '3.0.0'
    );
});

/*--------------------------------------------------------------
  AJAX: VALIDATE USERNAME/EMAIL AVAILABILITY
--------------------------------------------------------------*/

add_action('wp_ajax_nopriv_pmpc_trial_check_user', 'pmpc_trial_ajax_check_user');
add_action('wp_ajax_pmpc_trial_check_user', 'pmpc_trial_ajax_check_user');

function pmpc_trial_ajax_check_user() {
    check_ajax_referer('pmpc_trial_checkout_nonce', 'nonce');
    
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
    if (!pmpc_is_trial_checkout()) return $ok;

    if (empty($_POST['pmpc_councils'])) {
        pmpro_setMessage('Please select at least ' . PMPC_TRIAL_MIN . ' council.', 'pmpro_error');
        return false;
    }

    $selected = array_map('sanitize_text_field', (array) $_POST['pmpc_councils']);
    $count = count($selected);

    if ($count < PMPC_TRIAL_MIN) {
        pmpro_setMessage('Please select at least ' . PMPC_TRIAL_MIN . ' council.', 'pmpro_error');
        return false;
    }
    if ($count > PMPC_TRIAL_MAX) {
        pmpro_setMessage('Maximum ' . PMPC_TRIAL_MAX . ' councils for free trial.', 'pmpro_error');
        return false;
    }

    $_REQUEST['pmpc_councils'] = $selected;
    $_REQUEST['pmpc_calculated_price'] = '0.00';

    return $ok;
});

/*--------------------------------------------------------------
  CORE: Override PMPro checkout level - Force £0
--------------------------------------------------------------*/

add_filter('pmpro_checkout_level', function ($level) {
    if (!pmpc_is_trial_checkout()) return $level;
    
    $level->initial_payment = 0;
    $level->billing_amount = 0;
    $level->trial_amount = 0;
    
    return $level;
}, 20);

/*--------------------------------------------------------------
  SAVE USER DATA AFTER CHECKOUT
--------------------------------------------------------------*/

add_action('pmpro_after_checkout', function ($user_id, $morder) {
    if (!pmpc_is_trial_checkout()) return;

    // Councils — always save (subscription-specific)
    if (!empty($_REQUEST['pmpc_councils'])) {
        $councils = array_map('sanitize_text_field', (array) $_REQUEST['pmpc_councils']);
        update_user_meta($user_id, PMPC_TRIAL_META_KEY, $councils);
    }

    // CHECK: If Settings page was EVER saved, do NOT overwrite business info / template
    $existing_info = get_user_meta($user_id, '_pi_business_info', true);
    $settings_saved = is_array($existing_info) && !empty($existing_info['settings_updated_at']);

    if ($settings_saved) {
        error_log("[PMPC Trial] Settings exist for user #$user_id — skipping business info/template override from checkout");
        // Still save to plugin-specific meta for reference
        if (!empty($_REQUEST['pmpc_default_template'])) {
            update_user_meta($user_id, PMPC_TRIAL_META_TEMPLATE, sanitize_text_field($_REQUEST['pmpc_default_template']));
        }
    } else {
        // No settings saved yet — checkout data becomes primary
        if (!empty($_REQUEST['pmpc_default_template'])) {
            $template = sanitize_text_field($_REQUEST['pmpc_default_template']);
            update_user_meta($user_id, PMPC_TRIAL_META_TEMPLATE, $template);
            
            $business_info = get_user_meta($user_id, '_pi_business_info', true) ?: [];
            $business_info['default_template'] = $template;
            $business_info['source'] = 'checkout';
            update_user_meta($user_id, '_pi_business_info', $business_info);
        }

        // Business info from checkout form
        $checkout_business_info = [];
        $business_fields = ['pmpc_company_name', 'pmpc_business_email', 'pmpc_business_phone', 'pmpc_company_address', 'pmpc_website'];
        
        foreach ($business_fields as $field) {
            if (!empty($_REQUEST[$field])) {
                $checkout_business_info[$field] = sanitize_text_field($_REQUEST[$field]);
            }
        }
        
        if (!empty($checkout_business_info)) {
            update_user_meta($user_id, PMPC_TRIAL_META_BUSINESS, $checkout_business_info);
            
            $business_info = get_user_meta($user_id, '_pi_business_info', true) ?: [];
            
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
            
            $business_info['source'] = 'checkout';
            update_user_meta($user_id, '_pi_business_info', $business_info);
        }
    }

    // Mark as trial user with expiration
    $expiration = date('Y-m-d H:i:s', strtotime('+' . PMPC_TRIAL_DAYS . ' days'));
    update_user_meta($user_id, 'pmpc_trial_expiration', $expiration);
    update_user_meta($user_id, 'pmpc_is_trial_user', 1);

    // Order note
    if (!empty($morder)) {
        $councils = isset($_REQUEST['pmpc_councils']) ? $_REQUEST['pmpc_councils'] : [];
        $morder->notes = 'Free Trial - Councils: ' . wp_json_encode($councils) . ' | Expires: ' . $expiration;
        if (method_exists($morder, 'save')) {
            $morder->save();
        }
    }
}, 10, 2);

/*--------------------------------------------------------------
  TRIAL EXPIRATION CHECK
--------------------------------------------------------------*/

add_action('init', function() {
    if (!is_user_logged_in()) return;
    
    $user_id = get_current_user_id();
    $is_trial = get_user_meta($user_id, 'pmpc_is_trial_user', true);
    
    if (!$is_trial) return;
    
    $expiration = get_user_meta($user_id, 'pmpc_trial_expiration', true);
    
    if ($expiration && strtotime($expiration) < time()) {
        // Trial expired - cancel membership using proper PMPro 3.0+ function
        if (function_exists('pmpro_cancelMembershipLevel')) {
            // Get current level to cancel specifically
            $current_level = pmpro_getMembershipLevelForUser($user_id);
            if ($current_level && !empty($current_level->ID)) {
                pmpro_cancelMembershipLevel($current_level->ID, $user_id, 'inactive');
            } else {
                // Fallback: cancel all levels
                $levels = pmpro_getMembershipLevelsForUser($user_id);
                foreach ($levels as $level) {
                    pmpro_cancelMembershipLevel($level->id, $user_id, 'inactive');
                }
            }
        } elseif (function_exists('pmpro_changeMembershipLevel')) {
            // Legacy fallback for older PMPro versions
            pmpro_changeMembershipLevel(0, $user_id);
        }
        update_user_meta($user_id, 'pmpc_trial_expired', 1);
    }
});

/*--------------------------------------------------------------
  ACCESS RESTRICTION FOR EXPIRED TRIALS
--------------------------------------------------------------*/

add_filter('pmpro_has_membership_access_filter', function ($access, $post_id, $user_id, $levels) {
    if (!$user_id) return $access;
    
    $is_trial = get_user_meta($user_id, 'pmpc_is_trial_user', true);
    $trial_expired = get_user_meta($user_id, 'pmpc_trial_expired', true);
    
    if ($is_trial && $trial_expired) {
        // Skip redirect on upgrade/cancel pages
        $current_page = get_queried_object_id();
        $upgrade_page = get_page_by_path('upgrade');
        $cancel_page = get_page_by_path('cancel-trial');
        $checkout_page = function_exists('pmpro_getOption') ? pmpro_getOption('checkout_page_id') : 0;
        
        $skip_pages = [$checkout_page];
        if ($upgrade_page) $skip_pages[] = $upgrade_page->ID;
        if ($cancel_page) $skip_pages[] = $cancel_page->ID;
        
        if (!is_admin() && !in_array($current_page, $skip_pages)) {
            return false;
        }
    }
    
    return $access;
}, 10, 4);

/*--------------------------------------------------------------
  TRIAL EXPIRED MESSAGE SHORTCODE
--------------------------------------------------------------*/

add_shortcode('pmpc_trial_expired_message', function() {
    if (!is_user_logged_in()) return '';
    
    $trial_expired = get_user_meta(get_current_user_id(), 'pmpc_trial_expired', true);
    
    if (!$trial_expired) return '';
    
    ob_start();
    ?>
    <div class="pmpc-trial-expired-notice">
        <div class="pmpc-expired-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
            </svg>
        </div>
        <h2>Your Free Trial Has Ended</h2>
        <p>Your 2-week free trial has expired. To continue accessing planning applications and using our services, please subscribe to a paid plan.</p>
        <div class="pmpc-expired-actions">
            <a href="/checkout/?level=58" class="pmpc-btn-primary">Subscribe Now</a>
            <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=pmpc_cancel_trial'), 'pmpc_cancel_trial'); ?>" class="pmpc-btn-secondary" onclick="return confirm('Are you sure? This will permanently delete your account and all data.');">Cancel & Delete Account</a>
        </div>
    </div>
    <?php
    return ob_get_clean();
});

/*--------------------------------------------------------------
  CANCEL TRIAL ACTION
--------------------------------------------------------------*/

add_action('admin_post_pmpc_cancel_trial', function() {
    if (!wp_verify_nonce($_GET['_wpnonce'], 'pmpc_cancel_trial')) {
        wp_die('Security check failed');
    }
    
    if (!is_user_logged_in()) {
        wp_redirect(home_url());
        exit;
    }
    
    $user_id = get_current_user_id();
    $is_trial = get_user_meta($user_id, 'pmpc_is_trial_user', true);
    
    if ($is_trial) {
        require_once(ABSPATH . 'wp-admin/includes/user.php');
        wp_delete_user($user_id, true);
    }
    
    wp_redirect(home_url('/?trial_cancelled=1'));
    exit;
});

/*--------------------------------------------------------------
  MULTI-STEP SERVER-SIDE HANDLER
--------------------------------------------------------------*/

add_action('pmpro_checkout_preheader', 'pmpc_trial_multi_step_handler');

function pmpc_trial_multi_step_handler() {
    if (!pmpc_is_trial_checkout()) {
        return;
    }

    if (!session_id()) {
        @session_start();
    }

    // Restore session data on every load
    $data = isset($_SESSION[PMPC_TRIAL_SESSION_KEY]) ? (array) $_SESSION[PMPC_TRIAL_SESSION_KEY] : [];
    if (!empty($data)) {
        $_REQUEST = array_merge($_REQUEST, $data);
    }

    // Handle final checkout submit
    if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
        (isset($_POST['submit-checkout']) || isset($_POST['pmpro_submit']) || isset($_POST['javascriptok']))) {
        
        if (!empty($data)) {
            if (isset($data['councils']))     $_REQUEST['pmpc_councils']         = $data['councils'];
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
        unset($_SESSION[PMPC_TRIAL_SESSION_KEY]);
        return;
    }

    // Handle AJAX step saves
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pmpc_trial_save_step') {
        check_ajax_referer('pmpc_trial_checkout_nonce', 'nonce');

        $step = isset($_POST['step']) ? intval($_POST['step']) : 0;
        if (!$step || $step >= PMPC_TRIAL_TOTAL_STEPS) {
            wp_send_json_error(['message' => 'Invalid step']);
        }

        switch ($step) {
            case 1:
                $selected = array_map('sanitize_text_field', (array) ($_POST['councils'] ?? []));
                $count = count($selected);
                if ($count < PMPC_TRIAL_MIN) {
                    wp_send_json_error(['message' => 'Please select at least ' . PMPC_TRIAL_MIN . ' council.']);
                }
                if ($count > PMPC_TRIAL_MAX) {
                    wp_send_json_error(['message' => 'Maximum ' . PMPC_TRIAL_MAX . ' councils for trial.']);
                }
                $data['councils'] = $selected;
                break;

            case 2:
                $data['template'] = sanitize_text_field($_POST['template'] ?? 'professional');
                break;

            case 3:
                if (!is_user_logged_in()) {
                    $data['username'] = sanitize_user($_POST['username'] ?? '');
                    $data['password'] = $_POST['password'] ?? '';
                    $data['email']    = sanitize_email($_POST['bemail'] ?? '');
                }
                // Business info (optional)
                $business = [];
                $fields = ['pmpc_company_name', 'pmpc_business_email', 'pmpc_business_phone', 'pmpc_company_address', 'pmpc_website'];
                foreach ($fields as $f) {
                    if (isset($_POST[$f])) {
                        $business[$f] = sanitize_text_field($_POST[$f]);
                    }
                }
                $data['business'] = $business;
                break;
        }

        $_SESSION[PMPC_TRIAL_SESSION_KEY] = $data;
        wp_send_json_success(['step' => $step + 1]);
    }
}

/*--------------------------------------------------------------
  FORCE INCLUDE CUSTOM CHECKOUT TEMPLATE
--------------------------------------------------------------*/

add_action('pmpro_checkout_preheader', function() {
    global $pmpro_pages;

    if (!is_page($pmpro_pages['checkout']) || !pmpc_is_trial_checkout()) {
        return;
    }

    // Skip on bfcache / back-button / history navigation
    $is_real_load = (
        $_SERVER['REQUEST_METHOD'] === 'GET' ||
        ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['submit-checkout']))
    );

    if (!$is_real_load) {
        return;
    }

    // Skip on confirmation/review
    if (isset($_GET['confirm']) || isset($_GET['review'])) {
        return;
    }

    $custom_file = get_stylesheet_directory() . '/paid-memberships-pro/pages/checkout-trial.php';

    if (!file_exists($custom_file)) {
        error_log('PMPC Trial: Missing checkout template → ' . $custom_file);
        return;
    }

    // Clear conflicting filters
    remove_all_filters('pmpro_get_template');
    remove_all_filters('pmpro_pages_custom_template_path');

    // ENQUEUE ASSETS HERE (before get_header ensures they load even with early exit)
    // This is critical for logged-out users where wp_enqueue_scripts hasn't fired yet
    
    // Explicitly enqueue jQuery before our script
    wp_enqueue_script('jquery');
    
    wp_enqueue_script(
        'pmpc-trial-js',
        plugin_dir_url(__FILE__) . 'assets/pmpc-trial.js',
        ['jquery'],
        '3.0.0',
        true
    );

    $user_current_template = 'professional';
    if (is_user_logged_in()) {
        $business_info = get_user_meta(get_current_user_id(), '_pi_business_info', true) ?: [];
        $user_current_template = $business_info['default_template'] ?? 'professional';
    }

    $councils = pmpc_trial_get_all_councils();
    $templates = pmpc_trial_get_templates();

    // Detect reduced motion preference server-side
    $is_reduced_motion = false;
    if (isset($_SERVER['HTTP_SEC_CH_PREFERS_REDUCED_MOTION'])) {
        $is_reduced_motion = $_SERVER['HTTP_SEC_CH_PREFERS_REDUCED_MOTION'] === 'reduce';
    }

    wp_localize_script('pmpc-trial-js', 'pmpcTrialConfig', [
        'maxSelection' => PMPC_TRIAL_MAX,
        'minSelection' => PMPC_TRIAL_MIN,
        'totalSteps' => PMPC_TRIAL_TOTAL_STEPS,
        'trialDays' => PMPC_TRIAL_DAYS,
        'councils' => $councils,
        'templates' => $templates,
        'checkoutUrl' => pmpro_url('checkout'),
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'restUrl' => rest_url('pmpc-trial/v1'),
        'restNonce' => wp_create_nonce('wp_rest'),
        'nonce' => wp_create_nonce('pmpc_trial_checkout_nonce'),
        'isLoggedIn' => is_user_logged_in(),
        'userCurrentTemplate' => $user_current_template,
        'isReducedMotion' => $is_reduced_motion,
        'strings' => [
            'selectMinCouncils' => 'Please select at least 1 council to continue.',
            'maxCouncils' => 'Maximum 5 councils during your free trial.',
            'usernameRequired' => 'Please enter a username.',
            'passwordRequired' => 'Please enter a password with at least 8 characters.',
            'passwordMismatch' => 'Passwords do not match.',
            'emailRequired' => 'Please enter a valid email address.',
            'emailMismatch' => 'Email addresses do not match.',
            'processing' => 'Starting your free trial...',
            'continue' => 'Continue',
            'startTrial' => 'Start 2 Week Free Trial',
            'loadingTemplates' => 'Loading templates...',
            'templateLoadError' => 'Unable to load templates. Using defaults.',
            // New strings for Phase 2/3
            'searchPlaceholder' => 'Search 300+ councils...',
            'noResults' => 'No councils found. Try a different search.',
            'councilLimitReached' => 'Maximum 5 councils reached. Remove one to add another.',
            'passwordWeak' => 'Weak password',
            'passwordMedium' => 'Medium strength',
            'passwordStrong' => 'Strong password',
            'emailsMatch' => 'Emails match',
            'emailsDontMatch' => 'Emails do not match',
            'fieldRequired' => 'This field is required',
            'trialStarted' => 'Your free trial has started!',
            'genericError' => 'Something went wrong. Please try again.'
        ]
    ]);

    wp_enqueue_style(
        'pmpc-trial-css',
        plugin_dir_url(__FILE__) . 'assets/pmpc-trial.css',
        [],
        '3.0.0'
    );

    // Output with full theme structure
    ob_start();
    get_header();
    
    // CRITICAL FIX: For logged-out users, always manually output CSS directly
    // This bypasses any WordPress hooks that might be preventing proper enqueue
    if (!is_user_logged_in()) {
        $css_url = plugin_dir_url(__FILE__) . 'assets/pmpc-trial.css';
        echo "\n<!-- PMPC TRIAL: Direct CSS injection for logged-out users -->\n";
        echo '<link rel="stylesheet" id="pmpc-trial-css-direct" href="' . esc_url($css_url) . '?v=3.0.0" type="text/css" media="all" />' . "\n";
    }
    
    include $custom_file;
    get_footer();

    $output = ob_get_clean();
    echo $output;

    exit;
}, 5);

/*--------------------------------------------------------------
  REST API: Get councils list
--------------------------------------------------------------*/

add_action('rest_api_init', function () {
    register_rest_route('pmpc-trial/v1', '/councils', [
        'methods' => 'GET',
        'callback' => function () {
            return new WP_REST_Response(pmpc_trial_get_all_councils(), 200);
        },
        'permission_callback' => '__return_true'
    ]);
});

/*--------------------------------------------------------------
  ADMIN: Trial Users Dashboard Widget
--------------------------------------------------------------*/

add_action('wp_dashboard_setup', function() {
    wp_add_dashboard_widget('pmpc_trial_users', 'Trial Users', function() {
        global $wpdb;
        
        $trial_users = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'pmpc_is_trial_user' AND meta_value = '1'");
        $expired_trials = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'pmpc_trial_expired' AND meta_value = '1'");
        
        echo '<p><strong>Active Trial Users:</strong> ' . intval($trial_users) . '</p>';
        echo '<p><strong>Expired Trials:</strong> ' . intval($expired_trials) . '</p>';
    });
});

?>
