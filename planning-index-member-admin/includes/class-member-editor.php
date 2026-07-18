<?php
/**
 * Member Editor Class
 * Handles fetching and saving individual member data.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PIMA_Member_Editor {

    /**
     * Get all UK councils for the multiselect
     */
    public function get_all_councils() {
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
     * Get templates for dropdown
     */
    public function get_templates() {
        if (function_exists('pmpc_get_templates')) {
            $tpls = pmpc_get_templates();
            $out = [];
            foreach ($tpls as $key => $info) {
                $out[$key] = $info['name'] ?? $key;
            }
            return $out;
        }
        return [
            'professional' => 'Professional',
            'modern'       => 'Modern',
            'classic'      => 'Classic',
            'minimal'      => 'Minimal',
        ];
    }

    /**
     * Get member data for editing
     */
    public function get_member_data($user_id) {
        global $wpdb;

        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        // Get active membership
        $membership = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->pmpro_memberships_users} 
             WHERE user_id = %d AND status = 'active' 
             ORDER BY id DESC LIMIT 1",
            $user_id
        ));

        $levels = function_exists('pmpro_getAllLevels') ? pmpro_getAllLevels(true, true) : [];
        $level_name = isset($levels[$membership->membership_id]) ? $levels[$membership->membership_id]->name : 'Level ' . $membership->membership_id;

        // Get all relevant meta
        $meta_keys = [
            'pmpc_selected_councils',
            'pmrb_allowed_councils',
            'pmrb_region_bundle',
            'pmpc_calculated_price',
            'pmrb_calculated_price',
            'pmpc_default_template',
            'pmrb_default_template',
            'pmpe_default_template',
            'pmpc_business_info',
            'pmrb_business_info',
            'pmpe_business_info',
            '_pi_business_info',
        ];

        $meta = [];
        foreach ($meta_keys as $key) {
            $meta[$key] = get_user_meta($user_id, $key, true);
        }

        // Detect product type
        $product_type = 'Unknown';
        $region_bundle = $meta['pmrb_region_bundle'] ?? '';
        if ($region_bundle === 'Enterprise Access') {
            $product_type = 'Enterprise';
        } elseif (!empty($region_bundle)) {
            $product_type = 'Regional Bundle';
        } elseif ($membership && $membership->membership_id == 63) {
            $product_type = 'Trial';
        } elseif (!empty($meta['pmpc_selected_councils'])) {
            $product_type = 'Per-Council';
        }

        // Template
        $template = '';
        if (!empty($meta['pmpc_default_template'])) {
            $template = $meta['pmpc_default_template'];
        } elseif (!empty($meta['pmrb_default_template'])) {
            $template = $meta['pmrb_default_template'];
        } elseif (!empty($meta['pmpe_default_template'])) {
            $template = $meta['pmpe_default_template'];
        }
        if (empty($template) && !empty($meta['_pi_business_info']['default_template'])) {
            $template = $meta['_pi_business_info']['default_template'];
        }

        // Price
        $price = '';
        if (!empty($meta['pmpc_calculated_price'])) {
            $price = $meta['pmpc_calculated_price'];
        } elseif (!empty($meta['pmrb_calculated_price'])) {
            $price = $meta['pmrb_calculated_price'];
        }

        // Business info
        $business_info = [];
        if (!empty($meta['_pi_business_info']) && is_array($meta['_pi_business_info'])) {
            $business_info = $meta['_pi_business_info'];
        }
        $plugin_business = [];
        if (!empty($meta['pmpc_business_info']) && is_array($meta['pmpc_business_info'])) {
            $plugin_business = $meta['pmpc_business_info'];
        } elseif (!empty($meta['pmrb_business_info']) && is_array($meta['pmrb_business_info'])) {
            $plugin_business = $meta['pmrb_business_info'];
        } elseif (!empty($meta['pmpe_business_info']) && is_array($meta['pmpe_business_info'])) {
            $plugin_business = $meta['pmpe_business_info'];
        }
        $field_map = [
            'pmpc_company_name'  => 'company_name',
            'pmpc_business_email' => 'email',
            'pmpc_business_phone' => 'phone',
            'pmpc_company_address' => 'company_address',
            'pmpc_website'        => 'website',
            'pmpc_vat_number'     => 'vat_number',
            'pmrb_company_name'   => 'company_name',
            'pmrb_business_email' => 'email',
            'pmrb_business_phone' => 'phone',
            'pmrb_company_address' => 'company_address',
            'pmrb_website'        => 'website',
            'pmrb_vat_number'     => 'vat_number',
            'pmpe_company_name'   => 'company_name',
            'pmpe_business_email' => 'email',
            'pmpe_business_phone' => 'phone',
            'pmpe_company_address' => 'company_address',
            'pmpe_website'        => 'website',
            'pmpe_vat_number'     => 'vat_number',
        ];
        foreach ($plugin_business as $key => $val) {
            if (isset($field_map[$key]) && empty($business_info[$field_map[$key]])) {
                $business_info[$field_map[$key]] = $val;
            } elseif (!isset($field_map[$key]) && empty($business_info[$key])) {
                $business_info[$key] = $val;
            }
        }

        return [
            'user_id'           => $user_id,
            'user_login'        => $user->user_login,
            'display_name'      => $user->display_name,
            'user_email'        => $user->user_email,
            'membership_id'     => $membership->membership_id ?? 0,
            'level_name'        => $level_name,
            'membership_status' => $membership->status ?? '',
            'startdate'         => $membership->startdate ?? '',
            'enddate'           => $membership->enddate ?? '',
            'product_type'      => $product_type,
            'region_bundle'     => $region_bundle,
            'councils_selected' => (array) ($meta['pmpc_selected_councils'] ?? []),
            'councils_allowed'  => (array) ($meta['pmrb_allowed_councils'] ?? []),
            'template'          => $template,
            'price'             => $price,
            'business_info'     => $business_info,
            'all_councils'      => $this->get_all_councils(),
            'all_templates'     => $this->get_templates(),
        ];
    }

    /**
     * Save member data from admin edit form
     */
    public function save_member_data($user_id, $data) {
        $user = get_userdata($user_id);
        if (!$user) {
            return new WP_Error('invalid_user', 'User not found.');
        }

        // Detect product type from existing meta
        $region_bundle = get_user_meta($user_id, 'pmrb_region_bundle', true);
        $product_type  = 'Unknown';
        if ($region_bundle === 'Enterprise Access') {
            $product_type = 'Enterprise';
        } elseif (!empty($region_bundle)) {
            $product_type = 'Regional Bundle';
        } elseif (get_user_meta($user_id, 'pmpc_selected_councils', true)) {
            $product_type = 'Per-Council';
        }

        // Update councils
        if (isset($data['councils'])) {
            $councils = array_map('sanitize_text_field', (array) $data['councils']);
            update_user_meta($user_id, 'pmpc_selected_councils', $councils);

            if (in_array($product_type, ['Regional Bundle', 'Enterprise'], true)) {
                update_user_meta($user_id, 'pmrb_allowed_councils', $councils);
            }
        }

        // Update template
        if (isset($data['template'])) {
            $template = sanitize_text_field($data['template']);

            if ($product_type === 'Per-Council' || $product_type === 'Trial') {
                update_user_meta($user_id, 'pmpc_default_template', $template);
            } elseif ($product_type === 'Regional Bundle') {
                update_user_meta($user_id, 'pmrb_default_template', $template);
            } elseif ($product_type === 'Enterprise') {
                update_user_meta($user_id, 'pmpe_default_template', $template);
            }

            // Also update unified business info
            $business_info = get_user_meta($user_id, '_pi_business_info', true);
            if (!is_array($business_info)) {
                $business_info = [];
            }
            $business_info['default_template'] = $template;
            $business_info['settings_updated_at'] = current_time('mysql');
            update_user_meta($user_id, '_pi_business_info', $business_info);
        }

        // Update price
        if (isset($data['price'])) {
            $price = floatval(sanitize_text_field($data['price']));
            $formatted = number_format($price, 2, '.', '');

            if ($product_type === 'Per-Council' || $product_type === 'Trial') {
                update_user_meta($user_id, 'pmpc_calculated_price', $formatted);
            } elseif ($product_type === 'Regional Bundle' || $product_type === 'Enterprise') {
                update_user_meta($user_id, 'pmrb_calculated_price', $formatted);
            }
        }

        // Update business info
        if (isset($data['business_info']) && is_array($data['business_info'])) {
            $business_info = get_user_meta($user_id, '_pi_business_info', true);
            if (!is_array($business_info)) {
                $business_info = [];
            }

            $fields = ['company_name', 'email', 'phone', 'company_address', 'website', 'vat_number'];
            foreach ($fields as $field) {
                if (isset($data['business_info'][$field])) {
                    $business_info[$field] = sanitize_text_field($data['business_info'][$field]);
                }
            }
            $business_info['settings_updated_at'] = current_time('mysql');
            update_user_meta($user_id, '_pi_business_info', $business_info);

            // Also update plugin-specific business meta
            $plugin_meta = [];
            $prefix = '';
            if ($product_type === 'Per-Council' || $product_type === 'Trial') {
                $prefix = 'pmpc_';
            } elseif ($product_type === 'Regional Bundle' || $product_type === 'Enterprise') {
                $prefix = 'pmrb_';
                if ($product_type === 'Enterprise') {
                    $prefix = 'pmpe_';
                }
            }

            if ($prefix) {
                $plugin_meta[$prefix . 'company_name']   = sanitize_text_field($data['business_info']['company_name'] ?? '');
                $plugin_meta[$prefix . 'business_email'] = sanitize_text_field($data['business_info']['email'] ?? '');
                $plugin_meta[$prefix . 'business_phone'] = sanitize_text_field($data['business_info']['phone'] ?? '');
                $plugin_meta[$prefix . 'company_address']  = sanitize_text_field($data['business_info']['company_address'] ?? '');
                $plugin_meta[$prefix . 'website']         = sanitize_text_field($data['business_info']['website'] ?? '');
                $plugin_meta[$prefix . 'vat_number']      = sanitize_text_field($data['business_info']['vat_number'] ?? '');

                $meta_key = ($product_type === 'Enterprise') ? 'pmpe_business_info' : (($product_type === 'Regional Bundle') ? 'pmrb_business_info' : 'pmpc_business_info');
                update_user_meta($user_id, $meta_key, $plugin_meta);
            }
        }

        // Update membership status
        if (isset($data['membership_status'])) {
            $new_status = sanitize_text_field($data['membership_status']);
            $valid_statuses = ['active', 'cancelled', 'expired', 'admin_cancelled', 'admin_changed'];

            if (in_array($new_status, $valid_statuses, true)) {
                global $wpdb;
                $wpdb->update(
                    $wpdb->pmpro_memberships_users,
                    ['status' => $new_status],
                    ['user_id' => $user_id, 'status' => 'active'],
                    ['%s'],
                    ['%d', '%s']
                );
            }
        }

        return true;
    }
}
