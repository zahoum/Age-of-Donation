<?php
// messenger-new-design.php
session_start();

// فحص تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$user_nom = $_SESSION['user_nom'];
$user_type = $_SESSION['user_type'];

$selected_user_id = $_GET['user_id'] ?? null;
$action = $_GET['action'] ?? '';

// ========== إرسال رسالة ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message']) && $selected_user_id) {
    $message = trim($_POST['message']);
    
    if (!empty($message)) {
        $query = "INSERT INTO messages (expediteur_id, destinataire_id, message, created_at) 
                  VALUES (:expediteur_id, :destinataire_id, :message, NOW())";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':expediteur_id', $user_id);
        $stmt->bindParam(':destinataire_id', $selected_user_id);
        $stmt->bindParam(':message', $message);
        
        if ($stmt->execute()) {
            // إعادة التوجيه بعد الإرسال
            header("Location: ?user_id=" . $selected_user_id);
            exit();
        }
    }
}

// ========== جلب المحادثات ==========
$query_conversations = "
    SELECT DISTINCT 
        u.id as other_id,
        u.nom as other_nom,
        u.type as other_type,
        (SELECT message FROM messages m 
         WHERE (m.expediteur_id = u.id AND m.destinataire_id = :user_id)
            OR (m.expediteur_id = :user_id AND m.destinataire_id = u.id)
         ORDER BY m.created_at DESC LIMIT 1) as last_message,
        (SELECT COUNT(*) FROM messages m2 
         WHERE m2.expediteur_id = u.id AND m2.destinataire_id = :user_id AND m2.lu = 0) as unread,
        (SELECT created_at FROM messages m3 
         WHERE (m3.expediteur_id = u.id AND m3.destinataire_id = :user_id)
            OR (m3.expediteur_id = :user_id AND m3.destinataire_id = u.id)
         ORDER BY m3.created_at DESC LIMIT 1) as last_time
    FROM users u
    WHERE u.id IN (
        SELECT DISTINCT 
            CASE 
                WHEN m.expediteur_id = :user_id THEN m.destinataire_id
                ELSE m.expediteur_id
            END
        FROM messages m
        WHERE m.expediteur_id = :user_id OR m.destinataire_id = :user_id
    )
    AND u.id != :user_id
    ORDER BY last_time DESC
";

$stmt_conv = $db->prepare($query_conversations);
$stmt_conv->bindParam(':user_id', $user_id);
$stmt_conv->execute();
$conversations = $stmt_conv->fetchAll(PDO::FETCH_ASSOC);

// ========== جلب الرسائل ==========
$messages = [];
$other_user = null;

if ($selected_user_id) {
    // جلب معلومات المستخدم الآخر
    $query_user = "SELECT id, nom, type FROM users WHERE id = :id";
    $stmt_user = $db->prepare($query_user);
    $stmt_user->bindParam(':id', $selected_user_id);
    $stmt_user->execute();
    $other_user = $stmt_user->fetch(PDO::FETCH_ASSOC);
    
    // جلب الرسائل
    $query_messages = "
        SELECT m.*, u.nom as sender_name 
        FROM messages m 
        INNER JOIN users u ON m.expediteur_id = u.id 
        WHERE (m.expediteur_id = :user_id AND m.destinataire_id = :other_id)
           OR (m.expediteur_id = :other_id AND m.destinataire_id = :user_id)
        ORDER BY m.created_at ASC
    ";
    
    $stmt_msg = $db->prepare($query_messages);
    $stmt_msg->bindParam(':user_id', $user_id);
    $stmt_msg->bindParam(':other_id', $selected_user_id);
    $stmt_msg->execute();
    $messages = $stmt_msg->fetchAll(PDO::FETCH_ASSOC);
    
    // تحديث حالة القراءة
    $query_update = "UPDATE messages SET lu = 1 
                    WHERE destinataire_id = :user_id AND expediteur_id = :other_id AND lu = 0";
    $stmt_update = $db->prepare($query_update);
    $stmt_update->bindParam(':user_id', $user_id);
    $stmt_update->bindParam(':other_id', $selected_user_id);
    $stmt_update->execute();
}

