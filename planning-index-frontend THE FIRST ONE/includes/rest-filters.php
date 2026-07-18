<?php
add_filter('rest_planning_app_query', function($args, $request) {
    if (is_user_logged_in() && current_user_can('manage_options')) {
        return $args;
    }
    if (!is_user_logged_in()) {
        $args['post__in'] = [0];
        return $args;
    }

    $user = wp_get_current_user();
    $allowed = get_user_meta($user->ID, 'pmpc_selected_councils', true);
    if (empty($allowed) || !is_array($allowed)) {
        $args['post__in'] = [0];
        return $args;
    }

    $term_ids = [];
    foreach ($allowed as $name) {
        $term = get_term_by('name', $name, 'authority');
        if ($term) $term_ids[] = $term->term_id;
    }

    if (!empty($term_ids)) {
        $args['tax_query'][] = [
            'taxonomy' => 'authority',
            'field'    => 'term_id',
            'terms'    => $term_ids,
            'operator' => 'IN',
        ];
    } else {
        $args['post__in'] = [0];
    }

    // Handle OR-based search for multiple keywords
    $search = $request->get_param('search');
    if (!empty($search)) {
        // Split search terms by spaces
        $keywords = array_filter(array_map('trim', preg_split('/\s+/', $search)));
        
        if (!empty($keywords)) {
            // Clear the default 's' param to prevent default AND behavior
            unset($args['s']);
            
            // Build OR-based query for each keyword
            // We'll use a custom filter to modify the WHERE clause for better performance and coverage
            add_filter('posts_where', function($where, $query) use ($keywords) {
                global $wpdb;
                if ($query->get('post_type') !== 'planning_app') return $where;
                
                $or_conditions = [];
                foreach ($keywords as $kw) {
                    $like = '%' . $wpdb->esc_like($kw) . '%';
                    $or_conditions[] = $wpdb->prepare(
                        "($wpdb->posts.post_title LIKE %s OR $wpdb->posts.post_content LIKE %s)",
                        $like, $like
                    );
                }
                
                if (!empty($or_conditions)) {
                    $where .= ' AND (' . implode(' OR ', $or_conditions) . ')';
                }
                
                return $where;
            }, 10, 2);
        }
    }

    // Handle date filtering (date_from and date_to)
    // Filter by date_received meta field, OR fallback to post_date if date_received is empty
    $date_from = $request->get_param('date_from');
    $date_to = $request->get_param('date_to');
    
    if (!empty($date_from) || !empty($date_to)) {
        add_filter('posts_where', function($where, $query) use ($date_from, $date_to) {
            global $wpdb;
            if ($query->get('post_type') !== 'planning_app') return $where;
            
            $date_conditions = [];
            
            if (!empty($date_from) && !empty($date_to)) {
                // Both dates provided: filter between date_from and date_to
                // Use COALESCE to fallback to post_date if date_received is empty/null
                $date_conditions[] = $wpdb->prepare(
                    "(
                        COALESCE(
                            NULLIF((SELECT meta_value FROM $wpdb->postmeta WHERE post_id = $wpdb->posts.ID AND meta_key = 'date_received' LIMIT 1), ''),
                            DATE($wpdb->posts.post_date)
                        ) BETWEEN %s AND %s
                    )",
                    $date_from,
                    $date_to
                );
            } elseif (!empty($date_from)) {
                // Only date_from: filter >= date_from
                $date_conditions[] = $wpdb->prepare(
                    "(
                        COALESCE(
                            NULLIF((SELECT meta_value FROM $wpdb->postmeta WHERE post_id = $wpdb->posts.ID AND meta_key = 'date_received' LIMIT 1), ''),
                            DATE($wpdb->posts.post_date)
                        ) >= %s
                    )",
                    $date_from
                );
            } elseif (!empty($date_to)) {
                // Only date_to: filter <= date_to
                $date_conditions[] = $wpdb->prepare(
                    "(
                        COALESCE(
                            NULLIF((SELECT meta_value FROM $wpdb->postmeta WHERE post_id = $wpdb->posts.ID AND meta_key = 'date_received' LIMIT 1), ''),
                            DATE($wpdb->posts.post_date)
                        ) <= %s
                    )",
                    $date_to
                );
            }
            
            if (!empty($date_conditions)) {
                $where .= ' AND ' . implode(' AND ', $date_conditions);
            }
            
            return $where;
        }, 10, 2);
    }

    return $args;
}, 10, 2);
