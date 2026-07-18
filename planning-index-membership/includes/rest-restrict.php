<?php
// Add a REST filter so planning_app queries are limited to the authorities a user has via PMPro

add_filter('rest_post_query', function($args, $request) {
    // Only act for our post type planning_app endpoints
    $post_type = $request->get_param('post_type');
    $route_post_types = $request->get_param('type'); // fallback
    // If this is a wp/v2 posts REST route, $request->get_params() will include route info.
    // Simpler approach: check current requested post type via query args or endpoint path
    $route = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    if (strpos($route, '/wp/v2/planning_app') === false && (empty($post_type) && empty($route_post_types))) {
        return $args;
    }

    // If PMPro not present, do nothing (or enforce login)
    if (!function_exists('pmpro_hasMembershipLevel')) {
        return $args;
    }

    // Get mapping option
    $map = get_option(PI_MEM_OPTION, []);

    // Determine allowed term IDs for current user
    $allowed_term_ids = [];

    $user = wp_get_current_user();
    if ($user && $user->ID && current_user_can('manage_options')) {
        return $args;
    }
    if (! $user || ! $user->ID) {
        // not logged in: default deny (return zero results)
        // To make page partially public, comment out the next line
        $args['post__in'] = [0];
        return $args;
    }

    // Get current user's active levels (returns array of level objects)
    if ( function_exists('pmpro_getMembershipLevelsForUser') ) {
        $levels = pmpro_getMembershipLevelsForUser($user->ID);
        if (!empty($levels)) {
            foreach ($levels as $lvl) {
                $id = $lvl->id;
                if (isset($map[$id]) && is_array($map[$id])) {
                    foreach ($map[$id] as $tid) {
                        $allowed_term_ids[] = intval($tid);
                    }
                }
            }
        }
    } else {
        // fallback: check pmpro_hasMembershipLevel for each mapped level
        foreach ($map as $level_id => $tids) {
            if (pmpro_hasMembershipLevel(intval($level_id), $user->ID)) {
                $allowed_term_ids = array_merge($allowed_term_ids, (array)$tids);
            }
        }
    }

    $allowed_term_ids = array_values(array_unique($allowed_term_ids));

    // If user has no mapped authorities -> deny
    if (empty($allowed_term_ids)) {
        $args['post__in'] = [0];
        return $args;
    }

    // Default: add tax_query to limit by authority term IDs
    $tax_query = isset($args['tax_query']) ? $args['tax_query'] : [];
    $tax_query[] = [
        'taxonomy' => 'authority',
        'field' => 'term_id',
        'terms' => $allowed_term_ids,
        'operator' => 'IN'
    ];
    $args['tax_query'] = $tax_query;

    // Additionally: if the client requested a specific authority via ?authority=<term_id>
    // ensure they can't request something outside their allowed set
    $req_auth = $request->get_param('authority');
    if ($req_auth) {
        $req_ids = is_array($req_auth) ? array_map('intval', $req_auth) : [intval($req_auth)];
        // intersection
        $inter = array_intersect($req_ids, $allowed_term_ids);
        if (empty($inter)) {
            $args['post__in'] = [0];
            return $args;
        }
        // restrict to intersection
        $args['tax_query'][] = [
            'taxonomy' => 'authority',
            'field' => 'term_id',
            'terms' => array_values($inter)
        ];
    }

    return $args;
}, 10, 2);
