<?php
/**
 * Plugin Name: Planning Index Member Admin
 * Description: Unified admin dashboard for viewing and editing all active PMPro member data across Planning Index membership products.
 * Version: 1.0.0
 * Author: Planning Index
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PIMA_VERSION', '1.0.0');
define('PIMA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PIMA_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once PIMA_PLUGIN_DIR . 'includes/class-member-query.php';
require_once PIMA_PLUGIN_DIR . 'includes/class-member-editor.php';

/**
 * Main plugin class
 */
class Planning_Index_Member_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_pima_get_member', [$this, 'ajax_get_member']);
        add_action('wp_ajax_pima_save_member', [$this, 'ajax_save_member']);
        add_action('wp_ajax_pima_export_csv', [$this, 'ajax_export_csv']);
    }

    /**
     * Register top-level admin menu
     */
    public function register_admin_menu() {
        add_menu_page(
            'PI Members',
            'PI Members',
            'manage_options',
            'planning-index-members',
            [$this, 'render_admin_page'],
            'dashicons-groups',
            30
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_planning-index-members') {
            return;
        }

        wp_enqueue_style('pima-admin-css', PIMA_PLUGIN_URL . 'assets/admin.css', [], PIMA_VERSION);
        wp_enqueue_script('pima-admin-js', PIMA_PLUGIN_URL . 'assets/admin.js', ['jquery'], PIMA_VERSION, true);

        wp_localize_script('pima-admin-js', 'pimaAjax', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('pima_admin_nonce'),
        ]);
    }

    /**
     * Render the admin page
     */
    public function render_admin_page() {
        $search       = sanitize_text_field($_GET['pima_search'] ?? '');
        $filter_level = intval($_GET['pima_level'] ?? 0);
        $filter_type  = sanitize_text_field($_GET['pima_type'] ?? '');

        $query   = new PIMA_Member_Query();
        $members = $query->get_members();
        $levels  = $query->get_membership_levels();
        $total   = $query->get_total_count($search, $filter_level, $filter_type);

        include PIMA_PLUGIN_DIR . 'views/admin-page.php';
    }

    /**
     * AJAX: Get single member data for editing
     */
    public function ajax_get_member() {
        check_ajax_referer('pima_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied.');
        }

        $user_id = intval($_POST['user_id'] ?? 0);
        if (!$user_id) {
            wp_send_json_error('Invalid user ID.');
        }

        $editor = new PIMA_Member_Editor();
        $data   = $editor->get_member_data($user_id);

        if (!$data) {
            wp_send_json_error('Member not found.');
        }

        wp_send_json_success($data);
    }

    /**
     * AJAX: Save member data
     */
    public function ajax_save_member() {
        check_ajax_referer('pima_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied.');
        }

        $user_id = intval($_POST['user_id'] ?? 0);
        if (!$user_id) {
            wp_send_json_error('Invalid user ID.');
        }

        $editor = new PIMA_Member_Editor();
        $result = $editor->save_member_data($user_id, $_POST);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success('Member updated successfully.');
    }

    /**
     * AJAX: Export members to CSV
     */
    public function ajax_export_csv() {
        check_ajax_referer('pima_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied.');
        }

        $query = new PIMA_Member_Query();
        // Get ALL members for export (ignore pagination)
        $members = $query->get_members(9999, 0);

        $filename = 'pi-members-export-' . date('Y-m-d-H-i-s') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');

        // BOM for Excel
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($output, [
            'User ID',
            'Username',
            'Display Name',
            'Email',
            'Membership Level',
            'Status',
            'Start Date',
            'End Date',
            'Product Type',
            'Region Bundle',
            'Councils Selected',
            'Councils Allowed',
            'Template',
            'Price',
            'Company Name',
            'Business Email',
            'Business Phone',
            'Company Address',
            'Website',
            'VAT Number',
        ]);

        foreach ($members as $m) {
            fputcsv($output, [
                $m->user_id,
                $m->user_login,
                $m->display_name,
                $m->user_email,
                $m->level_name,
                $m->membership_status,
                $m->startdate,
                $m->enddate,
                $m->product_type,
                $m->region_bundle,
                is_array($m->councils_selected) ? implode(', ', $m->councils_selected) : $m->councils_selected,
                is_array($m->councils_allowed) ? implode(', ', $m->councils_allowed) : $m->councils_allowed,
                $m->template,
                $m->price,
                $m->business_info['company_name'] ?? '',
                $m->business_info['email'] ?? '',
                $m->business_info['phone'] ?? '',
                $m->business_info['company_address'] ?? '',
                $m->business_info['website'] ?? '',
                $m->business_info['vat_number'] ?? '',
            ]);
        }

        fclose($output);
        exit;
    }
}

new Planning_Index_Member_Admin();
