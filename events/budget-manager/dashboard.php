  <?php
session_start(); 

$userName = ($_SESSION['userName']);

?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Manager Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
        }
        
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px 0;
        }
        .dashboard-container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .dashboard-header {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            padding: 25px;
            margin-bottom: 30px;
        }
        .dashboard-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
            color: #212529;
        }
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            text-align: left;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            cursor: pointer;
            border: 1px solid rgba(0,0,0,0.05);
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: white !important;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1.1;
            margin-bottom: 3px;
            color: #212529;
        }
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .stat-card.pending .stat-icon { background-color: var(--warning-color); }
        .stat-card.approved .stat-icon { background-color: var(--success-color); }
        .stat-card.rejected .stat-icon { background-color: var(--danger-color); }
        .stat-card.total .stat-icon { background-color: var(--primary-color); }
        .filter-tabs {
            padding: 0;
            margin-bottom: 20px;
            border-bottom: 1px solid #e9ecef;
        }
        .filter-tabs .nav-link {
            color: #6c757d;
            font-weight: 600;
            border-radius: 8px 8px 0 0;
            padding: 10px 20px;
            margin-right: 5px;
            transition: all 0.2s;
            border: none;
            border-bottom: 3px solid transparent;
        }
        .filter-tabs .nav-link.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        .filter-tabs .nav-link:not(.active):hover {
            color: var(--primary-color);
            background-color: #f8f9fa;
        }
        .proposals-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            padding: 30px;
            margin-bottom: 30px;
        }
        .section-title {
            font-weight: 700;
            color: #212529;
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 3px solid var(--primary-color);
            display: inline-block;
        }
        .proposal-card {
            background: white;
            border: 2px solid #dee2e6;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        .proposal-card:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            transform: translateX(5px);
        }
        .proposal-card.pending { border-left: 5px solid var(--warning-color); }
        .proposal-card.approved { border-left: 5px solid var(--success-color); }
        .proposal-card.rejected { border-left: 5px solid var(--danger-color); }
        .badge-status {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-approved { background: #d4edda; color: #155724; }
        .badge-rejected { background: #f8d7da; color: #721c24; }
        .action-buttons .btn {
            margin: 0 5px;
            border-radius: 25px;
            font-weight: 600;
            padding: 8px 20px;
        }
        .proposal-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .detail-item {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
        }
        .detail-item label {
            font-weight: 600;
            color: #495057;
            font-size: 0.85rem;
            display: block;
            margin-bottom: 5px;
        }
        .detail-item .value {
            color: #212529;
            font-size: 1rem;
        }
        #message-box {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            max-width: 400px;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        .empty-state svg {
            width: 100px;
            height: 100px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        .notes-section {
            background: #e7f3ff;
            border-left: 4px solid #0d6efd;
            padding: 15px;
            margin-top: 15px;
            border-radius: 5px;
        }
        
        .client-assignment-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            padding: 30px;
            margin-bottom: 30px;
        }
        .client-list-table {
            max-height: 500px;
            overflow-y: auto;
        }
        .client-row {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            transition: background-color 0.2s;
        }
        .client-row:hover {
            background-color: #f8f9fa;
        }
        .client-info {
            flex: 1;
        }
        .assigned-badge {
            background: #d4edda;
            color: #155724;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .unassigned-badge {
            background: #fff3cd;
            color: #856404;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .loading-spinner {
            display: none;
            text-align: center;
            padding: 20px;
        }
    </style>
</head>
<body>

<div id="message-box"></div>

<div class="dashboard-container">
    <div class="dashboard-header">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
            <div>
                <h1><i class="bi bi-clipboard-check text-primary me-2"></i>Budget Manager Dashboard</h1>
                <p class="text-muted mb-0">Review and manage sales proposals</p>
            </div>
            <div class="mt-3 mt-md-0">
                 <div class="ms-2 d-flex">
        <span class="d-none d-md-block fw-bold me-3 text-secondary" style="
        white-space: nowrap; 
        max-width: 150px; /* Adjust this value as needed for long names */
        overflow: hidden; 
        text-overflow: ellipsis;
    ">
        Hello, <?php echo $userName; ?>
    </span>
            <a href="../../logout.php" class="btn btn-sm btn-danger">
              <i class="bi bi-box-arrow-right"></i> Logout
            </a>
          </div>
            </div>
        </div>
    </div>

    <div class="stats-cards" id="statsCards">
        <div class="loading-spinner" style="display: block;">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    </div>

    <div class="client-assignment-section">
        <h3 class="section-title"><i class="bi bi-people text-primary me-2"></i>All Clients - Assignment Management</h3>
        
        <div class="mb-3">
            <input type="text" class="form-control" id="searchClient" placeholder="Search clients by name, contact, or event type...">
        </div>

        <div class="client-list-table" id="clientList">
            <div class="loading-spinner" style="display: block;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading clients...</span>
                </div>
            </div>
        </div>
    </div>

    <div class="proposals-section">
        <h3 class="section-title"><i class="bi bi-table text-primary me-2"></i>All Submissions for Review</h3>
        
        <ul class="nav nav-pills filter-tabs" role="tablist" id="filterTabs">
        </ul>

        <div id="proposalsContainer">
            <div class="loading-spinner" style="display: block;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading proposals...</span>
                </div>
            </div>
        </div>

        <div id="noResults" class="alert alert-warning text-center mt-3" style="display:none;">
            <i class="bi bi-info-circle me-2"></i>No proposals found for the selected status.
        </div>
    </div>

    <div class="text-center p-4">
        <a href="../sales_dashboard" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Sales Dashboard
        </a>
    </div>
</div>

<div class="modal fade" id="approvalModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Notes (Optional)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="modal-client-id">
                <input type="hidden" id="modal-status">
                <div class="mb-3">
                    <label class="form-label">Notes:</label>
                    <textarea class="form-control" id="modal-notes" rows="4" 
                              placeholder="Add any comments or reasons for this decision..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitApproval()">Confirm</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Assign Sales Manager</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="assign-client-id">
                <div class="mb-3">
                    <label class="form-label"><strong>Client Name:</strong></label>
                    <p id="assign-client-name" class="text-muted"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label"><strong>Select Sales Manager:</strong></label>
                    <select class="form-select" id="assign-sales-manager">
                        <option value="">-- Select a Sales Manager --</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitAssignment()">
                    <i class="bi bi-check-circle me-1"></i>Assign Client
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let approvalModal;
let assignModal;
let currentFilter = 'PENDING';
let allProposals = [];
let allClients = [];
let salesManagers = [];

document.addEventListener('DOMContentLoaded', function() {
    approvalModal = new bootstrap.Modal(document.getElementById('approvalModal'));
    assignModal = new bootstrap.Modal(document.getElementById('assignModal'));
    
    const urlParams = new URLSearchParams(window.location.search);
    currentFilter = urlParams.get('filter') || 'PENDING';
    
    loadStats();
    loadProposals();
    loadClients();
    loadSalesManagers();
    
    const searchInput = document.getElementById('searchClient');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            filterClients(this.value);
        });
    }
});

