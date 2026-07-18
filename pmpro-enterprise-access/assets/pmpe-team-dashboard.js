/**
 * Planning Index - Team Management Dashboard
 * Full-scale enterprise team analytics & management
 * Version 1.0
 */
(function($) {
    'use strict';

    const CONFIG = window.pmpeTeamConfig || {};
    const restUrl = CONFIG.restUrl || '/wp-json/pi/v1';
    const ajaxUrl = CONFIG.ajaxUrl || '/wp-admin/admin-ajax.php';
    const nonce = CONFIG.nonce || '';
    const restNonce = CONFIG.restNonce || '';
    const isOwner = CONFIG.isOwner || false;
    const currentUserId = CONFIG.currentUserId || 0;

    // State
    let teamData = null;
    let currentTab = 'overview';

    // ============================================================
    // INITIALIZATION
    // ============================================================
    function init() {
        initTabs();
        initInviteForm();
        loadTeamData();
    }

    // ============================================================
    // TABS
    // ============================================================
    function initTabs() {
        $(document).on('click', '.td-tab', function(e) {
            e.preventDefault();
            const tab = $(this).data('tab');
            if (tab === currentTab) return;
            
            currentTab = tab;
            $('.td-tab').removeClass('active');
            $(this).addClass('active');
            
            $('.td-panel').removeClass('active');
            $(`#td-panel-${tab}`).addClass('active');
        });
    }

    // ============================================================
    // DATA LOADING
    // ============================================================
    async function loadTeamData() {
        showLoading();

        try {
            const resp = await fetch(`${restUrl}/team/overview`, {
                headers: { 'X-WP-Nonce': restNonce }
            });

            if (!resp.ok) throw new Error('Failed to load team data');

            teamData = await resp.json();
            renderDashboard();
        } catch (err) {
            console.error('[Team Dashboard] Load failed:', err);
            showError('Failed to load team data. Please refresh.');
        }
    }

    function showLoading() {
        $('#td-overview-content').html(`
            <div class="td-loading">
                <div class="td-spinner"></div>
                <p>Loading team dashboard...</p>
            </div>
        `);
    }

    function showError(msg) {
        $('#td-overview-content').html(`
            <div class="td-empty">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/>
                    <line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
                <h3>Error</h3>
                <p>${escapeHtml(msg)}</p>
            </div>
        `);
    }

    // ============================================================
    // RENDER DASHBOARD
    // ============================================================
    function renderDashboard() {
        if (!teamData) return;
        renderOverview();
        renderMembers();
        renderActivity();
    }

    // ============================================================
    // OVERVIEW TAB
    // ============================================================
    function renderOverview() {
        const d = teamData;
        const totalLeads = d.total_leads || 0;
        const totalValue = d.total_value || 0;
        const wonValue = d.won_value || 0;
        const totalTasks = d.total_tasks || 0;
        const pendingTasks = d.pending_tasks || 0;
        const totalProposals = d.total_proposals || 0;
        const memberCount = (d.members || []).length;
        const pipelineBreakdown = d.pipeline_breakdown || {};

        let html = '';

        // KPI Grid
        html += `<div class="td-kpi-grid">
            <div class="td-kpi kpi-members">
                <div class="td-kpi-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
                <div class="td-kpi-value">${memberCount}</div>
                <div class="td-kpi-label">Team Members</div>
            </div>
            <div class="td-kpi kpi-leads">
                <div class="td-kpi-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg></div>
                <div class="td-kpi-value">${totalLeads}</div>
                <div class="td-kpi-label">Total Leads</div>
            </div>
            <div class="td-kpi kpi-value">
                <div class="td-kpi-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
                <div class="td-kpi-value">${formatCurrency(totalValue)}</div>
                <div class="td-kpi-label">Total Pipeline Value</div>
            </div>
            <div class="td-kpi kpi-won">
                <div class="td-kpi-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
                <div class="td-kpi-value">${formatCurrency(wonValue)}</div>
                <div class="td-kpi-label">Won Value</div>
            </div>
        </div>`;

        // Two-column: Pipeline + Value by Member
        html += `<div class="td-grid-2">`;

        // Pipeline Distribution
        html += `<div class="td-card">
            <div class="td-card-header">
                <div class="td-card-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                    Pipeline Distribution
                </div>
                <span class="td-card-badge">${totalLeads} total</span>
            </div>
            <div class="td-card-body">
                ${renderPipelineChart(pipelineBreakdown, totalLeads)}
            </div>
        </div>`;

        // Value by Member (bar chart)
        html += `<div class="td-card">
            <div class="td-card-header">
                <div class="td-card-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                    Value by Member
                </div>
            </div>
            <div class="td-card-body">
                ${renderValueChart(d.members || [])}
            </div>
        </div>`;

        html += `</div>`;

        // Two-column: Leaderboard + Tasks Summary
        html += `<div class="td-grid-2">`;

        // Leaderboard
        html += `<div class="td-card">
            <div class="td-card-header">
                <div class="td-card-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    Leaderboard
                </div>
            </div>
            <div class="td-card-body" style="padding:0;">
                ${renderLeaderboard(d.members || [])}
            </div>
        </div>`;

        // Tasks Summary
        html += `<div class="td-card">
            <div class="td-card-header">
                <div class="td-card-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    Tasks Overview
                </div>
                <span class="td-card-badge">${totalTasks} total</span>
            </div>
            <div class="td-card-body">
                ${renderTasksSummary(d)}
            </div>
        </div>`;

        html += `</div>`;

        // Recent Activity (quick view)
        html += `<div class="td-card">
            <div class="td-card-header">
                <div class="td-card-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    Recent Team Activity
                </div>
                <button class="td-tab" data-tab="activity" style="flex:0;padding:6px 14px;font-size:12px;">View All</button>
            </div>
            <div class="td-card-body" style="padding:0;">
                ${renderActivityList((d.activity || []).slice(0, 8))}
            </div>
        </div>`;

        $('#td-overview-content').html(html);
    }

    // ============================================================
    // PIPELINE CHART
    // ============================================================
    function renderPipelineChart(breakdown, total) {
        if (!total) return '<div class="td-empty"><p>No leads in the pipeline yet.</p></div>';

        const stages = [
            { key: 'new_lead', label: 'New Lead' },
            { key: 'proposal_sent', label: 'Proposal Sent' },
            { key: 'contacted', label: 'Contacted' },
            { key: 'negotiation', label: 'Negotiation' },
            { key: 'won', label: 'Won' }
        ];

        let barHtml = '<div class="td-pipeline-bar">';
        let legendHtml = '<div class="td-pipeline-legend">';

        stages.forEach(s => {
            const count = breakdown[s.key] || 0;
            const pct = total > 0 ? (count / total * 100) : 0;
            if (pct > 0) {
                barHtml += `<div class="td-pipeline-segment" data-stage="${s.key}" style="width:${pct}%;" title="${s.label}: ${count}"></div>`;
            }
            legendHtml += `<div class="td-legend-item">
                <div class="td-legend-dot" data-stage="${s.key}"></div>
                <span>${s.label}</span>
                <span class="td-legend-count">${count}</span>
            </div>`;
        });

        barHtml += '</div>';
        legendHtml += '</div>';

        return barHtml + legendHtml;
    }

    // ============================================================
    // VALUE CHART
    // ============================================================
    function renderValueChart(members) {
        if (!members.length) return '<div class="td-empty"><p>No member data available.</p></div>';

        const maxValue = Math.max(...members.map(m => m.total_value || 0), 1);

        let html = '<div class="td-bar-chart">';
        members.forEach(m => {
            const value = m.total_value || 0;
            const height = Math.max(4, (value / maxValue) * 140);
            const name = (m.display_name || 'User').split(' ')[0];
            html += `<div class="td-bar-group">
                <div class="td-bar" style="height:${height}px;">
                    <span class="td-bar-value">${formatCurrencyShort(value)}</span>
                </div>
                <span class="td-bar-label">${escapeHtml(name)}</span>
            </div>`;
        });
        html += '</div>';

        return html;
    }

    // ============================================================
    // LEADERBOARD
    // ============================================================
    function renderLeaderboard(members) {
        if (!members.length) return '<div class="td-empty"><p>No members yet.</p></div>';

        const sorted = [...members].sort((a, b) => (b.won_value || 0) - (a.won_value || 0));

        let html = '';
        sorted.forEach((m, i) => {
            const rank = i + 1;
            const rankClass = rank <= 3 ? `rank-${rank}` : '';
            const initials = getInitials(m.display_name || m.username || 'U');

            html += `<div class="td-leaderboard-item">
                <div class="td-leaderboard-rank ${rankClass}">${rank}</div>
                <div class="td-member-avatar member" style="width:32px;height:32px;min-width:32px;font-size:12px;">${escapeHtml(initials)}</div>
                <div class="td-leaderboard-info">
                    <div class="td-leaderboard-name">${escapeHtml(m.display_name || m.username)}</div>
                    <div class="td-leaderboard-detail">${m.total_leads || 0} leads · ${m.won_leads || 0} won</div>
                </div>
                <div class="td-leaderboard-value">${formatCurrency(m.won_value || 0)}</div>
            </div>`;
        });

        return html;
    }

    // ============================================================
    // TASKS SUMMARY
    // ============================================================
    function renderTasksSummary(d) {
        const total = d.total_tasks || 0;
        const pending = d.pending_tasks || 0;
        const completed = total - pending;
        const overdue = d.overdue_tasks || 0;

        if (!total) return '<div class="td-empty"><p>No tasks created by team members yet.</p></div>';

        const completionPct = total > 0 ? Math.round((completed / total) * 100) : 0;

        return `
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                <div class="td-member-stat">
                    <div class="td-member-stat-value" style="color:var(--td-info)">${pending}</div>
                    <div class="td-member-stat-label">Pending</div>
                </div>
                <div class="td-member-stat">
                    <div class="td-member-stat-value" style="color:var(--td-success)">${completed}</div>
                    <div class="td-member-stat-label">Completed</div>
                </div>
                <div class="td-member-stat">
                    <div class="td-member-stat-value" style="color:var(--td-danger)">${overdue}</div>
                    <div class="td-member-stat-label">Overdue</div>
                </div>
                <div class="td-member-stat">
                    <div class="td-member-stat-value" style="color:var(--td-gold)">${completionPct}%</div>
                    <div class="td-member-stat-label">Completion</div>
                </div>
            </div>
            <div class="td-progress-track" style="height:6px;">
                <div class="td-progress-fill" style="width:${completionPct}%;background:linear-gradient(90deg,var(--td-success),#34d399);"></div>
            </div>
        `;
    }

    // ============================================================
    // MEMBERS TAB
    // ============================================================
    function renderMembers() {
        if (!teamData) return;

        const members = teamData.members || [];
        let html = '';

        if (!members.length) {
            html = '<div class="td-empty"><h3>No team members yet</h3><p>Invite members to get started.</p></div>';
        } else {
            html = '<div class="td-member-grid">';
            members.forEach(m => {
                const initials = getInitials(m.display_name || m.username || 'U');
                const isOwnerMember = m.is_owner;
                const pipeline = m.pipeline_breakdown || {};
                const totalLeads = m.total_leads || 0;

                html += `<div class="td-member-card" data-user-id="${m.user_id}">
                    <div class="td-member-card-header">
                        <div class="td-member-avatar ${isOwnerMember ? 'owner' : 'member'}">${escapeHtml(initials)}</div>
                        <div class="td-member-info">
                            <div class="td-member-name">${escapeHtml(m.display_name || m.username)}</div>
                            <div class="td-member-email">${escapeHtml(m.email || '')}</div>
                        </div>
                        <span class="td-member-badge ${isOwnerMember ? 'owner-badge' : 'member-badge'}">${isOwnerMember ? 'Owner' : 'Member'}</span>
                    </div>
                    <div class="td-member-stats">
                        <div class="td-member-stat">
                            <div class="td-member-stat-value">${m.total_leads || 0}</div>
                            <div class="td-member-stat-label">Leads</div>
                        </div>
                        <div class="td-member-stat">
                            <div class="td-member-stat-value">${formatCurrencyShort(m.total_value || 0)}</div>
                            <div class="td-member-stat-label">Value</div>
                        </div>
                        <div class="td-member-stat">
                            <div class="td-member-stat-value">${m.won_leads || 0}</div>
                            <div class="td-member-stat-label">Won</div>
                        </div>
                    </div>
                    ${totalLeads > 0 ? renderMemberPipeline(pipeline, totalLeads) : ''}
                    <div class="td-member-stats" style="margin-bottom:0;">
                        <div class="td-member-stat">
                            <div class="td-member-stat-value" style="font-size:14px;">${m.total_tasks || 0}</div>
                            <div class="td-member-stat-label">Tasks</div>
                        </div>
                        <div class="td-member-stat">
                            <div class="td-member-stat-value" style="font-size:14px;">${m.total_proposals || 0}</div>
                            <div class="td-member-stat-label">Proposals</div>
                        </div>
                        <div class="td-member-stat">
                            <div class="td-member-stat-value" style="font-size:14px;">${formatCurrencyShort(m.won_value || 0)}</div>
                            <div class="td-member-stat-label">Won £</div>
                        </div>
                    </div>
                    ${isOwner && !isOwnerMember ? `
                    <div class="td-member-actions">
                        <button class="td-member-action-btn td-remove-member" data-user-id="${m.user_id}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="18" y1="11" x2="23" y2="11"/></svg>
                            Remove
                        </button>
                    </div>` : ''}
                </div>`;
            });
            html += '</div>';
        }

        $('#td-members-content').html(html);

        // Bind remove member
        $(document).off('click', '.td-remove-member').on('click', '.td-remove-member', function() {
            const userId = $(this).data('user-id');
            if (confirm('Are you sure you want to remove this team member? They will lose access.')) {
                removeMember(userId);
            }
        });
    }

    function renderMemberPipeline(pipeline, total) {
        const stages = ['new_lead', 'proposal_sent', 'contacted', 'negotiation', 'won'];
        let html = '<div class="td-member-pipeline" style="margin-bottom:12px;">';
        stages.forEach(s => {
            const count = pipeline[s] || 0;
            const pct = total > 0 ? (count / total * 100) : 0;
            if (pct > 0) {
                html += `<div class="td-member-pipeline-seg" data-stage="${s}" style="width:${pct}%;" title="${s}: ${count}"></div>`;
            }
        });
        html += '</div>';
        return html;
    }

    // ============================================================
    // ACTIVITY TAB
    // ============================================================
    function renderActivity() {
        if (!teamData) return;

        const activity = teamData.activity || [];

        if (!activity.length) {
            $('#td-activity-content').html('<div class="td-empty"><h3>No activity yet</h3><p>Team activity will appear here as members work on leads and tasks.</p></div>');
            return;
        }

        $('#td-activity-content').html(`
            <div class="td-card">
                <div class="td-card-header">
                    <div class="td-card-title">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                        All Team Activity
                    </div>
                    <span class="td-card-badge">${activity.length} events</span>
                </div>
                <div class="td-card-body" style="padding:0;">
                    ${renderActivityList(activity)}
                </div>
            </div>
        `);
    }

    function renderActivityList(items) {
        if (!items.length) return '<div class="td-empty" style="padding:32px;"><p>No recent activity.</p></div>';

        let html = '<div class="td-activity-list">';
        items.forEach(item => {
            const initials = getInitials(item.user_name || 'U');
            const typeClass = item.type || 'lead';

            html += `<div class="td-activity-item">
                <div class="td-activity-avatar">${escapeHtml(initials)}</div>
                <div class="td-activity-content">
                    <div class="td-activity-text">
                        <strong>${escapeHtml(item.user_name || 'Unknown')}</strong> ${escapeHtml(item.description || '')}
                    </div>
                    <div class="td-activity-time">${escapeHtml(item.time_ago || '')}</div>
                </div>
                <span class="td-activity-type ${typeClass}">${escapeHtml(item.type_label || item.type || '')}</span>
            </div>`;
        });
        html += '</div>';
        return html;
    }

    // ============================================================
    // INVITE FORM
    // ============================================================
    function initInviteForm() {
        $(document).on('submit', '#td-invite-form', function(e) {
            e.preventDefault();
            const email = $('#td-invite-email').val().trim();
            if (!email) return;

            const $btn = $('#td-invite-btn');
            const $msg = $('#td-invite-msg');

            $btn.prop('disabled', true).html('<span class="td-spinner" style="width:16px;height:16px;border-width:2px;margin:0;"></span> Sending...');
            $msg.removeClass('success error').hide();

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pmpe_send_team_invite',
                    email: email,
                    nonce: CONFIG.inviteNonce || ''
                },
                success: function(res) {
                    if (res.success) {
                        $msg.addClass('success').html('✓ Invitation sent to <strong>' + escapeHtml(email) + '</strong>').show();
                        $('#td-invite-email').val('');
                        setTimeout(() => loadTeamData(), 2000);
                    } else {
                        $msg.addClass('error').text(res.data || 'Failed to send invitation.').show();
                    }
                },
                error: function() {
                    $msg.addClass('error').text('Network error. Please try again.').show();
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Send Invite');
                }
            });
        });
    }

    // ============================================================
    // REMOVE MEMBER
    // ============================================================
    async function removeMember(userId) {
        try {
            const resp = await fetch(`${restUrl}/team/remove-member`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': restNonce
                },
                body: JSON.stringify({ user_id: userId })
            });

            if (!resp.ok) throw new Error('Failed');

            showToast('Member removed successfully', 'success');
            loadTeamData();
        } catch (err) {
            showToast('Failed to remove member', 'error');
        }
    }

    // ============================================================
    // UTILITIES
    // ============================================================
    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function formatCurrency(amount) {
        return new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(amount || 0);
    }

    function formatCurrencyShort(amount) {
        if (!amount) return '£0';
        if (amount >= 1000000) return '£' + (amount / 1000000).toFixed(1) + 'M';
        if (amount >= 1000) return '£' + (amount / 1000).toFixed(1) + 'k';
        return '£' + Math.round(amount);
    }

    function getInitials(name) {
        if (!name) return 'U';
        const parts = name.trim().split(' ');
        if (parts.length > 1) return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
        return parts[0][0].toUpperCase();
    }

    function showToast(message, type) {
        const $toast = $(`<div class="td-toast ${type}">${escapeHtml(message)}</div>`);
        $('body').append($toast);
        setTimeout(() => $toast.fadeOut(300, function() { $(this).remove(); }), 3500);
    }

    // ============================================================
    // START
    // ============================================================
    $(document).ready(init);

})(jQuery);
