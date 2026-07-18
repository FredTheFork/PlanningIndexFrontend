<?php
/**
 * Dedicated REST endpoint: GET /wp-json/pi/v1/apps
 *
 * Replaces wp/v2/planning_app to avoid:
 *  - SiteGround Optimizer caching the WP core REST endpoint
 *  - Double tax_query conflicts between rest-filters.php and rest-restrict.php
 *  - The frontend JS version being silently minified at an old version
 *
 * Query params:
 *   page        (int)    page number, default 1
 *   per_page    (int)    results per page, default 40, max 100
 *   search      (string) keyword search across title + content + address meta
 *   authority   (string) comma-separated authority term IDs to filter by
 *   app_category(string) app_category term ID to filter by
 *   date_from   (string) YYYY-MM-DD – filter by date_received >= this
 *   date_to     (string) YYYY-MM-DD – filter by date_received <= this
 *
 * Response headers:
 *   X-WP-Total        total matching posts
 *   X-WP-TotalPages   total pages
 *   Cache-Control     no-store (prevents SiteGround REST caching)
 */

add_action('rest_api_init', function () {
    register_rest_route('pi/v1', '/apps', [
        'methods'             => 'GET',
        'callback'            => 'pi_apps_rest_callback',
        'permission_callback' => '__return_true', // Auth checked inside callback
    ]);
});

