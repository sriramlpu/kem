<?php
// kmk/finance/employee_create.php
require_once dirname(__DIR__) . '/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Add Employee</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style>
  body { background:#f6f8fb; }
</style>
</head>
<body class="bg-light">

<div class="container py-4" style="max-width: 720px;">
  <h3 class="mb-3">Add Employee</h3>
  <div id="errBox" class="alert alert-danger d-none"></div>
  <div id="okBox" class="alert alert-success d-none"></div>

  <form id="empForm" novalidate>
    <div class="mb-3">
      <label class="form-label">Employee Name *</label>
      <input type="text" name="employee_name" class="form-control" required />
    </div>

    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Mobile</label>
        <input type="text" name="mobile_number" class="form-control" />
      </div>
      <div class="col-md-6">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" />
      </div>
    </div>

    <div class="mb-3 mt-3">
      <label class="form-label">Address</label>
      <textarea name="address" rows="2" class="form-control"></textarea>
    </div>

    <!-- Branch -->
    <div class="mb-3">
      <div class="d-flex align-items-center justify-content-between">
        <label class="form-label mb-0">Branch *</label>
        <button type="button" id="btnReloadBranches" class="btn btn-sm btn-outline-secondary">Reload</button>
      </div>
      <select name="branch_id" id="branchSelect" class="form-select" required>
        <option value="">Loading branches…</option>
      </select>
      <div class="form-text">If you add a new branch, click Reload to refresh the list.</div>
    </div>

    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Role *</label>
        <input type="text" name="role" class="form-control" required />
      </div>
      <div class="col-md-6">
        <label class="form-label">Salary (₹) *</label>
        <input type="number" step="0.01" min="0" name="salary" class="form-control" required />
      </div>
    </div>

    <div class="mt-4 d-flex gap-2">
      <button class="btn btn-primary" type="submit">Save</button>
      <a class="btn btn-secondary" href="employees.php">Cancel</a>
    </div>
  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  const API = 'api/employee_api.php';
  const form = document.getElementById('empForm');
  const errBox = document.getElementById('errBox');
  const okBox  = document.getElementById('okBox');
  const branchSelect = document.getElementById('branchSelect');
  const btnReloadBranches = document.getElementById('btnReloadBranches');

  function showErr(msg){
    errBox.textContent = msg || 'Something went wrong';
    errBox.classList.remove('d-none');
    okBox.classList.add('d-none');
  }
  function showOk(msg){
    okBox.textContent = msg || 'Saved';
    okBox.classList.remove('d-none');
    errBox.classList.add('d-none');
  }

  async function loadBranches(){
    branchSelect.innerHTML = '<option value="">Loading branches…</option>';
    try {
      const res = await fetch(API + '?action=branches', { method:'GET' });
      const text = await res.text();
      let payload;
      try { payload = JSON.parse(text); }
      catch(e){
        showErr('Could not load branches. Response: ' + text.slice(0,200));
        branchSelect.innerHTML = '<option value="">Failed to load</option>';
        return;
      }

      if (Array.isArray(payload)) {
        if (!payload.length){
          branchSelect.innerHTML = '<option value="">No branches found</option>';
          return;
        }
        branchSelect.innerHTML = '<option value="">Select Branch</option>';
        payload.forEach(r => {
          if (r && r.branch_id && r.branch_name){
            const opt = document.createElement('option');
            opt.value = String(r.branch_id);
            opt.textContent = String(r.branch_name);
            branchSelect.appendChild(opt);
          }
        });
        return;
      }
      if (payload && payload.status === 'error') {
        showErr(payload.message || 'Failed to load branches');
        branchSelect.innerHTML = '<option value="">Failed to load</option>';
        return;
      }
      showErr('Unexpected branches payload');
      branchSelect.innerHTML = '<option value="">Failed to load</option>';
    } catch (e){
      showErr('Failed to fetch branches. Check ' + API + ' is reachable.');
      branchSelect.innerHTML = '<option value="">Failed to load</option>';
    }
  }
  loadBranches();

  if (btnReloadBranches) {
    btnReloadBranches.addEventListener('click', loadBranches);
  }

  form.addEventListener('submit', async function(e){
    e.preventDefault();
    const fd = new FormData(form);
    fd.append('action','create');
    try {
      const res = await fetch(API, { method:'POST', body: fd });
      const text = await res.text();
      try {
        const j = JSON.parse(text);
        if (j.status === 'success') {
          showOk('Employee created: ' + (j.employee_uid || ''));
          window.location.href = 'employees.php';
        } else {
          showErr(j.message || 'Failed to create employee');
        }
      } catch(_){
        showErr('API did not return JSON. Response: ' + text.slice(0,300));
      }
    } catch(err){
      showErr('Request failed. Make sure '+API+' exists and is reachable.');
    }
  });
})();
</script>
</body>
</html>
