<?php
// Add a REST filter so planning_app queries are limited to the authorities a user has via PMPro.
// Defers to rest-filters.php (pmpc_selected_councils) when that meta is set, to avoid double tax_query.

add_filter('rest_post_query', function($args, $request) {
    $route = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    if (strpos($route, '/wp/v2/planning_app') === false) {
        return $args;
    }

    // Admins see everything
    $user = wp_get_current_user();
    if ($user && $user->ID && current_user_can('manage_options')) {
        return $args;
    }

    // Not logged in: deny
    if (! $user || ! $user->ID) {
        $args['post__in'] = [0];
        return $args;
    }

    // If pmpc_selected_councils is set, rest-filters.php already handles restriction.
    // Deerring avoids two conflicting tax_query clauses on the same taxonomy.
    $selected = get_user_meta($user->ID, 'pmpc_selected_councils', true);
    if (!empty($selected) && is_array($selected)) {
        return $args;
    }

    // If PMPro not present, deny
    if (!function_exists('pmpro_hasMembershipLevel')) {
        $args['post__in'] = [0];
        return $args;
    }

    // Get mapping option (level_id => [term_ids])
    $map = get_option(PI_MEM_OPTION, []);
    if (empty($map)) {
        $args['post__in'] = [0];
        return $args;
    }

    // Determine allowed term IDs for current user from PMPro levels
    $allowed_term_ids = [];
    if (function_exists('pmpro_getMembershipLevelsForUser')) {
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

    // Add tax_query to limit by authority term IDs (only one clause here)
    $tax_query = isset($args['tax_query']) ? $args['tax_query'] : [];
    $tax_query[] = [
        'taxonomy' => 'authority',
        'field' => 'term_id',
        'terms' => $allowed_term_ids,
        'operator' => 'IN'
    ];
    $args['tax_query'] = $tax_query;

    // If the client requested a specific authority, ensure it's within their allowed set
    $req_auth = $request->get_param('authority');
    if ($req_auth) {
        $req_ids = is_array($req_auth) ? array_map('intval', $req_auth) : [intval($req_auth)];
        $inter = array_intersect($req_ids, $allowed_term_ids);
        if (empty($inter)) {
            $args['post__in'] = [0];
            return $args;
        }
        $args['tax_query'][] = [
            'taxonomy' => 'authority',
            'field' => 'term_id',
            'terms' => array_values($inter)
        ];
    }

    return $args;
}, 10, 2);
