<?php
session_start();
require_once 'config.php';

if (isLoggedIn()) {
    redirect('index.php');
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = "Veuillez remplir tous les champs.";
    } else {
        $sql = "SELECT id, username, password_hash FROM users WHERE email = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $email); // Utilisez $email au lieu de $param_email
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password_hash'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    redirect('index.php');
                } else {
                    $error = "Mot de passe incorrect.";
                }
            } else {
                $error = "Aucun compte trouvé avec cet email.";
            }
            $stmt->close();
        } else {
            $error = "Erreur de base de données.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Nexa</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .login-container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,.1); width: 400px; text-align: center; }
        h2 { color: #1c1e21; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; text-align: left; }
        label { display: block; margin-bottom: 5px; color: #606770; }
        input[type="email"], input[type="password"] { width: calc(100% - 22px); padding: 10px; border: 1px solid #dddfe2; border-radius: 6px; font-size: 16px; }
        .btn { width: 100%; padding: 12px; background-color: #1a3a3a; color: white; border: none; border-radius: 6px; font-size: 18px; cursor: pointer; transition: background-color .2s; }
        .btn:hover { background-color: #1a3a3a; }
        .error { color: #fa3e3e; font-size: 14px; margin-top: 5px; }
        .register-link, .forgot-password-link { margin-top: 20px; font-size: 14px; color: #1a3a3a; text-decoration: none; display: block; }
        .register-link:hover, .forgot-password-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Connectez-vous sur Nexa</h2>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>">
                <span class="error"><?php echo $email_err; ?></span>
            </div>
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password">
                <span class="error"><?php echo $password_err; ?></span>
            </div>
            <div class="form-group">
                <input type="submit" class="btn" value="Se connecter">
            </div>
        </form>
        <a href="forgot_password.php" class="forgot-password-link">Mot de passe oublié ?</a>
        <a href="register.php" class="register-link">Pas encore de compte ? Inscrivez-vous</a>
    </div>
</body>
</html>
