<?php
// Lock down planning_app queries to only allowed councils for current user
add_action('pre_get_posts', function($query) {
    if (is_admin() || !$query->is_main_query()) return;

    // Apply only to planning_app archive, taxonomy, or search
    if ($query->get('post_type') !== 'planning_app') return;

    // Admins see all planning apps
    if (is_user_logged_in() && current_user_can('manage_options')) {
        return;
    }

    if (!is_user_logged_in()) {
        // non-members see nothing
        $query->set('post__in', [0]);
        return;
    }

    $user = wp_get_current_user();
    $allowed = get_user_meta($user->ID, 'pmpc_selected_councils', true);

    if (empty($allowed) || !is_array($allowed)) {
        $query->set('post__in', [0]);
        return;
    }

    // Map council names to term IDs
    $term_ids = [];
    foreach ($allowed as $name) {
        $term = get_term_by('name', $name, 'authority');
        if ($term) $term_ids[] = $term->term_id;
    }

    if (!empty($term_ids)) {
        $query->set('tax_query', [
            [
                'taxonomy' => 'authority',
                'field'    => 'term_id',
                'terms'    => $term_ids,
                'operator' => 'IN',
            ],
        ]);
    } else {
        $query->set('post__in', [0]);
    }
});
