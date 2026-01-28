<?php
// vendor_redemption.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Vendor Redemption Points</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      padding: 20px;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .container { width: 100%; max-width: 600px; }
    .card {
      background: #fff;
      padding: 30px;
      border-radius: 12px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    }
    h2 {
      margin-bottom: 25px;
      color: #333;
      font-size: 24px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    h2 i { color: #667eea; }
    .form-group { margin-bottom: 20px; }
    label {
      display: block;
      margin-bottom: 8px;
      font-weight: 600;
      color: #555;
      font-size: 14px;
    }
    select, input {
      width: 100%;
      padding: 12px 15px;
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      font-size: 14px;
      transition: all 0.3s ease;
    }
    select:focus, input:focus {
      outline: none;
      border-color: #667eea;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    select { cursor: pointer; background: #fff; }
    input[type="number"] { -moz-appearance: textfield; }
    input[type="number"]::-webkit-outer-spin-button,
    input[type="number"]::-webkit-inner-spin-button {
      -webkit-appearance: none;
      margin: 0;
    }
    button {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: #fff;
      border: none;
      padding: 14px 24px;
      border-radius: 8px;
      cursor: pointer;
      font-size: 16px;
      font-weight: 600;
      width: 100%;
      transition: all 0.3s ease;
    }
    button:hover:not(:disabled) {
      transform: translateY(-2px);
      box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
    }
    button:disabled {
      background: #ccc;
      cursor: not-allowed;
      transform: none;
    }
    .msg {
      margin-top: 20px;
      font-size: 14px;
      padding: 12px 16px;
      border-radius: 8px;
      display: none;
      animation: slideIn 0.3s ease;
    }
    @keyframes slideIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .msg.success { color: #155724; background: #d4edda; border: 1px solid #c3e6cb; }
    .msg.error { color: #721c24; background: #f8d7da; border: 1px solid #f5c6cb; }
    .info-box {
      background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
      padding: 15px;
      border-radius: 8px;
      font-size: 14px;
      margin-bottom: 20px;
      display: none;
      border: 1px solid #c7d2fe;
    }
    .info-box strong { display: block; color: #4c51bf; margin-bottom: 5px; }
    .info-box .points { font-size: 20px; font-weight: bold; color: #667eea; margin-top: 5px; }
    .debug-box {
      background: #fff3cd;
      padding: 12px;
      border-radius: 8px;
      font-size: 11px;
      margin-top: 15px;
      display: none;
      border: 1px solid #ffc107;
      font-family: monospace;
      white-space: pre-wrap;
      max-height: 200px;
      overflow-y: auto;
    }
    #responseFrame { display: none; }
    @media (max-width: 640px) {
      body { padding: 10px; }
      .card { padding: 20px; }
      h2 { font-size: 20px; }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <h2>
        <i class="fa fa-credit-card"></i>
        Vendor Redemption Points
      </h2>

      <div id="currentInfo" class="info-box"></div>

      <form id="mainForm" method="POST" action="save_vendor_redemption.php" target="responseFrame">
        <div class="form-group">
          <label for="vendor_id">Vendor <span style="color:#e53e3e">*</span></label>
          <select id="vendor_id" name="vendor_id" required>
            <option value="">-- Select Vendor --</option>
          </select>
        </div>

        <div class="form-group">
          <label for="redemption_points">Redemption Points <span style="color:#e53e3e">*</span></label>
          <input
            type="number"
            step="0.01"
            id="redemption_points"
            name="redemption_points"
            placeholder="Enter redemption points"
            min="0"
            required
          >
        </div>

        <button type="submit" id="saveBtn">
          <i class="fa fa-save"></i> Save Redemption Points
        </button>
      </form>

      <iframe id="responseFrame" name="responseFrame"></iframe>

      <div id="msg" class="msg"></div>
      <div id="debugBox" class="debug-box"></div>
    </div>
  </div>

  <script>
    const GET_VENDORS_API = 'get_vendors.php';
    let isSubmitting = false;

    function showMessage(msg, isError = false) {
      const box = document.getElementById('msg');
      box.textContent = msg;
      box.className = 'msg ' + (isError ? 'error' : 'success');
      box.style.display = 'block';
      if (!isError) setTimeout(() => box.style.display = 'none', 5000);
    }

    function showDebug(data) {
      const box = document.getElementById('debugBox');
      box.style.display = 'block';
      box.textContent = 'Debug:\n' + JSON.stringify(data, null, 2);
    }

    function hideDebug() {
      document.getElementById('debugBox').style.display = 'none';
    }

    function showCurrentInfo(points, vendorName, previousPoints) {
      const info = document.getElementById('currentInfo');
      info.style.display = 'block';
      let html = '<strong>' + vendorName + '</strong>';
      html += '<div class="points">' + parseFloat(points).toFixed(2) + ' Points</div>';
      if (previousPoints !== undefined && previousPoints != points) {
        const change = parseFloat(points) - parseFloat(previousPoints);
        html += '<div style="font-size: 12px; color: #666; margin-top: 5px;">';
        html += 'Previous: ' + parseFloat(previousPoints).toFixed(2) + ' (' + (change > 0 ? '+' : '') + change.toFixed(2) + ')';
        html += '</div>';
      }
      info.innerHTML = html;
    }

    function hideCurrentInfo() {
      document.getElementById('currentInfo').style.display = 'none';
    }

    function setButtonLoading(isLoading) {
      const btn = document.getElementById('saveBtn');
      btn.disabled = isLoading;
      btn.innerHTML = isLoading
        ? '<i class="fa fa-spinner fa-spin"></i> Saving...'
        : '<i class="fa fa-save"></i> Save Redemption Points';
    }

    function loadVendors() {
      const xhr = new XMLHttpRequest();
      xhr.open('GET', GET_VENDORS_API, true);
      xhr.onload = function() {
        if (xhr.status === 200) {
          try {
            const data = JSON.parse(xhr.responseText);
            if (data.success && data.data) {
              const sel = document.getElementById('vendor_id');
              data.data.forEach(function(v) {
                const opt = document.createElement('option');
                opt.value = v.vendor_id;
                opt.textContent = v.vendor_name;
                sel.appendChild(opt);
              });
            } else {
              showMessage('Could not load vendors', true);
            }
          } catch (e) {
            showMessage('Error loading vendors: ' + e.message, true);
          }
        } else {
          showMessage('Failed to load vendors (Status: ' + xhr.status + ')', true);
        }
      };
      xhr.onerror = function() {
        showMessage('Network error loading vendors', true);
      };
      xhr.send();
    }

    window.handleIframeResponse = function() {
      try {
        const iframe = document.getElementById('responseFrame');
        const raw = iframe.contentWindow.document.body.textContent || iframe.contentWindow.document.body.innerText;
        const response = raw.trim();
        if (response) {
          const data = JSON.parse(response);
          setButtonLoading(false);
          if (data.success) {
            showMessage(data.message || 'Saved successfully!');
            const vendorSelect = document.getElementById('vendor_id');
            const vendorName = vendorSelect.options[vendorSelect.selectedIndex].text;
            if (data.current_points !== undefined) {
              showCurrentInfo(data.current_points, vendorName, data.previous_points);
            }
            if (data.debug) showDebug(data.debug);
            document.getElementById('redemption_points').value = '';
          } else {
            showMessage(data.message || 'Save failed', true);
            if (data.debug) showDebug(data.debug);
          }
        }
      } catch (e) {
        setButtonLoading(false);
        showMessage('Error processing response: ' + e.message, true);
      }
      isSubmitting = false;
    };

    function submitViaAJAX(e) {
      e.preventDefault();
      if (isSubmitting) return;
      isSubmitting = true;

      const vendor_id = document.getElementById('vendor_id').value;
      const redemption_points = document.getElementById('redemption_points').value;

      if (!vendor_id || !redemption_points) {
        showMessage('Please fill all required fields', true);
        isSubmitting = false;
        return;
      }

      setButtonLoading(true);
      hideDebug();

      const params = 'vendor_id=' + encodeURIComponent(vendor_id) +
                     '&redemption_points=' + encodeURIComponent(redemption_points);

      const xhr = new XMLHttpRequest();
      xhr.open('POST', 'save_vendor_redemption.php', true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.onload = function() {
        setButtonLoading(false);
        isSubmitting = false;
        try {
          const data = JSON.parse(xhr.responseText);
          if (data.success) {
            showMessage(data.message || 'Saved successfully!');
            const vendorSelect = document.getElementById('vendor_id');
            const vendorName = vendorSelect.options[vendorSelect.selectedIndex].text;
            if (data.current_points !== undefined) {
              showCurrentInfo(data.current_points, vendorName, data.previous_points);
            }
            if (data.debug) showDebug(data.debug);
            document.getElementById('redemption_points').value = '';
            document.getElementById('redemption_points').focus();
          } else {
            showMessage(data.message || 'Save failed', true);
            if (data.debug) showDebug(data.debug);
          }
        } catch (e) {
          showMessage('Error: ' + e.message, true);
          showDebug({ error: e.message, response: xhr.responseText, status: xhr.status });
        }
      };
      xhr.onerror = function() {
        setButtonLoading(false);
        isSubmitting = false;
        showMessage('Network error occurred', true);
      };
      xhr.timeout = 30000;
      xhr.send(params);
    }

    document.addEventListener('DOMContentLoaded', function() {
      loadVendors();
      document.getElementById('mainForm').addEventListener('submit', submitViaAJAX);

      document.getElementById('responseFrame').addEventListener('load', function() {
        if (isSubmitting) {
          handleIframeResponse();
        }
      });

      document.getElementById('vendor_id').addEventListener('change', function() {
        document.getElementById('redemption_points').value = '';
        hideCurrentInfo();
        hideDebug();
      });

      document.getElementById('redemption_points').addEventListener('blur', function() {
        if (this.value) {
          const val = parseFloat(this.value);
          if (!isNaN(val)) this.value = val.toFixed(2);
        }
      });
    });
  </script>

  <?php include 'footer.php'; ?>

  <script src="js/jquery.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  <script src="js/popper.min.js"></script>
  <script src="js/jquery-ui.js"></script>
  <script src="js/bootstrap.min.js"></script>
  <script src="js/jquery.fancybox.js"></script>
  <script src="js/jquery.magnific-popup.min.js"></script>
  <script src="js/jquery.mCustomScrollbar.concat.min.js"></script>
  <script src="js/owl.js"></script>
  <script src="js/paroller.js"></script>
  <script src="js/wow.js"></script>
  <script src="js/main.js"></script>
  <script src="js/nav-tool.js"></script>
  <script src="js/jquery-ui.js"></script>
  <script src="js/appear.js"></script>
  <script src="js/script.js"></script>
</body>
</html>
