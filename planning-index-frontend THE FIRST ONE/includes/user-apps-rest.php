<?php
/**
 * REST API endpoints for user saved apps and recently viewed apps
 * Stores data per-user in user meta
 */

add_action('rest_api_init', function() {
    // Get user's saved apps
    register_rest_route('pi/v1', '/user-apps/saved', [
        'methods' => 'GET',
        'callback' => 'pi_get_saved_apps',
        'permission_callback' => 'is_user_logged_in'
    ]);

    // Save/unsave an app
    register_rest_route('pi/v1', '/user-apps/save', [
        'methods' => 'POST',
        'callback' => 'pi_save_app',
        'permission_callback' => 'is_user_logged_in',
        'args' => [
            'post_id' => [
                'required' => true,
                'type' => 'integer',
                'sanitize_callback' => 'absint'
            ]
        ]
    ]);

    // Unsave an app
    register_rest_route('pi/v1', '/user-apps/unsave', [
        'methods' => 'POST',
        'callback' => 'pi_unsave_app',
        'permission_callback' => 'is_user_logged_in',
        'args' => [
            'post_id' => [
                'required' => true,
                'type' => 'integer',
                'sanitize_callback' => 'absint'
            ]
        ]
    ]);

    // Get recently viewed apps
    register_rest_route('pi/v1', '/user-apps/recent', [
        'methods' => 'GET',
        'callback' => 'pi_get_recent_apps',
        'permission_callback' => 'is_user_logged_in'
    ]);

    // Track a view
    register_rest_route('pi/v1', '/user-apps/view', [
        'methods' => 'POST',
        'callback' => 'pi_track_view',
        'permission_callback' => 'is_user_logged_in',
        'args' => [
            'post_id' => [
                'required' => true,
                'type' => 'integer',
                'sanitize_callback' => 'absint'
            ]
        ]
    ]);
    // Add to your REST API initialization
    add_filter('rest_pre_serve_request', function($served, $result, $request, $server) {
        if (strpos($request->get_route(), 'pi/v1') !== false) {
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
        return $served;
    }, 10, 4);
    // Check if apps are saved (bulk check)
    register_rest_route('pi/v1', '/user-apps/check-saved', [
        'methods' => 'POST',
        'callback' => 'pi_check_saved_apps',
        'permission_callback' => 'is_user_logged_in',
        'args' => [
            'post_ids' => [
                'required' => true,
                'type' => 'array'
            ]
        ]
    ]);
});

/**
 * Get user's saved apps
 */
function pi_get_saved_apps($request) {
    $user_id = get_current_user_id();
    $saved_ids = get_user_meta($user_id, 'pi_saved_apps', true);
    
    if (empty($saved_ids) || !is_array($saved_ids)) {
        return rest_ensure_response(['apps' => []]);
    }

    // Get the actual posts
    $apps = [];
    foreach ($saved_ids as $item) {
        $post_id = is_array($item) ? $item['id'] : $item;
        $post = get_post($post_id);
        if ($post && $post->post_type === 'planning_app') {
            $apps[] = pi_format_app_data($post, is_array($item) ? $item['saved_at'] : null);
        }
    }

    return rest_ensure_response(['apps' => array_reverse($apps)]); // Most recent first
}

/**
 * Save an app
 */
function pi_save_app($request) {
    $user_id = get_current_user_id();
    $post_id = $request->get_param('post_id');

    // Verify post exists and is a planning_app
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'planning_app') {
        return new WP_Error('invalid_post', 'Invalid planning application', ['status' => 400]);
    }

    $saved = get_user_meta($user_id, 'pi_saved_apps', true);
    if (!is_array($saved)) {
        $saved = [];
    }

    // Check if already saved
    $existing_ids = array_map(function($item) {
        return is_array($item) ? $item['id'] : $item;
    }, $saved);

    if (in_array($post_id, $existing_ids)) {
        return rest_ensure_response(['success' => true, 'already_saved' => true]);
    }

    // Add to saved (store with timestamp)
    $saved[] = [
        'id' => $post_id,
        'saved_at' => current_time('mysql')
    ];

    update_user_meta($user_id, 'pi_saved_apps', $saved);

    return rest_ensure_response(['success' => true, 'saved' => true]);
}

/**
 * Unsave an app
 */
