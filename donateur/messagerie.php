<?php
require_once '../config/database.php';
checkAuth(['donateur']);

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$selected_user_id = $_GET['user_id'] ?? null;

// Récupérer les conversations avec les bénéficiaires
$query = "
    SELECT DISTINCT 
        u.id as other_user_id,
        u.nom as other_user_nom,
        u.type as other_user_type,
        d.titre as don_titre,
        MAX(m.created_at) as last_message_date,
        (SELECT COUNT(*) FROM messages m2 
         WHERE ((m2.expediteur_id = u.id AND m2.destinataire_id = :user_id) 
                OR (m2.expediteur_id = :user_id AND m2.destinataire_id = u.id))
         AND m2.lu = 0 AND m2.destinataire_id = :user_id) as unread_count
    FROM demandes de
    INNER JOIN dons d ON de.don_id = d.id
    INNER JOIN users u ON de.beneficiaire_id = u.id
    LEFT JOIN messages m ON ((m.expediteur_id = u.id AND m.destinataire_id = :user_id) 
                           OR (m.expediteur_id = :user_id AND m.destinataire_id = u.id))
    WHERE d.donateur_id = :user_id
    GROUP BY u.id, d.titre
    ORDER BY last_message_date DESC
";

$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $user_id);
$stmt->execute();
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les messages si une conversation est sélectionnée
$messages = [];
$other_user = null;

if ($selected_user_id) {
    // Récupérer les informations de l'autre utilisateur
    $user_query = "SELECT id, nom, type FROM users WHERE id = :user_id";
    $user_stmt = $db->prepare($user_query);
    $user_stmt->bindParam(":user_id", $selected_user_id);
    $user_stmt->execute();
    $other_user = $user_stmt->fetch(PDO::FETCH_ASSOC);

    // Récupérer les messages
    $messages_query = "
        SELECT m.*, 
               u_exp.nom as expediteur_nom,
               u_dest.nom as destinataire_nom
        FROM messages m
        INNER JOIN users u_exp ON m.expediteur_id = u_exp.id
        INNER JOIN users u_dest ON m.destinataire_id = u_dest.id
        WHERE (m.expediteur_id = :user_id AND m.destinataire_id = :other_user_id)
           OR (m.expediteur_id = :other_user_id AND m.destinataire_id = :user_id)
        ORDER BY m.created_at ASC
    ";
    
    $messages_stmt = $db->prepare($messages_query);
    $messages_stmt->bindParam(":user_id", $user_id);
    $messages_stmt->bindParam(":other_user_id", $selected_user_id);
    $messages_stmt->execute();
    $messages = $messages_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Marquer les messages comme lus
    $update_query = "
        UPDATE messages 
        SET lu = 1, lu_at = NOW() 
        WHERE destinataire_id = :user_id 
        AND expediteur_id = :other_user_id 
        AND lu = 0
    ";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(":user_id", $user_id);
    $update_stmt->bindParam(":other_user_id", $selected_user_id);
    $update_stmt->execute();
}

