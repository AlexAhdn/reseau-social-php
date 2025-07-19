<?php
require_once 'config.php';

$query = "SELECT p.id, p.user_id, u.username, p.content, p.image_path, p.created_at FROM posts p LEFT JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Liste des posts publiés</title>
    <link rel="stylesheet" href="assets/css/bootstrap.css">
    <style>
        body {
            background: #f6f8fa;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .main-center {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .header {
            background: #1a3a3a;
            color: #fff;
            padding: 36px 32px 24px 32px;
            border-radius: 22px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            margin-bottom: 48px;
            text-align: center;
            max-width: 900px;
        }
        .header h2 {
            font-weight: 700;
            font-size: 2rem;
            letter-spacing: 1px;
            margin-bottom: 6px;
        }
        .header p {
            font-size: 1rem;
            font-weight: 400;
            color: #e0e0e0;
            margin-bottom: 0;
        }
        .table-container {
            max-width: 900px;
            margin: 0 auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            padding: 32px 24px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .table-responsive {
            width: 100%;
            display: flex;
            justify-content: center;
        }
        table.table {
            margin: 0 auto;
        }
        .table thead th {
            background: #e8f5e9;
            color: #1a3a3a;
            font-weight: 600;
            padding: 16px 18px;
            text-align: center;
        }
        .table tbody td {
            padding: 14px 18px;
            vertical-align: middle;
        }
        .table tbody td.content-col {
            max-width: 320px;
            white-space: pre-line;
            overflow-wrap: break-word;
        }
        .table tbody td.date-col {
            padding-left: 32px;
            padding-right: 32px;
            white-space: nowrap;
        }
        .table tbody tr:hover {
            background: #e0f2f1;
        }
        .post-image {
            max-width: 80px;
            max-height: 80px;
            border-radius: 8px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
        }
        .btn-delete {
            background: #e53935;
            color: #fff;
            border: none;
            padding: 6px 16px;
            border-radius: 6px;
            font-size: 0.97em;
            transition: background 0.2s;
        }
        .btn-delete:hover {
            background: #b71c1c;
            color: #fff;
        }
        @media (max-width: 1000px) {
            .table-container { max-width: 100%; padding: 12px 2px; }
            .header { max-width: 100%; }
        }
        @media (max-width: 768px) {
            .header { padding: 18px 0 12px 0; }
            .table-responsive { font-size: 0.95em; }
        }
    </style>
</head>
<body>
    <div class="main-center">
        <div class="header">
            <h2>Liste des posts publiés</h2>
            <p>Visualisez tous les posts de la plateforme</p>
        </div>
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Auteur</th>
                            <th>Contenu</th>
                            <th>Image</th>
                            <th>Date de publication</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['id']) ?></td>
                            <td><?= htmlspecialchars($row['username'] ?? 'Inconnu') ?></td>
                            <td class="content-col"><?= nl2br(htmlspecialchars($row['content'])) ?></td>
                            <td>
                                <?php if (!empty($row['image_path'])): ?>
                                    <img src="<?= htmlspecialchars($row['image_path']) ?>" class="post-image" alt="Image du post">
                                <?php else: ?>
                                    <span class="text-muted">Aucune</span>
                                <?php endif; ?>
                            </td>
                            <td class="date-col"><?= htmlspecialchars($row['created_at']) ?></td>
                            <td>
                                <form method="POST" action="delete_post.php" onsubmit="return confirm('Voulez-vous vraiment supprimer ce post ?');" style="display:inline;">
                                    <input type="hidden" name="id" value="<?= htmlspecialchars($row['id']) ?>">
                                    <button type="submit" class="btn-delete">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html> 