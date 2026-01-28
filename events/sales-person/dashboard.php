  <?php
session_start(); 

$userName = ($_SESSION['userName']);

?>

<html lang="en">
  
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --card-shadow: 0 2px 4px rgba(0,0,0,0.08);
            --card-shadow-hover: 0 8px 16px rgba(0,0,0,0.12);
        }

        body {
            background: var(--primary-gradient);
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .dashboard-container {
            padding: 1.5rem;
            max-width: 1600px;
            margin: 0 auto;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
        }

        .stat-card {
            cursor: pointer;
            border-left: 4px solid;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--card-shadow-hover);
        }

        .stat-card.active {
            transform: translateY(-4px);
            box-shadow: var(--card-shadow-hover);
            background-color: rgba(255, 255, 255, 0.95) !important;
        }

        .stat-card.bg-info { border-left-color: #0dcaf0; }
        .stat-card.bg-secondary { border-left-color: #6c757d; }
        .stat-card.bg-warning { border-left-color: #ffc107; }
        .stat-card.bg-success { border-left-color: #198754; }
        .stat-card.bg-purple { border-left-color: #6f42c1; }
        .stat-card.bg-primary { border-left-color: #0d6efd; }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 0.75rem;
        }

        .stat-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #212529;
            line-height: 1;
        }

        .table > :not(caption) > * > * {
            padding: 1rem 0.75rem;
        }

        .table thead th {
            background-color: #f8f9fa;
            color: #495057;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #dee2e6;
        }

        .table tbody tr {
            transition: background-color 0.2s ease;
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .badge {
            padding: 0.375rem 0.75rem;
            font-weight: 600;
            font-size: 0.75rem;
        }

        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.8125rem;
            font-weight: 500;
        }

        .search-box {
            position: relative;
            width: 100%;
            max-width: 300px;
        }

        .search-box .form-control {
            padding-left: 2.5rem;
            border-radius: 8px;
        }

        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }

        .filter-active-badge {
            display: inline-block;
            margin-left: 0.5rem;
            padding: 0.25rem 0.5rem;
            background-color: #0d6efd;
            color: white;
            border-radius: 4px;
            font-size: 0.75rem;
        }

        @media (max-width: 768px) {
            .stat-value {
                font-size: 1.5rem;
            }
            
            .search-box {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="card mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 fw-bold mb-2">Sales Dashboard</h1>
                        
                    </div>
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

        <div class="row g-3 mb-4">
            <div class="col-md-6 col-lg-4 col-xl-2">
                <div class="card stat-card bg-info bg-opacity-10" id="card-all" onclick="filterByStatus('all')">
                    <div class="card-body">
                        <div class="stat-icon bg-info">
                            <i class="bi bi-people"></i>
                        </div>
                        <div class="stat-title">Total Leads</div>
                        <div class="stat-value" id="totalLeads">0</div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4 col-xl-2">
                <div class="card stat-card bg-secondary bg-opacity-10" id="card-draft" onclick="filterByStatus('draft')">
                    <div class="card-body">
                        <div class="stat-icon bg-secondary">
                            <i class="bi bi-file-earmark-text"></i>
                        </div>
                        <div class="stat-title">Draft Proposals</div>
                        <div class="stat-value" id="draftProposals">0</div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4 col-xl-2">
                <div class="card stat-card bg-warning bg-opacity-10" id="card-pending_admin" onclick="filterByStatus('pending_admin')">
                    <div class="card-body">
                        <div class="stat-icon bg-warning">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <div class="stat-title">Pending Admin / Review</div>
                        <div class="stat-value" id="pendingAdmin">0</div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4 col-xl-2">
                <div class="card stat-card bg-success bg-opacity-10" id="card-admin_approved" onclick="filterByStatus('admin_approved')">
                    <div class="card-body">
                        <div class="stat-icon bg-success">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <div class="stat-title">Admin Approved</div>
                        <div class="stat-value" id="adminApproved">0</div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4 col-xl-2">
                <div class="card stat-card bg-purple bg-opacity-10" id="card-pending_executive" onclick="filterByStatus('pending_executive')" style="--bs-bg-opacity: 0.1; background-color: #6f42c1 !important;">
                    <div class="card-body">
                        <div class="stat-icon" style="background-color: #6f42c1;">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                        <div class="stat-title">Pending Executive</div>
                        <div class="stat-value" id="pendingExecutive">0</div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4 col-xl-2">
                <div class="card stat-card bg-primary bg-opacity-10" id="card-completed" onclick="filterByStatus('completed')">
                    <div class="card-body">
                        <div class="stat-icon bg-primary">
                            <i class="bi bi-trophy"></i>
                        </div>
                        <div class="stat-title">Completed</div>
                        <div class="stat-value" id="completed">0</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <h3 class="h4 fw-bold mb-0">
                        Clients & Proposals
                        <span id="filterBadge" class="filter-active-badge" style="display: none;"></span>
                    </h3>
                    <div class="d-flex gap-2 align-items-center">
                        <button class="btn btn-sm btn-outline-secondary" id="clearFilter" style="display: none;" onclick="filterByStatus('all')">
                            <i class="bi bi-x-circle"></i> Clear Filter
                        </button>
                        <div class="search-box">
                            <i class="bi bi-search"></i>
                            <input type="text" class="form-control" id="searchInput" placeholder="Search clients...">
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Client Name</th>
                                <th>Lead Status</th>
                                <th>Contact</th>
                                <th>Event Date</th>
                                <th>Event Type</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="clientsTbody">
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        let allClients = [];
        let currentFilter = 'all';

        // Helper function to get badge class for lead status
        function getLeadBadgeClass(status) {
            const statusLower = status.toLowerCase();
            if (statusLower.includes('hot')) return 'bg-danger';
            if (statusLower.includes('warm')) return 'bg-warning';
            return 'bg-info';
        }

        // Helper function to get badge class for workflow status
        function getStatusBadgeClass(status) {
            // Ensure status is correctly processed for display badges
            const statusLower = status.toLowerCase().trim();
            if (statusLower === 'draft') return 'bg-secondary';
            if (statusLower === 'pending admin' || statusLower === 'admin review') return 'bg-warning';
            if (statusLower === 'admin approved') return 'bg-success';
            if (statusLower === 'pending executive') return 'bg-purple';
            if (statusLower === 'completed') return 'bg-primary';
            if (statusLower === 'rejected') return 'bg-danger';
            return 'bg-secondary';
        }

        // Calculate stats from clients data (REFINED LOGIC)
        function calculateStats(clients) {
            const stats = {
                total_leads: clients.length,
                draft_proposals: 0,
                pending_admin: 0,
                admin_review: 0, 
                admin_approved: 0,
                pending_executive: 0,
                completed: 0
            };

            clients.forEach(client => {
                // Normalize the stage name from the database for reliable comparison
                const stage = client.workflow_stage.toLowerCase().trim();
                
                if (stage === 'draft') {
                    stats.draft_proposals++;
                } else if (stage === 'pending admin') {
                    stats.pending_admin++;
                } else if (stage === 'admin review') { 
                    stats.admin_review++;
                } else if (stage === 'admin approved') {
                    stats.admin_approved++;
                } else if (stage === 'pending executive') {
                    stats.pending_executive++;
                } else if (stage === 'completed') {
                    stats.completed++;
                }
            });

            // Combine for the 'Pending Admin / Review' card
            stats.pending_admin_combined = stats.pending_admin + stats.admin_review;
            return stats;
        }

        // Update stats display
        function updateStatsDisplay(stats) {
            animateValue('totalLeads', parseInt($('#totalLeads').text()) || 0, stats.total_leads, 500);
            animateValue('draftProposals', parseInt($('#draftProposals').text()) || 0, stats.draft_proposals, 500);
            // Display the combined count for 'Pending Admin' card
            animateValue('pendingAdmin', parseInt($('#pendingAdmin').text()) || 0, stats.pending_admin_combined, 500); 
            animateValue('adminApproved', parseInt($('#adminApproved').text()) || 0, stats.admin_approved, 500);
            animateValue('pendingExecutive', parseInt($('#pendingExecutive').text()) || 0, stats.pending_executive, 500);
            animateValue('completed', parseInt($('#completed').text()) || 0, stats.completed, 500);
        }

        // Fetch dashboard stats (API URL without .php)
        function fetchDashboardStats() {
            $.ajax({
                url: 'get_dashboard_stats',
                method: 'GET',
                success: function(response) {
                    const data = JSON.parse(response);
                    $('#salespersonName').text(data.salesperson_name);
                },
                error: function(error) {
                    console.error('Error fetching dashboard stats:', error);
                    $('#salespersonName').text('User');
                }
            });
        }

        // Animate number counting
        function animateValue(id, start, end, duration) {
            const obj = document.getElementById(id);
            const range = end - start;
            const increment = range / (duration / 16);
            let current = start;
            
            const timer = setInterval(() => {
                current += increment;
                if ((increment > 0 && current >= end) || (increment < 0 && current <= end)) {
                    current = end;
                    clearInterval(timer);
                }
                obj.textContent = Math.floor(current);
            }, 16);
        }

        // Fetch clients (API URL without .php)
        function fetchClients() {
            $.ajax({
                url: 'get_clients',
                method: 'GET',
                success: function(response) {
                    allClients = JSON.parse(response);
                    
                    const stats = calculateStats(allClients);
                    updateStatsDisplay(stats);
                    
                    // Re-apply the current filter after fetch
                    const currentStatus = currentFilter; 
                    currentFilter = 'all'; 
                    filterByStatus(currentStatus); 
                },
                error: function(error) {
                    console.error('Error fetching clients:', error);
                    $('#clientsTbody').html(`
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-exclamation-circle d-block mb-3" style="font-size: 3rem;"></i>
                                Error loading clients. Please try again.
                            </td>
                        </tr>
                    `);
                }
            });
        }

        // Render clients table (Action URLs without .php)
        function renderClientsTable(clients) {
            let tbodyContent = '';
            
            if (clients.length === 0) {
                tbodyContent = `
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox d-block mb-3" style="font-size: 3rem;"></i>
                            No clients found
                        </td>
                    </tr>
                `;
            } else {
                clients.forEach(client => {
                    const workflowStage = client.workflow_stage.trim();
                    const workflowStageLower = workflowStage.toLowerCase();
                    
                    // Strict comparison on the normalized stage
                    const isPendingAdmin = workflowStageLower === 'pending admin';
                    const isAdminReview = workflowStageLower === 'admin review'; 
                    const isAdminApproved = workflowStageLower === 'admin approved';
                    const isDraft = workflowStageLower === 'draft';
                    const isRejected = workflowStageLower === 'rejected';
                    
                    // Propose button is needed for Draft, Rejected, Pending Admin, or Admin Review
                    const isProposable = isDraft || isRejected || isPendingAdmin || isAdminReview;
                    
                    const proposeButton = isProposable ? `
                        <a href="prepare_proposal?client_id=${client.client_id}" class="btn btn-sm btn-success">
                            <i class="bi bi-file-earmark-plus"></i> Propose
                        </a>
                    ` : '';

                    // Show Edit Menu button when status is "Admin Review"
                    const editMenuButton = isAdminReview ? `
                        <a href="edit_menu_items?client_id=${client.client_id}" class="btn btn-sm btn-warning">
                            <i class="bi bi-pencil-square"></i> Edit Menu
                        </a>
                    ` : '';

                    tbodyContent += `
                        <tr>
                            <td class="fw-semibold">${client.client_name}</td>
                            <td>
                                <span class="badge ${getLeadBadgeClass(client.lead_status)}">
                                    ${client.lead_status}
                                </span>
                            </td>
                            <td>${client.contact_no}</td>
                            <td>${client.date_time_of_event}</td>
                            <td>${client.event_type}</td>
                            <td>
                                <span class="badge ${getStatusBadgeClass(workflowStage)}">
                                    ${workflowStage}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex gap-1 flex-wrap">
                                    <a href="view_proposal?client_id=${client.client_id}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                    ${proposeButton}
                                    ${editMenuButton}
                                    <a href="client_sales_update?client_id=${client.client_id}" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteClient(${client.client_id})">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                });
            }
            
            $('#clientsTbody').html(tbodyContent);
        }

        // Filter by status (REFINED LOGIC)
        function filterByStatus(status) {
            currentFilter = status;
            
            // Remove active class from all cards
            $('.stat-card').removeClass('active');
            
            // Add active class to clicked card
            $('#card-' + status).addClass('active');
            
            // Update filter badge and clear button
            if (status === 'all') {
                $('#filterBadge').hide();
                $('#clearFilter').hide();
            } else {
                const filterNames = {
                    'draft': 'Draft',
                    'pending_admin': 'Pending Admin/Review', 
                    'admin_approved': 'Admin Approved',
                    'pending_executive': 'Pending Executive',
                    'completed': 'Completed'
                };
                $('#filterBadge').text('Filter: ' + filterNames[status]).show();
                $('#clearFilter').show();
            }
            
            let filteredClients = allClients;

            if (status !== 'all') {
                filteredClients = allClients.filter(client => {
                    // Normalize the stage name for filtering
                    const workflowStage = client.workflow_stage.toLowerCase().trim();
                    
                    switch(status) {
                        case 'draft':
                            return workflowStage === 'draft';
                        case 'pending_admin': // Filters both 'pending admin' AND 'admin review'
                            return workflowStage === 'pending admin' || workflowStage === 'admin review';
                        case 'admin_approved':
                            return workflowStage === 'admin approved';
                        case 'pending_executive':
                            return workflowStage === 'pending executive';
                        case 'completed':
                            return workflowStage === 'completed';
                        default:
                            return false; // Should not happen
                    }
                });
            }

            renderClientsTable(filteredClients);
            
            // Clear search when filtering
            $('#searchInput').val('');
        }

        // Search functionality (API URL without .php)
        $('#searchInput').on('keyup', function() {
            const searchTerm = $(this).val().toLowerCase();
            
            let clientsToSearch;
            if (currentFilter === 'all') {
                clientsToSearch = allClients;
            } else {
                // Re-apply the current filter logic
                clientsToSearch = allClients.filter(client => {
                     const workflowStage = client.workflow_stage.toLowerCase().trim();
                     if (currentFilter === 'draft') return workflowStage === 'draft';
                     if (currentFilter === 'pending_admin') return workflowStage === 'pending admin' || workflowStage === 'admin review';
                     if (currentFilter === 'admin_approved') return workflowStage === 'admin approved';
                     if (currentFilter === 'pending_executive') return workflowStage === 'pending executive';
                     if (currentFilter === 'completed') return workflowStage === 'completed';
                     return false; 
                });
            }
            
            // Apply the search to the filtered list
            const searchedClients = clientsToSearch.filter(client => {
                const searchSource = [
                    client.client_name, 
                    client.contact_no,
                    client.event_type,
                    client.workflow_stage,
                    client.lead_status
                ].join(' ').toLowerCase();
                
                return searchSource.includes(searchTerm);
            });

            renderClientsTable(searchedClients);
        });

        // Delete client function (API URL without .php)
        function deleteClient(clientId) {
            if (confirm('Are you sure you want to delete this client?')) {
                $.ajax({
                    url: 'delete_client',
                    method: 'POST',
                    data: { client_id: clientId },
                    success: function(response) {
                        const data = JSON.parse(response);
                        if (data.success) {
                            fetchClients();
                            fetchDashboardStats();
                            
                            alert('Client deleted successfully!');
                        } else {
                            alert('Error deleting client: ' + (data.message || 'Unknown error'));
                        }
                    },
                    error: function(error) {
                        console.error('Error deleting client:', error);
                        alert('Could not delete client due to a server error.');
                    }
                });
            }
        }

        // Initialize dashboard
        $(document).ready(function() {
            fetchDashboardStats();
            fetchClients();
        });
    </script>
    </body>
</html>