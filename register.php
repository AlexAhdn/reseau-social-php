<?php
session_start();
require_once 'config.php';

if (isLoggedIn()) {
    redirect('index.php');
}

$error = "";
$username = "";
$email = "";
$password = "";
$confirm_password = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "Veuillez remplir tous les champs.";
    } elseif ($password !== $confirm_password) {
        $error = "Les mots de passe ne correspondent pas.";
    } elseif (strlen($password) < 6) {
        $error = "Le mot de passe doit contenir au moins 6 caractères.";
    } else {
        // Vérifier si l'email existe déjà
        $sql = "SELECT id FROM users WHERE email = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = "Cet email est déjà utilisé.";
            } else {
                // Vérifier si le nom d'utilisateur existe déjà
                $sql = "SELECT id FROM users WHERE username = ?";
                if ($stmt = $conn->prepare($sql)) {
                    $stmt->bind_param("s", $username);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $error = "Ce nom d'utilisateur est déjà utilisé.";
                    } else {
                        // Créer le compte
                        $sql = "INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)";
                        if ($stmt = $conn->prepare($sql)) {
                            $password_hash = password_hash($password, PASSWORD_DEFAULT);
                            $stmt->bind_param("sss", $username, $email, $password_hash);
                            
                            if ($stmt->execute()) {
                                // Connexion automatique
                                $_SESSION['user_id'] = $stmt->insert_id;
                                $_SESSION['username'] = $username;
                                redirect('index.php');
                            } else {
                                $error = "Erreur lors de la création du compte.";
                            }
                            $stmt->close();
                        } else {
                            $error = "Erreur de base de données.";
                        }
                    }
                    $stmt->close();
                } else {
                    $error = "Erreur de base de données.";
                }
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
    <title>Inscription - Nexa</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f2f5;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .register-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        .register-title {
            text-align: center;
            margin-bottom: 30px;
            color: #1a73e8;
            font-size: 24px;
            font-weight: bold;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
        }
        input[type="text"], input[type="email"], input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box;
        }
        input:focus {
            outline: none;
            border-color: #1a73e8;
            box-shadow: 0 0 0 2px rgba(26,115,232,0.2);
        }
        .register-btn {
            width: 100%;
            padding: 12px;
            background-color: #1a73e8;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            margin-bottom: 20px;
        }
        .register-btn:hover {
            background-color: #1557b0;
        }
        .error {
            color: #d93025;
            text-align: center;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .links {
            text-align: center;
        }
        .links a {
            color: #1a73e8;
            text-decoration: none;
            font-size: 14px;
        }
        .links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <h1 class="register-title">Inscription sur Nexa</h1>
        
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo h($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Nom d'utilisateur</label>
                <input type="text" id="username" name="username" value="<?php echo h($username); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo h($email); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirmer le mot de passe</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            
            <button type="submit" class="register-btn">S'inscrire</button>
        </form>
        
        <div class="links">
            <a href="login.php">Déjà un compte ? Connectez-vous</a>
        </div>
    </div>
</body>
</html>
