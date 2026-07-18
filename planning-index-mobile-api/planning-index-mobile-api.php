<?php
/**
 * Plugin Name: Planning Index Mobile API
 * Description: Mobile-focused REST API layer for PlanningIndex (auth, dashboard, and planning search wrappers).
 * Version: 0.1.0
 * Author: Planning Index
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Generate a signed mobile token for a user.
 * Simple HMAC-signed payload: base64(json) . '.' . signature.
 */
function pi_mobile_generate_token($user_id, $ttl = WEEK_IN_SECONDS) {
    $issued_at = time();
    $exp       = $issued_at + (int) $ttl;

    $payload = array(
        'sub' => (int) $user_id,
        'iat' => $issued_at,
        'exp' => $exp,
    );

    $json = wp_json_encode($payload);
    if (!$json) {
        return '';
    }

    $body = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    $key  = wp_salt('auth');
    $sig  = hash_hmac('sha256', $body, $key);

    return $body . '.' . $sig;
}

/**
 * Validate a mobile token and return the associated user ID or 0.
 */
function pi_mobile_validate_token($token) {
    if (empty($token) || strpos($token, '.') === false) {
        return 0;
    }

    list($body, $sig) = explode('.', $token, 2);
    $key        = wp_salt('auth');
    $calc_sig   = hash_hmac('sha256', $body, $key);

    if (!hash_equals($calc_sig, $sig)) {
        return 0;
    }

    $json = base64_decode(strtr($body, '-_', '+/'));
    if (!$json) {
        return 0;
    }

    $payload = json_decode($json, true);
    if (!is_array($payload)) {
        return 0;
    }

    $exp = isset($payload['exp']) ? (int) $payload['exp'] : 0;
    if ($exp && $exp < time()) {
        return 0;
    }

    $user_id = isset($payload['sub']) ? (int) $payload['sub'] : 0;
    if ($user_id <= 0 || !get_user_by('id', $user_id)) {
        return 0;
    }

    return $user_id;
}

/**
 * Attempt to authenticate a REST request via Authorization: Bearer <token>.
 * If valid, sets current user and returns true.
 */
function pi_mobile_auth_from_header(WP_REST_Request $request) {
    $auth = $request->get_header('authorization');
    if (!$auth) {
        $auth = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    }

    if (!$auth || stripos($auth, 'bearer ') !== 0) {
        return false;
    }

    $token = trim(substr($auth, 7));
    $user_id = pi_mobile_validate_token($token);
    if (!$user_id) {
        return false;
    }

    wp_set_current_user($user_id);
    return true;
}

/**
 * Build a compact user payload for the mobile app.
 */
function pi_mobile_build_user_payload(WP_User $user) {
    $data = array(
        'id'           => (int) $user->ID,
        'email'        => $user->user_email,
        'display_name' => $user->display_name ?: $user->user_login,
    );

    // Membership information via PMPro if available.
    if (function_exists('pmpro_getMembershipLevelsForUser')) {
        $levels = pmpro_getMembershipLevelsForUser($user->ID) ?: array();
        $data['memberships'] = array_map(
            function ($lvl) {
                return array(
                    'id'   => (int) $lvl->id,
                    'name' => $lvl->name,
                );
            },
            $levels
        );
    } else {
        $data['memberships'] = array();
    }

    // Councils from per‑council/region/enterprise meta.
    $councils = get_user_meta($user->ID, 'pmpc_selected_councils', true);
    if (!is_array($councils)) {
        $councils = array();
    }
    $data['councils'] = array_values(array_unique(array_map('strval', $councils)));

    // Business profile + default proposal template.
    $business = get_user_meta($user->ID, '_pi_business_info', true);
    if (!is_array($business)) {
        $business = array();
    }
    $data['business'] = array(
        'company_name'    => $business['company_name'] ?? '',
        'company_address' => $business['company_address'] ?? '',
        'phone'           => $business['phone'] ?? '',
        'email'           => $business['email'] ?? '',
        'website'         => $business['website'] ?? '',
        'default_template'=> $business['default_template'] ?? 'basic',
    );

    // Simple flags for plan type based on meta used by pmpro-enterprise / regional / per-council / trial.
    $data['flags'] = array(
        'is_trial_user'      => (bool) get_user_meta($user->ID, 'pmpc_is_trial_user', true),
        'trial_expired'      => (bool) get_user_meta($user->ID, 'pmpc_trial_expired', true),
        'has_enterprise'     => function_exists('pmpro_hasMembershipLevel')
            ? pmpro_hasMembershipLevel(get_option('pmpe_enterprise_checkout_level_id'), $user->ID)
            : false,
    );

    return $data;
}

