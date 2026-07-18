<?php
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('pi-frontend-css', plugin_dir_url(__FILE__) . '../assets/frontend.css', [], '2.6');
    
    // Mapbox GL CSS and JS
    wp_enqueue_style('mapbox-gl-css', 'https://api.mapbox.com/mapbox-gl-js/v3.0.1/mapbox-gl.css', [], '3.0.1');
    wp_enqueue_script('mapbox-gl-js', 'https://api.mapbox.com/mapbox-gl-js/v3.0.1/mapbox-gl.js', [], '3.0.1', true);
    
    wp_enqueue_script('pi-frontend-js', plugin_dir_url(__FILE__) . '../assets/frontend.js', ['jquery', 'mapbox-gl-js'], '2.6', true);
    
    // Get Mapbox token from options (set in WP admin or wp-config.php)
    $mapbox_token = defined('PI_MAPBOX_TOKEN') ? PI_MAPBOX_TOKEN : get_option('pi_mapbox_token', '');
    
    wp_localize_script('pi-frontend-js', 'PI_Settings', [
        'rest_base' => rest_url('wp/v2/planning_app'),
        'nonce' => wp_create_nonce('wp_rest'),
        'mapbox_token' => $mapbox_token,
    ]);
});

// Shortcode: [planning_index_search]
add_shortcode('planning_index_search', function($atts) {
    ob_start(); ?>
    <div id="pi-search">
      <!-- Header with My Apps button -->
      <div class="pi-header">
        <div class="pi-header-row">
          <div class="pi-header-content">
            <h1 class="pi-title">Planning Applications</h1>
            <p class="pi-subtitle">Search and track construction planning applications</p>
          </div>
          <button class="pi-my-apps-btn" id="pi-open-my-apps">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
            </svg>
            <span>My Apps</span>
            <span class="pi-my-apps-count" id="pi-my-apps-count" style="display:none;">0</span>
          </button>
        </div>
      </div>

      <!-- Main Search Bar -->
      <div class="pi-search-bar">
        <div class="pi-search-input-wrap">
          <svg class="pi-search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"></circle>
            <path d="m21 21-4.35-4.35"></path>
          </svg>
          <input type="text" id="pi-keyword" placeholder="Search by keyword, address, or reference..." />
        </div>
        <button id="pi-search-btn">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"></circle>
            <path d="m21 21-4.35-4.35"></path>
          </svg>
          Search
        </button>
      </div>

      <!-- Advanced Filters (collapsible) -->
      <div class="pi-filters-section">
        <button id="pi-toggle-filters" class="pi-toggle-btn">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
          </svg>
          Filters
          <span class="pi-filter-count" id="pi-filter-count"></span>
          <svg class="pi-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="6 9 12 15 18 9"></polyline>
          </svg>
        </button>
        
        <div class="pi-filters" id="pi-filters-panel">
          <div class="pi-filter-group pi-filter-group-authority">
            <label>Authority</label>
            <div class="pi-multiselect" id="pi-authority-multiselect">
              <button type="button" class="pi-multiselect-toggle" id="pi-authority-toggle">
                <span class="pi-multiselect-label">All authorities</span>
                <svg class="pi-multiselect-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
              </button>
              <div class="pi-multiselect-dropdown" id="pi-authority-dropdown">
                <div class="pi-multiselect-search-wrap">
                  <input type="text" class="pi-multiselect-search" id="pi-authority-search" placeholder="Search authorities..." />
                </div>
                <div class="pi-multiselect-actions">
                  <button type="button" class="pi-multiselect-action" id="pi-authority-select-all">Select All</button>
                  <button type="button" class="pi-multiselect-action" id="pi-authority-clear-all">Clear All</button>
                </div>
                <div class="pi-multiselect-options" id="pi-authority-options">
                  <div class="pi-multiselect-loading">Loading authorities...</div>
                </div>
              </div>
            </div>
          </div>
          <div class="pi-filter-group">
            <label for="pi-category">Category</label>
            <select id="pi-category">
              <option value="">All categories</option>
            </select>
          </div>
          <div class="pi-filter-group">
            <label for="pi-date-from">From date</label>
            <input type="date" id="pi-date-from" />
          </div>
          <div class="pi-filter-group">
            <label for="pi-date-to">To date</label>
            <input type="date" id="pi-date-to" />
          </div>
          <button id="pi-clear-filters" class="pi-clear-btn">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M18 6L6 18M6 6l12 12"></path>
            </svg>
            Clear all
          </button>
        </div>
      </div>

      <!-- Quick Filters -->
      <div class="pi-quick-filters">
        <span class="pi-quick-label">Quick:</span>
        <button class="pi-quick-chip" data-filter="date" data-value="7">This Week</button>
        <button class="pi-quick-chip" data-filter="date" data-value="30">This Month</button>
        <button class="pi-quick-chip" data-filter="keyword" data-value="extension">Extensions</button>
        <button class="pi-quick-chip" data-filter="keyword" data-value="window door">Windows/Doors</button>
        <button class="pi-quick-chip" data-filter="keyword" data-value="new build dwelling">New Builds</button>
      </div>

      <!-- Toolbar: Sort + Saved Searches + Results Count + View Toggle -->
      <div class="pi-toolbar">
        <div class="pi-results-info">
          <span id="pi-results-count">Loading...</span>
        </div>
        
        <div class="pi-toolbar-actions">
          <!-- View Toggle -->
          <div class="pi-view-toggle" id="pi-view-toggle">
            <button class="pi-view-btn active" data-view="grid" title="Grid View">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="7" height="7"></rect>
                <rect x="14" y="3" width="7" height="7"></rect>
                <rect x="3" y="14" width="7" height="7"></rect>
                <rect x="14" y="14" width="7" height="7"></rect>
              </svg>
              <span>Grid</span>
            </button>
            <button class="pi-view-btn" data-view="map" title="Map View">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                <circle cx="12" cy="10" r="3"></circle>
              </svg>
              <span>Map</span>
            </button>
          </div>

          <!-- Saved Searches -->
          <div class="pi-saved-searches">
            <button id="pi-save-search-btn" class="pi-toolbar-btn" title="Save current search">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
              </svg>
              Save
            </button>
            <div class="pi-saved-dropdown" id="pi-saved-dropdown">
              <div class="pi-saved-header">Saved Searches</div>
              <div id="pi-saved-list"></div>
            </div>
          </div>

          <!-- Sort -->
          <div class="pi-sort-wrap">
            <label for="pi-sort">Sort:</label>
            <select id="pi-sort">
              <option value="date_desc">Newest first</option>
              <option value="date_asc">Oldest first</option>
              <option value="alpha_asc">A-Z by address</option>
              <option value="alpha_desc">Z-A by address</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Results Grid -->
      <div id="pi-results" class="pi-grid"></div>

      <!-- Map View Container -->
      <div id="pi-map-container" class="pi-map-container" style="display:none;">
        <div id="pi-map" class="pi-map"></div>
        <div id="pi-map-loading" class="pi-map-loading">
          <svg class="pi-map-spinner" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 12a9 9 0 11-6.219-8.56"></path>
          </svg>
          <span>Loading map...</span>
        </div>
        <div id="pi-map-no-token" class="pi-map-notice" style="display:none;">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
            <circle cx="12" cy="10" r="3"></circle>
          </svg>
          <h3>Map Not Available</h3>
          <p>Mapbox token not configured. Please add your token to enable the map view.</p>
        </div>
      </div>

      <!-- Loading Skeleton -->
      <div id="pi-skeleton" class="pi-grid pi-skeleton-grid" style="display:none;">
        <div class="pi-skeleton-card"><div class="pi-skel-title"></div><div class="pi-skel-meta"></div><div class="pi-skel-desc"></div><div class="pi-skel-actions"></div></div>
        <div class="pi-skeleton-card"><div class="pi-skel-title"></div><div class="pi-skel-meta"></div><div class="pi-skel-desc"></div><div class="pi-skel-actions"></div></div>
        <div class="pi-skeleton-card"><div class="pi-skel-title"></div><div class="pi-skel-meta"></div><div class="pi-skel-desc"></div><div class="pi-skel-actions"></div></div>
        <div class="pi-skeleton-card"><div class="pi-skel-title"></div><div class="pi-skel-meta"></div><div class="pi-skel-desc"></div><div class="pi-skel-actions"></div></div>
        <div class="pi-skeleton-card"><div class="pi-skel-title"></div><div class="pi-skel-meta"></div><div class="pi-skel-desc"></div><div class="pi-skel-actions"></div></div>
        <div class="pi-skeleton-card"><div class="pi-skel-title"></div><div class="pi-skel-meta"></div><div class="pi-skel-desc"></div><div class="pi-skel-actions"></div></div>
      </div>

      <!-- Empty State -->
      <div id="pi-empty" class="pi-empty-state" style="display:none;">
        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <circle cx="11" cy="11" r="8"></circle>
          <path d="m21 21-4.35-4.35"></path>
        </svg>
        <h3>No applications found</h3>
        <p>Try adjusting your search or filters</p>
      </div>

      <!-- Load More -->
      <div id="pi-load-more-wrap">
        <button id="pi-load-more" data-page="1">
          <span class="pi-load-text">Load more</span>
          <svg class="pi-load-spinner" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 12a9 9 0 11-6.219-8.56"></path>
          </svg>
        </button>
      </div>
    </div>

    <!-- Details Modal -->
    <div id="pi-modal" class="pi-modal-overlay" style="display:none;">
      <div class="pi-modal">
        <button class="pi-modal-close" id="pi-modal-close">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 6L6 18M6 6l12 12"></path>
          </svg>
        </button>
        <div class="pi-modal-content">
          <div class="pi-modal-header">
            <span class="pi-modal-council" id="pi-modal-council"></span>
            <h2 class="pi-modal-title" id="pi-modal-title"></h2>
          </div>
          <div class="pi-modal-meta">
            <div class="pi-modal-meta-item">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
              </svg>
              <span id="pi-modal-date"></span>
            </div>
            <div class="pi-modal-meta-item">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
              </svg>
              <span id="pi-modal-ref"></span>
            </div>
          </div>
          <div class="pi-modal-body" id="pi-modal-body"></div>
          <div class="pi-modal-actions">
            <button class="pi-modal-add-btn" id="pi-modal-add">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 5v14M5 12h14"></path>
              </svg>
              Workspace
            </button>
            <a class="pi-modal-link" id="pi-modal-link" href="#" target="_blank" rel="noopener">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                <polyline points="15 3 21 3 21 9"></polyline>
                <line x1="10" y1="14" x2="21" y2="3"></line>
              </svg>
              View on Council Site
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- Save Search Modal -->
    <div id="pi-save-modal" class="pi-modal-overlay" style="display:none;">
      <div class="pi-modal pi-modal-sm">
        <button class="pi-modal-close" id="pi-save-modal-close">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 6L6 18M6 6l12 12"></path>
          </svg>
        </button>
        <div class="pi-modal-content">
          <h2 class="pi-modal-title">Save Search</h2>
          <p class="pi-save-desc">Give this search a name to quickly access it later.</p>
          <input type="text" id="pi-save-name" placeholder="e.g. Extensions in Manchester" class="pi-save-input" />
          <div class="pi-save-actions">
            <button id="pi-save-cancel" class="pi-btn-secondary">Cancel</button>
            <button id="pi-save-confirm" class="pi-btn-primary">Save Search</button>
          </div>
        </div>
      </div>
    </div>

    <!-- My Apps Sidebar (Saved & Recently Viewed) - Slides in from right -->
    <div id="pi-my-apps-overlay" class="pi-sidebar-overlay"></div>
    <aside id="pi-my-apps-panel" class="pi-sidebar">
      <div class="pi-sidebar-header">
        <h2 class="pi-sidebar-title">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
          </svg>
          My Apps
        </h2>
        <button class="pi-sidebar-close" id="pi-my-apps-close" title="Close sidebar">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 6L6 18M6 6l12 12"></path>
          </svg>
        </button>
      </div>
      
      <!-- Tabs -->
      <div class="pi-sidebar-tabs">
        <button class="pi-sidebar-tab active" data-tab="saved">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
          </svg>
          Saved
          <span class="pi-sidebar-tab-count" id="pi-saved-count">0</span>
        </button>
        <button class="pi-sidebar-tab" data-tab="recent">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <polyline points="12 6 12 12 16 14"></polyline>
          </svg>
          Recently Viewed
          <span class="pi-sidebar-tab-count" id="pi-recent-count">0</span>
        </button>
      </div>

      <!-- Tab Content -->
      <div class="pi-sidebar-content">
        <!-- Saved Apps -->
        <div class="pi-sidebar-pane active" id="pi-pane-saved">
          <div class="pi-apps-loading" id="pi-saved-loading">
            <svg class="pi-spinner" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 12a9 9 0 11-6.219-8.56"></path>
            </svg>
            Loading saved apps...
          </div>
          <div class="pi-apps-empty" id="pi-saved-empty" style="display:none;">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
            </svg>
            <h4>No saved apps yet</h4>
            <p>Click the bookmark icon on any planning application to save it here for quick access.</p>
          </div>
          <div class="pi-apps-list" id="pi-saved-apps-list"></div>
        </div>

        <!-- Recently Viewed -->
        <div class="pi-sidebar-pane" id="pi-pane-recent">
          <div class="pi-apps-loading" id="pi-recent-loading">
            <svg class="pi-spinner" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 12a9 9 0 11-6.219-8.56"></path>
            </svg>
            Loading recent apps...
          </div>
          <div class="pi-apps-empty" id="pi-recent-empty" style="display:none;">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
              <circle cx="12" cy="12" r="10"></circle>
              <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
            <h4>No recently viewed apps</h4>
            <p>Apps you view will appear here automatically. Click "Details" on any planning application to view it.</p>
          </div>
          <div class="pi-apps-list" id="pi-recent-apps-list"></div>
        </div>
      </div>
    </aside>

    <?php
    return ob_get_clean();
});