function loadStats() {
    fetch('api/get_stats')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderStats(data.stats);
            } else {
                console.error('Failed to load stats:', data.error);
                showError('Failed to load statistics');
            }
        })
        .catch(error => {
            console.error('Error loading stats:', error);
            showError('Network error while loading statistics');
        });
}

function renderStats(stats) {
    const statsHtml = `
        <div class="stat-card pending" onclick="filterByStatus('PENDING')">
            <div class="stat-icon">
                <i class="bi bi-clock-history"></i>
            </div>
            <div class="stat-number">${stats.pending_count || 0}</div>
            <div class="stat-label">Pending Review</div>
        </div>
        <div class="stat-card approved" onclick="filterByStatus('APPROVED')">
            <div class="stat-icon">
                <i class="bi bi-check-circle"></i>
            </div>
            <div class="stat-number">${stats.approved_count || 0}</div>
            <div class="stat-label">Approved</div>
        </div>
        <div class="stat-card rejected" onclick="filterByStatus('REJECTED')">
            <div class="stat-icon">
                <i class="bi bi-x-circle"></i>
            </div>
            <div class="stat-number">${stats.rejected_count || 0}</div>
            <div class="stat-label">Rejected</div>
        </div>
        <div class="stat-card total" onclick="filterByStatus('ALL')">
            <div class="stat-icon">
                <i class="bi bi-list-ul"></i>
            </div>
            <div class="stat-number">${stats.total_count || 0}</div>
            <div class="stat-label">Total Submissions</div>
        </div>
    `;
    document.getElementById('statsCards').innerHTML = statsHtml;
    renderFilterTabs(stats);
}

