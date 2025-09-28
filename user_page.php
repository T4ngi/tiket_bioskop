<?php
session_start();
require 'config.php';

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'user') {
    header("Location: index.php");
    exit();
}

$success = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pelanggan = $_SESSION['name'];
    $film   = trim($_POST['film'] ?? '');
    $snack  = trim($_POST['snack'] ?? '');
    $drink  = trim($_POST['drink'] ?? '');
    $kursi  = strtoupper(trim($_POST['kursi'] ?? ''));
    $start_waktu_input = trim($_POST['start_waktu'] ?? ''); // format: 2025-09-28T19:00
    $durasi_hours = (int) ($_POST['durasi_hours'] ?? 0);

    // validasi dasar
    if ($film === '' || $start_waktu_input === '' || $durasi_hours < 1 || $durasi_hours > 12) {
        $errors[] = "Pilih film, waktu tayang, dan durasi yang valid.";
    }

    // validasi kursi (A-J; A-I 1..20, J 1..10)
    if (!preg_match('/^([A-J])([1-9][0-9]?|20)$/', $kursi, $m)) {
        $errors[] = "⚠ Format kursi tidak valid. Contoh: A10";
    } else {
        $row_letter = $m[1];
        $row_number = (int)$m[2];
        if ($row_letter === 'J' && ($row_number < 1 || $row_number > 10)) {
            $errors[] = "Baris J hanya tersedia J1–J10.";
        } elseif ($row_letter !== 'J' && ($row_number < 1 || $row_number > 20)) {
            $errors[] = "Baris $row_letter hanya tersedia 1–20.";
        }
    }

    // proses jika valid
    if (empty($errors)) {
        // konversi format datetime-local -> MySQL DATETIME
        $start_waktu = str_replace('T', ' ', $start_waktu_input) . ':00';
        // hitung end datetime
        $durasi_dt = date('Y-m-d H:i:s', strtotime($start_waktu . " +{$durasi_hours} hours"));

        // cek overlap: existing.start_waktu < new_end AND existing.durasi > new_start
        $check_sql = "
            SELECT COUNT(*) AS cnt
            FROM pemesanan
            WHERE kursi = ?
              AND start_waktu IS NOT NULL
              AND durasi IS NOT NULL
              AND start_waktu < ?
              AND durasi > ?
        ";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param('sss', $kursi, $durasi_dt, $start_waktu);
        $stmt->execute();
        $res = $stmt->get_result();
        $cnt_row = $res->fetch_assoc();
        $stmt->close();

        if ($cnt_row && $cnt_row['cnt'] > 0) {
            $errors[] = "Maaf, kursi $kursi sudah dipesan pada jadwal yang waktu tayangnya tumpang tindih.";
        } else {
            // insert pemesanan termasuk start_waktu & durasi (end datetime)
            $stmt = $conn->prepare("INSERT INTO pemesanan (pelanggan, film, snack, drink, kursi, start_waktu, durasi, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param('sssssss', $pelanggan, $film, $snack, $drink, $kursi, $start_waktu, $durasi_dt);
            if ($stmt->execute()) {
                $success = "✅ Tiket berhasil dipesan 🎉";
            } else {
                $errors[] = "❌ Gagal memesan tiket. Coba lagi.";
            }
            $stmt->close();
        }
    }
}

// ambil riwayat pesanan user
$stmt = $conn->prepare("SELECT * FROM pemesanan WHERE pelanggan = ? ORDER BY created_at DESC");
$stmt->bind_param('s', $_SESSION['name']);
$stmt->execute();
$orders = $stmt->get_result();
$stmt->close();

/**
 * Helper: format datetime atau tampilkan '-'
 */
function fmt_datetime($dt) {
    if (empty($dt)) return '-';
    $t = strtotime($dt);
    if ($t === false) return htmlspecialchars($dt);
    return date('d M Y H:i', $t);
}

