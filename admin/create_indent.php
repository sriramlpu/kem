<?php
require_once("header.php");
require_once("nav.php");
?>
<section class="container-fluid section">
  <div class="row justify-content-center">
    <div class="col-md-12">
      <div class="card shadow-sm border-0">
        <div class="card-header bg-success text-white">Create Indent</div>
        <div class="card-body">
          <form id="indentForm">
            <div class="row">
              <div class="col-md-4">
                <div class="form-floating mb-3">
                  <select class="form-select" id="indent_against" name="indent_against">
                    <option value="">Select Indent Against</option>
                    <option value="Direct">Direct</option>
                     <option value="Order">Order</option> 
                  </select>
                  <label for="indent_against">Indent Against</label>
                </div>
              </div>
             
              <div class="col-md-4">
                <div class="form-floating mb-3">
                  <input type="date" class="form-control" id="indent_date" name="indent_date" required>
                  <label for="indent_date">Indent Date</label>
                </div>
              </div>
              <div class=" col-lg-4 col-md-6">
                <div class="form-floating mb-3">
                  <select class="form-select" id="branch" name="branch">
                    <option value="">Select Branch</option>
                  </select>
                  <label for="branch">Branch</label>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-floating mb-3">
                  <select class="form-select" id="raised_by" name="raised_by" required>
                    <option value="">Select User</option>
                  </select>
                  <label for="raised_by">Raised By</label>
                </div>
              </div>
               <div class="col-md-4">
                <div class="form-floating mb-3">
                  <textarea class="form-control" id="remarks" name="remarks" rows="1" placeholder="Remarks"></textarea>
                  <label for="remarks">Remarks</label>
                </div>
              </div>
            </div>
            
            <!-- Items Section -->
            <div class="mb-3">
              <label class="form-label">Items (Optional)</label>
              <div id="itemsContainer">
                <!-- Items will be added dynamically when user clicks Add Item -->
              </div>
              <button type="button" id="addItemBtn" class="btn btn-outline-success btn-sm mt-2">+ Add Item</button>
            </div>
            
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-success flex-fill">Create Indent</button>
              <button type="reset" class="btn btn-outline-secondary flex-fill">Reset</button>
              <a href="indent_list.php" class="btn btn-outline-primary flex-fill">View Indents</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

