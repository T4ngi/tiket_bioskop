<?php
session_start();
require_once 'config.php';

if (isset($_POST['register'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $rawPassword = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'user';

    if ($name === '' || $email === '' || $rawPassword === '' || !in_array($role, ['user','admin'])) {
        $_SESSION['register_error'] = 'Isi semua field dengan benar.';
        $_SESSION['active_form'] = 'register';
        header("Location: index.php");
        exit();
    }

    // Cek apakah email sudah terdaftar
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $_SESSION['register_error'] = 'Email telah terdaftar!';
        $_SESSION['active_form'] = 'register';
        $stmt->close();
        header("Location: index.php");
        exit();
    }
    $stmt->close();

    $passwordHash = password_hash($rawPassword, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('ssss', $name, $email, $passwordHash, $role);
    if (!$stmt->execute()) {
        $_SESSION['register_error'] = 'Gagal mendaftar. Coba lagi.';
        $_SESSION['active_form'] = 'register';
        $stmt->close();
        header("Location: index.php");
        exit();
    }
    $stmt->close();

    // Sukses -> tunjuk form login
    $_SESSION['active_form'] = 'login';
    header("Location: index.php");
    exit();
}

if (isset($_POST['login'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $_SESSION['login_error'] = 'Isi email dan password.';
        $_SESSION['active_form'] = 'login';
        header("Location: index.php");
        exit();
    }

    $stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $user = $res->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            // login sukses
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] === 'admin') {
                header("Location: admin_page.php");
            } else {
                header("Location: user_page.php");
            }
            exit();
        }
    }
    // gagal
    $_SESSION['login_error'] = 'Email atau password salah';
    $_SESSION['active_form'] = 'login';
    header("Location: index.php");
    exit();
}
?>