function empty_or_dash($val) {
    return ($val === null || $val === '') ? '-' : htmlspecialchars($val);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>User - Pesan Tiket</title>
  <link rel="stylesheet" href="user.css">
  <style>
    /* tambahan styling untuk tabel riwayat agar rapih */
    .container-main {
      max-width: 980px;
      margin: 20px auto;
      padding: 0 16px;
    }

    .form-container {
      max-width: 560px;
      margin: 18px auto;
      padding: 18px;
      background: #fff;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }

    .messages { max-width:560px; margin:12px auto; }
    .errors { background:#fff0f0; border:1px solid #f5c2c7; padding:10px 14px; border-radius:6px; color:#a42834; list-style: none; }
    .success { max-width:560px; margin:12px auto; background:#e9f7ef; border:1px solid #cbeed6; padding:10px 14px; border-radius:6px; color:#155724; text-align:center; }

    .history-wrap {
      margin: 24px auto;
      max-width: 1000px;
      overflow-x: auto;
      background: #fff;
      border-radius: 8px;
      padding: 12px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }

    table.history-table {
      width: 100%;
      border-collapse: collapse;
      min-width: 860px;
      font-size: 14px;
    }
    table.history-table thead th {
      position: sticky;
      top: 0;
      background: #2c3e50;
      color: #fff;
      padding: 10px 12px;
      text-align: left;
      font-weight: 600;
    }
    table.history-table tbody td {
      padding: 10px 12px;
      border-bottom: 1px solid #eef2f6;
      vertical-align: middle;
      color: #333;
    }

    table.history-table tbody tr:nth-child(even) { background: #fbfdff; }
    table.history-table tbody tr:hover { background: #f1f8ff; }

    .col-id { width:60px; text-align:center; font-weight:600; }
    .col-film { width:220px; }
    .col-time { width:170px; }
    .col-snack { width:140px; text-align:center; }
    .col-drink { width:120px; text-align:center; }
    .col-seat { width:90px; text-align:center; font-weight:600; color:#0d6efd; }
    .col-created { width:160px; color:#666; }

    .action-links a { color:#d9534f; text-decoration:none; margin-left:6px; }
    .action-links a.edit { color:#0d6efd; }
    @media (max-width:720px) {
      table.history-table thead th, table.history-table tbody td { font-size:13px; padding:8px; }
    }
  </style>
</head>
<body>
  <header class="navbar">
    <nav>
      <ul>
        <li>Hai, <b><?= htmlspecialchars($_SESSION['name']); ?></b> 👋</li>
        <li><a href="logout.php">Logout</a></li>
      </ul>
    </nav>
  </header>

  <main class="container-main">
    <h1 style="text-align:center; color:#2c3e50; margin-top:18px;">🎟 Pesan Tiket - FAE Film</h1>

    <?php if (!empty($success)): ?>
      <div class="success"><?= htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <ul class="errors">
        <?php foreach ($errors as $e): ?>
          <li><?= htmlspecialchars($e); ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <div class="form-container" aria-labelledby="form-title">
      <form method="POST" id="orderForm">
        <label for="film">Pilih Film:</label>
        <select name="film" id="film" required>
          <option value="">-- Pilih Film --</option>
          <option value="Avengers: Endgame">Avengers: Endgame</option>
          <option value="Spiderman: No Way Home">Spiderman: No Way Home</option>
          <option value="The Batman">The Batman</option>
          <option value="Inside Out 2">Inside Out 2</option>
        </select>

        <label for="start_waktu">Waktu Mulai (tanggal & jam):</label>
        <input type="datetime-local" name="start_waktu" id="start_waktu" required>

        <label for="durasi_hours">Durasi (jam):</label>
        <select name="durasi_hours" id="durasi_hours" required>
          <option value="1">1 jam</option>
          <option value="2" selected>2 jam</option>
          <option value="3">3 jam</option>
        </select>

        <label for="snack">Pilih Snack:</label>
        <select name="snack" id="snack">
          <option value="">Tidak pesan</option>
          <option value="Popcorn Caramel">Popcorn Caramel</option>
          <option value="Popcorn Salted">Popcorn Salted</option>
          <option value="Hotdog">Hotdog</option>
        </select>

        <label for="drink">Pilih Minuman:</label>
        <select name="drink" id="drink">
          <option value="">Tidak pesan</option>
          <option value="Cola">Cola</option>
          <option value="Ice Tea">Ice Tea</option>
          <option value="Mineral Water">Mineral Water</option>
        </select>

        <label for="kursi">Nomor Kursi:</label>
        <input type="text" name="kursi" id="kursi" placeholder="Contoh: A10" required>

        <button type="submit" style="margin-top:8px;">Pesan Tiket</button>
      </form>
    </div>

    <section class="history-wrap" aria-labelledby="history-title">
      <h2 id="history-title" style="margin:6px 0 12px 4px; color:#2c3e50;">📜 Riwayat Pesanan</h2>
      <table class="history-table" role="table" aria-describedby="history-title">
        <thead>
          <tr>
            <th class="col-id">#</th>
            <th class="col-film">Film</th>
            <th class="col-time">Mulai</th>
            <th class="col-time">Selesai</th>
            <th class="col-snack">Snack</th>
            <th class="col-drink">Drink</th>
            <th class="col-seat">Kursi</th>
            <th class="col-created">Waktu Pesan</th>
            <th style="min-width:110px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($orders->num_rows > 0): ?>
          <?php while ($row = $orders->fetch_assoc()): ?>
            <tr>
              <td class="col-id"><?= (int)$row['id']; ?></td>
              <td class="col-film"><?= htmlspecialchars($row['film']); ?></td>
              <td class="col-time"><?= fmt_datetime($row['start_waktu'] ?? null); ?></td>
              <td class="col-time"><?= fmt_datetime($row['durasi'] ?? null); ?></td>
              <td class="col-snack"><?= empty_or_dash($row['snack']); ?></td>
              <td class="col-drink"><?= empty_or_dash($row['drink']); ?></td>
              <td class="col-seat"><?= htmlspecialchars($row['kursi']); ?></td>
              <td class="col-created"><?= (!empty($row['created_at']) ? date('d M Y H:i', strtotime($row['created_at'])) : '-'); ?></td>
              <td class="action-links">
                <!-- jika mau tambah tombol edit/hapus untuk user, bisa diaktifkan -->
                <!-- contoh: <a class="edit" href="edit_order.php?id=...">Edit</a> -->
                <span style="color:#888;">—</span>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="9" style="text-align:center; padding:18px; color:#666;">Belum ada pemesanan.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </section>
  </main>
</body>
</html>
