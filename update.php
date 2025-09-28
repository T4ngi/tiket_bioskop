<?php
session_start();
require 'config.php';

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$stmt = $conn->prepare("SELECT * FROM pemesanan WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$data = $res->fetch_assoc();
$stmt->close();

if (!$data) {
    header("Location: admin_page.php");
    exit();
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pelanggan = trim($_POST['pelanggan'] ?? '');
    $film      = trim($_POST['film'] ?? '');
    $snack     = trim($_POST['snack'] ?? '');
    $drink     = trim($_POST['drink'] ?? '');
    $kursi     = strtoupper(trim($_POST['kursi'] ?? ''));
    $start_waktu_input = trim($_POST['start_waktu'] ?? '');
    $durasi_hours = (int) ($_POST['durasi_hours'] ?? 0);

    // validasi sederhana
    if ($pelanggan === '' || $film === '' || $kursi === '' || $start_waktu_input === '' || $durasi_hours < 1) {
        $err = "Lengkapi field yang wajib.";
    } else {
        $start_waktu = str_replace('T', ' ', $start_waktu_input) . ':00';
        $durasi_dt = date('Y-m-d H:i:s', strtotime($start_waktu . " +{$durasi_hours} hours"));

        $stmt = $conn->prepare("UPDATE pemesanan SET pelanggan = ?, film = ?, snack = ?, drink = ?, kursi = ?, start_waktu = ?, durasi = ? WHERE id = ?");
        $stmt->bind_param('sssssssi', $pelanggan, $film, $snack, $drink, $kursi, $start_waktu, $durasi_dt, $id);
        $stmt->execute();
        $stmt->close();

        header("Location: admin_page.php");
        exit();
    }
}

// prepare values for datetime-local inputs
$start_val = !empty($data['start_waktu']) ? date('Y-m-d\TH:i', strtotime($data['start_waktu'])) : '';
$durasi_val = '';
if (!empty($data['durasi']) && !empty($data['start_waktu'])) {
    $diff_hours = (strtotime($data['durasi']) - strtotime($data['start_waktu'])) / 3600;
    $durasi_val = (int) round($diff_hours);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Edit Pesanan</title>
  <link rel="stylesheet" href="admin.css">
</head>
<body>
  <h1>Edit Pesanan</h1>

  <?php if ($err): ?><p style="color:red;"><?= htmlspecialchars($err); ?></p><?php endif; ?>

  <form method="POST">
    <label>Pelanggan:</label>
    <input type="text" name="pelanggan" value="<?= htmlspecialchars($data['pelanggan']); ?>" required><br>

    <label>Film:</label>
    <input type="text" name="film" value="<?= htmlspecialchars($data['film']); ?>" required><br>

    <label>Snack:</label>
    <input type="text" name="snack" value="<?= htmlspecialchars($data['snack']); ?>"><br>

    <label>Drink:</label>
    <input type="text" name="drink" value="<?= htmlspecialchars($data['drink']); ?>"><br>

    <label>Kursi:</label>
    <input type="text" name="kursi" value="<?= htmlspecialchars($data['kursi']); ?>" required><br>

    <label>Start Waktu (tanggal & jam):</label>
    <input type="datetime-local" name="start_waktu" value="<?= $start_val; ?>" required><br>

    <label>Durasi (jam):</label>
    <select name="durasi_hours" required>
      <option value="1" <?= ($durasi_val==1)?'selected':''; ?>>1 jam</option>
      <option value="2" <?= ($durasi_val==2)?'selected':''; ?>>2 jam</option>
      <option value="3" <?= ($durasi_val==3)?'selected':''; ?>>3 jam</option>
    </select><br><br>

    <button type="submit">Update</button>
  </form>
</body>
</html>
