<?php
session_start();
require 'config.php';
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// ambil semua pemesanan, sertakan kolom start_waktu & durasi
$result = $conn->query("SELECT * FROM pemesanan ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard - FAE Film</title>
<link rel="stylesheet" href="admin.css">
</head>
<body>
<header class="navbar">
<nav>
<ul>
<li>Welcome, <b><?= htmlspecialchars($_SESSION['name']); ?></b> (Admin)</li>
<li><a href="logout.php">Logout</a></li>
</ul>
</nav>
</header>

<h1> Admin Dashboard - FAE Film</h1>

<table border="1" cellpadding="10" cellspacing="0">
<thead>
<tr>
  <th>ID</th>
  <th>Pelanggan</th>
  <th>Film</th>
  <th>Start Waktu</th>
  <th>End Waktu</th>
  <th>Snack</th>
  <th>Drink</th>
  <th>Kursi</th>
  <th>Waktu Pesan</th>
  <th>Aksi</th>
</tr>
</thead>
<tbody>
<?php while ($row = $result->fetch_assoc()): ?>
<tr>
  <td><?= (int)$row['id']; ?></td>
  <td><?= htmlspecialchars($row['pelanggan']); ?></td>
  <td><?= htmlspecialchars($row['film']); ?></td>
  <td><?= htmlspecialchars($row['start_waktu'] ?? '-'); ?></td>
  <td><?= htmlspecialchars($row['durasi'] ?? '-'); ?></td>
  <td><?= htmlspecialchars($row['snack']); ?></td>
  <td><?= htmlspecialchars($row['drink']); ?></td>
  <td><?= htmlspecialchars($row['kursi']); ?></td>
  <td><?= htmlspecialchars($row['created_at']); ?></td>
  <td>
    <a href="update.php?id=<?= (int)$row['id']; ?>">✏ Edit</a> |
    <a href="delete.php?id=<?= (int)$row['id']; ?>" onclick="return confirm('Yakin hapus data ini?')">🗑 Delete</a>
  </td>
</tr>
<?php endwhile; ?>
</tbody>
</table>

</body>
</html>

      </tbody>
    </table>

    
  </main>
</body>
</html>
