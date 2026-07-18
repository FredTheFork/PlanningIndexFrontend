<?php
/**
 * Plugin Name: Planning Featured Shortcode
 * Description: Ultra-smooth infinite planning application carousel. Shortcode: [planning_featured refs="REF1,REF2"]
 * Version: 6.3
 * Author: Noel
 */

if (!defined('ABSPATH')) exit;

add_shortcode('planning_featured', function($atts){

    $atts = shortcode_atts(['refs' => ''], $atts);
    $refs_raw = trim($atts['refs']);
    if (!$refs_raw) return '<p>No reference numbers provided.</p>';

    $refs = array_unique(array_filter(array_map('trim', explode(',', $refs_raw))));
    if (empty($refs)) return '<p>No valid reference numbers provided.</p>';

    $meta_query = ['relation' => 'OR'];
    foreach ($refs as $r) {
        $meta_query[] = [
            'key'     => 'council_reference',
            'value'   => sanitize_text_field($r),
            'compare' => '='
        ];
    }

    $posts = get_posts([
        'post_type'      => 'planning_app',
        'posts_per_page' => -1,
        'meta_query'     => $meta_query
    ]);

    if (!$posts) return '<p>No planning applications found.</p>';

    $ref_to_post = [];
    foreach ($posts as $p) {
        $ref = get_post_meta($p->ID, 'council_reference', true);
        $ref_to_post[$ref] = $p;
    }

    $ordered_posts = [];
    foreach ($refs as $r) {
        if (isset($ref_to_post[$r])) {
            $ordered_posts[] = $ref_to_post[$r];
        }
    }

    if (empty($ordered_posts)) return '<p>No planning applications found.</p>';

    $uid = 'pi-featuredapps-carousel-' . uniqid();

    wp_enqueue_style('pi-featuredapps-carousel-css', plugin_dir_url(__FILE__) . 'assets/pi-carousel.css', [], '6.3');
    wp_enqueue_script('pi-featuredapps-carousel-js', plugin_dir_url(__FILE__) . 'assets/pi-carousel.js', [], '6.3', true);

    ob_start();
    ?>

    <div id="<?php echo $uid; ?>" class="pi-featuredapps-carousel" data-pi-featuredapps-carousel>
        <div class="pi-featuredapps-track">
            <?php foreach ($ordered_posts as $p):
                $meta = get_post_meta($p->ID);
                $ref       = esc_html($meta['council_reference'][0] ?? '');
                $authority = esc_html($meta['authority_name'][0] ?? '');
                $date      = esc_html($meta['date_received'][0] ?? '');
                $info_url  = esc_url($meta['info_url'][0] ?? '#');
                $address   = !empty($meta['address'][0]) ? esc_html($meta['address'][0]) : esc_html(get_the_title($p));
                $content   = wpautop($p->post_content);
            ?>
                <article class="pi-featuredapps-card">
                    <div class="pi-featuredapps-card-inner">
                        <div class="pi-featuredapps-card-title"><?php echo $address; ?></div>
                        <div class="pi-featuredapps-card-meta">
                            <div><?php echo $authority; ?></div>
                            <div><?php echo $ref; ?></div>
                            <div><?php echo $date; ?></div>
                        </div>
                        <div class="pi-featuredapps-card-desc"><?php echo $content; ?></div>
                        <div class="pi-featuredapps-card-actions">
                            <a class="pi-featuredapps-view-original" href="<?php echo $info_url; ?>" target="_blank" rel="noopener">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M12 15a3 3 0 100-6 3 3 0 000 6z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>

    <?php
    return ob_get_clean();
});