function pi_unsave_app($request) {
    $user_id = get_current_user_id();
    $post_id = $request->get_param('post_id');

    $saved = get_user_meta($user_id, 'pi_saved_apps', true);
    if (!is_array($saved)) {
        return rest_ensure_response(['success' => true]);
    }

    // Remove the app
    $saved = array_filter($saved, function($item) use ($post_id) {
        $id = is_array($item) ? $item['id'] : $item;
        return $id != $post_id;
    });

    update_user_meta($user_id, 'pi_saved_apps', array_values($saved));

    return rest_ensure_response(['success' => true, 'removed' => true]);
}

/**
 * Get recently viewed apps
 */
function pi_get_recent_apps($request) {
    $user_id = get_current_user_id();
    $recent = get_user_meta($user_id, 'pi_recent_apps', true);
    
    if (empty($recent) || !is_array($recent)) {
        return rest_ensure_response(['apps' => []]);
    }

    // Get saved app IDs for comparison
    $saved = get_user_meta($user_id, 'pi_saved_apps', true);
    $saved_ids = [];
    if (is_array($saved)) {
        $saved_ids = array_map(function($item) {
            return is_array($item) ? $item['id'] : $item;
        }, $saved);
    }

    // Get the actual posts (excluding saved ones)
    $apps = [];
    foreach ($recent as $item) {
        $post_id = is_array($item) ? $item['id'] : $item;
        
        // Skip if already saved
        if (in_array($post_id, $saved_ids)) {
            continue;
        }

        $post = get_post($post_id);
        if ($post && $post->post_type === 'planning_app') {
            $apps[] = pi_format_app_data($post, is_array($item) ? $item['viewed_at'] : null);
        }
    }

    return rest_ensure_response(['apps' => array_reverse($apps)]); // Most recent first
}

/**
 * Track a view
 */
function pi_track_view($request) {
    $user_id = get_current_user_id();
    $post_id = $request->get_param('post_id');

    // Verify post exists
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'planning_app') {
        return new WP_Error('invalid_post', 'Invalid planning application', ['status' => 400]);
    }

    $recent = get_user_meta($user_id, 'pi_recent_apps', true);
    if (!is_array($recent)) {
        $recent = [];
    }

    // Remove if already exists (will re-add at end)
    $recent = array_filter($recent, function($item) use ($post_id) {
        $id = is_array($item) ? $item['id'] : $item;
        return $id != $post_id;
    });

    // Add to recent
    $recent[] = [
        'id' => $post_id,
        'viewed_at' => current_time('mysql')
    ];

    // Keep only last 50
    if (count($recent) > 50) {
        $recent = array_slice($recent, -50);
    }

    update_user_meta($user_id, 'pi_recent_apps', array_values($recent));

    return rest_ensure_response(['success' => true]);
}

/**
 * Check which apps are saved (bulk)
 */
function pi_check_saved_apps($request) {
    $user_id = get_current_user_id();
    $post_ids = $request->get_param('post_ids');

    $saved = get_user_meta($user_id, 'pi_saved_apps', true);
    $saved_ids = [];
    
    if (is_array($saved)) {
        $saved_ids = array_map(function($item) {
            return is_array($item) ? $item['id'] : $item;
        }, $saved);
    }

    $result = [];
    foreach ($post_ids as $id) {
        $result[$id] = in_array((int)$id, $saved_ids);
    }

    return rest_ensure_response(['saved' => $result]);
}

/**
 * Format app data for response
 */
function pi_format_app_data($post, $timestamp = null) {
    $meta = get_post_meta($post->ID);
    
    // Get authority name
    $authority_name = '';
    $authorities = get_the_terms($post->ID, 'authority');
    if ($authorities && !is_wp_error($authorities)) {
        $authority_name = $authorities[0]->name;
    }

  return [
      'id' => $post->ID,
      'title' => $post->post_title,
      'content' => $post->post_content,
      'meta' => [
          'address' => $meta['address'][0] ?? '',
          'council_reference' => $meta['council_reference'][0] ?? '',
          'date_received' => $meta['date_received'][0] ?? '',
          'info_url' => $meta['info_url'][0] ?? '',
          'authority' => $meta['authority'][0] ?? '',
          
          // ← NEW AI FIELDS
          'est_value'          => $meta['est_value'][0] ?? '',
          'est_value_numeric'  => $meta['est_value_numeric'][0] ?? '',
          'ai_badge'           => $meta['ai_badge'][0] ?? '',
          'is_construction_job'=> $meta['is_construction_job'][0] ?? '',
      ],
      '_authority_name' => $authority_name,
      'timestamp' => $timestamp
  ];
}
