<?php
require_once 'config.php';
$error = '';
if (isset($_POST['login_admin'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $sql = "SELECT id, username, password, role FROM users WHERE username = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($user = $result->fetch_assoc()) {
            if (password_verify($password, $user['password']) && $user['role'] === 'admin') {
                $_SESSION['admin_user'] = $user;
                header("Location: dashbord.php");
                exit();
            } else {
                $error = "Identifiants invalides ou accès refusé.";
            }
        } else {
            $error = "Identifiants invalides.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion Admin - Nexa</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" />
    <style>
        body { background: #f4f6f8; font-family: 'Segoe UI', Arial, sans-serif; }
        .admin-login-container {
            max-width: 400px;
            margin: 80px auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            padding: 36px 32px 28px 32px;
        }
        .admin-login-container h2 {
            text-align: center;
            color: #1aaf5d;
            margin-bottom: 24px;
        }
        .admin-login-container input[type="text"],
        .admin-login-container input[type="password"] {
            width: 100%;
            padding: 12px 14px;
            border-radius: 8px;
            border: 1px solid #e4e6eb;
            margin-bottom: 18px;
            font-size: 1rem;
        }
        .admin-login-container button {
            width: 100%;
            background: #1aaf5d;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .admin-login-container button:hover {
            background: #188d4a;
        }
        .admin-login-container .error {
            color: #e74c3c;
            text-align: center;
            margin-bottom: 16px;
        }
        .admin-login-container .logo {
            display: block;
            text-align: center;
            font-size: 2.2rem;
            font-weight: bold;
            color: #1aaf5d;
            margin-bottom: 18px;
            letter-spacing: 2px;
        }
    </style>
</head>
<body>
    <div class="admin-login-container">
        <span class="logo">Nexa</span>
        <h2>Connexion Admin</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="post" action="login_admin.php">
            <input type="text" name="username" placeholder="Nom d'utilisateur" required autofocus>
            <input type="password" name="password" placeholder="Mot de passe" required>
            <button type="submit" name="login_admin"><i class="fas fa-sign-in-alt"></i> Connexion</button>
        </form>
    </div>
</body>
</html>