<script>
 const LOGGED_USER_ID = <?= $userId ?>;
  $(document).ready(function() {
    // Add visual feedback for invalid fields
    $('[required]').on('change', function() {
        if ($(this).val()) {
            $(this).removeClass('is-invalid');
        } else {
            $(this).addClass('is-invalid');
        }
    });
    
    // Load users for dropdowns
    function loadUsers(selectId) {
      
      $.getJSON('./api/users_api.php?action=getUsers', function(res) {
        let sel = $(selectId);
        sel.empty();
        sel.append('<option value="">Select User</option>');
        if (res.data && res.data.length > 0) {
          res.data.forEach(function(user) {
    let selected = (user.user_id == LOGGED_USER_ID) ? "selected" : "";
    sel.append(`<option value="${user.user_id}" ${selected}>${user.username}</option>`);
});
        }
      }).fail(function(xhr, status, error) {
        console.error('Failed to load users:', error);
        console.error('Response:', xhr.responseText);
        showAlert('Failed to load users: ' + error, 'danger');
      });
    }
    
    // Load branches for dropdowns
    function loadBranches() {
      return $.getJSON('./api/branches_api.php?action=getActiveBranches');
    }
    
    // Load services for dropdowns
    function loadServices() {
      return $.getJSON('./api/services_api.php?action=list');
    }
    
    // Load items for dropdowns
    function loadItems() {
      return $.getJSON('./api/items_api.php?action=getActiveItems');
    }
    
   
    
    
    loadUsers('#raised_by');
    
    // Load branches
    loadBranches().done(function(res) {
      if (res.status === 'success' && res.data) {
        const branchSelect = $('#branch');
        branchSelect.empty();
        branchSelect.append('<option value="">Select Branch</option>');
        res.data.forEach(function(branch) {
          branchSelect.append(`<option value="${branch.branch_id}">${branch.branch_name}</option>`);
        });
      } else {
        showAlert('Failed to load branches: ' + (res.message || 'Unknown error'), 'danger');
      }
    }).fail(function(xhr, status, error) {
      console.error('Failed to load branches:', error);
      console.error('Response:', xhr.responseText);
      showAlert('Failed to load branches: ' + error, 'danger');
    });
    
    
    
    // Initialize services and items data but don't add any item rows initially
    let servicesPromise = loadServices();
    let itemsPromise = loadItems();
    
    $.when(servicesPromise, itemsPromise).done(function(servicesRes, itemsRes) {
      if (servicesRes[0].status === 'success' && servicesRes[0].data && 
          itemsRes[0].status === 'success' && itemsRes[0].data) {
        window.services = servicesRes[0].data;
        window.items = itemsRes[0].data;
        // Don't add any item rows by default since items are optional
      } else {
        showAlert('Failed to load services or items', 'danger');
      }
    }).fail(function(xhr, status, error) {
      console.error('Failed to load services or items:', error);
      console.error('Response:', xhr.responseText);
      showAlert('Failed to load services or items: ' + error, 'danger');
    });
    
    // Counter for item rows
    let itemCount = 0;
    
    // Function to add a new item row
    function addItemRow() {
      const container = $('#itemsContainer');
      const index = itemCount++;
      
      const rowHtml = `
        <div class="row mb-3 item-row" data-index="${index}">
          <div class="col-md-4">
            <div class="form-floating">
              <select class="form-select" id="services_${index}" name="services[${index}]">
                <option value="">Select Service</option>
              </select>
              <label for="services_${index}">Services</label>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-floating">
              <select class="form-select" id="item_name_${index}" name="item_name[${index}]">
                <option value="">Select Item</option>
              </select>
              <label for="item_name_${index}">Item Name</label>
            </div>
          </div>
          <div class="col-md-3">
            <div class="form-floating">
              <input type="number" class="form-control" id="quantity_${index}" name="quantity[${index}]" min="1" step="0.01">
              <label for="quantity_${index}">Quantity</label>
            </div>
          </div>
          <div class="col-md-1 d-flex align-items-center">
            <button type="button" class="btn btn-outline-danger remove-item-btn" data-index="${index}">
              <i class="bi bi-trash"></i>
            </button>
          </div>
        </div>
      `;
      
      container.append(rowHtml);
      
      // Populate services dropdown
      const serviceSelect = $(`#services_${index}`);
      if (window.services) {
        window.services.forEach(function(service) {
          serviceSelect.append(`<option value="${service.service_id}">${service.service_name}</option>`);
        });
      }
      
      // Populate items dropdown
      const itemSelect = $(`#item_name_${index}`);
      if (window.items) {
        window.items.forEach(function(item) {
          itemSelect.append(`<option value="${item.item_id}">${item.item_name}</option>`);
        });
      }
    }
    
    // Add item button click handler
    $('#addItemBtn').on('click', function() {
      addItemRow();
    });
    
    // Use event delegation for remove buttons
    $('#itemsContainer').on('click', '.remove-item-btn', function() {
      const index = $(this).data('index');
      $(`.item-row[data-index="${index}"]`).remove();
    });
    
    // Reset form handler - clear all items and don't add any new ones
    $('#indentForm').on('reset', function() {
      // Clear all item rows
      $('#itemsContainer').empty();
      itemCount = 0;
      // Don't add any item rows after reset since items are optional
    });
    
    // Create Indent - Combined form submission handler
    $('#indentForm').on('submit', function(e) {
        e.preventDefault();
        
        // Validate required fields
        let isValid = true;
        $(this).find('[required]').each(function() {
            if (!$(this).val()) {
                isValid = false;
                $(this).addClass('is-invalid');
            } else {
                $(this).removeClass('is-invalid');
            }
        });
        
        if (!isValid) {
            showAlert('Please fill all required fields', 'danger');
            return false;
        }
        
        // Collect items data (items are optional)
        var items = [];
        $('.item-row').each(function() {
            const index = $(this).data('index');
            const serviceId = $(`#services_${index}`).val();
            const itemId = $(`#item_name_${index}`).val();
            const quantity = $(`#quantity_${index}`).val();
            
            // Only add if all fields are filled and quantity is positive
            if (serviceId && itemId && quantity && quantity > 0) {
                items.push({
                    service_id: parseInt(serviceId),
                    item_id: parseInt(itemId),
                    quantity: parseFloat(quantity)
                });
            }
        });
        
        // Create FormData object
        var formData = new FormData(this);
        formData.append('action', 'create');
        formData.append('items', JSON.stringify(items));
        
        // Debug: Log FormData contents
        console.log('FormData contents:');
        for (let [key, value] of formData.entries()) {
            console.log(key + ': ' + value);
        }
        
        // Show loading state
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.text();
        submitBtn.prop('disabled', true).text('Creating...');
        
        // Submit via AJAX
        $.ajax({
            url: './api/indent_api.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            processData: false,
            contentType: false,
            success: function(res) {
                if (res.status === 'success') {
                  showAlert(res.message);
                    submitBtn.prop('disabled', false).text(originalText);
                    window.location.href = 'indent_list.php';
                } else {
                    showAlert(res.message || 'Error occurred while creating indent', 'danger');
                    submitBtn.prop('disabled', false).text(originalText);
                }
            },
            error: function(xhr, status, error) {
                // Try to parse the response as JSON in case it's a JSON error message
                try {
                    var response = JSON.parse(xhr.responseText);
                    showAlert(response.message || 'An error occurred', 'danger');
                } catch (e) {
                    // If it's not JSON, show a more detailed error
                    let errorMsg = 'An error occurred while creating the indent. ';
                    errorMsg += 'Status: ' + status + ', ';
                    errorMsg += 'Error: ' + error;
                    
                    // Show first 200 characters of response if available
                    if (xhr.responseText) {
                        let responseText = xhr.responseText;
                        if (responseText.length > 200) {
                            responseText = responseText.substring(0, 200) + '...';
                        }
                        errorMsg += ', Response: ' + responseText;
                    }
                    
                    showAlert(errorMsg, 'danger');
                }
                
                submitBtn.prop('disabled', false).text(originalText);
            }
        });
        
        return false;
    });
  });
</script>
<script src="./js/showAlert.js"></script>
<?php
require_once("footer.php");
?>