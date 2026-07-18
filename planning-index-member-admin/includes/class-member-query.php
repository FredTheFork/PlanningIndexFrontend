<?php
/**
 * Member Query Class
 * Handles fetching active PMPro members with all relevant metadata.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PIMA_Member_Query {

    private $per_page = 50;

    /**
     * Get membership levels for filter dropdown
     */
    public function get_membership_levels() {
        if (!function_exists('pmpro_getAllLevels')) {
            return [];
        }
        return pmpro_getAllLevels(true, true);
    }

    /**
     * Get total count of active members (for pagination)
     */
    public function get_total_count($search = '', $level_id = 0, $product_type = '') {
        global $wpdb;

        $sql = "SELECT COUNT(DISTINCT mu.user_id) 
                FROM {$wpdb->pmpro_memberships_users} mu
                INNER JOIN {$wpdb->users} u ON mu.user_id = u.ID
                WHERE mu.status = 'active'";

        if ($search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $sql .= $wpdb->prepare(" AND (u.user_login LIKE %s OR u.display_name LIKE %s OR u.user_email LIKE %s)", $like, $like, $like);
        }

        if ($level_id > 0) {
            $sql .= $wpdb->prepare(" AND mu.membership_id = %d", $level_id);
        }

        $count = (int) $wpdb->get_var($sql);

        // If product type filter is applied, we can't easily count in SQL — return rough count
        if ($product_type) {
            // We will filter after the fact, so just return the base count
            // (may be slightly inaccurate but avoids heavy usermeta joins)
        }

        return $count;
    }

    /**
     * Get active members with all metadata
     */
    public function get_members($per_page = null, $offset = null) {
        global $wpdb;

        $page   = max(1, intval($_GET['pima_page'] ?? 1));
        $limit  = $per_page !== null ? intval($per_page) : $this->per_page;
        $offset = $offset !== null ? intval($offset) : (($page - 1) * $limit);

        $search       = sanitize_text_field($_GET['pima_search'] ?? '');
        $filter_level = intval($_GET['pima_level'] ?? 0);
        $filter_type  = sanitize_text_field($_GET['pima_type'] ?? '');

        $sql = "SELECT mu.user_id, mu.membership_id, mu.startdate, mu.enddate, mu.status as membership_status,
                       u.user_login, u.display_name, u.user_email
                FROM {$wpdb->pmpro_memberships_users} mu
                INNER JOIN {$wpdb->users} u ON mu.user_id = u.ID
                WHERE mu.status = 'active'";

        if ($search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $sql .= $wpdb->prepare(" AND (u.user_login LIKE %s OR u.display_name LIKE %s OR u.user_email LIKE %s)", $like, $like, $like);
        }

        if ($filter_level > 0) {
            $sql .= $wpdb->prepare(" AND mu.membership_id = %d", $filter_level);
        }

        $sql .= " ORDER BY mu.startdate DESC LIMIT %d OFFSET %d";
        $sql = $wpdb->prepare($sql, $limit, $offset);

        $results = $wpdb->get_results($sql);

        if (empty($results)) {
            return [];
        }

        $user_ids = wp_list_pluck($results, 'user_id');
        $meta_map = $this->bulk_get_user_meta($user_ids);
        $levels   = $this->get_membership_levels();

        $members = [];
        foreach ($results as $row) {
            $meta = $meta_map[$row->user_id] ?? [];
            $member = $this->build_member_object($row, $meta, $levels);

            if ($filter_type && $member->product_type !== $filter_type) {
                continue;
            }

            $members[] = $member;
        }

        return $members;
    }

    /**
     * Bulk fetch usermeta for multiple users
     */
    private function bulk_get_user_meta($user_ids) {
        global $wpdb;

        if (empty($user_ids)) {
            return [];
        }

        $ids = implode(',', array_map('intval', $user_ids));
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
        $keys_in = implode("','", array_map('esc_sql', $meta_keys));

        $sql = "SELECT user_id, meta_key, meta_value 
                FROM {$wpdb->usermeta} 
                WHERE user_id IN ({$ids}) 
                AND meta_key IN ('{$keys_in}')";

        $rows = $wpdb->get_results($sql);
        $map  = [];

        foreach ($rows as $row) {
            if (!isset($map[$row->user_id])) {
                $map[$row->user_id] = [];
            }
            $value = maybe_unserialize($row->meta_value);
            $map[$row->user_id][$row->meta_key] = $value;
        }

        return $map;
    }

    /**
     * Build a member object with all relevant data
     */
    private function build_member_object($row, $meta, $levels) {
        $level_name = isset($levels[$row->membership_id]) ? $levels[$row->membership_id]->name : 'Level ' . $row->membership_id;

        // Detect product type
        $product_type = 'Unknown';
        $region_bundle = $meta['pmrb_region_bundle'] ?? '';

        if ($region_bundle === 'Enterprise Access') {
            $product_type = 'Enterprise';
        } elseif (!empty($region_bundle)) {
            $product_type = 'Regional Bundle';
        } elseif ($row->membership_id == 63) {
            $product_type = 'Trial';
        } elseif (!empty($meta['pmpc_selected_councils'])) {
            $product_type = 'Per-Council';
        }

        // Councils
        $councils_selected = $meta['pmpc_selected_councils'] ?? [];
        $councils_allowed  = $meta['pmrb_allowed_councils'] ?? $councils_selected;
        if (!is_array($councils_selected)) {
            $councils_selected = [];
        }
        if (!is_array($councils_allowed)) {
            $councils_allowed = [];
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

        // Business info (merge _pi_business_info with plugin-specific)
        $business_info = [];
        if (!empty($meta['_pi_business_info']) && is_array($meta['_pi_business_info'])) {
            $business_info = $meta['_pi_business_info'];
        }

        // Fallback to plugin-specific business info
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

        $member = new stdClass();
        $member->user_id            = $row->user_id;
        $member->user_login         = $row->user_login;
        $member->display_name       = $row->display_name;
        $member->user_email         = $row->user_email;
        $member->membership_id      = $row->membership_id;
        $member->level_name         = $level_name;
        $member->membership_status  = $row->membership_status;
        $member->startdate          = $row->startdate;
        $member->enddate            = $row->enddate;
        $member->product_type       = $product_type;
        $member->region_bundle      = $region_bundle;
        $member->councils_selected  = $councils_selected;
        $member->councils_allowed   = $councils_allowed;
        $member->template           = $template;
        $member->price              = $price;
        $member->business_info      = $business_info;
        $member->raw_meta           = $meta;

        return $member;
    }
}
