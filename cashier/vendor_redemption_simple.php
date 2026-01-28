<?php
// vendor_redemption_simple.php

$message = '';
$message_type = '';
$previous_points = null;
$current_points  = null;
$selected_vendor_id = '';
$selected_vendor_name = '';

// DB config
$host = 'localhost';
$dbname = 'kmkglobal_web';
$username = 'kmkglobal_web';
$password = 'tI]rfPhdOo9zHdKw';

// 1) HANDLE POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // --- basic presence checks (important!) ---
        if (!isset($_POST['vendor_id']) || $_POST['vendor_id'] === '') {
            throw new Exception('Please select a vendor');
        }
        if (!isset($_POST['redemption_points']) || $_POST['redemption_points'] === '') {
            // this was your problem: empty string becomes 0.00
            throw new Exception('Please enter redemption points');
        }

        $vendor_id = (int)$_POST['vendor_id'];
        $redemption_points_raw = $_POST['redemption_points'];

        // allow 12.50, 12, "12.5"
        if (!is_numeric($redemption_points_raw)) {
            throw new Exception('Redemption points must be a number');
        }

        $redemption_points = (float)$redemption_points_raw;

        if ($vendor_id <= 0) {
            throw new Exception('Invalid vendor selected');
        }
        if ($redemption_points < 0) {
            throw new Exception('Points cannot be negative');
        }
        if ($redemption_points > 999999.99) {
            throw new Exception('Points too large');
        }

        // connect
        $pdo = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );

        $pdo->beginTransaction();

        // check vendor exists
        $stmtV = $pdo->prepare("SELECT vendor_name FROM vendors WHERE vendor_id = ?");
        $stmtV->execute([$vendor_id]);
        $vendor = $stmtV->fetch();
        if (!$vendor) {
            throw new Exception('Vendor not found in vendors table');
        }
        $selected_vendor_name = $vendor['vendor_name'];
        $selected_vendor_id = $vendor_id;

        // read existing vendor_totals row
        $stmtC = $pdo->prepare("SELECT redemption_points FROM vendor_totals WHERE vendor_id = ?");
        $stmtC->execute([$vendor_id]);
        $existing = $stmtC->fetch();

        if ($existing) {
            $previous_points = (float)$existing['redemption_points'];

            // update
            $stmtU = $pdo->prepare("
                UPDATE vendor_totals
                SET redemption_points = ?, updated_at = CURRENT_TIMESTAMP
                WHERE vendor_id = ?
            ");
            $stmtU->execute([$redemption_points, $vendor_id]);

            $message = "Redemption points updated for {$selected_vendor_name}.";
        } else {
            $previous_points = 0.00;

            // insert – match your table structure from dump
            $stmtI = $pdo->prepare("
                INSERT INTO vendor_totals
                (vendor_id, total_bill, total_paid, balance, updated_at, advance, redemption_points)
                VALUES (?, 0.00, 0.00, 0.00, CURRENT_TIMESTAMP, 0.00, ?)
            ");
            $stmtI->execute([$vendor_id, $redemption_points]);

            $message = "Redemption points added for {$selected_vendor_name}.";
        }

        // read back to confirm
        $stmtR = $pdo->prepare("SELECT redemption_points FROM vendor_totals WHERE vendor_id = ?");
        $stmtR->execute([$vendor_id]);
        $rowNow = $stmtR->fetch();
        $current_points = $rowNow ? (float)$rowNow['redemption_points'] : null;

        $pdo->commit();
        $message_type = 'success';

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = $e->getMessage();
        $message_type = 'error';
    }
}

// 2) LOAD VENDORS for dropdown
try {
    $pdo2 = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $vendorsStmt = $pdo2->query("SELECT vendor_id, vendor_name FROM vendors ORDER BY vendor_name");
    $vendors = $vendorsStmt->fetchAll();
} catch (PDOException $e) {
    $vendors = [];
    if ($message === '') {
        $message = 'Could not load vendors: ' . $e->getMessage();
        $message_type = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Vendor Redemption Points</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
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
    button:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
    }
    .alert {
      margin-top: 20px;
      padding: 12px 16px;
      border-radius: 8px;
      font-size: 14px;
      animation: slideIn 0.3s ease;
    }
    .alert-success { color: #155724; background: #d4edda; border: 1px solid #c3e6cb; }
    .alert-error { color: #721c24; background: #f8d7da; border: 1px solid #f5c6cb; }
    @keyframes slideIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .required { color: #e53e3e; }
    .info-box {
      margin-top: 12px;
      background: #eef2ff;
      border: 1px solid #cbd5ff;
      border-radius: 8px;
      padding: 10px 14px;
      font-size: 13px;
    }
    .info-box strong { display: block; margin-bottom: 4px; }
    @media (max-width: 640px) {
      body { padding: 10px; }
      .card { padding: 20px; }
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

      <?php if ($message): ?>
      <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'error'; ?>">
        <?php echo htmlspecialchars($message); ?>
      </div>
      <?php endif; ?>

      <?php if ($message_type === 'success' && $selected_vendor_name): ?>
        <div class="info-box">
          <strong><?php echo htmlspecialchars($selected_vendor_name); ?></strong>
          <div>Previous points: <?php echo number_format((float)$previous_points, 2); ?></div>
          <div>Current points: <?php echo number_format((float)$current_points, 2); ?></div>
        </div>
      <?php endif; ?>

      <form method="POST" action="">
        <div class="form-group">
          <label for="vendor_id">Vendor <span class="required">*</span></label>
          <select id="vendor_id" name="vendor_id" required>
            <option value="">-- Select Vendor --</option>
            <?php foreach ($vendors as $v): ?>
              <option value="<?php echo $v['vendor_id']; ?>"
                <?php echo ($v['vendor_id'] == ($selected_vendor_id ?: '')) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($v['vendor_name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="redemption_points">Redemption Points <span class="required">*</span></label>
          <input
            type="number"
            step="0.01"
            id="redemption_points"
            name="redemption_points"
            placeholder="Enter redemption points"
            min="0"
            value="<?php echo isset($_POST['redemption_points']) ? htmlspecialchars($_POST['redemption_points']) : ''; ?>"
            required
          >
        </div>

        <button type="submit">
          <i class="fa fa-save"></i> Save Redemption Points
        </button>
      </form>
    </div>
  </div>

  <script>
    document.getElementById('redemption_points').addEventListener('blur', function() {
      if (this.value) {
        const val = parseFloat(this.value);
        if (!isNaN(val)) this.value = val.toFixed(2);
      }
    });
  </script>
</body>
</html>