function pi_apps_rest_callback(WP_REST_Request $request) {
    // Prevent SiteGround (and any other proxy) from caching this endpoint
    nocache_headers();

    $user    = wp_get_current_user();
    $user_id = $user ? $user->ID : 0;
    $is_admin = $user_id && current_user_can('manage_options');

    // ── AUTH CHECK ──────────────────────────────────────────────────────────
    if (!$user_id) {
        return new WP_REST_Response([], 200, [
            'X-WP-Total'      => '0',
            'X-WP-TotalPages' => '0',
            'Cache-Control'   => 'no-store, no-cache, must-revalidate',
        ]);
    }

    // ── ALLOWED AUTHORITIES ─────────────────────────────────────────────────
    // Admins see everything. Members are scoped to pmpc_selected_councils.
    $allowed_term_ids = [];
    $restrict_by_authority = false;

    if (!$is_admin) {
        $selected_councils = get_user_meta($user_id, 'pmpc_selected_councils', true);

        if (!empty($selected_councils) && is_array($selected_councils)) {
            // Map council names → term IDs
            foreach ($selected_councils as $name) {
                $term = get_term_by('name', trim($name), 'authority');
                if ($term && !is_wp_error($term)) {
                    $allowed_term_ids[] = (int) $term->term_id;
                }
            }
        }

        // Fallback: try PMPro level→authority mapping
        if (empty($allowed_term_ids) && function_exists('pmpro_hasMembershipLevel')) {
            $map = get_option('pi_membership_authority_map', []);
            if (!empty($map)) {
                if (function_exists('pmpro_getMembershipLevelsForUser')) {
                    $levels = pmpro_getMembershipLevelsForUser($user_id);
                    if (!empty($levels)) {
                        foreach ($levels as $lvl) {
                            if (isset($map[$lvl->id])) {
                                foreach ((array) $map[$lvl->id] as $tid) {
                                    $allowed_term_ids[] = (int) $tid;
                                }
                            }
                        }
                    }
                }
            }
        }

        if (empty($allowed_term_ids)) {
            // Logged in but no councils assigned — return empty
            return new WP_REST_Response([], 200, [
                'X-WP-Total'      => '0',
                'X-WP-TotalPages' => '0',
                'Cache-Control'   => 'no-store, no-cache, must-revalidate',
            ]);
        }

        $allowed_term_ids    = array_values(array_unique($allowed_term_ids));
        $restrict_by_authority = true;
    }

    // ── PAGINATION ───────────────────────────────────────────────────────────
    $per_page = min(100, max(1, (int) ($request->get_param('per_page') ?: 40)));
    $page     = max(1, (int) ($request->get_param('page') ?: 1));

    // ── WP_QUERY ARGS ────────────────────────────────────────────────────────
    $args = [
        'post_type'      => 'planning_app',
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'no_found_rows'  => false,
    ];

    // Authority filter — intersect requested with allowed
    $tax_query = [];

    if ($restrict_by_authority) {
        $req_auth = $request->get_param('authority');
        if (!empty($req_auth)) {
            $req_ids = array_map('intval', explode(',', $req_auth));
            $inter   = array_values(array_intersect($req_ids, $allowed_term_ids));
            if (empty($inter)) {
                // Requested authorities not in user's allowed set
                return new WP_REST_Response([], 200, [
                    'X-WP-Total'      => '0',
                    'X-WP-TotalPages' => '0',
                    'Cache-Control'   => 'no-store, no-cache, must-revalidate',
                ]);
            }
            $tax_query[] = [
                'taxonomy' => 'authority',
                'field'    => 'term_id',
                'terms'    => $inter,
                'operator' => 'IN',
            ];
        } else {
            $tax_query[] = [
                'taxonomy' => 'authority',
                'field'    => 'term_id',
                'terms'    => $allowed_term_ids,
                'operator' => 'IN',
            ];
        }
    } elseif (!empty($request->get_param('authority'))) {
        // Admin with authority filter
        $req_ids = array_map('intval', explode(',', $request->get_param('authority')));
        $tax_query[] = [
            'taxonomy' => 'authority',
            'field'    => 'term_id',
            'terms'    => $req_ids,
            'operator' => 'IN',
        ];
    }

    // App category filter
    $app_cat = $request->get_param('app_category');
    if (!empty($app_cat)) {
        $tax_query[] = [
            'taxonomy' => 'app_category',
            'field'    => 'term_id',
            'terms'    => [(int) $app_cat],
            'operator' => 'IN',
        ];
    }

    if (!empty($tax_query)) {
        $args['tax_query'] = array_merge(['relation' => 'AND'], $tax_query);
    }

    // Date filter via meta_query on date_received
    $date_from = $request->get_param('date_from');
    $date_to   = $request->get_param('date_to');

    if (!empty($date_from) || !empty($date_to)) {
        $meta_q = ['key' => 'date_received', 'type' => 'DATE'];
        if (!empty($date_from) && !empty($date_to)) {
            $meta_q['compare'] = 'BETWEEN';
            $meta_q['value']   = [$date_from, $date_to];
        } elseif (!empty($date_from)) {
            $meta_q['compare'] = '>=';
            $meta_q['value']   = $date_from;
        } else {
            $meta_q['compare'] = '<=';
            $meta_q['value']   = $date_to;
        }
        $args['meta_query'] = [$meta_q];
    }

    // Keyword search across title + content + address meta
    $search = $request->get_param('search');
    if (!empty($search)) {
        // Use 's' for title/content search; address is in post_content (description) too
        $args['s'] = sanitize_text_field($search);
    }

    // Default ordering: date_received desc, then post_date desc
    $args['orderby']  = 'meta_value';
    $args['meta_key'] = 'date_received';
    $args['order']    = 'DESC';

    // ── RUN QUERY ────────────────────────────────────────────────────────────
    $query = new WP_Query($args);
    $total = (int) $query->found_posts;
    $total_pages = (int) $query->max_num_pages ?: 1;

    // ── FORMAT RESULTS ───────────────────────────────────────────────────────
    $posts = [];
    foreach ($query->posts as $post) {
        $meta = get_post_meta($post->ID);

        // Get authority name from taxonomy
        $authority_name = '';
        $authority_term_id = 0;
        $terms = get_the_terms($post->ID, 'authority');
        if ($terms && !is_wp_error($terms)) {
            $authority_name    = $terms[0]->name;
            $authority_term_id = (int) $terms[0]->term_id;
        }
        if (!$authority_name) {
            $authority_name = $meta['authority_name'][0] ?? '';
        }

        $posts[] = [
            'id'              => $post->ID,
            'title'           => ['rendered' => esc_html($post->post_title)],
            'content'         => ['rendered' => wpautop($post->post_content)],
            '_authority_name' => $authority_name,
            'authority_id'    => $authority_term_id,
            'meta'            => [
                'address'            => $meta['address'][0] ?? '',
                'council_reference'  => $meta['council_reference'][0] ?? '',
                'date_received'      => $meta['date_received'][0] ?? '',
                'info_url'           => $meta['info_url'][0] ?? '',
                'status'             => $meta['status'][0] ?? '',
                'decision'           => $meta['decision'][0] ?? '',
                'est_value'          => $meta['est_value'][0] ?? '',
                'est_value_numeric'  => $meta['est_value_numeric'][0] ?? '',
                'ai_badge'           => $meta['ai_badge'][0] ?? '',
                'is_construction_job'=> $meta['is_construction_job'][0] ?? '',
            ],
        ];
    }

    $response = new WP_REST_Response($posts, 200);
    $response->header('X-WP-Total', $total);
    $response->header('X-WP-TotalPages', $total_pages);
    $response->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    $response->header('Pragma', 'no-cache');

    return $response;
}
