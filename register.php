<?php
// register.php
require_once 'config.php'; // Assurez-vous que ce fichier contient session_start() ou qu'il est appelé avant tout autre output.

if (isLoggedIn()) {
    redirect('index.php'); // Redirige si déjà connecté
}

$username_err = $email_err = $password_err = $dob_err = $sex_err = "";
$username = $email = $dob = $sex = ""; // Pour conserver les valeurs entrées par l'utilisateur
$confirm_password_err = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Valider le nom d'utilisateur
    if (empty(trim($_POST["username"]))) {
        $username_err = "Veuillez entrer un nom d'utilisateur.";
    } else {
        $username = trim($_POST["username"]);
        $sql = "SELECT id FROM users WHERE username = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_username);
            $param_username = $username;
            if ($stmt->execute()) {
                $stmt->store_result();
                if ($stmt->num_rows == 1) {
                    $username_err = "Ce nom d'utilisateur est déjà pris.";
                }
            } else {
                echo "Oops! Une erreur inattendue est survenue lors de la vérification du nom d'utilisateur.";
            }
            $stmt->close();
        }
    }

    // Valider l'email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Veuillez entrer une adresse email.";
    } elseif (!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        $email_err = "Format d'email invalide.";
    } else {
        $email = trim($_POST["email"]);
        $sql = "SELECT id FROM users WHERE email = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_email);
            $param_email = $email;
            if ($stmt->execute()) {
                $stmt->store_result();
                if ($stmt->num_rows == 1) {
                    $email_err = "Cette adresse email est déjà utilisée.";
                }
            } else {
                echo "Oops! Une erreur inattendue est survenue lors de la vérification de l'email.";
            }
            $stmt->close();
        }
    }

    // Valider la date de naissance
    if (empty(trim($_POST["date_of_birth"]))) {
        $dob_err = "Veuillez entrer votre date de naissance.";
    } else {
        $dob = trim($_POST["date_of_birth"]);
        // Validation simple du format de date AAAA-MM-JJ
        if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $dob)) {
            $dob_err = "Format de date invalide (AAAA-MM-JJ).";
        } else {
            // Vérifier que la date n'est pas dans le futur et qu'elle a plus de 13 ans minimum
            $min_age_date_limit = date('Y-m-d', strtotime('-13 years'));
            // L'utilisateur doit être né avant ou le jour de $min_age_date_limit
            if ($dob > date('Y-m-d') || $dob > $min_age_date_limit) {
                $dob_err = "Vous devez avoir au moins 13 ans pour vous inscrire et la date ne peut pas être dans le futur.";
            }
        }
    }

    // Valider le sexe
    if (empty($_POST["sex"])) {
        $sex_err = "Veuillez sélectionner votre sexe.";
    } else {
        $sex = $_POST["sex"];
        // Assurez-vous que la valeur soumise est une des valeurs ENUM attendues
        if (!in_array($sex, ['Homme', 'Femme'])) { // Ajoutez d'autres options si votre ENUM les inclut
            $sex_err = "Valeur de sexe invalide.";
        }
    }

    // Valider le mot de passe
    if (empty($_POST["password"])) { // Pas besoin de trim ici pour la vérification empty
        $password_err = "Veuillez entrer un mot de passe.";
    } elseif (strlen($_POST["password"]) < 6) {
        $password_err = "Le mot de passe doit contenir au moins 6 caractères.";
    }
    // Valider la confirmation du mot de passe
    if (empty($_POST["confirm_password"])) {
        $confirm_password_err = "Veuillez confirmer votre mot de passe.";
    } elseif ($_POST["password"] !== $_POST["confirm_password"]) {
        $confirm_password_err = "Les mots de passe ne correspondent pas.";
    }

    // Vérifier les erreurs avant d'insérer dans la base de données
    if (empty($username_err) && empty($email_err) && empty($dob_err) && empty($sex_err) && empty($password_err) && empty($confirm_password_err)) {
        // La colonne du mot de passe doit être 'password_hash' selon votre code précédent.
        // Assurez-vous que le nom de la colonne dans la DB correspond bien.
        $sql = "INSERT INTO users (username, email, date_of_birth, sex, password_hash, profile_picture) VALUES (?, ?, ?, ?, ?, ?)";

        if ($stmt = $conn->prepare($sql)) {
            // Lier les paramètres : ssssss pour 6 chaînes (username, email, dob, sex, password_hash, profile_picture)
            $param_username = $username;
            $param_email = $email;
            $param_dob = $dob;
            $param_sex = $sex; // Le nouveau paramètre
            $param_password_hash = password_hash($_POST["password"], PASSWORD_DEFAULT); // Utilisez $_POST["password"] directement
            $default_profile_pic = 'default.jpg';

            $stmt->bind_param("ssssss", $param_username, $param_email, $param_dob, $param_sex, $param_password_hash, $default_profile_pic);

            if ($stmt->execute()) {
                redirect('login.php');
            } else {
                echo "Quelque chose s'est mal passé lors de l'enregistrement. Veuillez réessayer plus tard. Erreur: " . $stmt->error;
            }
            $stmt->close();
        } else {
            echo "Erreur de préparation de la requête d'insertion: " . $conn->error;
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
    <title>Inscription - Nexa</title>
    <link rel="stylesheet" href="Devoir_Php-feature-auth-chat/assets/css/style.css">
    <style>
        body {
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f0f2f5;
        }
        .register-container {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
            width: 400px;
            text-align: center;
        }
        h2 { color: #1a3a3a; margin-bottom: 20px; font-size: 2rem; }
        .form-group { margin-bottom: 15px; text-align: left; }
        label { display: block; margin-bottom: 5px; color: #606770; }
        input[type="text"], input[type="email"], input[type="password"], input[type="date"], select { width: calc(100% - 22px); padding: 10px; border: 1px solid #dddfe2; border-radius: 6px; font-size: 16px; }
        select {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%234dcab1%22%20d%3D%22M287%2C188.85L146.2%2C32.17L5.4%2C188.85h281.6z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 12px;
            padding-right: 30px;
        }
        .btn { width: 100%; padding: 12px; background-color: #1a3a3a; color: white; border: none; border-radius: 6px; font-size: 18px; cursor: pointer; transition: background-color .2s; }
        .btn:hover { background-color: #1a3a3a; }
        .error { color: #fa3e3e; font-size: 14px; margin-top: 5px; }
        .login-link { margin-top: 20px; font-size: 14px; color: #4dcab1; text-decoration: none; }
        .login-link:hover { text-decoration: underline; color: #31a24c; }
    </style>
</head>
<body>

<h2></h2>
    <div class="register-container">
        <h2>Bienvenu sur Nexa inscrivez-vous</h2>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label for="username">Nom d'utilisateur</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
                <span class="error"><?php echo $username_err; ?></span>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                <span class="error"><?php echo $email_err; ?></span>
            </div>
            <div class="form-group">
                <label for="date_of_birth">Date de naissance</label>
                <input type="date" id="date_of_birth" name="date_of_birth" value="<?php echo htmlspecialchars($dob); ?>" required>
                <span class="error"><?php echo $dob_err; ?></span>
            </div>
            <div class="form-group">
                <label for="sex">Sexe</label>
                <select name="sex" id="sex" required>
                    <option value="" disabled <?php echo empty($sex) ? 'selected' : ''; ?>>Sélectionner votre sexe</option>
                    <option value="Homme" <?php echo ($sex == 'Homme') ? 'selected' : ''; ?>>Homme</option>
                    <option value="Femme" <?php echo ($sex == 'Femme') ? 'selected' : ''; ?>>Femme</option>
                    </select>
                <span class="error"><?php echo $sex_err; ?></span>
            </div>
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required>
                <span class="error"><?php echo $password_err; ?></span>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirmer le mot de passe</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
                <span class="error"><?php echo $confirm_password_err; ?></span>
            </div>
            <div class="form-group">
                <input type="submit" class="btn" value="S'inscrire">
            </div>
        </form>
        <a href="login.php" class="login-link">Vous avez déjà un compte ? Connectez-vous</a>
    </div>
</body>
</html>