<?php
/**
 * Admin Page View
 */
if (!defined('ABSPATH')) {
    exit;
}

$page       = max(1, intval($_GET['pima_page'] ?? 1));
$total_pages = ceil($total / 50);
$has_more    = count($members) >= 50;
?>
<div class="wrap pima-admin-wrap">
    <h1>Planning Index Members</h1>
    <p>View and manage all active membership owners in one place.</p>

    <div class="pima-toolbar">
        <form method="get" class="pima-filters">
            <input type="hidden" name="page" value="planning-index-members">

            <label>
                <span>Search</span>
                <input type="text" name="pima_search" value="<?php echo esc_attr($search); ?>" placeholder="Name, email, username">
            </label>

            <label>
                <span>Level</span>
                <select name="pima_level">
                    <option value="0">All Levels</option>
                    <?php foreach ($levels as $level): ?>
                        <option value="<?php echo intval($level->id); ?>" <?php selected($filter_level, $level->id); ?>>
                            <?php echo esc_html($level->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>Type</span>
                <select name="pima_type">
                    <option value="">All Types</option>
                    <option value="Per-Council" <?php selected($filter_type, 'Per-Council'); ?>>Per-Council</option>
                    <option value="Regional Bundle" <?php selected($filter_type, 'Regional Bundle'); ?>>Regional Bundle</option>
                    <option value="Enterprise" <?php selected($filter_type, 'Enterprise'); ?>>Enterprise</option>
                    <option value="Trial" <?php selected($filter_type, 'Trial'); ?>>Trial</option>
                </select>
            </label>

            <button type="submit" class="button">Filter</button>
            <a href="<?php echo admin_url('admin.php?page=planning-index-members'); ?>" class="button">Reset</a>
        </form>

        <button id="pima-export-csv" class="button button-primary">Download CSV</button>
    </div>

    <form id="pima-export-form" method="post" action="<?php echo admin_url('admin-ajax.php'); ?>" style="display:none;">
        <input type="hidden" name="action" value="pima_export_csv">
        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('pima_admin_nonce'); ?>">
    </form>

    <table class="wp-list-table widefat fixed striped pima-members-table">
        <thead>
            <tr>
                <th>User ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Level</th>
                <th>Type</th>
                <th>Councils</th>
                <th>Template</th>
                <th>Price</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($members)): ?>
                <tr>
                    <td colspan="9">No active members found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($members as $m): ?>
                    <tr>
                        <td><?php echo intval($m->user_id); ?></td>
                        <td>
                            <strong><?php echo esc_html($m->display_name); ?></strong><br>
                            <small><?php echo esc_html($m->user_login); ?></small>
                        </td>
                        <td><?php echo esc_html($m->user_email); ?></td>
                        <td>
                            <?php echo esc_html($m->level_name); ?><br>
                            <small class="pima-status pima-status-<?php echo esc_attr(strtolower($m->membership_status)); ?>">
                                <?php echo esc_html(ucfirst($m->membership_status)); ?>
                            </small>
                        </td>
                        <td>
                            <?php echo esc_html($m->product_type); ?>
                            <?php if ($m->region_bundle && $m->product_type !== 'Per-Council'): ?>
                                <br><small><?php echo esc_html($m->region_bundle); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $count = count($m->councils_selected);
                            echo intval($count) . ' selected';
                            if (!empty($m->councils_allowed) && $m->councils_allowed !== $m->councils_selected) {
                                echo '<br><small>' . count($m->councils_allowed) . ' allowed</small>';
                            }
                            ?>
                        </td>
                        <td><?php echo esc_html($m->template ?: '—'); ?></td>
                        <td>
                            <?php if ($m->price): ?>
                                £<?php echo number_format(floatval($m->price), 2); ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="button pima-edit-btn" data-user-id="<?php echo intval($m->user_id); ?>">Edit</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($page > 1 || $has_more): ?>
        <div class="tablenav">
            <div class="tablenav-pages">
                <span class="displaying-num">Results</span>
                <span class="pagination-links">
                    <?php
                    $base_url = admin_url('admin.php?page=planning-index-members');
                    if ($search) $base_url .= '&pima_search=' . urlencode($search);
                    if ($filter_level) $base_url .= '&pima_level=' . intval($filter_level);
                    if ($filter_type) $base_url .= '&pima_type=' . urlencode($filter_type);

                    if ($page > 1):
                        ?>
                        <a class="button" href="<?php echo esc_url($base_url . '&pima_page=' . ($page - 1)); ?>">&laquo; Prev</a>
                    <?php endif; ?>

                    <span class="paging-input">
                        <span class="tablenav-paging-text">Page <?php echo intval($page); ?></span>
                    </span>

                    <?php if ($has_more): ?>
                        <a class="button" href="<?php echo esc_url($base_url . '&pima_page=' . ($page + 1)); ?>">Next &raquo;</a>
                    <?php endif; ?>
                </span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Edit Modal -->
    <div id="pima-modal" class="pima-modal" style="display:none;">
        <div class="pima-modal-content">
            <div class="pima-modal-header">
                <h2>Edit Member</h2>
                <button type="button" class="pima-modal-close">&times;</button>
            </div>
            <div class="pima-modal-body">
                <form id="pima-edit-form">
                    <input type="hidden" name="user_id" id="pima-edit-user-id" value="">

                    <div class="pima-field-group">
                        <label>User</label>
                        <div id="pima-edit-user-display" class="pima-readonly-field"></div>
                    </div>

                    <div class="pima-field-group">
                        <label>Membership Level</label>
                        <div id="pima-edit-level-display" class="pima-readonly-field"></div>
                    </div>

                    <div class="pima-field-group">
                        <label>Product Type</label>
                        <div id="pima-edit-type-display" class="pima-readonly-field"></div>
                    </div>

                    <div class="pima-field-group">
                        <label for="pima-edit-status">Membership Status</label>
                        <select name="membership_status" id="pima-edit-status">
                            <option value="active">Active</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="expired">Expired</option>
                            <option value="admin_cancelled">Admin Cancelled</option>
                        </select>
                    </div>

                    <div class="pima-field-group">
                        <label for="pima-edit-councils">Councils Selected</label>
                        <select name="councils[]" id="pima-edit-councils" multiple="multiple" size="8"></select>
                        <p class="description">Hold Ctrl/Cmd to select multiple. Updates both selected and allowed councils.</p>
                    </div>

                    <div class="pima-field-group">
                        <label for="pima-edit-template">Template</label>
                        <select name="template" id="pima-edit-template"></select>
                    </div>

                    <div class="pima-field-group">
                        <label for="pima-edit-price">Price (£)</label>
                        <input type="number" name="price" id="pima-edit-price" step="0.01" min="0">
                    </div>

                    <hr>

                    <div class="pima-field-group">
                        <label for="pima-edit-company">Company Name</label>
                        <input type="text" name="business_info[company_name]" id="pima-edit-company">
                    </div>

                    <div class="pima-field-group">
                        <label for="pima-edit-bemail">Business Email</label>
                        <input type="email" name="business_info[email]" id="pima-edit-bemail">
                    </div>

                    <div class="pima-field-group">
                        <label for="pima-edit-phone">Business Phone</label>
                        <input type="text" name="business_info[phone]" id="pima-edit-phone">
                    </div>

                    <div class="pima-field-group">
                        <label for="pima-edit-address">Company Address</label>
                        <textarea name="business_info[company_address]" id="pima-edit-address" rows="3"></textarea>
                    </div>

                    <div class="pima-field-group">
                        <label for="pima-edit-website">Website</label>
                        <input type="text" name="business_info[website]" id="pima-edit-website">
                    </div>

                    <div class="pima-field-group">
                        <label for="pima-edit-vat">VAT Number</label>
                        <input type="text" name="business_info[vat_number]" id="pima-edit-vat">
                    </div>
                </form>
            </div>
            <div class="pima-modal-footer">
                <span id="pima-save-message" class="pima-message"></span>
                <button type="button" class="button pima-modal-close">Cancel</button>
                <button type="button" class="button button-primary" id="pima-save-btn">Save Changes</button>
            </div>
        </div>
    </div>
</div>
