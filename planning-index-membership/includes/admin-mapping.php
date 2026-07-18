<?php
// Admin UI for mapping PMPro levels -> authority terms

add_action('admin_menu', function() {
    add_submenu_page(
        'options-general.php',
        'PI Membership Mapping',
        'PI Membership Mapping',
        'manage_options',
        'pi-membership-mapping',
        'pi_membership_mapping_page'
    );
});

function pi_get_pmpro_levels() {
    // If PMPro not installed, return empty
    if ( ! function_exists('pmpro_getAllLevels') ) {
        return [];
    }
    $levels = pmpro_getAllLevels(true, true);
    // normalize to array of (id => name)
    $out = [];
    foreach($levels as $l) {
        $out[$l->id] = $l->name;
    }
    return $out;
}

function pi_get_authority_terms() {
    $terms = get_terms([
        'taxonomy' => 'authority',
        'hide_empty' => false,
    ]);
    $out = [];
    foreach($terms as $t) {
        $out[$t->term_id] = $t->name;
    }
    return $out;
}

function pi_membership_mapping_page() {
    if (!current_user_can('manage_options')) wp_die('No.');

    $levels = pi_get_pmpro_levels();
    $terms = pi_get_authority_terms();

    $saved = get_option(PI_MEM_OPTION, []);
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('pi_map_save', 'pi_map_nonce')) {
        $new = [];
        foreach ($levels as $id => $name) {
            $key = "level_{$id}";
            $sel = isset($_POST[$key]) && is_array($_POST[$key]) ? array_map('intval', $_POST[$key]) : [];
            $new[$id] = $sel;
        }
        update_option(PI_MEM_OPTION, $new, false);
        $saved = $new;
        echo '<div class="updated"><p>Mapping saved.</p></div>';
    }

    ?>
    <div class="wrap">
      <h1>Planning Index — Membership → Authorities mapping</h1>
      <?php if (empty($levels)): ?>
        <div class="notice notice-warning"><p><strong>PMPro not detected</strong>. Install/activate Paid Memberships Pro or create membership levels first.</p></div>
      <?php endif; ?>

      <form method="post">
        <?php wp_nonce_field('pi_map_save', 'pi_map_nonce'); ?>

        <table class="form-table" role="presentation">
          <thead><tr><th>PMPro Level</th><th>Mapped Authorities (select)</th></tr></thead>
          <tbody>
          <?php foreach ($levels as $id => $name): ?>
            <tr>
              <th scope="row"><?php echo esc_html($name); ?> (ID: <?php echo intval($id); ?>)</th>
              <td>
                <select name="level_<?php echo intval($id); ?>[]" multiple style="min-width:400px; min-height:120px;">
                  <?php
                    $selected = isset($saved[$id]) ? (array)$saved[$id] : [];
                    foreach ($terms as $tid => $tname):
                      $sel = in_array($tid, $selected) ? 'selected' : '';
                  ?>
                    <option value="<?php echo intval($tid); ?>" <?php echo $sel; ?>><?php echo esc_html($tname); ?></option>
                  <?php endforeach; ?>
                </select>
                <p class="description">Hold Ctrl/Cmd to select multiple. Leave empty = no access.</p>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>

        <?php submit_button('Save mapping'); ?>
      </form>

      <h2>Tips</h2>
      <ul>
        <li>Map each PMPro level to the authority terms (councils) that membership should unlock.</li>
        <li>For "Enterprise", create a PMPro level and leave it mapped to <strong>all</strong> authorities by selecting them all — or we can detect an "enterprise" special level (see notes below).</li>
      </ul>
    </div>
    <?php
}