function renderFilterTabs(stats) {
    const tabsHtml = `
        <li class="nav-item" role="presentation">
            <a class="nav-link ${currentFilter === 'PENDING' ? 'active' : ''}" 
               href="#" onclick="filterProposals(event, 'PENDING')">
                <i class="bi bi-clock-history me-1"></i>Pending (${stats.pending_count || 0})
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link ${currentFilter === 'APPROVED' ? 'active' : ''}" 
               href="#" onclick="filterProposals(event, 'APPROVED')">
                <i class="bi bi-check-circle me-1"></i>Approved (${stats.approved_count || 0})
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link ${currentFilter === 'REJECTED' ? 'active' : ''}" 
               href="#" onclick="filterProposals(event, 'REJECTED')">
                <i class="bi bi-x-circle me-1"></i>Rejected (${stats.rejected_count || 0})
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link ${currentFilter === 'ALL' ? 'active' : ''}" 
               href="#" onclick="filterProposals(event, 'ALL')">
                <i class="bi bi-list-ul me-1"></i>All (${stats.total_count || 0})
            </a>
        </li>
    `;
    document.getElementById('filterTabs').innerHTML = tabsHtml;
}

function loadProposals() {
    fetch(`api/get_proposals?filter=${currentFilter}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                allProposals = data.proposals;
                renderProposals(allProposals);
            } else {
                showError('Failed to load proposals: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error loading proposals:', error);
            showError('Network error while loading proposals');
        });
}

function renderProposals(proposals) {
    const container = document.getElementById('proposalsContainer');
    
    if (proposals.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h3>No proposals found</h3>
                <p>There are no proposals matching the current filter.</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    proposals.forEach(proposal => {
        const status_class = (proposal.admin_status || 'pending').toLowerCase();
        const badge_class = 'badge-' + status_class;
        
        html += `
            <div class="proposal-card ${status_class}" 
                 id="proposal-${proposal.client_id}"
                 data-status="${proposal.admin_status || 'PENDING'}">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h4 class="mb-1">${escapeHtml(proposal.client_name)}</h4>
                        <span class="text-muted">Client ID: ${proposal.client_id}</span>
                    </div>
                    <span class="badge-status ${badge_class}">
                        ${(proposal.admin_status || 'PENDING').toUpperCase()}
                    </span>
                </div>

                <div class="proposal-details">
                    <div class="detail-item">
                        <label><i class="bi bi-calendar-event me-1"></i>Event Type</label>
                        <div class="value">${escapeHtml(proposal.event_type || 'N/A')}</div>
                    </div>
                    <div class="detail-item">
                        <label><i class="bi bi-calendar-date me-1"></i>Event Date</label>
                        <div class="value">${formatDate(proposal.date_time_of_event)}</div>
                    </div>
                    <div class="detail-item">
                        <label><i class="bi bi-telephone me-1"></i>Contact Number</label>
                        <div class="value">${escapeHtml(proposal.contact_no)}</div>
                    </div>
                    <div class="detail-item">
                        <label><i class="bi bi-star me-1"></i>Lead Status</label>
                        <div class="value">${escapeHtml(proposal.lead_status)}</div>
                    </div>
                    <div class="detail-item">
                        <label><i class="bi bi-gear me-1"></i>Services Required</label>
                        <div class="value">${escapeHtml(proposal.services_required || 'N/A')}</div>
                    </div>
                    <div class="detail-item">
                        <label><i class="bi bi-basket me-1"></i>Food Category</label>
                        <div class="value">${escapeHtml(proposal.food_category || 'N/A')}</div>
                    </div>
                    <div class="detail-item">
                        <label><i class="bi bi-palette me-1"></i>Decor Type</label>
                        <div class="value">${escapeHtml(proposal.decor_type || 'N/A')}</div>
                    </div>
                    <div class="detail-item">
                        <label><i class="bi bi-cash-stack me-1"></i>Expected Budget</label>
                        <div class="value">${escapeHtml(proposal.expected_budget || 'N/A')}</div>
                    </div>
                    <div class="detail-item">
                        <label><i class="bi bi-currency-rupee me-1"></i><strong>Proposed Budget</strong></label>
                        <div class="value"><strong>₹${formatNumber(proposal.budget_draft_sales)}</strong></div>
                    </div>
                </div>

                ${proposal.sales_notes ? `
                    <div class="notes-section">
                        <strong><i class="bi bi-journal-text me-1"></i>Sales Notes:</strong><br>
                        ${escapeHtml(proposal.sales_notes).replace(/\n/g, '<br>')}
                    </div>
                ` : ''}

                ${proposal.admin_notes && proposal.admin_status !== 'PENDING' ? `
                    <div class="alert alert-secondary mt-3">
                        <strong><i class="bi bi-person-badge me-1"></i>Admin Notes:</strong><br>
                        ${escapeHtml(proposal.admin_notes).replace(/\n/g, '<br>')}
                        ${proposal.admin_approved_by ? `
                            <br><small class="text-muted">By: ${escapeHtml(proposal.admin_approved_by)} 
                            on ${formatDateTime(proposal.admin_approved_at)}</small>
                        ` : ''}
                    </div>
                ` : ''}

                <div class="action-buttons text-end mt-3">
                    <a href="../view_proposal?client_id=${proposal.client_id}" 
                       class="btn btn-sm btn-info" target="_blank">
                        <i class="bi bi-eye me-1"></i>View Details
                    </a>
                    
                    ${renderActionButtons(proposal)}
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function renderActionButtons(proposal) {
    const status = proposal.admin_status || 'PENDING';
    
    if (status === 'PENDING') {
        return `
            <button class="btn btn-sm btn-success" onclick="openApprovalModal(${proposal.client_id}, 'APPROVED')">
                <i class="bi bi-check-circle me-1"></i>Approve
            </button>
            <button class="btn btn-sm btn-danger" onclick="openApprovalModal(${proposal.client_id}, 'REJECTED')">
                <i class="bi bi-x-circle me-1"></i>Reject
            </button>
        `;
    } else if (status === 'APPROVED') {
        return `
            <button class="btn btn-sm btn-warning" onclick="updateStatus(${proposal.client_id}, 'PENDING', '')">
                <i class="bi bi-arrow-clockwise me-1"></i>Move to Pending
            </button>
            <button class="btn btn-sm btn-danger" onclick="openApprovalModal(${proposal.client_id}, 'REJECTED')">
                <i class="bi bi-x-circle me-1"></i>Reject
            </button>
        `;
    } else if (status === 'REJECTED') {
        return `
            <button class="btn btn-sm btn-success" onclick="openApprovalModal(${proposal.client_id}, 'APPROVED')">
                <i class="bi bi-check-circle me-1"></i>Approve
            </button>
            <button class="btn btn-sm btn-warning" onclick="updateStatus(${proposal.client_id}, 'PENDING', '')">
                <i class="bi bi-arrow-clockwise me-1"></i>Move to Pending
            </button>
        `;
    }
    return '';
}

function loadClients() {
    fetch('api/get_all_clients')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                allClients = data.clients;
                renderClients(allClients);
            } else {
                console.error('Failed to load clients:', data.error);
            }
        })
        .catch(error => {
            console.error('Error loading clients:', error);
        });
}

