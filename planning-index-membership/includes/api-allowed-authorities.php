<?php
/**
 * REST endpoint: /wp-json/pi/v1/allowed-authorities
 * Returns only the councils the current user purchased (from PMPro checkout).
 */


add_action('rest_api_init', function() {
    register_rest_route('pi/v1', '/allowed-authorities', [
        'methods'  => 'GET',
        'permission_callback' => function() {
            return is_user_logged_in(); // only members can call it
        },
        'callback' => function() {
            $user = wp_get_current_user();
            $allowed = get_user_meta($user->ID, 'pmpc_selected_councils', true);


            // Return empty if none selected
            if (empty($allowed) || !is_array($allowed)) {
                return rest_ensure_response([]);
            }


            $result = [];
            foreach ($allowed as $name) {
                // Match by name → taxonomy term ID
                $term = get_term_by('name', trim($name), 'authority');
                if ($term) {
                    $result[] = [
                        'id'   => (int) $term->term_id,
                        'name' => esc_html($term->name),
                    ];
                }
            }


            // Sort alphabetically for clean UI
            usort($result, fn($a, $b) => strcmp($a['name'], $b['name']));


            return rest_ensure_response($result);
        },
    ]);
});
