<?php
// login.php
require_once 'config.php';

if (isLoggedIn()) {
    redirect('index.php'); // Redirige si déjà connecté
}

$email_err = $password_err = "";
$email = ""; // Pour conserver la valeur entrée par l'utilisateur

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Valider l'email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Veuillez entrer votre email.";
    } else {
        $email = trim($_POST["email"]);
    }

    // Valider le mot de passe
    if (empty(trim($_POST["password"]))) {
        $password_err = "Veuillez entrer votre mot de passe.";
    } else {
        $password = trim($_POST["password"]);
    }

    // Vérifier les identifiants
    if (empty($email_err) && empty($password_err)) {
        $sql = "SELECT id, username, password_hash FROM users WHERE email = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_email);
            $param_email = $email;

            if ($stmt->execute()) {
                $stmt->store_result();
                if ($stmt->num_rows == 1) {
                    $stmt->bind_result($id, $username, $password_hash);
                    if ($stmt->fetch()) {
                        if (password_verify($password, $password_hash)) {
                            // Mot de passe correct, démarrer une nouvelle session
                            $_SESSION["user_id"] = $id;
                            $_SESSION["username"] = $username;
                            redirect('index.php'); // Redirige vers la page d'accueil
                        } else {
                            $password_err = "Le mot de passe que vous avez entré n'est pas valide.";
                        }
                    }
                } else {
                    $email_err = "Aucun compte trouvé avec cet email.";
                }
            } else {
                echo "Oops! Une erreur inattendue est survenue.";
            }
            $stmt->close();
        }
    }
    $conn->close();
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