<?php
add_action('pi_daily_cleanup_event', 'pi_daily_cleanup');

function pi_schedule_cron() {
    if (!wp_next_scheduled('pi_daily_cleanup_event')) {
        wp_schedule_event(time(), 'daily', 'pi_daily_cleanup_event');
    }
}
add_action('wp', 'pi_schedule_cron');

function pi_daily_cleanup() {
    $days = apply_filters('pi_cleanup_days', 50); // change cutoff here
    $cutoff = date('Y-m-d', strtotime("-{$days} days"));

    $args = [
        'post_type' => 'planning_app',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'date_received',
                'value' => $cutoff,
                'compare' => '<',
                'type' => 'DATE'
            ]
        ],
        'fields' => 'ids'
    ];
    $posts = get_posts($args);
    foreach ($posts as $post_id) {
        wp_delete_post($post_id, true);
        //wp_update_post(['ID' => $post_id, 'post_status' => 'draft']);
    }
}