// ========== البحث ==========
$search_results = [];
if ($action === 'search' && isset($_GET['search']) && !empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $query_search = "SELECT id, nom, type FROM users 
                     WHERE (nom LIKE :search OR email LIKE :search) 
                     AND id != :user_id
                     LIMIT 10";
    $stmt_search = $db->prepare($query_search);
    $stmt_search->bindParam(':search', $search);
    $stmt_search->bindParam(':user_id', $user_id);
    $stmt_search->execute();
    $search_results = $stmt_search->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fr" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>💬 المراسلة - Age of Donnation</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3a0ca3;
            --accent: #7209b7;
            --light: #f8f9fa;
            --dark: #212529;
            --success: #4cc9f0;
            --warning: #f72585;
            --gray: #6c757d;
            --light-gray: #e9ecef;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', 'Cairo', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 0;
            margin: 0;
        }
        
        /* Header */
        .app-header {
            background: white;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            padding: 15px 30px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
            text-decoration: none;
            color: var(--primary);
            font-weight: bold;
            font-size: 22px;
        }
        
        .logo i {
            font-size: 28px;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 18px;
        }
        
        .nav-links {
            display: flex;
            gap: 25px;
            list-style: none;
        }
        
        .nav-links a {
            text-decoration: none;
            color: var(--dark);
            font-weight: 500;
            padding: 8px 15px;
            border-radius: 20px;
            transition: all 0.3s;
        }
        
        .nav-links a:hover {
            background: var(--light-gray);
            color: var(--primary);
        }
        
        .nav-links a.active {
            background: var(--primary);
            color: white;
        }
        
        /* Main Container */
        .main-container {
            max-width: 1400px;
            margin: 80px auto 20px;
            padding: 20px;
        }
        
        .messenger-wrapper {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
            height: calc(100vh - 120px);
            display: flex;
        }
        
        /* Sidebar */
        .sidebar {
            width: 380px;
            background: var(--light);
            border-left: 1px solid var(--light-gray);
            display: flex;
            flex-direction: column;
        }
        
        .sidebar-header {
            padding: 25px;
            background: white;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .sidebar-header h3 {
            color: var(--dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .search-box {
            position: relative;
        }
        
        .search-box input {
            width: 100%;
            padding: 12px 45px 12px 20px;
            border: 2px solid var(--light-gray);
            border-radius: 12px;
            font-size: 14px;
            outline: none;
            transition: border 0.3s;
        }
        
        .search-box input:focus {
            border-color: var(--primary);
        }
        
        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
        }
        
        /* Conversations */
        .conversations-container {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
        }
        
        .conversation-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 18px;
            margin-bottom: 10px;
            background: white;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        
        .conversation-item:hover {
            transform: translateX(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border-color: var(--light-gray);
        }
        
        .conversation-item.active {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            border-color: var(--primary);
        }
        
        .avatar {
            width: 55px;
            height: 55px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 20px;
            position: relative;
        }
        
        .avatar.online::after {
            content: '';
            position: absolute;
            bottom: 5px;
            right: 5px;
            width: 12px;
            height: 12px;
            background: #4ade80;
            border-radius: 50%;
            border: 2px solid white;
        }
        
        .avatar.type-donateur { background: linear-gradient(135deg, #10b981, #059669); }
        .avatar.type-beneficiaire { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .avatar.type-livreur { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
        .avatar.type-admin { background: linear-gradient(135deg, #ef4444, #dc2626); }
        
        .conversation-info {
            flex: 1;
            min-width: 0;
        }
        
        .conversation-info h4 {
            margin: 0 0 5px 0;
            color: var(--dark);
            font-size: 16px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .conversation-info p {
            margin: 0;
            color: var(--gray);
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .conversation-meta {
            text-align: left;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 5px;
        }
        
        .conversation-time {
            font-size: 12px;
            color: var(--gray);
        }
        
        .unread-badge {
            background: var(--warning);
            color: white;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }
        
        /* Chat Area */
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: linear-gradient(to bottom, #f5f7fa, #e4e8f0);
        }
        
        .chat-header {
            padding: 20px 30px;
            background: white;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .chat-user {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .chat-user-info h3 {
            margin: 0 0 5px 0;
            color: var(--dark);
        }
        
        .chat-user-info small {
            color: var(--gray);
        }
        
        .chat-actions {
            display: flex;
            gap: 10px;
        }
        
        .chat-btn {
            background: none;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .chat-btn:hover {
            background: var(--light-gray);
            color: var(--primary);
        }
        
        /* Messages Container */
        .messages-container {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .message {
            max-width: 65%;
            padding: 15px 20px;
            border-radius: 20px;
            position: relative;
            word-wrap: break-word;
            animation: fadeIn 0.3s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .message-sent {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 5px;
        }
        
        .message-received {
            background: white;
            color: var(--dark);
            align-self: flex-start;
            border-bottom-left-radius: 5px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        }
        
        .message-sender {
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 8px;
            opacity: 0.9;
        }
        
        .message-text {
            line-height: 1.5;
            font-size: 15px;
        }
        
        .message-time {
            font-size: 12px;
            opacity: 0.8;
            margin-top: 8px;
            text-align: left;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .message-status {
            font-size: 12px;
            margin-right: 5px;
        }
        
        /* Input Area */
        .input-area {
            padding: 20px 30px;
            background: white;
            border-top: 1px solid var(--light-gray);
            display: flex;
            gap: 15px;
            align-items: flex-end;
        }
        
        .message-input {
            flex: 1;
            padding: 15px 20px;
            border: 2px solid var(--light-gray);
            border-radius: 25px;
            outline: none;
            font-size: 15px;
            resize: none;
            min-height: 50px;
            max-height: 120px;
            transition: border 0.3s;
        }
        
        .message-input:focus {
            border-color: var(--primary);
        }
        
        .send-btn {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 20px;
        }
        
        .send-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.4);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: var(--gray);
        }
        
        .empty-state i {
            font-size: 80px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .empty-state h3 {
            margin-bottom: 15px;
            color: var(--dark);
        }
        
        /* Search Results */
        .search-results {
            background: white;
            border-radius: 15px;
            margin-top: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            max-height: 400px;
            overflow-y: auto;
        }
        
        .search-result-item {
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            cursor: pointer;
            border-bottom: 1px solid var(--light-gray);
            transition: background 0.3s;
        }
        
        .search-result-item:hover {
            background: var(--light);
        }
        
        .search-result-item:last-child {
            border-bottom: none;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .messenger-wrapper {
                flex-direction: column;
                height: auto;
                min-height: calc(100vh - 120px);
            }
            
            .sidebar {
                width: 100%;
                height: 300px;
            }
            
            .nav-links {
                display: none;
            }
        }
        
        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #a1a1a1;
        }
        
        /* Typing Animation */
        .typing-indicator {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 10px 15px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            max-width: 100px;
        }
        
        .typing-dot {
            width: 8px;
            height: 8px;
            background: var(--gray);
            border-radius: 50%;
            animation: typing 1.4s infinite;
        }
        
        .typing-dot:nth-child(2) { animation-delay: 0.2s; }
        .typing-dot:nth-child(3) { animation-delay: 0.4s; }
        
        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); opacity: 0.6; }
            30% { transform: translateY(-5px); opacity: 1; }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="app-header">
        <a href="../index.php" class="logo">
            <i class="fas fa-hands-helping"></i>
            <span>Age of Donnation</span>
        </a>
        
        <ul class="nav-links">
            <li><a href="dashboard.php"><i class="fas fa-home"></i> لوحة التحكم</a></li>
            <li><a href="catalogue.php"><i class="fas fa-box-open"></i> الكتالوج</a></li>
            <li><a href="mes-demandes.php"><i class="fas fa-file-alt"></i> طلباتي</a></li>
            <li><a href="messagerie.php" class="active"><i class="fas fa-comments"></i> المراسلة</a></li>
        </ul>
        
        <div class="user-menu">
            <div class="user-avatar">
                <?php echo strtoupper(substr($user_nom, 0, 1)); ?>
            </div>
            <span style="color: var(--dark); font-weight: 500;"><?php echo $user_nom; ?></span>
            <a href="../auth/logout.php" class="chat-btn" title="تسجيل الخروج">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </header>
    
    <!-- Main Content -->
    <div class="main-container">
        <div class="messenger-wrapper">
            <!-- Sidebar -->
            <div class="sidebar">
                <div class="sidebar-header">
                    <h3><i class="fas fa-inbox"></i> المحادثات</h3>
                    
                    <form method="GET" class="search-box">
                        <input type="text" name="search" placeholder="ابحث عن مستخدم..." 
                               value="<?php echo $_GET['search'] ?? ''; ?>">
                        <input type="hidden" name="action" value="search">
                        <i class="fas fa-search"></i>
                    </form>
                    
                    <?php if(!empty($search_results)): ?>
                    <div class="search-results">
                        <?php foreach($search_results as $user): ?>
                        <div class="search-result-item" onclick="startChat(<?php echo $user['id']; ?>)">
                            <div class="avatar type-<?php echo $user['type']; ?>">
                                <?php echo strtoupper(substr($user['nom'], 0, 1)); ?>
                            </div>
                            <div>
                                <strong><?php echo htmlspecialchars($user['nom']); ?></strong><br>
                                <small><?php echo $user['type']; ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="conversations-container">
                    <?php if(empty($conversations)): ?>
                        <div class="empty-state">
                            <i class="fas fa-comments"></i>
                            <h3>لا توجد محادثات</h3>
                            <p>ابحث عن مستخدم لبدء محادثة جديدة</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($conversations as $conv): ?>
                        <div class="conversation-item <?php echo $selected_user_id == $conv['other_id'] ? 'active' : ''; ?>"
                             onclick="startChat(<?php echo $conv['other_id']; ?>)">
                            <div class="avatar type-<?php echo $conv['other_type']; ?> online">
                                <?php echo strtoupper(substr($conv['other_nom'], 0, 1)); ?>
                            </div>
                            <div class="conversation-info">
                                <h4><?php echo htmlspecialchars($conv['other_nom']); ?></h4>
                                <p><?php echo $conv['last_message'] ? htmlspecialchars($conv['last_message']) : 'بداية المحادثة...'; ?></p>
                            </div>
                            <div class="conversation-meta">
                                <?php if($conv['last_time']): ?>
                                    <span class="conversation-time">
                                        <?php echo date('H:i', strtotime($conv['last_time'])); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if($conv['unread'] > 0): ?>
                                    <div class="unread-badge"><?php echo $conv['unread']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Chat Area -->
            <div class="chat-area">
                <?php if($selected_user_id && $other_user): ?>
                    <!-- Chat Header -->
                    <div class="chat-header">
                        <div class="chat-user">
                            <div class="avatar type-<?php echo $other_user['type']; ?>">
                                <?php echo strtoupper(substr($other_user['nom'], 0, 1)); ?>
                            </div>
                            <div class="chat-user-info">
                                <h3><?php echo htmlspecialchars($other_user['nom']); ?></h3>
                                <small><?php echo $other_user['type']; ?></small>
                            </div>
                        </div>
                        
                        <div class="chat-actions">
                            <button class="chat-btn" title="معلومات">
                                <i class="fas fa-info-circle"></i>
                            </button>
                            <button class="chat-btn" onclick="window.location.reload()" title="تحديث">
                                <i class="fas fa-redo"></i>
                            </button>
                            <button class="chat-btn" onclick="clearChat()" title="مسح المحادثة">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Messages -->
                    <div class="messages-container" id="messagesContainer">
                        <?php if(empty($messages)): ?>
                            <div class="empty-state" style="margin: auto;">
                                <i class="fas fa-comment-dots"></i>
                                <h3>ابدأ المحادثة</h3>
                                <p>أرسل أول رسالة إلى <?php echo htmlspecialchars($other_user['nom']); ?></p>
                            </div>
                        <?php else: ?>
                            <?php foreach($messages as $msg): ?>
                            <div class="message <?php echo $msg['expediteur_id'] == $user_id ? 'message-sent' : 'message-received'; ?>">
                                <?php if($msg['expediteur_id'] != $user_id): ?>
                                    <div class="message-sender"><?php echo htmlspecialchars($msg['sender_name']); ?></div>
                                <?php endif; ?>
                                <div class="message-text"><?php echo htmlspecialchars($msg['message']); ?></div>
                                <div class="message-time">
                                    <span><?php echo date('H:i', strtotime($msg['created_at'])); ?></span>
                                    <?php if($msg['expediteur_id'] == $user_id): ?>
                                        <span class="message-status">
                                            <?php echo $msg['lu'] ? '✓✓' : '✓'; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Input -->
                    <form method="POST" class="input-area">
                        <textarea name="message" class="message-input" placeholder="اكتب رسالتك هنا..." 
                                  rows="1" oninput="autoResize(this)" required></textarea>
                        <input type="hidden" name="destinataire_id" value="<?php echo $selected_user_id; ?>">
                        <button type="submit" class="send-btn">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                    
                <?php else: ?>
                    <!-- Empty Chat State -->
                    <div class="empty-state" style="margin: auto; max-width: 500px;">
                        <i class="fas fa-comment-alt" style="color: var(--primary);"></i>
                        <h3>مرحبًا في المراسلة</h3>
                        <p style="margin-bottom: 25px; line-height: 1.6;">
                            اختر محادثة من القائمة أو ابحث عن مستخدم لبدء محادثة جديدة.
                            يمكنك التواصل مع جميع مستخدمي المنصة بسهولة وأمان.
                        </p>
                        <div style="display: flex; gap: 20px; justify-content: center; margin-top: 30px;">
                            <div style="text-align: center;">
                                <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #10b981, #059669); 
                                     border-radius: 50%; display: flex; align-items: center; justify-content: center; 
                                     color: white; font-size: 24px; margin: 0 auto 10px;">
                                    <i class="fas fa-hand-holding-heart"></i>
                                </div>
                                <small>متبرعون</small>
                            </div>
                            <div style="text-align: center;">
                                <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #f59e0b, #d97706); 
                                     border-radius: 50%; display: flex; align-items: center; justify-content: center; 
                                     color: white; font-size: 24px; margin: 0 auto 10px;">
                                    <i class="fas fa-users"></i>
                                </div>
                                <small>مستفيدون</small>
                            </div>
                            <div style="text-align: center;">
                                <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #3b82f6, #1d4ed8); 
                                     border-radius: 50%; display: flex; align-items: center; justify-content: center; 
                                     color: white; font-size: 24px; margin: 0 auto 10px;">
                                    <i class="fas fa-truck"></i>
                                </div>
                                <small>سائقون</small>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
    // JavaScript Functions
    function startChat(userId) {
        window.location.href = '?user_id=' + userId;
    }
    
    function clearChat() {
        if (confirm('هل تريد حقًا مسح كل الرسائل في هذه المحادثة؟')) {
            // يمكن إضافة AJAX هنا لحذف الرسائل من قاعدة البيانات
            window.location.reload();
        }
    }
    
    function autoResize(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
    }
    
    // Auto-scroll to bottom
    function scrollToBottom() {
        const container = document.getElementById('messagesContainer');
        if (container) {
            container.scrollTop = container.scrollHeight;
        }
    }
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'Enter') {
            const form = document.querySelector('.input-area form');
            if (form) form.submit();
        }
        
        // Focus search on Ctrl+K
        if (e.ctrlKey && e.key === 'k') {
            e.preventDefault();
            document.querySelector('.search-box input').focus();
        }
    });
    
    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        scrollToBottom();
        
        // Auto-refresh every 30 seconds if in chat
        <?php if($selected_user_id): ?>
        setInterval(function() {
            // يمكن إضافة AJAX هنا لجلب الرسائل الجديدة
            // fetch('?user_id=<?php echo $selected_user_id; ?>&refresh=true')
        }, 30000);
        <?php endif; ?>
        
        // Auto-focus message input
        const messageInput = document.querySelector('.message-input');
        if (messageInput) {
            messageInput.focus();
            
            // Prevent form submit on Enter (use Ctrl+Enter instead)
            messageInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.ctrlKey) {
                    e.preventDefault();
                    this.form.submit();
                }
            });
        }
    });
    </script>
</body>
</html>