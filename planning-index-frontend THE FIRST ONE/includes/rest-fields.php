<?php
/**
 * Expose _authority_name and authority meta on the wp/v2/planning_app REST response.
 * The frontend JS relies on post._authority_name to render the council name on each card.
 */

add_action('rest_api_init', function() {

    register_rest_field('planning_app', '_authority_name', [
        'get_callback' => function($post) {
            $terms = get_the_terms($post['id'], 'authority');
            if ($terms && !is_wp_error($terms)) {
                return $terms[0]->name;
            }
            $name = get_post_meta($post['id'], 'authority_name', true);
            return $name ?: '';
        },
        'update_callback' => null,
        'schema' => [
            'type'    => 'string',
            'context' => ['view', 'edit', 'embed'],
        ],
    ]);

    register_rest_field('planning_app', 'authority_id', [
        'get_callback' => function($post) {
            $terms = get_the_terms($post['id'], 'authority');
            if ($terms && !is_wp_error($terms)) {
                return (int) $terms[0]->term_id;
            }
            return 0;
        },
        'update_callback' => null,
        'schema' => [
            'type'    => 'integer',
            'context' => ['view', 'edit', 'embed'],
        ],
    ]);
});
