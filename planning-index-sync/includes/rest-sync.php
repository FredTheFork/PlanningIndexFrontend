<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * REST endpoint: /wp-json/planning-index/v1/sync
 * Accepts POST with JSON: { "apps": [ {...}, {...} ], "replace_missing": false }
 */
add_action( 'rest_api_init', function() {
    register_rest_route( 'planning-index/v1', '/sync', array(
        'methods'             => 'POST',
        'callback'            => 'planning_index_rest_sync_callback',
        'permission_callback' => function ( $request ) {
            return current_user_can( 'edit_posts' );
        }
    ) );
} );

function planning_index_rest_sync_callback( WP_REST_Request $request ) {
    $params = $request->get_json_params();
    if ( ! is_array( $params ) && ! isset( $params['apps'] ) ) {
        return new WP_REST_Response( array( 'error' => 'Invalid payload; expected apps array' ), 400 );
    }

    $apps = isset( $params['apps'] ) ? $params['apps'] : ( is_array( $params ) ? $params : array() );
    if ( ! is_array( $apps ) || empty( $apps ) ) {
        return new WP_REST_Response( array( 'processed' => 0, 'message' => 'No apps provided' ), 200 );
    }

    $count = 0;
    $errors = array();

    foreach ( $apps as $idx => $a ) {

        // Required fields
        $authority   = isset( $a['authority_name'] ) ? sanitize_text_field( $a['authority_name'] ) : '';
        $ref         = isset( $a['council_reference'] ) ? sanitize_text_field( $a['council_reference'] ) : '';
        $description = isset( $a['description'] ) ? wp_kses_post( $a['description'] ) : '';
        $address     = isset( $a['address'] ) ? sanitize_text_field( $a['address'] ) : '';
        $info_url    = isset( $a['info_url'] ) ? esc_url_raw( $a['info_url'] ) : '';
        $date_received = isset( $a['date_received'] ) ? sanitize_text_field( $a['date_received'] ) : '';

        if ( empty( $authority ) || empty( $ref ) || empty( $description ) ) {
            $errors[] = "Missing required fields on item $idx";
            continue;
        }

        // Title + content (description only, no address concatenation)
        $title = wp_strip_all_tags( substr( $description, 0, 120 ) . " — {$ref}" );
        $content = $description;

        // Find existing post
        $existing = get_posts( array(
            'post_type' => 'planning_app',
            'meta_query' => array(
                'relation' => 'AND',
                array( 'key' => 'council_reference', 'value' => $ref ),
                array( 'key' => 'authority_name', 'value' => $authority ),
            ),
            'posts_per_page' => 1,
        ) );

        if ( $existing ) {
            $post_id = $existing[0]->ID;
            wp_update_post( array(
                'ID'          => $post_id,
                'post_title'  => $title,
                'post_content'=> $content,
            ) );
        } else {
            $post_id = wp_insert_post( array(
                'post_type'   => 'planning_app',
                'post_status' => 'publish',
                'post_title'  => $title,
                'post_content'=> $content,
            ) );
        }

        if ( is_wp_error( $post_id ) ) {
            $errors[] = "WP error on item $idx: " . $post_id->get_error_message();
            continue;
        }

        // Authority taxonomy
        if ( ! empty( $authority ) ) {
            if ( ! term_exists( $authority, 'authority' ) ) {
                wp_insert_term( $authority, 'authority' );
            }
            wp_set_post_terms( $post_id, array( $authority ), 'authority', false );
            update_post_meta( $post_id, 'authority_name', $authority );
        }

        // Update meta - store description and address as SEPARATE fields
        update_post_meta( $post_id, 'council_reference', $ref );
        update_post_meta( $post_id, 'description', $description );
        update_post_meta( $post_id, 'address', $address );
        update_post_meta( $post_id, 'info_url', $info_url );
        update_post_meta( $post_id, 'date_received', $date_received );
        update_post_meta( $post_id, 'date_validated', sanitize_text_field( $a['date_validated'] ?? '' ) );
        update_post_meta( $post_id, 'status', sanitize_text_field( $a['status'] ?? '' ) );
        update_post_meta( $post_id, 'decision', sanitize_text_field( $a['decision'] ?? '' ) );
        update_post_meta( $post_id, 'documents_url', sanitize_text_field( $a['documents_url'] ?? '' ) );
        update_post_meta( $post_id, 'last_seen', current_time( 'mysql' ) );
        update_post_meta( $post_id, 'synced_by', 'rest' );

        /*
        ---------------------------------------------------------
        ✅ AUTO-ASSIGN KEYWORDS BASED ON DESCRIPTION (NEW BLOCK)
        ---------------------------------------------------------
        */
        $keywords = get_terms([
            'taxonomy'   => 'pi_keywords',
            'hide_empty' => false,
        ]);

        if ( ! is_wp_error( $keywords ) && ! empty( $keywords ) ) {
            foreach ( $keywords as $kw ) {
                if ( stripos( $description, $kw->name ) !== false ) {
                    wp_set_post_terms( $post_id, [$kw->term_id], 'pi_keywords', true );
                }
            }
        }
        /*
        ---------------------------------------------------------
        END KEYWORD AUTO-ASSIGN
        ---------------------------------------------------------
        */

        $count++;
    }
    // =============================================
    // AI PRICING — AUTOMATIC AFTER SYNC (v2)
    // =============================================
    if (!empty($apps) && $count > 0) {
        $n8n_webhook = 'https://planningindex-n8n.onrender.com/webhook/planai';

        $body = array('current_apps' => $apps);

        $response = wp_remote_post($n8n_webhook, array(
            'timeout'     => 30,
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking'    => true,
            'headers'     => array('Content-Type' => 'application/json'),
            'body'        => json_encode($body),
        ));
        error_log('=== AI CALL SENT to n8n ===');
        error_log('Raw response body: ' . wp_remote_retrieve_body($response));
        error_log('HTTP code: ' . wp_remote_retrieve_response_code($response));





        if (is_wp_error($response)) {
            error_log('AI Pricing failed: ' . $response->get_error_message());
        } else {
            $body = wp_remote_retrieve_body($response);
            $ai_data = json_decode($body, true);

            if (isset($ai_data['annotated_apps']) && is_array($ai_data['annotated_apps'])) {
                error_log('AI returned ' . count($ai_data['annotated_apps']) . ' annotated apps');
                foreach ($ai_data['annotated_apps'] as $ai_app) {
                    $ref = sanitize_text_field($ai_app['council_reference'] ?? '');

                    if (empty($ref)) continue;

                    // Find the post we just created/updated
                    $existing = get_posts(array(
                        'post_type'      => 'planning_app',
                        'meta_key'       => 'council_reference',
                        'meta_value'     => $ref,
                        'posts_per_page' => 1,
                    ));

                    if (!empty($existing)) {
                        $post_id = $existing[0]->ID;

                        update_post_meta($post_id, 'est_value', $ai_app['est_value'] ?? null);
                        update_post_meta($post_id, 'est_value_numeric', $ai_app['est_value_numeric'] ?? 0);
                        update_post_meta($post_id, 'ai_badge', $ai_app['badge'] ?? null);
                        update_post_meta($post_id, 'is_construction_job', !empty($ai_app['is_construction_job']) ? 1 : 0);
                        update_post_meta($post_id, 'ai_priced_at', current_time('mysql'));
                        update_post_meta($post_id, 'ai_skip_reason', $ai_app['skip_reason'] ?? null);
                    }
                }
            }
            
        }
    }
    // =============================================
    return new WP_REST_Response( array( 'processed' => $count, 'errors' => $errors ), 200 );
}
