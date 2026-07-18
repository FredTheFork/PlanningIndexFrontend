<?php
add_action('init', function() {

    // === Custom Post Type ===
    register_post_type('planning_app', [
        'labels' => [
            'name' => 'Planning Applications',
            'singular_name' => 'Planning Application',
        ],
        'public' => true,
        'has_archive' => true,
        'rewrite' => ['slug' => 'planning'],
        'supports' => ['title', 'editor', 'custom-fields', 'revisions'],
        'show_in_rest' => true,
        'menu_icon' => 'dashicons-location-alt',
    ]);

    // === Taxonomies ===
    register_taxonomy('authority', 'planning_app', [
        'label' => 'Authority',
        'public' => true,
        'show_in_rest' => true,
        'hierarchical' => false,
    ]);

    register_taxonomy('app_category', 'planning_app', [
        'label' => 'Application Category',
        'public' => true,
        'show_in_rest' => true,
        'hierarchical' => false,
    ]);

    // === Meta fields ===
    $meta_fields = [
        'council_reference' => 'Council Reference',
        'info_url'          => 'Information URL',
        'date_received'     => 'Date Received',
        'date_validated'    => 'Date Validated',
        'status'            => 'Status',
        'decision'          => 'Decision',
        'documents_url'     => 'Documents URL',
        'last_seen'         => 'Last Seen',
        'synced_by'         => 'Synced By',
        'address'           => 'Address',
        'description'       => 'Description',
        'authority_name'    => 'Authority Name',
        'est_value'          => 'AI Estimated Value',
        'est_value_numeric'  => 'AI Value Numeric',
        'ai_badge'           => 'AI Badge',
        'is_construction_job'=> 'Is Construction Job',
        'ai_priced_at'       => 'AI Priced At',
        'ai_skip_reason'     => 'AI Skip Reason',
    ];

    foreach ($meta_fields as $key => $label) {
        register_post_meta('planning_app', $key, [
            'type' => 'string',
            'description' => $label,
            'single' => true,
            'show_in_rest' => true,
        ]);
    }
});
// ────────────────────────────────────────────────
// Admin list table: add "AI Est. Value" column
// ────────────────────────────────────────────────

add_filter('manage_planning_app_posts_columns', function ($columns) {
    // Insert after 'title' (or change position as you prefer)
    $new_columns = [];
    foreach ($columns as $key => $title) {
        $new_columns[$key] = $title;
        if ($key === 'title') {
            $new_columns['ai_est_value'] = 'AI Est. Value';
        }
    }
    return $new_columns;
}, 20);

add_action('manage_planning_app_posts_custom_column', function ($column, $post_id) {
    if ($column !== 'ai_est_value') {
        return;
    }

    $value     = get_post_meta($post_id, 'est_value', true);
    $numeric   = get_post_meta($post_id, 'est_value_numeric', true);
    $badge     = get_post_meta($post_id, 'ai_badge', true);
    $skip      = get_post_meta($post_id, 'ai_skip_reason', true);
    $is_job    = get_post_meta($post_id, 'is_construction_job', true);

    if ($is_job && $value) {
        // Show badge if present, fallback to plain value
        $display = $badge ?: $value;
        echo '<strong>' . esc_html($display) . '</strong>';
    } elseif ($skip) {
        echo '<span style="color:#999;">— skipped: ' . esc_html(substr($skip, 0, 60)) . '…</span>';
    } else {
        echo '—';
    }
}, 10, 2);

// Optional: make the column sortable (by numeric value)
add_filter('manage_edit-planning_app_sortable_columns', function ($columns) {
    $columns['ai_est_value'] = 'ai_est_value_numeric';
    return $columns;
});

add_action('pre_get_posts', function ($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    $orderby = $query->get('orderby');
    if ('ai_est_value_numeric' === $orderby) {
        $query->set('meta_key', 'est_value_numeric');
        $query->set('orderby', 'meta_value_num');
    }
});
// Quick Edit – show AI Est. Value
add_action('quick_edit_custom_box', function ($column_name, $post_type) {
    if ($post_type !== 'planning_app' || $column_name !== 'ai_est_value') {
        return;
    }
    ?>
    <fieldset class="inline-edit-col-right">
        <div class="inline-edit-col">
            <label>
                <span class="title">AI Est. Value</span>
                <span class="input-text-wrap">
                    <input type="text" name="ai_est_value_display" value="" readonly>
                </span>
            </label>
            <p class="description">This is read-only (set by AI)</p>
        </div>
    </fieldset>
    <?php
}, 10, 2);

// Populate Quick Edit field via JS (very simple version)
add_action('admin_footer', function () {
    global $current_screen;
    if ($current_screen->id !== 'edit-planning_app') {
        return;
    }
    ?>
    <script>
    jQuery(function($){
        var wp_inline_edit_function = inlineEditPost.edit;
        inlineEditPost.edit = function(id){
            wp_inline_edit_function.apply(this, arguments);

            var post_id = 0;
            if (typeof(id) == 'object') {
                post_id = parseInt(this.getId(id));
            }

            if (post_id > 0) {
                var $row     = $('#post-'+post_id),
                    $editRow = $('#edit-'+post_id),
                    value    = $row.find('td.ai_est_value').text().trim();

                $editRow.find('input[name="ai_est_value_display"]').val(value);
            }
        };
    });
    </script>
    <?php
});