// Envoyer un message
if ($_POST && isset($_POST['message']) && $selected_user_id) {
    $message_content = trim($_POST['message']);
    
    if (!empty($message_content)) {
        $insert_query = "
            INSERT INTO messages (expediteur_id, destinataire_id, message, created_at)
            VALUES (:expediteur_id, :destinataire_id, :message, NOW())
        ";
        
        $insert_stmt = $db->prepare($insert_query);
        $insert_stmt->bindParam(":expediteur_id", $user_id);
        $insert_stmt->bindParam(":destinataire_id", $selected_user_id);
        $insert_stmt->bindParam(":message", $message_content);
        
        if ($insert_stmt->execute()) {
            // Recharger la page pour afficher le nouveau message
            header("Location: messagerie.php?user_id=" . $selected_user_id);
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messagerie - Age of Donnation</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .chat-container {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 1rem;
            height: 70vh;
        }
        .conversations-list {
            border-right: 1px solid #eee;
            overflow-y: auto;
        }
        .chat-messages {
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        .messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
        }
        .message {
            margin-bottom: 1rem;
            padding: 0.8rem 1rem;
            border-radius: 15px;
            max-width: 70%;
            word-wrap: break-word;
        }
        .message-sent {
            background: #28a745;
            color: white;
            margin-left: auto;
            border-bottom-right-radius: 5px;
        }
        .message-received {
            background: white;
            border: 1px solid #dee2e6;
            margin-right: auto;
            border-bottom-left-radius: 5px;
        }
        .message-time {
            font-size: 0.7rem;
            opacity: 0.7;
            margin-top: 0.3rem;
        }
        .conversation-item {
            padding: 1rem;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background 0.3s;
        }
        .conversation-item:hover {
            background: #f8f9fa;
        }
        .conversation-item.active {
            background: #e8f5e8;
            border-left: 4px solid #28a745;
        }
        .unread-badge {
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            margin-left: auto;
        }
        .message-form {
            margin-top: 1rem;
            display: flex;
            gap: 0.5rem;
        }
        .empty-chat {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        .chat-header {
            padding: 1rem;
            border-bottom: 1px solid #eee;
            background: white;
            border-radius: 10px 10px 0 0;
        }
    </style>
</head>
<body>
    <header class="header">
        <nav class="nav">
            <a href="../index.php" class="logo">Age of Donnation</a>
            <ul class="nav-links">
                <li><a href="dashboard.php">Tableau de bord</a></li>
                <li><a href="publier-don.php">Publier un don</a></li>
                <li><a href="mes-dons.php">Mes dons</a></li>
                <li><a href="messagerie.php" class="active">Messagerie</a></li>
            </ul>
            <div class="auth-buttons">
                <span style="color: #333; margin-right: 1rem;"><?php echo $_SESSION['user_nom']; ?></span>
                <a href="../auth/logout.php" class="btn btn-outline">Déconnexion</a>
            </div>
        </nav>
    </header>

    <div class="container">
        <div class="dashboard-header">
            <h1>💬 Messagerie</h1>
            <p>Communiquez avec les bénéficiaires</p>
        </div>

        <div class="chat-container">
            <!-- Liste des conversations -->
            <div class="conversations-list card">
                <div class="card-header">
                    <h3>Conversations</h3>
                </div>
                <div class="card-body" style="padding: 0;">
                    <?php if(empty($conversations)): ?>
                        <div style="text-align: center; padding: 2rem; color: #666;">
                            <p>Aucune conversation</p>
                            <small>Les conversations apparaîtront quand vous aurez des demandes</small>
                        </div>
                    <?php else: ?>
                        <?php foreach($conversations as $conv): ?>
                            <div class="conversation-item <?php echo $selected_user_id == $conv['other_user_id'] ? 'active' : ''; ?>" 
                                 onclick="window.location.href='messagerie.php?user_id=<?php echo $conv['other_user_id']; ?>'">
                                <div style="display: flex; align-items: center; gap: 1rem;">
                                    <div style="width: 40px; height: 40px; background: #ffc107; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                                        <?php echo strtoupper(substr($conv['other_user_nom'], 0, 1)); ?>
                                    </div>
                                    <div style="flex: 1;">
                                        <strong><?php echo htmlspecialchars($conv['other_user_nom']); ?></strong>
                                        <br>
                                        <small style="color: #666;">
                                            <?php echo htmlspecialchars($conv['don_titre']); ?>
                                        </small>
                                        <br>
                                        <small style="color: #888;">
                                            <?php echo $conv['last_message_date'] ? date('d/m H:i', strtotime($conv['last_message_date'])) : 'Aucun message'; ?>
                                        </small>
                                    </div>
                                    <?php if($conv['unread_count'] > 0): ?>
                                        <div class="unread-badge">
                                            <?php echo $conv['unread_count']; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Zone de chat -->
            <div class="chat-messages card">
                <?php if($selected_user_id && $other_user): ?>
                    <div class="chat-header">
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="width: 40px; height: 40px; background: #ffc107; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                                <?php echo strtoupper(substr($other_user['nom'], 0, 1)); ?>
                            </div>
                            <div>
                                <strong><?php echo htmlspecialchars($other_user['nom']); ?></strong>
                                <br>
                                <small style="color: #666;">Bénéficiaire</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="messages-container">
                        <?php if(empty($messages)): ?>
                            <div class="empty-chat">
                                <h4>💬 Commencez la conversation</h4>
                                <p>Envoyez votre premier message à <?php echo htmlspecialchars($other_user['nom']); ?></p>
                            </div>
                        <?php else: ?>
                            <?php foreach($messages as $msg): ?>
                                <div class="message <?php echo $msg['expediteur_id'] == $user_id ? 'message-sent' : 'message-received'; ?>">
                                    <div><?php echo htmlspecialchars($msg['message']); ?></div>
                                    <div class="message-time">
                                        <?php echo date('H:i', strtotime($msg['created_at'])); ?>
                                        <?php if($msg['expediteur_id'] == $user_id && $msg['lu']): ?>
                                            • ✅ Lu
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Formulaire d'envoi de message -->
                    <form method="POST" class="message-form" style="padding: 1rem;">
                        <div style="flex: 1;">
                            <input type="text" name="message" class="form-control" placeholder="Tapez votre message..." required>
                        </div>
                        <button type="submit" class="btn btn-primary">Envoyer</button>
                    </form>

                <?php else: ?>
                    <div class="empty-chat">
                        <h4>👋 Bienvenue dans la messagerie</h4>
                        <p>Sélectionnez une conversation pour commencer à discuter</p>
                        <div style="font-size: 4rem; margin: 2rem 0;">💬</div>
                        <p>Communiquez avec les bénéficiaires pour organiser les dons</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    // Auto-scroll vers le bas des messages
    function scrollToBottom() {
        const messagesContainer = document.querySelector('.messages-container');
        if (messagesContainer) {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
    }

    // Scroll au chargement de la page
    document.addEventListener('DOMContentLoaded', function() {
        scrollToBottom();
    });

    // Actualisation automatique des messages toutes les 10 secondes
    setInterval(function() {
        if (<?php echo $selected_user_id ? 'true' : 'false'; ?>) {
            window.location.reload();
        }
    }, 10000);
    </script>
</body>
</html>