function renderClients(clients) {
    const container = document.getElementById('clientList');
    
    if (clients.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <p>No clients found in the system.</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    clients.forEach(client => {
        html += `
            <div class="client-row d-flex align-items-center" 
                 data-client-name="${escapeHtml(client.client_name)}" 
                 data-contact="${escapeHtml(client.contact_no)}" 
                 data-event="${escapeHtml(client.event_type)}">
                <div class="client-info">
                    <h5 class="mb-1">${escapeHtml(client.client_name)}</h5>
                    <div class="text-muted small">
                        <i class="bi bi-telephone me-1"></i>${escapeHtml(client.contact_no)} | 
                        <i class="bi bi-calendar-event ms-2 me-1"></i>${escapeHtml(client.event_type)} | 
                        <i class="bi bi-calendar-date ms-2 me-1"></i>${formatDate(client.date_time_of_event)}
                    </div>
                    ${client.assigned_to ? `
                        <span class="assigned-badge mt-2 d-inline-block">
                            <i class="bi bi-check-circle me-1"></i>Assigned to: ${escapeHtml(client.assigned_to)}
                        </span>
                    ` : `
                        <span class="unassigned-badge mt-2 d-inline-block">
                            <i class="bi bi-exclamation-circle me-1"></i>Not Assigned
                        </span>
                    `}
                </div>
                <div>
                    <button class="btn btn-primary btn-sm" onclick="openAssignModal(${client.client_id}, '${escapeHtml(client.client_name).replace(/'/g, "\\'")}')">
                        <i class="bi bi-person-plus me-1"></i>Assign
                    </button>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function loadSalesManagers() {
    fetch('api/get_sales_managers')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                salesManagers = data.managers;
                populateSalesManagerDropdown();
            } else {
                console.error('Failed to load sales managers:', data.error);
            }
        })
        .catch(error => {
            console.error('Error loading sales managers:', error);
        });
}

function populateSalesManagerDropdown() {
    const select = document.getElementById('assign-sales-manager');
    let html = '<option value="">-- Select a Sales Manager --</option>';
    
    salesManagers.forEach(manager => {
        html += `<option value="${manager.user_id}">${escapeHtml(manager.full_name)} (${escapeHtml(manager.username)})</option>`;
    });
    
    select.innerHTML = html;
}

function filterClients(searchTerm) {
    const clientRows = document.querySelectorAll('.client-row');
    const term = searchTerm.toLowerCase();
    
    clientRows.forEach(row => {
        const clientName = row.getAttribute('data-client-name').toLowerCase();
        const contact = row.getAttribute('data-contact').toLowerCase();
        const eventType = row.getAttribute('data-event').toLowerCase();
        
        if (clientName.includes(term) || contact.includes(term) || eventType.includes(term)) {
            row.style.display = 'flex';
        } else {
            row.style.display = 'none';
        }
    });
}

function filterByStatus(status) {
    window.location.href = '?filter=' + status;
}

function filterProposals(event, status) {
    event.preventDefault();
    window.location.href = '?filter=' + status;
}

function openApprovalModal(clientId, newStatus) {
    document.getElementById('modal-client-id').value = clientId;
    document.getElementById('modal-status').value = newStatus;
    document.getElementById('modal-notes').value = '';
    approvalModal.show();
}

function submitApproval() {
    const clientId = document.getElementById('modal-client-id').value;
    const status = document.getElementById('modal-status').value;
    const notes = document.getElementById('modal-notes').value;
    
    approvalModal.hide();
    updateStatus(clientId, status, notes);
}

function updateStatus(clientId, newStatus, notes = '') {
    if (!notes && !confirm(`Are you sure you want to mark this proposal as ${newStatus}?`)) {
        return;
    }

    const formData = new FormData();
    formData.append('client_id', clientId);
    formData.append('admin_status', newStatus);
    formData.append('admin_notes', notes);
    
    fetch('api/update_status', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess(data.message);
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showError('Error: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showError('Network Error: ' + error.message);
    });
}

function openAssignModal(clientId, clientName) {
    document.getElementById('assign-client-id').value = clientId;
    document.getElementById('assign-client-name').textContent = clientName;
    document.getElementById('assign-sales-manager').value = '';
    assignModal.show();
}

function submitAssignment() {
    const clientId = document.getElementById('assign-client-id').value;
    const salesManagerId = document.getElementById('assign-sales-manager').value;
    
    if (!salesManagerId) {
        alert('Please select a sales manager');
        return;
    }
    
    assignModal.hide();
    
    const formData = new FormData();
    formData.append('client_id', clientId);
    formData.append('sales_manager_id', salesManagerId);
    
    fetch('api/assign_client', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess(data.message);
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showError('Error: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showError('Network Error: ' + error.message);
    });
}

function showSuccess(message) {
    const msgBox = document.getElementById('message-box');
    msgBox.innerHTML = `<div class='alert alert-success alert-dismissible fade show'>
        <i class="bi bi-check-circle me-2"></i>${escapeHtml(message)}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
    
    setTimeout(() => {
        msgBox.innerHTML = '';
    }, 5000);
}

function showError(message) {
    const msgBox = document.getElementById('message-box');
    msgBox.innerHTML = `<div class='alert alert-danger alert-dismissible fade show'>
        <i class="bi bi-exclamation-triangle me-2"></i>${escapeHtml(message)}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
    
    setTimeout(() => {
        msgBox.innerHTML = '';
    }, 5000);
}

function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.toString().replace(/[&<>"']/g, m => map[m]);
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatDateTime(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function formatNumber(num) {
    if (!num) return '0.00';
    return parseFloat(num).toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}
</script>

</body>
</html>