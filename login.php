<?php
session_start();
require_once 'config.php';

// DEBUG : Afficher les utilisateurs
if (isset($_GET['debug'])) {
    echo "<h2>Test de connexion à la base de données</h2>";
    
    try {
        $sql = "SELECT id, username, email FROM users";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->execute();
            $result = $stmt->get_result();
            
            echo "<h3>Utilisateurs dans la base :</h3>";
            if ($result->num_rows() > 0) {
                while ($row = $result->fetch_assoc()) {
                    echo "ID: " . $row['id'] . " - Username: " . $row['username'] . " - Email: " . $row['email'] . "<br>";
                }
            } else {
                echo "Aucun utilisateur trouvé dans la base.";
            }
        } else {
            echo "Erreur de préparation de la requête.";
        }
    } catch (Exception $e) {
        echo "Erreur : " . $e->getMessage();
    }
    exit();
}

// ... reste du code login.php ...
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Nexa</title>
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
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        .login-title {
            text-align: center;
            margin-bottom: 30px;
            color: #1a3a3a;
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
        input[type="email"], input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box;
        }
        input[type="email"]:focus, input[type="password"]:focus {
            outline: none;
            border-color: #1a3a3a;
            box-shadow: 0 0 0 2px rgba(26,115,232,0.2);
        }
        .login-btn {
            width: 100%;
            padding: 12px;
            background-color: #1a3a3a;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            margin-bottom: 20px;
        }
        .login-btn:hover {
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
            margin: 0 10px;
        }
        .links a:hover {
            text-decoration: underline;
        }
        .test-credentials {
            background-color: #e8f5e8;
            border: 1px solid #4caf50;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
        }
        .test-credentials h4 {
            margin: 0 0 10px 0;
            color: #2e7d32;
        }
        .test-credentials p {
            margin: 5px 0;
            font-size: 14px;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1 class="login-title">Connectez-vous sur Nexa</h1>
        
        <div class="test-credentials">
            <h4>Compte de test créé automatiquement :</h4>
            <p><strong>Email :</strong> test@test.com</p>
            <p><strong>Mot de passe :</strong> test123</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo h($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo h($email); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="login-btn">Se connecter</button>
        </form>
        
        <div class="links">
            <a href="forgot_password.php">Mot de passe oublié ?</a>
            <a href="register.php">Pas encore de compte ? Inscrivez-vous</a>
        </div>
    </div>
</body>
</html>
