(function($) {
  const restBase = PI_Settings.rest_base;
  const mapboxToken = PI_Settings.mapbox_token || 'pk.eyJ1IjoicGxhbm5pbmdpbmRleCIsImEiOiJjbWs4ZnZ6MGUxOWg1M2NyNW9xbnZodWx3In0.SOAFHPon69-aJS2G6qAoBQ';
  let page = 1;
  const perPage = 40;
  let totalResults = 0;
  let currentSort = 'date_desc';
  let allPosts = []; // Store all fetched posts for client-side sorting
  let currentView = 'grid'; // 'grid' or 'map'
  
  // Map-related variables
  let map = null;
  let mapMarkers = [];
  let mapPopup = null;
  let geocodeCache = {}; // Cache for postcode -> coordinates
  const UK_POSTCODE_REGEX = /([A-Z]{1,2}[0-9][0-9A-Z]?\s?[0-9][A-Z]{2})/i;

  // User saved apps tracking
  let userSavedApps = new Set(); // Set of saved post IDs
  let userSavedAppsData = []; // Full app data for saved apps
  let userRecentAppsData = []; // Full app data for recent apps
  // -------------------------------
  // Utils
  // -------------------------------
  function escapeHtml(str) {
    return String(str || '').replace(/[&<>"']/g, function(m) {
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]);
    });
  }
  function getEstPrice(meta) {
      if (!meta) return '';
      // Extract just the price (e.g. "£150,000")
      const badge = meta.ai_badge || meta.est_value || '';
      if (!badge) return '';
      // If badge contains a £ amount, extract it; otherwise return the whole thing
      const match = badge.match(/(£[\d,]+(?:\.\d{2})?(?:\s*[-–]\s*£[\d,]+(?:\.\d{2})?)?)/);
      return match ? match[1] : (meta.est_value || badge);
  }
  function isHighValue(meta) {
      if (!meta) return false;
      const badge = (meta.ai_badge || '').toLowerCase();
      return badge.includes('high value');
  }
  function titleCaseAddress(s) {
    if (!s) return '';
    return String(s).toLowerCase().replace(/(^|[\s\-\/\(\)\,\.])([a-z0-9])/g, function(m, p1, p2){
      return p1 + p2.toUpperCase();
    });
  }

  function elOrEmpty(sel) {
    const $el = $(sel);
    return $el.length ? $el : $();
  }

  function stripHtml(html) {
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    return tmp.textContent || tmp.innerText || '';
  }

  // -------------------------------
  // Postcode Extraction
  // -------------------------------
  function extractPostcode(address) {
    if (!address) return null;
    const match = String(address).match(UK_POSTCODE_REGEX);
    return match ? match[1].toUpperCase().replace(/\s+/g, ' ') : null;
  }
  async function buildSearchUrl(page, perPage) {
    const url = new URL(restBase, location.origin);
    url.searchParams.set('per_page', perPage);
    url.searchParams.set('page', page);
    const kw = $('#pi-keyword').val()?.trim() || '';
    if (kw) url.searchParams.set('search', kw);
    const authorities = getSelectedAuthorities();
    if (authorities.length) url.searchParams.set('authority', authorities.join(','));
    if ($('#pi-category').val()) url.searchParams.set('app_category', $('#pi-category').val());
    if ($('#pi-date-from').val()) url.searchParams.set('date_from', $('#pi-date-from').val());
    if ($('#pi-date-to').val()) url.searchParams.set('date_to', $('#pi-date-to').val());
    return url;
  }

  async function fetchAllForMap() {
    let all = [];
    let page = 1;
    const perPage = 100;
    let total = 0;

    while (true) {
      const url = await buildSearchUrl(page, perPage);
      const resp = await fetch(url.toString(), {
        credentials: 'include',
        headers: { 'X-WP-Nonce': PI_Settings.nonce }
      });
      if (!resp.ok) break;

      const posts = await resp.json();
      all = all.concat(posts);
      total = parseInt(resp.headers.get('X-WP-Total') || '0', 10);

      if (posts.length < perPage || all.length >= total) break;
      page++;
      await new Promise(r => setTimeout(r, 10));
    }
    return { posts: all, total };
  }
  // -------------------------------
  // Geocoding with Cache
  // -------------------------------
  async function geocodePostcode(postcode) {
    if (!postcode || !mapboxToken) return null;
    
    // Check cache first
    const cacheKey = postcode.replace(/\s+/g, '').toUpperCase();
    if (geocodeCache[cacheKey]) {
      return geocodeCache[cacheKey];
    }
    
    try {
      const url = `https://api.mapbox.com/geocoding/v5/mapbox.places/${encodeURIComponent(postcode)}.json?access_token=${mapboxToken}&country=GB&types=postcode&limit=1`;
      const resp = await fetch(url);
      if (!resp.ok) return null;
      
      const data = await resp.json();
      if (data.features && data.features.length > 0) {
        const coords = data.features[0].center; // [lng, lat]
        geocodeCache[cacheKey] = coords;
        // Persist to localStorage for performance
        try {
          localStorage.setItem('pi-geocode-cache', JSON.stringify(geocodeCache));
        } catch (e) { /* ignore storage errors */ }
        return coords;
      }
    } catch (err) {
      console.warn('Geocoding failed for', postcode, err);
    }
    return null;
  }

  // Load geocode cache from localStorage
  function loadGeocodeCache() {
    try {
      const cached = localStorage.getItem('pi-geocode-cache');
      if (cached) {
        geocodeCache = JSON.parse(cached);
      }
    } catch (e) {
      geocodeCache = {};
    }
  }

  // -------------------------------
  // User Saved Apps & Recently Viewed
  // -------------------------------
  async function loadUserSavedApps() {
    try {
      const resp = await fetch('/wp-json/pi/v1/user-apps/saved', {
        credentials: 'include',
        headers: { 'X-WP-Nonce': PI_Settings.nonce }
      });
      if (!resp.ok) return;
      const data = await resp.json();
      userSavedAppsData = data.apps || [];
      userSavedApps = new Set(userSavedAppsData.map(app => String(app.id)));
      updateMyAppsCount();
    } catch (err) {
      console.warn('Failed to load saved apps:', err);
    }
  }

  async function loadUserRecentApps() {
    try {
      const resp = await fetch('/wp-json/pi/v1/user-apps/recent', {
        credentials: 'include',
        headers: { 'X-WP-Nonce': PI_Settings.nonce }
      });
      if (!resp.ok) return;
      const data = await resp.json();
      userRecentAppsData = data.apps || [];
    } catch (err) {
      console.warn('Failed to load recent apps:', err);
    }
  }

  async function saveApp(postId) {
    try {
      const resp = await fetch('/wp-json/pi/v1/user-apps/save', {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': PI_Settings.nonce
        },
        body: JSON.stringify({ post_id: parseInt(postId, 10) })
      });
      if (!resp.ok) throw new Error('Save failed');
      
      userSavedApps.add(String(postId));
      updateSaveButtonUI(postId, true);
      updateMyAppsCount();
      
      // Add to local data from allPosts or fetch fresh data
      const post = allPosts.find(p => String(p.id) === String(postId));
      if (post) {
        // Check if not already in userSavedAppsData
        const alreadyExists = userSavedAppsData.some(app => String(app.id) === String(postId));
        if (!alreadyExists) {
          userSavedAppsData.unshift({
            id: post.id,
            title: post.title?.rendered || '',
            content: post.content?.rendered || '',
            meta: post.meta || {},
            _authority_name: post._authority_name || ''
          });
        }
      } else {
        // If post not in allPosts, refetch saved apps to get the data
        await loadUserSavedApps();
      }
      
      return true;
    } catch (err) {
      console.error('Save app failed:', err);
      return false;
    }
  }

  async function unsaveApp(postId) {
    try {
      const resp = await fetch('/wp-json/pi/v1/user-apps/unsave', {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': PI_Settings.nonce
        },
        body: JSON.stringify({ post_id: parseInt(postId, 10) })
      });
      if (!resp.ok) throw new Error('Unsave failed');
      
      userSavedApps.delete(String(postId));
      updateSaveButtonUI(postId, false);
      updateMyAppsCount();
      
      // Remove from local data
      userSavedAppsData = userSavedAppsData.filter(app => String(app.id) !== String(postId));
      
      return true;
    } catch (err) {
      console.error('Unsave app failed:', err);
      return false;
    }
  }

  async function trackView(postId) {
    try {
      await fetch('/wp-json/pi/v1/user-apps/view', {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': PI_Settings.nonce
        },
        body: JSON.stringify({ post_id: parseInt(postId, 10) })
      });
    } catch (err) {
      // Silent fail for view tracking
    }
  }

  function updateSaveButtonUI(postId, isSaved) {
    const $card = $(`.pi-card[data-id="${postId}"]`);
    const $btn = $card.find('.pi-save-btn');
    
    $card.attr('data-saved', isSaved ? '1' : '0');
    
    const svg = `
      <svg width="20" height="24" viewBox="0 0 20 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
      </svg>
    `;
    
    if (isSaved) {
      $btn.addClass('saved').attr('title', 'Remove from saved').html(svg);
    } else {
      $btn.removeClass('saved').attr('title', 'Save for later').html(svg);
    }
  }

  function updateMyAppsCount() {
    const count = userSavedApps.size;
    const $countBadge = $('#pi-my-apps-count');
    if (count > 0) {
      $countBadge.text(count).show();
    } else {
      $countBadge.hide();
    }
  }

  // -------------------------------
  // My Apps Sidebar
  // -------------------------------
  function openMyAppsPanel() {
    $('#pi-my-apps-overlay').addClass('open');
    $('#pi-my-apps-panel').addClass('open');
    document.body.style.overflow = 'hidden';
    switchMyAppsTab('saved');
  }

  function closeMyAppsPanel() {
    $('#pi-my-apps-overlay').removeClass('open');
    $('#pi-my-apps-panel').removeClass('open');
    document.body.style.overflow = '';
  }

  function switchMyAppsTab(tab) {
    $('.pi-sidebar-tab').removeClass('active');
    $(`.pi-sidebar-tab[data-tab="${tab}"]`).addClass('active');
    $('.pi-sidebar-pane').removeClass('active');
    $(`#pi-pane-${tab}`).addClass('active');

    if (tab === 'saved') {
      loadAndRenderSavedApps();
    } else {
      loadAndRenderRecentApps();
    }
  }

  async function loadAndRenderSavedApps() {
    $('#pi-saved-loading').show();
    $('#pi-saved-empty').hide();
    $('#pi-saved-apps-list').empty();

    await loadUserSavedApps();

    $('#pi-saved-loading').hide();
    $('#pi-saved-count').text(userSavedAppsData.length);

    if (userSavedAppsData.length === 0) {
      $('#pi-saved-empty').show();
      return;
    }

    const html = userSavedAppsData.map(app => renderMyAppCard(app, 'saved')).join('');
    $('#pi-saved-apps-list').html(html);
  }

  async function loadAndRenderRecentApps() {
    $('#pi-recent-loading').show();
    $('#pi-recent-empty').hide();
    $('#pi-recent-apps-list').empty();

    await loadUserRecentApps();

    $('#pi-recent-loading').hide();
    $('#pi-recent-count').text(userRecentAppsData.length);

    if (userRecentAppsData.length === 0) {
      $('#pi-recent-empty').show();
      return;
    }

    const html = userRecentAppsData.map(app => renderMyAppCard(app, 'recent')).join('');
    $('#pi-recent-apps-list').html(html);
  }

  function renderMyAppCard(app, type) {
    const meta = app.meta || {};
    const addr = titleCaseAddress(meta.address || app.title || 'Unknown address');
    const title = escapeHtml(addr);
    const council = escapeHtml(app._authority_name || '');
    const ref = escapeHtml(meta.council_reference || '');
    const date = escapeHtml(meta.date_received || '');
    const postId = String(app.id);
    const isSaved = userSavedApps.has(postId);

    return `
      <div class="pi-my-app-card" data-id="${postId}">
        <div class="pi-my-app-info">
          <div class="pi-my-app-council">${council}</div>
          <div class="pi-my-app-title">${title}</div>
          ${getEstPrice(meta) ? `<div class="pi-card-est-price">${escapeHtml(getEstPrice(meta))}</div>` : ''}
          <div class="pi-my-app-meta">
            <span>${ref}</span>
            ${date ? `<span>• ${date}</span>` : ''}
          </div>
        </div>
        <div class="pi-my-app-actions">
        <button class="pi-my-app-save-btn${isSaved ? ' saved' : ''}" data-postid="${postId}" title="${isSaved ? 'Saved' : 'Save this app'}">
          <svg width="18" height="22" viewBox="0 0 20 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
          </svg>
        </button>
          ${type === 'recent' ? `
            <button class="pi-my-app-save-btn${isSaved ? ' saved' : ''}" data-postid="${postId}" title="${isSaved ? 'Saved' : 'Save this app'}">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="${isSaved ? 'currentColor' : 'none'}" stroke="currentColor" stroke-width="2">
                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
              </svg>
            </button>
          ` : `
            <button class="pi-my-app-remove-btn" data-postid="${postId}" title="Remove from saved">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 6L6 18M6 6l12 12"></path>
              </svg>
            </button>
          `}
          <button class="pi-my-app-view-btn" data-postid="${postId}" title="View details">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
              <polyline points="15 3 21 3 21 9"></polyline>
              <line x1="10" y1="14" x2="21" y2="3"></line>
            </svg>
          </button>
        </div>
      </div>
    `;
  }

  // -------------------------------
  // Saved Searches (localStorage)
  // -------------------------------
  const SAVED_SEARCHES_KEY = 'pi-saved-searches';

  function getSavedSearches() {
    try {
      return JSON.parse(localStorage.getItem(SAVED_SEARCHES_KEY)) || [];
    } catch (e) {
      return [];
    }
  }

  function saveSearch(name, filters) {
    const searches = getSavedSearches();
    searches.push({ id: Date.now(), name, filters });
    localStorage.setItem(SAVED_SEARCHES_KEY, JSON.stringify(searches));
    renderSavedSearches();
  }

  function deleteSearch(id) {
    const searches = getSavedSearches().filter(s => s.id !== id);
    localStorage.setItem(SAVED_SEARCHES_KEY, JSON.stringify(searches));
    renderSavedSearches();
  }

  function renderSavedSearches() {
    const searches = getSavedSearches();
    const $list = $('#pi-saved-list');
    
    if (searches.length === 0) {
      $list.html('<div class="pi-saved-empty">No saved searches yet</div>');
      return;
    }
    
    $list.html(searches.map(s => `
      <div class="pi-saved-item" data-id="${s.id}">
        <span class="pi-saved-item-name">${escapeHtml(s.name)}</span>
        <button class="pi-saved-item-delete" data-id="${s.id}" title="Delete">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 6L6 18M6 6l12 12"></path>
          </svg>
        </button>
      </div>
    `).join(''));
  }

  function getCurrentFilters() {
    return {
      keyword: $('#pi-keyword').val() || '',
      authority: getSelectedAuthorities(),
      category: $('#pi-category').val() || '',
      dateFrom: $('#pi-date-from').val() || '',
      dateTo: $('#pi-date-to').val() || ''
    };
  }

  function applyFilters(filters) {
    $('#pi-keyword').val(filters.keyword || '');
    setSelectedAuthorities(filters.authority || []);
    $('#pi-category').val(filters.category || '');
    $('#pi-date-from').val(filters.dateFrom || '');
    $('#pi-date-to').val(filters.dateTo || '');
    updateFilterCount();
    search(true);
  }

  // -------------------------------
  // Filter Count Badge
  // -------------------------------
  function updateFilterCount() {
    const filters = getCurrentFilters();
    let count = 0;
    if (filters.authority && filters.authority.length > 0) count++;
    if (filters.category) count++;
    if (filters.dateFrom) count++;
    if (filters.dateTo) count++;
    
    const $count = $('#pi-filter-count');
    if (count > 0) {
      $count.text(count).addClass('visible');
    } else {
      $count.removeClass('visible');
    }
  }

  // -------------------------------
  // Multi-select Authority helpers
  // -------------------------------
  let authorityOptions = []; // [{id, name}]

  function getSelectedAuthorities() {
    const checked = [];
    $('#pi-authority-options input[type="checkbox"]:checked').each(function() {
      checked.push($(this).val());
    });
    return checked;
  }

  function setSelectedAuthorities(ids) {
    const idSet = new Set(Array.isArray(ids) ? ids : [ids].filter(Boolean));
    $('#pi-authority-options input[type="checkbox"]').each(function() {
      $(this).prop('checked', idSet.has($(this).val()));
    });
    updateAuthorityToggleLabel();
  }

  function updateAuthorityToggleLabel() {
    const selected = getSelectedAuthorities();
    const $label = $('#pi-authority-multiselect .pi-multiselect-label');
    if (selected.length === 0) {
      $label.text('All authorities');
    } else if (selected.length === 1) {
      const name = authorityOptions.find(c => String(c.id) === selected[0])?.name || selected[0];
      $label.text(name);
    } else {
      $label.text(selected.length + ' authorities selected');
    }
  }

  function renderAuthorityOptions(councils, filterText) {
    const $container = $('#pi-authority-options');
    const filter = (filterText || '').toLowerCase();
    const filtered = filter ? councils.filter(c => c.name.toLowerCase().includes(filter)) : councils;

    if (filtered.length === 0) {
      $container.html('<div class="pi-multiselect-no-results">No authorities found</div>');
      return;
    }

    const selected = getSelectedAuthorities();
    const selectedSet = new Set(selected);

    const html = filtered.map(c => {
      const checked = selectedSet.has(String(c.id)) ? ' checked' : '';
      return `<label class="pi-multiselect-option">
        <input type="checkbox" value="${escapeHtml(String(c.id))}"${checked} />
        <span class="pi-multiselect-checkbox"></span>
        <span class="pi-multiselect-option-label">${escapeHtml(c.name)}</span>
      </label>`;
    }).join('');
    $container.html(html);
  }

  // -------------------------------
  // Load allowed councils via REST
  // -------------------------------
  async function loadAllowedAuthorities() {
    const $options = $('#pi-authority-options');
    if (!$options.length) return;

    $options.html('<div class="pi-multiselect-loading">Loading authorities...</div>');

    let councils = [];
    try {
      const res = await fetch('/wp-json/pi/v1/allowed-authorities', {
        credentials: 'include',
        headers: { 'X-WP-Nonce': PI_Settings?.nonce || '' }
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      councils = await res.json();
      if (!Array.isArray(councils)) councils = [];
    } catch (err) {
      console.error('Failed to load allowed councils:', err);
      $options.html('<div class="pi-multiselect-no-results">Error loading authorities</div>');
      return;
    }

    authorityOptions = councils;
    window.piCouncilsMap = new Map(councils.map(c => [String(c.id), c.name]));
    renderAuthorityOptions(councils, '');
  }

  // -------------------------------
  // Card HTML
  // -------------------------------
  function renderCard(post) {
    const meta = post.meta || {};
    const addr = titleCaseAddress(meta.address || post.title?.rendered || post.slug || 'Unknown address');
    const title = escapeHtml(addr);
    const councilId = meta.authority || (post.authority && post.authority[0]) || '';
    const council = escapeHtml(post._authority_name || (window.piCouncilsMap && window.piCouncilsMap.get(String(councilId))) || councilId || '');
    const ref = escapeHtml(meta.council_reference || '');
    const date = escapeHtml(meta.date_received || meta.date_received_raw || '');
    const content = post.content?.rendered || '';
    const plainContent = stripHtml(content);
    const truncatedContent = plainContent.length > 150 ? plainContent.substring(0, 150) + '...' : plainContent;
    const infoUrl = escapeHtml(meta.info_url || '#');
    const postId = String(post.id);
    const isAdded = !!localStorage.getItem('pi-workspace-added-' + postId);
    const isSaved = userSavedApps.has(postId);
    
    const estPrice = getEstPrice(meta);
    const highValue = isHighValue(meta);

    return `
      <article class="pi-card${highValue ? ' pi-card--high-value' : ''}" data-id="${postId}" data-added="${isAdded ? '1' : '0'}" data-saved="${isSaved ? '1' : '0'}">
        <div class="pi-card-inner">
        ${highValue ? `<span class="pi-high-value-tag">High Value</span>` : ''}
        <button class="pi-save-btn${isSaved ? ' saved' : ''}" title="${isSaved ? 'Remove from saved' : 'Save for later'}">
          <svg width="20" height="24" viewBox="0 0 20 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
          </svg>
        </button>
          <div class="pi-card-header">
              <div class="pi-card-council">${council}</div>
              <h3 class="pi-card-title">${title}</h3>
              ${estPrice ? `<div class="pi-card-est-price">${escapeHtml(estPrice)}</div>` : ''}
          </div>
          <div class="pi-card-meta">
            <span class="pi-card-meta-item">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
              </svg>
              ${date}
            </span>
            <span class="pi-card-meta-item">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
              </svg>
              ${ref}
            </span>
          </div>
          <div class="pi-card-desc">${escapeHtml(truncatedContent)}</div>
          <div class="pi-card-actions">
            <button class="pi-card-btn pi-add-to-workspace${isAdded ? ' added' : ''}" data-postid="${postId}" ${isAdded ? 'disabled' : ''}>
              ${isAdded ? `
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M20 6L9 17l-5-5"></path>
                </svg>
                Added
              ` : `
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M12 5v14M5 12h14"></path>
                </svg>
                Workspace
              `}
            </button>
            <button class="pi-card-btn pi-view-details" data-postid="${postId}">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
              </svg>
              Details
            </button>
          </div>
        </div>
      </article>
    `;
  }

  // -------------------------------
  // Sorting
  // -------------------------------
  function sortPosts(posts, sortType) {
    const sorted = [...posts];
    switch (sortType) {
      case 'date_asc':
        sorted.sort((a, b) => {
          const dateA = a.meta?.date_received || '';
          const dateB = b.meta?.date_received || '';
          return dateA.localeCompare(dateB);
        });
        break;
      case 'date_desc':
        sorted.sort((a, b) => {
          const dateA = a.meta?.date_received || '';
          const dateB = b.meta?.date_received || '';
          return dateB.localeCompare(dateA);
        });
        break;
      case 'alpha_asc':
        sorted.sort((a, b) => {
          const addrA = (a.meta?.address || a.title?.rendered || '').toLowerCase();
          const addrB = (b.meta?.address || b.title?.rendered || '').toLowerCase();
          return addrA.localeCompare(addrB);
        });
        break;
      case 'alpha_desc':
        sorted.sort((a, b) => {
          const addrA = (a.meta?.address || a.title?.rendered || '').toLowerCase();
          const addrB = (b.meta?.address || b.title?.rendered || '').toLowerCase();
          return addrB.localeCompare(addrA);
        });
        break;
    }
    return sorted;
  }

  function renderPosts(posts) {
    if (currentView !== 'grid') return;
    const $container = $('#pi-results');
    $container.html('');
    const sorted = sortPosts(posts, currentSort);
    sorted.forEach(p => $container.append(renderCard(p)));
  }

  // -------------------------------
  // Show/Hide UI States
  // -------------------------------
  function showSkeleton() {
    $('#pi-skeleton').show();
    $('#pi-results').hide();
    $('#pi-empty').hide();
  }

  function hideSkeleton() {
    $('#pi-skeleton').hide();
    if (currentView === 'grid') {
      $('#pi-results').show();
    }
  }

  function showEmpty() {
    $('#pi-skeleton').hide();
    $('#pi-results').hide();
    $('#pi-empty').show();
  }

  function updateResultsCount(showing, total) {
    if (total === 0) {
      $('#pi-results-count').text('No results found');
    } else if (currentView === 'map') {
      $('#pi-results-count').text(`Showing all ${total} applications on map`);
    } else if (showing >= total) {
      $('#pi-results-count').text(`Showing all ${total} applications`);
    } else {
      $('#pi-results-count').text(`Showing ${showing} of ${total}+ applications`);
    }
  }

  // -------------------------------
  // Fetch and display apps
  // -------------------------------
  async function search(reset = false) {
    if (reset) {
      page = 1;
      allPosts = [];
    }

    const isMapFullLoad = (currentView === 'map' && reset);

    if (reset) showSkeleton();

    try {
      let posts = [];
      let total = 0;

      if (isMapFullLoad) {
        const result = await fetchAllForMap();
        posts = result.posts;
        total = result.total;
        allPosts = posts;
        totalResults = total;
      } else {
        const url = new URL(restBase, location.origin);
        url.searchParams.set('per_page', perPage);
        url.searchParams.set('page', page);

        const kw = $('#pi-keyword').val()?.trim() || '';
        const authorities = getSelectedAuthorities();
        const cat = $('#pi-category').val();
        const dateFrom = $('#pi-date-from').val();
        const dateTo = $('#pi-date-to').val();

        if (kw) url.searchParams.set('search', kw);
        if (authorities.length > 0) url.searchParams.set('authority', authorities.join(','));
        if (cat) url.searchParams.set('app_category', cat);
        if (dateFrom) url.searchParams.set('date_from', dateFrom);
        if (dateTo) url.searchParams.set('date_to', dateTo);

        const resp = await fetch(url.toString(), {
          credentials: 'include',
          headers: { 'X-WP-Nonce': PI_Settings.nonce }
        });

        if (!resp.ok) throw new Error(`HTTP ${resp.status}`);

        posts = await resp.json();
        const totalHeader = resp.headers.get('X-WP-Total');
        total = totalHeader ? parseInt(totalHeader, 10) : posts.length;

        if (reset) {
          allPosts = posts;
        } else {
          allPosts = allPosts.concat(posts);
        }
        totalResults = total;
      }

      hideSkeleton();

      if (allPosts.length === 0) {
        if (currentView === 'grid') {
          showEmpty();
        } else {
          $('#pi-results').hide();
          $('#pi-empty').hide();
          if (map) updateMapMarkers();
        }
        updateResultsCount(0, 0);
        $('#pi-load-more').hide();
        return;
      }

      if (currentView === 'grid') {
        renderPosts(allPosts);
        $('#pi-results').show();
      } else {
        $('#pi-results').hide();
        $('#pi-empty').hide();
      }

      updateResultsCount(allPosts.length, totalResults);

      if (currentView === 'map') {
        if (map) {
          updateMapMarkers();
        } else {
          initMap();
        }
      }

      const hasMore = !isMapFullLoad && (posts.length >= perPage);
      $('#pi-load-more').toggle(hasMore && currentView === 'grid').removeClass('loading');

    } catch (err) {
      console.error('Search failed:', err);
      hideSkeleton();
      $('#pi-results-count').text('Error loading applications. Check browser console.');
      $('#pi-results').html('<div style="padding:2rem;text-align:center;color:#b91c1c;">Failed to load planning applications. Please refresh the page or check your connection.</div>').show();
    }
  }

  // -------------------------------
  // Modal
  // -------------------------------
  let currentModalPostId = null;

  function openModal(postId, skipTrackView = false) {
    // First try to find in allPosts (from search results)
    let post = allPosts.find(p => String(p.id) === String(postId));
    
    // If not found, try saved apps data
    if (!post) {
      const savedApp = userSavedAppsData.find(a => String(a.id) === String(postId));
      if (savedApp) {
        // Convert savedApp format to post format
        post = {
          id: savedApp.id,
          title: { rendered: savedApp.title || '' },
          content: { rendered: savedApp.content || '<p>No description available</p>' },
          meta: savedApp.meta || {},
          _authority_name: savedApp._authority_name || ''
        };
      }
    }
    
    // If still not found, try recent apps data
    if (!post) {
      const recentApp = userRecentAppsData.find(a => String(a.id) === String(postId));
      if (recentApp) {
        post = {
          id: recentApp.id,
          title: { rendered: recentApp.title || '' },
          content: { rendered: recentApp.content || '<p>No description available</p>' },
          meta: recentApp.meta || {},
          _authority_name: recentApp._authority_name || ''
        };
      }
    }
    
    if (!post) {
      console.warn('Post not found:', postId);
      return;
    }

    currentModalPostId = postId;
    
    // Track this view (for recently viewed - only if not already saved)
    if (!skipTrackView && !userSavedApps.has(String(postId))) {
      trackView(postId);
    }
    const meta = post.meta || {};
    const addr = titleCaseAddress(meta.address || post.title?.rendered || '');
    const councilId = meta.authority || (post.authority && post.authority[0]) || '';
    const council = post._authority_name || (window.piCouncilsMap && window.piCouncilsMap.get(String(councilId))) || councilId || '';
    const ref = meta.council_reference || '';
    const date = meta.date_received || meta.date_received_raw || '';
    const content = post.content?.rendered || '<p>No description available</p>';
    const infoUrl = meta.info_url || '#';
    const isAdded = !!localStorage.getItem('pi-workspace-added-' + postId);
    const estPrice = getEstPrice(meta);

    $('#pi-modal-council').text(council);
    $('#pi-modal-title').text(addr);
    // Remove any previous price element
    $('.pi-modal-est-price').remove();
    if (estPrice) {
      $('#pi-modal-title').after(`<div class="pi-modal-est-price">${escapeHtml(estPrice)}</div>`);
    }
    $('#pi-modal-date').text(date);
    $('#pi-modal-ref').text(ref);
    $('#pi-modal-body').html(content);
    $('#pi-modal-link').attr('href', infoUrl);

    const $addBtn = $('#pi-modal-add');
    if (isAdded) {
      $addBtn.addClass('added').html(`
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M20 6L9 17l-5-5"></path>
        </svg>
        Added to Workspace
      `);
    } else {
      $addBtn.removeClass('added').html(`
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M12 5v14M5 12h14"></path>
        </svg>
        Workspace
      `);
    }

    $('#pi-modal').css('display', 'flex');
    document.body.style.overflow = 'hidden';
  }

  function closeModal() {
    $('#pi-modal').hide();
    document.body.style.overflow = '';
    currentModalPostId = null;
  }

  // -------------------------------
  // Add to Workspace Handler
  // -------------------------------
  async function addToWorkspace(postId, $btn) {
    if (localStorage.getItem('pi-workspace-added-' + postId)) {
      return;
    }

    $btn.prop('disabled', true).addClass('loading');

    try {
      const resp = await fetch('/wp-json/pi/v1/workspace/add', {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': PI_Settings.nonce
        },
        body: JSON.stringify({ post_id: parseInt(postId, 10), stage: 'possible' })
      });

      if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
      const data = await resp.json();

      localStorage.setItem('pi-workspace-added-' + postId, '1');

      // Update card UI
      const $card = $(`.pi-card[data-id="${postId}"]`);
      $card.addClass('pi-card-added').attr('data-added', '1');
      const $cardBtn = $card.find('.pi-add-to-workspace');
      $cardBtn.removeClass('loading').addClass('added').prop('disabled', true).html(`
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M20 6L9 17l-5-5"></path>
        </svg>
        Added
      `);

      // Update modal if open
      if (currentModalPostId === postId) {
        $('#pi-modal-add').addClass('added').html(`
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20 6L9 17l-5-5"></path>
          </svg>
          Added to Workspace
        `);
      }

      // Update map popup button if visible
      const $popupBtn = $(`.pi-popup-add[data-postid="${postId}"]`);
      if ($popupBtn.length) {
        $popupBtn.removeClass('loading').addClass('added').prop('disabled', true).html(`
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20 6L9 17l-5-5"></path>
          </svg>
          Added
        `);
      }

      window.dispatchEvent(new CustomEvent('pi:workspace-updated', { detail: data }));

    } catch (err) {
      console.error('Add to workspace failed', err);
      $btn.prop('disabled', false).removeClass('loading');
    }
  }

  // -------------------------------
  // Quick Filters
  // -------------------------------
  function applyQuickFilter(filterType, value) {
    // Clear other quick filters first
    $('.pi-quick-chip').removeClass('active');
    
    if (filterType === 'date') {
      const days = parseInt(value, 10);
      const today = new Date();
      const fromDate = new Date(today);
      fromDate.setDate(fromDate.getDate() - days);
      
      const formatDate = (d) => d.toISOString().split('T')[0];
      $('#pi-date-from').val(formatDate(fromDate));
      $('#pi-date-to').val(formatDate(today));
      $('#pi-keyword').val('');
    } else if (filterType === 'keyword') {
      $('#pi-keyword').val(value);
      $('#pi-date-from').val('');
      $('#pi-date-to').val('');
    }
    
    updateFilterCount();
    search(true);
  }

  // -------------------------------
  // View Toggle
  // -------------------------------
  // -------------------------------
  // View Toggle
  // -------------------------------
  function switchView(view, skipRefresh = false) {
    if (view === currentView && !skipRefresh) return;
    currentView = view;
    
    // Update toggle buttons
    $('.pi-view-btn').removeClass('active');
    $(`.pi-view-btn[data-view="${view}"]`).addClass('active');
    
    if (view === 'grid') {
      $('#pi-map-container').hide();
      $('#pi-results').show();
      $('#pi-load-more-wrap').show();
      $('#pi-skeleton').parent().find('.pi-skeleton-grid').show();
    } else {
      // MAP VIEW → FORCE FULL LOAD OF EVERY SINGLE APPLICATION
      $('#pi-results').hide();
      $('#pi-skeleton').hide();
      $('#pi-load-more-wrap').hide();
      $('#pi-map-container').show();
      search(true);        // ← THIS IS THE KEY LINE
    }
  }

  // -------------------------------
  // Map Initialization
  // -------------------------------
  function initMap() {
    if (!mapboxToken) {
      $('#pi-map-loading').hide();
      $('#pi-map-no-token').show();
      return;
    }

    if (map) {
      updateMapMarkers();
      return;
    }

    mapboxgl.accessToken = mapboxToken;

    try {
      map = new mapboxgl.Map({
        container: 'pi-map',
        style: 'mapbox://styles/mapbox/light-v11',
        center: [-2.5, 54.0],
        zoom: 5.5,
        antialias: true
      });

      map.addControl(new mapboxgl.NavigationControl({ showCompass: false }), 'top-right');

      map.on('load', () => {
        $('#pi-map-loading').addClass('hidden');

        // GeoJSON source with clustering
        map.addSource('planning-apps', {
          type: 'geojson',
          data: { type: 'FeatureCollection', features: [] },
          cluster: true,
          clusterMaxZoom: 14,
          clusterRadius: 60
        });

        // ── CLUSTER LAYERS ──

        // Outer glow ring
        map.addLayer({
          id: 'cluster-glow',
          type: 'circle',
          source: 'planning-apps',
          filter: ['has', 'point_count'],
          paint: {
            'circle-color': '#1b2534',
            'circle-radius': ['step', ['get', 'point_count'], 30, 10, 38, 50, 48],
            'circle-opacity': 0.08,
            'circle-blur': 0.7
          }
        });

        // Subtle outer ring
        map.addLayer({
          id: 'cluster-ring',
          type: 'circle',
          source: 'planning-apps',
          filter: ['has', 'point_count'],
          paint: {
            'circle-color': 'transparent',
            'circle-radius': ['step', ['get', 'point_count'], 24, 10, 30, 50, 38],
            'circle-stroke-width': 2,
            'circle-stroke-color': 'rgba(27, 37, 52, 0.2)'
          }
        });

        // Main cluster circle
        map.addLayer({
          id: 'clusters',
          type: 'circle',
          source: 'planning-apps',
          filter: ['has', 'point_count'],
          paint: {
            'circle-color': [
              'step', ['get', 'point_count'],
              '#1b2534',    // < 10: navy
              10, '#2d3a4d', // 10-49
              50, '#f97316'  // 50+: orange accent
            ],
            'circle-radius': ['step', ['get', 'point_count'], 18, 10, 24, 50, 32],
            'circle-stroke-width': 3,
            'circle-stroke-color': '#ffffff'
          }
        });

        // Cluster count text
        map.addLayer({
          id: 'cluster-count',
          type: 'symbol',
          source: 'planning-apps',
          filter: ['has', 'point_count'],
          layout: {
            'text-field': ['get', 'point_count_abbreviated'],
            'text-font': ['DIN Pro Medium', 'Arial Unicode MS Bold'],
            'text-size': ['step', ['get', 'point_count'], 13, 10, 14, 50, 16],
            'text-allow-overlap': true
          },
          paint: { 'text-color': '#ffffff' }
        });

        // ── SINGLE POINT LAYERS ──

        // Ambient glow
        map.addLayer({
          id: 'unclustered-glow',
          type: 'circle',
          source: 'planning-apps',
          filter: ['!', ['has', 'point_count']],
          paint: {
            'circle-color': '#f97316',
            'circle-radius': 20,
            'circle-opacity': 0.1,
            'circle-blur': 0.9
          }
        });

        // Outer ring
        map.addLayer({
          id: 'unclustered-ring',
          type: 'circle',
          source: 'planning-apps',
          filter: ['!', ['has', 'point_count']],
          paint: {
            'circle-color': 'transparent',
            'circle-radius': 13,
            'circle-stroke-width': 1.5,
            'circle-stroke-color': 'rgba(249, 115, 22, 0.25)'
          }
        });

        // Main dot
        map.addLayer({
          id: 'unclustered-point',
          type: 'circle',
          source: 'planning-apps',
          filter: ['!', ['has', 'point_count']],
          paint: {
            'circle-color': '#f97316',
            'circle-radius': 7,
            'circle-stroke-width': 2.5,
            'circle-stroke-color': '#ffffff'
          }
        });

        // Inner highlight dot
        map.addLayer({
          id: 'unclustered-dot',
          type: 'circle',
          source: 'planning-apps',
          filter: ['!', ['has', 'point_count']],
          paint: {
            'circle-color': '#ffffff',
            'circle-radius': 2.5,
            'circle-opacity': 0.85
          }
        });

        // ── INTERACTIONS ──

        // Click cluster → zoom in smoothly
        map.on('click', 'clusters', (e) => {
          const features = map.queryRenderedFeatures(e.point, { layers: ['clusters'] });
          if (!features.length) return;
          const clusterId = features[0].properties.cluster_id;
          map.getSource('planning-apps').getClusterExpansionZoom(clusterId, (err, zoom) => {
            if (err) return;
            map.easeTo({
              center: features[0].geometry.coordinates,
              zoom: zoom,
              duration: 500
            });
          });
        });

        // Click single point → popup
        map.on('click', 'unclustered-point', (e) => {
          const props = e.features[0].properties;
          const coords = e.features[0].geometry.coordinates.slice();
          const post = allPosts.find(p => String(p.id) === props.postId);
          if (post) showMapPopup(post, coords);
        });

        // Cursor changes
        ['clusters', 'unclustered-point'].forEach(layer => {
          map.on('mouseenter', layer, () => { map.getCanvas().style.cursor = 'pointer'; });
          map.on('mouseleave', layer, () => { map.getCanvas().style.cursor = ''; });
        });

        updateMapMarkers();
      });

      map.on('error', (e) => {
        console.error('Map error:', e);
        $('#pi-map-loading').hide();
        const msg = e.error ? e.error.message || '' : 'Unknown error';
        $('#pi-map-no-token').find('p').text('Failed to load map. ' + msg);
        $('#pi-map-no-token').show();
      });

    } catch (err) {
      console.error('Map init failed:', err);
      $('#pi-map-loading').hide();
      $('#pi-map-no-token').show();
    }
  }

  // -------------------------------
  // Update Map Markers (GeoJSON source)
  // -------------------------------
  async function updateMapMarkers() {
    if (!map) return;
    const src = map.getSource('planning-apps');
    if (!src) return;

    if (mapPopup) { mapPopup.remove(); mapPopup = null; }

    const postsWithPc = allPosts
      .map(post => {
        const addr = post.meta?.address || post.title?.rendered || '';
        const pc = extractPostcode(addr);
        return pc ? { post, postcode: pc } : null;
      })
      .filter(Boolean);

    if (!postsWithPc.length) {
      src.setData({ type: 'FeatureCollection', features: [] });
      return;
    }

    const features = [];
    const batchSize = 15;

    for (let i = 0; i < postsWithPc.length; i += batchSize) {
      const batch = postsWithPc.slice(i, i + batchSize);
      await Promise.all(batch.map(async ({ post, postcode }) => {
        const coords = await geocodePostcode(postcode);
        if (coords) {
          features.push({
            type: 'Feature',
            geometry: { type: 'Point', coordinates: coords },
            properties: { postId: String(post.id) }
          });
        }
      }));
    }

    src.setData({ type: 'FeatureCollection', features });

    if (features.length) {
      const bounds = new mapboxgl.LngLatBounds();
      features.forEach(f => bounds.extend(f.geometry.coordinates));
      map.fitBounds(bounds, { padding: 60, maxZoom: 14, duration: 800 });
    }
  }

  // -------------------------------
  // Show Map Popup
  // -------------------------------
  function showMapPopup(post, coords) {
    const meta = post.meta || {};
    const addr = titleCaseAddress(meta.address || post.title?.rendered || 'Unknown address');
    const councilId = meta.authority || (post.authority && post.authority[0]) || '';
    const council = escapeHtml(post._authority_name || (window.piCouncilsMap && window.piCouncilsMap.get(String(councilId))) || councilId || '');
    const ref = escapeHtml(meta.council_reference || '');
    const date = escapeHtml(meta.date_received || '');
    const postId = String(post.id);
    const isAdded = !!localStorage.getItem('pi-workspace-added-' + postId);

    if (mapPopup) { mapPopup.remove(); }

    const popupContent = `
      <div class="pi-popup-card">
        <div class="pi-popup-council">${council}</div>
        ${isHighValue(meta) ? `<span class="pi-popup-high-value-tag">High Value</span>` : ''}
        <h3 class="pi-popup-title">${escapeHtml(addr)}</h3>
        ${getEstPrice(meta) ? `<div class="pi-card-est-price">${escapeHtml(getEstPrice(meta))}</div>` : ''}
        <div class="pi-popup-meta">
          <span class="pi-popup-meta-item">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
              <line x1="16" y1="2" x2="16" y2="6"></line>
              <line x1="8" y1="2" x2="8" y2="6"></line>
              <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
            ${date}
          </span>
          <span class="pi-popup-meta-item">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
              <polyline points="14 2 14 8 20 8"></polyline>
            </svg>
            ${ref}
          </span>
        </div>
        <div class="pi-popup-actions">
          <button class="pi-popup-btn pi-popup-add${isAdded ? ' added' : ''}" data-postid="${postId}" ${isAdded ? 'disabled' : ''}>
            ${isAdded ? `
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M20 6L9 17l-5-5"></path>
              </svg>
              Added
            ` : `
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 5v14M5 12h14"></path>
              </svg>
              Workspace
            `}
          </button>
          <button class="pi-popup-btn pi-popup-details" data-postid="${postId}">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
              <circle cx="12" cy="12" r="3"></circle>
            </svg>
            Details
          </button>
        </div>
      </div>
    `;

    mapPopup = new mapboxgl.Popup({
      closeOnClick: true,
      maxWidth: '320px',
      className: 'pi-map-popup',
      offset: 12,
      anchor: 'bottom'
    })
      .setLngLat(coords)
      .setHTML(popupContent)
      .addTo(map);
  }

  // -------------------------------
  // Event Listeners
  // -------------------------------
  $(document).ready(function() {
    // Initialize
    loadGeocodeCache();
    loadAllowedAuthorities();
    renderSavedSearches();

    // Load user saved apps
    loadUserSavedApps();

    // View toggle event handlers
    $(document).on('click', '.pi-view-btn', function() {
      const view = $(this).data('view');
      switchView(view);
    });

    // Save button click (on cards)
    $(document).on('click', '.pi-save-btn', async function(e) {
      e.preventDefault();
      e.stopPropagation();
      const $btn = $(this);
      const postId = $btn.data('postid');
      
      $btn.addClass('loading');
      
      if (userSavedApps.has(String(postId))) {
        await unsaveApp(postId);
      } else {
        await saveApp(postId);
      }
      
      $btn.removeClass('loading');
    });

    // My Apps panel handlers
    $('#pi-open-my-apps').on('click', function() {
      openMyAppsPanel();
    });

    $('#pi-my-apps-close, #pi-my-apps-panel').on('click', function(e) {
      if (e.target === this || $(this).is('#pi-my-apps-close')) {
        closeMyAppsPanel();
      }
    });

    // Prevent clicks inside the modal from closing it
    $('#pi-my-apps-panel .pi-modal').on('click', function(e) {
      e.stopPropagation();
    });

    // Tab switching
    $(document).on('click', '.pi-tab', function() {
      const tab = $(this).data('tab');
      switchMyAppsTab(tab);
    });

    // Save from recent apps list
    $(document).on('click', '.pi-my-app-save-btn', async function(e) {
      e.preventDefault();
      e.stopPropagation();
      const $btn = $(this);
      const postId = $btn.data('postid');
      
      if (!userSavedApps.has(String(postId))) {
        $btn.addClass('loading');
        await saveApp(postId);
        $btn.removeClass('loading').addClass('saved').html(`
          <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2">
            <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
          </svg>
        `);
      }
    });

    // Remove from saved apps list
    $(document).on('click', '.pi-my-app-remove-btn', async function(e) {
      e.preventDefault();
      e.stopPropagation();
      const $btn = $(this);
      const postId = $btn.data('postid');
      const $card = $btn.closest('.pi-my-app-card');
      
      $card.addClass('removing');
      await unsaveApp(postId);
      $card.slideUp(200, function() {
        $(this).remove();
        // Update count
        $('#pi-saved-count').text(userSavedAppsData.length);
        if (userSavedAppsData.length === 0) {
          $('#pi-saved-empty').show();
        }
      });
    });

    // View app from My Apps panel
    $(document).on('click', '.pi-my-app-view-btn', function(e) {
      e.preventDefault();
      e.stopPropagation();
      const postId = $(this).data('postid');
      
      // Get the app data and add to allPosts if not there
      const savedApp = userSavedAppsData.find(a => String(a.id) === String(postId));
      const recentApp = userRecentAppsData.find(a => String(a.id) === String(postId));
      const appData = savedApp || recentApp;
      
      if (appData && !allPosts.find(p => String(p.id) === String(postId))) {
        // Add to allPosts temporarily so modal can display it
        allPosts.push({
          id: appData.id,
          title: { rendered: appData.title },
          meta: appData.meta,
          content: { rendered: appData.content || '' },
          _authority_name: appData._authority_name
        });
      }
      
      closeMyAppsPanel();
      setTimeout(() => {
        openModal(postId, true); // Skip tracking since they're accessing from saved/recent
      }, 100);
    });

    // Map popup event handlers (delegated)
    $(document).on('click', '.pi-popup-add', function(e) {
      e.preventDefault();
      e.stopPropagation();
      const postId = $(this).data('postid');
      if (!localStorage.getItem('pi-workspace-added-' + postId)) {
        addToWorkspace(postId, $(this));
      }
    });

    $(document).on('click', '.pi-popup-details', function(e) {
      e.preventDefault();
      e.stopPropagation();
      const postId = $(this).data('postid');
      openModal(postId);
      if (mapPopup) {
        mapPopup.remove();
        mapPopup = null;
      }
    });

    if ($('#pi-results').length) {
      // Search button
      $('#pi-search-btn').on('click', e => {
        e.preventDefault();
        $('.pi-quick-chip').removeClass('active');
        search(true);
      });

      // Enter key in search
      $('#pi-keyword').on('keypress', e => {
        if (e.which === 13) {
          e.preventDefault();
          $('.pi-quick-chip').removeClass('active');
          search(true);
        }
      });

      // Load more
      $('#pi-load-more').on('click', e => {
        e.preventDefault();
        $(e.currentTarget).addClass('loading');
        page++;
        search(false);
      });

      // Toggle filters
      $('#pi-toggle-filters').on('click', function() {
        $(this).toggleClass('active');
        $('#pi-filters-panel').toggleClass('open');
      });

      // Clear filters
      $('#pi-clear-filters').on('click', function() {
        setSelectedAuthorities([]);
        $('#pi-category').val('');
        $('#pi-date-from').val('');
        $('#pi-date-to').val('');
        $('.pi-quick-chip').removeClass('active');
        updateFilterCount();
        search(true);
      });

      // Filter change triggers update count
      $('#pi-category, #pi-date-from, #pi-date-to').on('change', updateFilterCount);

      // Authority multi-select toggle
      $('#pi-authority-toggle').on('click', function(e) {
        e.stopPropagation();
        $('#pi-authority-dropdown').toggleClass('open');
        $(this).toggleClass('open');
      });

      // Close dropdown on outside click
      $(document).on('click', function(e) {
        if (!$(e.target).closest('#pi-authority-multiselect').length) {
          $('#pi-authority-dropdown').removeClass('open');
          $('#pi-authority-toggle').removeClass('open');
        }
      });

      // Search within authority options
      $('#pi-authority-search').on('input', function() {
        renderAuthorityOptions(authorityOptions, $(this).val());
      });

      // Select/Clear all
      $('#pi-authority-select-all').on('click', function() {
        $('#pi-authority-options input[type="checkbox"]').prop('checked', true);
        updateAuthorityToggleLabel();
        updateFilterCount();
      });
      $('#pi-authority-clear-all').on('click', function() {
        $('#pi-authority-options input[type="checkbox"]').prop('checked', false);
        updateAuthorityToggleLabel();
        updateFilterCount();
      });

      // Checkbox change
      $(document).on('change', '#pi-authority-options input[type="checkbox"]', function() {
        updateAuthorityToggleLabel();
        updateFilterCount();
      });

      // Quick filters
      $(document).on('click', '.pi-quick-chip', function() {
        const $chip = $(this);
        const isActive = $chip.hasClass('active');
        
        $('.pi-quick-chip').removeClass('active');
        
        if (!isActive) {
          $chip.addClass('active');
          applyQuickFilter($chip.data('filter'), $chip.data('value'));
        } else {
          // Clear the quick filter
          $('#pi-keyword').val('');
          $('#pi-date-from').val('');
          $('#pi-date-to').val('');
          updateFilterCount();
          search(true);
        }
      });

      // Sort change
      $('#pi-sort').on('change', function() {
        currentSort = $(this).val();
        if (allPosts.length > 0) {
          renderPosts(allPosts);
        }
      });

      // Initial search
      search(true);
    }

    // View details button
    $(document).on('click', '.pi-view-details', function() {
      const postId = $(this).data('postid');
      openModal(postId);
    });

    // Modal close
    $('#pi-modal-close, #pi-modal').on('click', function(e) {
      if (e.target === this || $(this).is('#pi-modal-close')) {
        closeModal();
      }
    });

    // Prevent modal content click from closing
    $('.pi-modal').on('click', function(e) {
      e.stopPropagation();
    });

    // ESC to close modal
    $(document).on('keydown', function(e) {
      if (e.key === 'Escape') {
        closeModal();
        $('#pi-save-modal').hide();
        $('#pi-saved-dropdown').removeClass('open');
      }
    });

    // Add to workspace from card
    $(document).on('click', '.pi-add-to-workspace', function(e) {
      e.preventDefault();
      e.stopPropagation();
      const postId = $(this).data('postid');
      addToWorkspace(postId, $(this));
    });

    // Add to workspace from modal
    $('#pi-modal-add').on('click', function() {
      if (currentModalPostId && !$(this).hasClass('added')) {
        addToWorkspace(currentModalPostId, $(this));
      }
    });

    // Save search button - show modal
    $('#pi-save-search-btn').on('click', function() {
      $('#pi-save-name').val('');
      $('#pi-save-modal').css('display', 'flex');
    });

    // Save modal close
    $('#pi-save-modal-close, #pi-save-cancel').on('click', function() {
      $('#pi-save-modal').hide();
    });

    // Save confirm
    $('#pi-save-confirm').on('click', function() {
      const name = $('#pi-save-name').val().trim();
      if (name) {
        saveSearch(name, getCurrentFilters());
        $('#pi-save-modal').hide();
      }
    });

    // Toggle saved searches dropdown
    $(document).on('click', '#pi-save-search-btn', function(e) {
      // Only toggle if not opening save modal
    });

    // Click on toolbar btn area to toggle dropdown
    $('.pi-saved-searches').on('click', function(e) {
      if (!$(e.target).closest('#pi-save-search-btn').length) {
        e.stopPropagation();
      }
    });

    // Add a load saved button
    $(document).on('click', '.pi-toolbar-btn[title="Save current search"]', function(e) {
      // Toggle dropdown on second part if needed
    });

    // Actually, let's add a dedicated dropdown toggle
    // For now, saved searches appear on hover or we add another button

    // Load saved search
    $(document).on('click', '.pi-saved-item', function(e) {
      if ($(e.target).closest('.pi-saved-item-delete').length) return;
      const id = $(this).data('id');
      const searches = getSavedSearches();
      const search = searches.find(s => s.id === id);
      if (search) {
        applyFilters(search.filters);
        $('#pi-saved-dropdown').removeClass('open');
      }
    });

    // Delete saved search
    $(document).on('click', '.pi-saved-item-delete', function(e) {
      e.stopPropagation();
      const id = $(this).data('id');
      deleteSearch(id);
    });

    // Show saved dropdown on hover or click
    let dropdownTimeout;
    $('.pi-saved-searches').on('mouseenter', function() {
      clearTimeout(dropdownTimeout);
      $('#pi-saved-dropdown').addClass('open');
    }).on('mouseleave', function() {
      dropdownTimeout = setTimeout(() => {
        $('#pi-saved-dropdown').removeClass('open');
      }, 200);
    });

    // Click outside to close dropdown
    $(document).on('click', function(e) {
      if (!$(e.target).closest('.pi-saved-searches').length) {
        $('#pi-saved-dropdown').removeClass('open');
      }
    });

    // =====================================================
    // MY APPS PANEL EVENT HANDLERS
    // =====================================================

    // Open My Apps panel
    $('#pi-open-my-apps').on('click', function() {
      openMyAppsPanel();
    });

    // Close My Apps panel
    $('#pi-my-apps-close').on('click', function() {
      closeMyAppsPanel();
    });

    // Close panel on overlay click
    $('#pi-my-apps-overlay').on('click', function() {
      closeMyAppsPanel();
    });

    // Tab switching
    $(document).on('click', '.pi-sidebar-tab', function() {
      const tab = $(this).data('tab');
      switchMyAppsTab(tab);
    });

    // View app details from My Apps panel
    $(document).on('click', '.pi-my-app-view-btn', function(e) {
      e.stopPropagation();
      const postId = $(this).data('postid');
      closeMyAppsPanel();
      openModal(postId);
    });

    // Click on app card to view details
    $(document).on('click', '.pi-my-app-card', function(e) {
      // Don't trigger if clicking on action buttons
      if ($(e.target).closest('.pi-my-app-actions').length) return;
      const postId = $(this).data('id');
      closeMyAppsPanel();
      openModal(postId);
    });

    // Remove app from saved
    $(document).on('click', '.pi-my-app-remove-btn', async function(e) {
      e.stopPropagation();
      const $btn = $(this);
      const postId = $btn.data('postid');
      const $card = $btn.closest('.pi-my-app-card');
      
      $btn.addClass('loading');
      $card.addClass('removing');
      
      const success = await unsaveApp(postId);
      
      if (success) {
        $card.slideUp(200, function() {
          $(this).remove();
          // Update count
          $('#pi-saved-count').text(userSavedAppsData.length);
          if (userSavedAppsData.length === 0) {
            $('#pi-saved-empty').show();
          }
        });
      } else {
        $btn.removeClass('loading');
        $card.removeClass('removing');
      }
    });

    // Save app from recently viewed
    $(document).on('click', '.pi-my-app-save-btn', async function(e) {
      e.stopPropagation();
      const $btn = $(this);
      const postId = $btn.data('postid');
      
      if ($btn.hasClass('saved')) return;
      
      $btn.addClass('loading');
      
      const success = await saveApp(postId);
      
      $btn.removeClass('loading');
      
      if (success) {
        $btn.addClass('saved').attr('title', 'Saved');
        $btn.find('svg').attr('fill', 'currentColor');
        // Remove from recent list since it's now saved
        const $card = $btn.closest('.pi-my-app-card');
        $card.slideUp(200, function() {
          $(this).remove();
          userRecentAppsData = userRecentAppsData.filter(app => String(app.id) !== String(postId));
          $('#pi-recent-count').text(userRecentAppsData.length);
          if (userRecentAppsData.length === 0) {
            $('#pi-recent-empty').show();
          }
        });
      }
    });

    // Save/unsave from card bookmark button
    $(document).on('click', '.pi-save-btn', async function(e) {
      e.preventDefault();
      e.stopPropagation();
      
      const $btn = $(this);
      const $card = $btn.closest('.pi-card');
      const postId = $card.data('id');
      const isSaved = $card.attr('data-saved') === '1';
      
      $btn.addClass('loading');
      
      if (isSaved) {
        await unsaveApp(postId);
      } else {
        await saveApp(postId);
      }
      
      $btn.removeClass('loading');
    });
  });

})(jQuery);
