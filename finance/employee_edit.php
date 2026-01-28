<?php
// kmk/finance/employee_edit.php
require_once dirname(__DIR__) . '/functions.php';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: employees.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Edit Employee</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<style> body { background:#f6f8fb; } </style>
</head>
<body class="bg-light">
<div class="container py-4" style="max-width: 720px;">
  <h3 class="mb-3">Edit Employee</h3>
  <div id="errBox" class="alert alert-danger d-none"></div>
  <div id="okBox" class="alert alert-success d-none"></div>

  <form id="empForm">
    <input type="hidden" name="id" id="empId" value="<?php echo (int)$id; ?>" />

    <div class="mb-3">
      <label class="form-label">Employee Name *</label>
      <input type="text" name="employee_name" id="employee_name" class="form-control" required />
    </div>

    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Mobile</label>
        <input type="text" name="mobile_number" id="mobile_number" class="form-control" />
      </div>
      <div class="col-md-6">
        <label class="form-label">Email</label>
        <input type="email" name="email" id="email" class="form-control" />
      </div>
    </div>

    <div class="mb-3 mt-3">
      <label class="form-label">Address</label>
      <textarea name="address" id="address" rows="2" class="form-control"></textarea>
    </div>

    <div class="mb-3">
      <label class="form-label">Branch *</label>
      <select name="branch_id" id="branch_id" class="form-select" required>
        <option value="">Loading branches…</option>
      </select>
    </div>

    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Role *</label>
        <input type="text" name="role" id="role" class="form-control" required />
      </div>
      <div class="col-md-6">
        <label class="form-label">Salary (₹) *</label>
        <input type="number" step="0.01" name="salary" id="salary" class="form-control" required />
      </div>
    </div>

    <div class="mt-4 d-flex gap-2">
      <button class="btn btn-primary" type="submit">Update</button>
      <a class="btn btn-secondary" href="employees.">Cancel</a>
    </div>
  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  const API = 'api/employee_api.php';
  const errBox = document.getElementById('errBox');
  const okBox  = document.getElementById('okBox');
  const form = document.getElementById('empForm');
  const id = document.getElementById('empId').value;

  function showErr(msg){ errBox.textContent = msg || 'Something went wrong'; errBox.classList.remove('d-none'); okBox.classList.add('d-none'); }
  function showOk(msg){ okBox.textContent = msg || 'Saved'; okBox.classList.remove('d-none'); errBox.classList.add('d-none'); }

  async function loadBranchesAndEmployee(){
    try {
      const br = await fetch(API + '?action=branches');
      const brText = await br.text();
      let branches = [];
      try { branches = JSON.parse(brText); } catch(e){ showErr('Branches response not JSON: '+brText.slice(0,200)); return; }
      const sel = document.getElementById('branch_id');
      sel.innerHTML = '<option value="">Select Branch</option>';
      branches.forEach(b => {
        if (b && b.branch_id && b.branch_name){
          const opt = document.createElement('option');
          opt.value = String(b.branch_id);
          opt.textContent = String(b.branch_name);
          sel.appendChild(opt);
        }
      });

      const er = await fetch(API + '?action=getEmployee&id=' + encodeURIComponent(id));
      const erText = await er.text();
      let payload;
      try { payload = JSON.parse(erText); } catch(e){ showErr('Employee response not JSON: '+erText.slice(0,200)); return; }
      if (!payload || payload.status !== 'success' || !payload.employee){ showErr(payload.message || 'Employee not found'); return; }

      const emp = payload.employee;
      document.getElementById('employee_name').value = emp.employee_name || '';
      document.getElementById('mobile_number').value = emp.mobile_number || '';
      document.getElementById('email').value = emp.email || '';
      document.getElementById('address').value = emp.address || '';
      document.getElementById('role').value = emp.role || '';
      document.getElementById('salary').value = (emp.salary !== undefined && emp.salary !== null) ? emp.salary : '';
      if (emp.branch_id) document.getElementById('branch_id').value = String(emp.branch_id);
    } catch (e){
      showErr('Failed to load data: ' + (e && e.message ? e.message : e));
    }
  }

  loadBranchesAndEmployee();

  form.addEventListener('submit', async function(e){
    e.preventDefault();
    const fd = new FormData(form);
    fd.append('action','updateEmployee');

    try {
      const res = await fetch(API, { method:'POST', body: fd });
      const text = await res.text();
      let j;
      try { j = JSON.parse(text); } catch(_){ showErr('API did not return JSON. Response: ' + text.slice(0,300)); return; }
      if (j.status === 'success') {
        showOk('Employee updated');
        setTimeout(()=>{ window.location.href = 'employees.php'; }, 600);
      } else {
        showErr(j.message || 'Update failed');
      }
    } catch (err){
      showErr('Request failed.');
    }
  });
})();
</script>
</body>
</html>
