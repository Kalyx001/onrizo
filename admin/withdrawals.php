<?php
session_start();
include '../db_config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit;
}

// CSRF token
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(24));

// Filters & pagination
$status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where = '';
if ($status !== '') {
    $where = "WHERE status = '" . $status . "'";
}

// export CSV
if (isset($_GET['export']) && $_GET['export'] == '1'){
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="withdrawals.csv"');
    $out = fopen('php://output','w');
    fputcsv($out, ['id','admin_id','amount','destination','status','requested_at','transaction_id','processed_at']);
    $q = "SELECT * FROM withdrawals $where ORDER BY requested_at DESC";
    $r = $conn->query($q);
    while($row = $r->fetch_assoc()){
        fputcsv($out, [$row['id'],$row['admin_id'],$row['amount'],$row['destination'],$row['status'],$row['requested_at'],$row['transaction_id'],$row['processed_at']]);
    }
    fclose($out);
    exit;
}

// fetch paginated withdrawals
$countRes = $conn->query("SELECT COUNT(*) as c FROM withdrawals $where");
$total = $countRes ? (int)$countRes->fetch_assoc()['c'] : 0;
$pages = max(1, ceil($total / $per_page));

$res = $conn->query("SELECT * FROM withdrawals $where ORDER BY requested_at DESC LIMIT $offset, $per_page");
$withdrawals = [];
if ($res) {
    while ($r = $res->fetch_assoc()) $withdrawals[] = $r;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Withdrawals - Onrizo Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="admin_style.css">
<style>
.container{max-width:1100px; margin:24px auto}
.badge-pending{background:#fff3cd;color:#856404;padding:6px 10px;border-radius:12px;font-weight:700}
.badge-paid{background:#d4edda;color:#155724;padding:6px 10px;border-radius:12px;font-weight:700}
.badge-cancel{background:#f8d7da;color:#721c24;padding:6px 10px;border-radius:12px;font-weight:700}
</style>
</head>
<body>
  <div id="pageLoader" class="active">
    <div>
      <div class="spinner"></div>
      <div class="loader-text">Loading...</div>
    </div>
  </div>

<header>
    <h1>💸 Withdrawals</h1>
    <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="transactions.php">Transactions</a>
    </nav>
</header>

<div class="container">
    <div class="card p-3 mt-3">
        <?php if(!empty($withdrawals)): ?>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                <div>
                    <a href="?export=1<?= $status !== '' ? '&status=' . urlencode($status) : '' ?>" class="btn btn-sm btn-outline-secondary">Export CSV</a>
                </div>
                <div>
                    <form method="get" style="display:flex; gap:8px;">
                        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">All</option>
                            <option value="Pending" <?= $status === 'Pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="Reserved" <?= $status === 'Reserved' ? 'selected' : '' ?>>Reserved</option>
                            <option value="Paid" <?= $status === 'Paid' ? 'selected' : '' ?>>Paid</option>
                            <option value="Cancelled" <?= $status === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </form>
                </div>
            </div>
            <table class="table">
                <thead><tr><th>#</th><th>Admin ID</th><th>Amount</th><th>Destination</th><th>Status</th><th>Requested At</th><th>Txn ID</th><th>Processed At</th></tr></thead>
                <tbody>
                <?php foreach($withdrawals as $w): ?>
                <tr id="w-<?= $w['id'] ?>">
                    <td><?= htmlspecialchars($w['id']) ?></td>
                    <td><?= htmlspecialchars($w['admin_id']) ?></td>
                    <td>KES <?= number_format($w['amount'],0) ?></td>
                    <td><?= htmlspecialchars($w['destination']) ?></td>
                    <td>
                        <?php if($w['status']==='Pending' || $w['status']==='Reserved'): ?><span class="badge-pending"><?= $w['status'] ?></span>
                        <?php elseif($w['status']==='Paid'): ?><span class="badge-paid"><?= $w['status'] ?></span>
                        <?php else: ?><span class="badge-cancel"><?= $w['status'] ?></span><?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($w['requested_at']) ?></td>
                    <td><?= htmlspecialchars($w['transaction_id'] ?? '') ?></td>
                    <td><?= htmlspecialchars($w['processed_at'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div style="display:flex; gap:8px; justify-content:center; margin-top:12px;">
                <?php for($p=1;$p<=$pages;$p++): ?>
                    <a class="btn btn-sm btn-outline-primary" href="?page=<?= $p ?><?= $status !== '' ? '&status=' . urlencode($status) : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
            </div>
            <?php else: ?>
                <p class="p-3">No withdrawal requests yet.</p>
            <?php endif; ?>
    </div>
</div>

<script src="../loader.js"></script>
</body>
</html>