/**
 * Allow Bearer token auth for REST API requests.
 * Runs before permission checks so pi/v1 endpoints (workspace, tasks, etc.) work with mobile token.
 */
add_filter('rest_pre_dispatch', function ($result, $server, $request) {
    if (!is_user_logged_in()) {
        pi_mobile_auth_from_header($request);
    }
    return $result;
}, 5, 3);

add_action('rest_api_init', function () {
    $namespace = 'pi-mobile/v1';

    // AUTH: /pi-mobile/v1/auth/login
    register_rest_route($namespace, '/auth/login', array(
        'methods'  => 'POST',
        'callback' => function (WP_REST_Request $request) {
            $username = $request->get_param('username');
            $password = $request->get_param('password');

            if (!$username || !$password) {
                return new WP_Error('pi_mobile_missing_creds', 'Username/email and password are required.', array('status' => 400));
            }

            // Allow email-based login as in invoices plugin.
            if (is_email($username)) {
                $user = get_user_by('email', $username);
                if ($user && wp_check_password($password, $user->user_pass, $user->ID)) {
                    wp_set_current_user($user->ID);
                } else {
                    return new WP_Error('pi_mobile_invalid_login', 'Invalid credentials.', array('status' => 401));
                }
            } else {
                $user = wp_authenticate($username, $password);
                if (is_wp_error($user)) {
                    return new WP_Error('pi_mobile_invalid_login', 'Invalid credentials.', array('status' => 401));
                }
                wp_set_current_user($user->ID);
            }

            $user = wp_get_current_user();
            $token = pi_mobile_generate_token($user->ID);

            return rest_ensure_response(array(
                'token' => $token,
                'user'  => pi_mobile_build_user_payload($user),
            ));
        },
        'permission_callback' => '__return_true',
    ));

    // AUTH: /pi-mobile/v1/auth/me
    register_rest_route($namespace, '/auth/me', array(
        'methods'  => 'GET',
        'callback' => function (WP_REST_Request $request) {
            if (!is_user_logged_in()) {
                // Try header token.
                if (!pi_mobile_auth_from_header($request)) {
                    return new WP_Error('pi_mobile_unauthorized', 'Authentication required.', array('status' => 401));
                }
            }

            $user = wp_get_current_user();
            if (!$user || !$user->ID) {
                return new WP_Error('pi_mobile_unauthorized', 'Authentication required.', array('status' => 401));
            }

            return rest_ensure_response(pi_mobile_build_user_payload($user));
        },
        'permission_callback' => '__return_true',
    ));

    // Optional: lightweight refresh endpoint to mint a new token if the old one is still valid.
    register_rest_route($namespace, '/auth/refresh', array(
        'methods'  => 'POST',
        'callback' => function (WP_REST_Request $request) {
            if (!pi_mobile_auth_from_header($request)) {
                return new WP_Error('pi_mobile_unauthorized', 'Authentication required.', array('status' => 401));
            }

            $user = wp_get_current_user();
            if (!$user || !$user->ID) {
                return new WP_Error('pi_mobile_unauthorized', 'Authentication required.', array('status' => 401));
            }

            $token = pi_mobile_generate_token($user->ID);
            return rest_ensure_response(array(
                'token' => $token,
                'user'  => pi_mobile_build_user_payload($user),
            ));
        },
        'permission_callback' => '__return_true',
    ));

    // Planning search wrapper: /pi/v1/planning/search (mobile can call this or wp/v2 directly)
    register_rest_route('pi/v1', '/planning/search', array(
        'methods'  => 'GET',
        'callback' => function (WP_REST_Request $request) {
            // Ensure user is authenticated via cookie or token.
            if (!is_user_logged_in() && !pi_mobile_auth_from_header($request)) {
                return new WP_Error('pi_mobile_unauthorized', 'Authentication required.', array('status' => 401));
            }

            // Proxy to core WP REST posts controller for planning_app so all existing filters apply
            // (planning-index-membership + planning-index-frontend rest filters).
            if (!class_exists('WP_REST_Posts_Controller')) {
                return new WP_Error('pi_mobile_missing_wp', 'Posts controller not available.', array('status' => 500));
            }

            $controller = new WP_REST_Posts_Controller('planning_app');

            // Map commonly-used query params through unchanged.
            $mapped = new WP_REST_Request('GET', '/wp/v2/planning_app');
            foreach (array('search', 'page', 'per_page', 'date_from', 'date_to') as $key) {
                $val = $request->get_param($key);
                if ($val !== null) {
                    $mapped->set_param($key, $val);
                }
            }

            // Pass through taxonomy filters if provided (authority, app_category).
            foreach (array('authority', 'app_category') as $tax_param) {
                $val = $request->get_param($tax_param);
                if ($val !== null) {
                    $mapped->set_param($tax_param, $val);
                }
            }

            return $controller->get_items($mapped);
        },
        'permission_callback' => '__return_true',
    ));

    // Mobile dashboard summary: /pi-mobile/v1/dashboard/summary
    register_rest_route($namespace, '/dashboard/summary', array(
        'methods'  => 'GET',
        'callback' => function (WP_REST_Request $request) {
            if (!is_user_logged_in() && !pi_mobile_auth_from_header($request)) {
                return new WP_Error('pi_mobile_unauthorized', 'Authentication required.', array('status' => 401));
            }

            $user_id = get_current_user_id();

            // Pipeline summary via existing workspace utilities.
            $pipeline = array(
                'new_lead'      => 0,
                'proposal_sent' => 0,
                'contacted'     => 0,
                'negotiation'   => 0,
                'won'           => 0,
            );

            if (function_exists('pi_get_user_workspace')) {
                $workspace = pi_get_user_workspace($user_id);
                foreach ($workspace as $stage => $items) {
                    if (isset($pipeline[$stage]) && is_array($items)) {
                        $pipeline[$stage] = count($items);
                    }
                }
            }

            // Tasks summary from _pi_tasks meta.
            $tasks = get_user_meta($user_id, '_pi_tasks', true) ?: array();
            $now   = current_time('timestamp');
            $task_summary = array(
                'total'    => 0,
                'pending'  => 0,
                'completed'=> 0,
                'overdue'  => 0,
            );
            foreach ((array) $tasks as $t) {
                $task_summary['total']++;
                $completed = !empty($t['completed']);
                if ($completed) {
                    $task_summary['completed']++;
                } else {
                    $task_summary['pending']++;
                    if (!empty($t['due']) && strtotime($t['due']) < $now) {
                        $task_summary['overdue']++;
                    }
                }
            }

            // Recently added planning applications in allowed councils (last 3 days).
            $recent_apps = array();

            $councils = get_user_meta($user_id, 'pmpc_selected_councils', true);
            if (!is_array($councils)) {
                $councils = array();
            }

            if (!empty($councils)) {
                $term_ids = array();
                foreach ($councils as $name) {
                    $term = get_term_by('name', $name, 'authority');
                    if ($term) {
                        $term_ids[] = (int) $term->term_id;
                    }
                }

                $date_from = date('Y-m-d', strtotime('-3 days', $now));

                $q_args = array(
                    'post_type'      => 'planning_app',
                    'posts_per_page' => 10,
                    'post_status'    => 'publish',
                    'orderby'        => 'date',
                    'order'          => 'DESC',
                    'tax_query'      => array(),
                    'meta_query'     => array(
                        array(
                            'key'     => 'date_received',
                            'value'   => $date_from,
                            'compare' => '>=',
                            'type'    => 'DATE',
                        ),
                    ),
                );

                if (!empty($term_ids)) {
                    $q_args['tax_query'][] = array(
                        'taxonomy' => 'authority',
                        'field'    => 'term_id',
                        'terms'    => $term_ids,
                        'operator' => 'IN',
                    );
                }

                $query = new WP_Query($q_args);
                if ($query->have_posts()) {
                    foreach ($query->posts as $post) {
                        if (function_exists('pi_format_app_data')) {
                            $recent_apps[] = pi_format_app_data($post);
                        } else {
                            $recent_apps[] = array(
                                'id'      => $post->ID,
                                'title'   => $post->post_title,
                                'content' => $post->post_content,
                            );
                        }
                    }
                }
                wp_reset_postdata();
            }

            return rest_ensure_response(array(
                'pipeline'     => $pipeline,
                'tasks'        => $task_summary,
                'recent_apps'  => $recent_apps,
            ));
        },
        'permission_callback' => '__return_true',
    ));
});

