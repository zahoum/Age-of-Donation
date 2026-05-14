<?php
require_once 'includes/admin_header.php';

// Get comprehensive statistics
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM users WHERE type != 'admin') as total_utilisateurs,
        (SELECT COUNT(*) FROM users WHERE type = 'donateur') as total_donateurs,
        (SELECT COUNT(*) FROM users WHERE type = 'beneficiaire') as total_beneficiaires,
        (SELECT COUNT(*) FROM users WHERE type = 'livreur') as total_livreurs,
        (SELECT COUNT(*) FROM dons) as total_dons,
        (SELECT COUNT(*) FROM demandes) as total_demandes,
        (SELECT COUNT(*) FROM livreurs WHERE statut = 'actif') as livreurs_actifs,
        (SELECT COUNT(*) FROM dons WHERE statut = 'disponible') as dons_disponibles,
        (SELECT COUNT(*) FROM dons WHERE statut = 'réservé') as dons_reserves,
        (SELECT COUNT(*) FROM dons WHERE statut = 'completé') as dons_completes,
        (SELECT COUNT(*) FROM demandes WHERE statut = 'en_attente') as demandes_attente,
        (SELECT COUNT(*) FROM demandes WHERE statut = 'accepté') as demandes_acceptees,
        (SELECT COUNT(*) FROM contact_messages WHERE status = 'new') as new_messages,
        (SELECT SUM(montant) FROM dons WHERE statut = 'completé') as total_dons_montant,
        (SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()) as nouveaux_utilisateurs_today,
        (SELECT COUNT(*) FROM dons WHERE DATE(created_at) = CURDATE()) as nouveaux_dons_today,
        (SELECT COUNT(*) FROM demandes WHERE DATE(created_at) = CURDATE()) as nouvelles_demandes_today
";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get monthly donations for chart
$monthly_query = "
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as count,
        SUM(montant) as total
    FROM dons 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
";
$monthly_stmt = $db->prepare($monthly_query);
$monthly_stmt->execute();
$monthly_data = $monthly_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent activity
$activity_query = "
    (SELECT 'don' as type, id, titre as title, created_at, 'تبرع جديد' as action 
     FROM dons 
     ORDER BY created_at DESC LIMIT 5)
    UNION ALL
    (SELECT 'demande' as type, id, NULL as title, created_at, 'طلب جديد' as action 
     FROM demandes 
     ORDER BY created_at DESC LIMIT 5)
    UNION ALL
    (SELECT 'user' as type, id, nom as title, created_at, 'مستخدم جديد' as action 
     FROM users 
     WHERE type != 'admin'
     ORDER BY created_at DESC LIMIT 5)
    ORDER BY created_at DESC LIMIT 10
";
$activity_stmt = $db->prepare($activity_query);
$activity_stmt->execute();
$recent_activity = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get top donors
$top_donors_query = "
    SELECT u.nom, u.email, COUNT(d.id) as donation_count, SUM(d.montant) as total_amount
    FROM users u
    INNER JOIN dons d ON u.id = d.donateur_id
    GROUP BY u.id
    ORDER BY total_amount DESC
    LIMIT 5
";
$top_donors_stmt = $db->prepare($top_donors_query);
$top_donors_stmt->execute();
$top_donors = $top_donors_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="welcome-banner">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h1 class="mb-3"><i class="fas fa-chart-line me-2"></i>لوحة التحكم الإدارية</h1>
            <p class="mb-2">مرحباً بك في نظام إدارة Age of Donnation. يمكنك من هنا إدارة جميع جوانب المنصة بكل سهولة.</p>
            <div class="mt-3">
                <span class="badge bg-light text-dark me-2">
                    <i class="fas fa-calendar me-1"></i>
                    <?php echo date('Y/m/d'); ?>
                </span>
                <span class="badge bg-light text-dark">
                    <i class="fas fa-clock me-1"></i>
                    <span id="welcome-time"></span>
                </span>
            </div>
        </div>
        <div class="col-md-4 text-center">
            <i class="fas fa-chart-pie" style="font-size: 100px; opacity: 0.3;"></i>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #667eea20, #764ba220); color: #667eea;">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-number"><?php echo number_format($stats['total_utilisateurs'] ?? 0); ?></div>
            <div class="stat-label">إجمالي المستخدمين</div>
            <small class="text-success mt-2 d-block">
                <i class="fas fa-arrow-up me-1"></i>
                +<?php echo $stats['nouveaux_utilisateurs_today'] ?? 0; ?> اليوم
            </small>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #00b89420, #00cec920); color: #00b894;">
                <i class="fas fa-gift"></i>
            </div>
            <div class="stat-number"><?php echo number_format($stats['total_dons'] ?? 0); ?></div>
            <div class="stat-label">إجمالي التبرعات</div>
            <small class="text-primary mt-2 d-block">
                <i class="fas fa-money-bill-wave me-1"></i>
                <?php echo number_format($stats['total_dons_montant'] ?? 0, 2); ?> درهم
            </small>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #fdcb6e20, #f39c1220); color: #f39c12;">
                <i class="fas fa-hand-paper"></i>
            </div>
            <div class="stat-number"><?php echo $stats['demandes_attente'] ?? 0; ?></div>
            <div class="stat-label">طلبات معلقة</div>
            <small class="text-warning mt-2 d-block">
                <i class="fas fa-clock me-1"></i>
                تحتاج إلى مراجعة
            </small>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #ff767520, #d6303120); color: #d63031;">
                <i class="fas fa-envelope"></i>
            </div>
            <div class="stat-number"><?php echo $stats['new_messages'] ?? 0; ?></div>
            <div class="stat-label">رسائل جديدة</div>
            <small class="text-danger mt-2 d-block">
                <i class="fas fa-exclamation-circle me-1"></i>
                بحاجة إلى رد
            </small>
        </div>
    </div>
</div>

<!-- Second Row Statistics -->
<div class="row g-4 mb-4">
    <div class="col-md-3 col-6">
        <div class="stat-card text-center">
            <div class="stat-number"><?php echo $stats['dons_disponibles'] ?? 0; ?></div>
            <div class="stat-label">تبرعات متاحة</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card text-center">
            <div class="stat-number"><?php echo $stats['dons_reserves'] ?? 0; ?></div>
            <div class="stat-label">تبرعات محجوزة</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card text-center">
            <div class="stat-number"><?php echo $stats['dons_completes'] ?? 0; ?></div>
            <div class="stat-label">تبرعات مكتملة</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card text-center">
            <div class="stat-number"><?php echo $stats['livreurs_actifs'] ?? 0; ?></div>
            <div class="stat-label">مساعدين نشطين</div>
        </div>
    </div>
</div>

<!-- Charts and Activity -->
<div class="row g-4">
    <!-- Monthly Donations Chart -->
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-chart-line me-2"></i>نظرة عامة على التبرعات</h5>
                <span class="badge bg-primary">آخر 6 أشهر</span>
            </div>
            <div class="card-body">
                <canvas id="monthlyChart" height="250"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Top Donors -->
    <div class="col-xl-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-trophy me-2"></i>أكثر المتبرعين</h5>
                <i class="fas fa-medal text-warning"></i>
            </div>
            <div class="card-body p-0">
                <?php if(empty($top_donors)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-chart-line fa-2x text-muted mb-2"></i>
                        <p class="text-muted">لا توجد بيانات كافية</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach($top_donors as $index => $donor): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge bg-primary rounded-circle me-2"><?php echo $index + 1; ?></span>
                                    <strong><?php echo htmlspecialchars($donor['nom']); ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo $donor['donation_count']; ?> تبرع</small>
                                </div>
                                <div class="text-end">
                                    <span class="fw-bold text-success"><?php echo number_format($donor['total_amount'], 2); ?></span>
                                    <small class="text-muted d-block">درهم</small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-2">
    <!-- Recent Activity -->
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-history me-2"></i>النشاطات الأخيرة</h5>
                <a href="javascript:void(0)" onclick="location.reload()" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-sync-alt"></i>
                </a>
            </div>
            <div class="card-body p-0">
                <?php if(empty($recent_activity)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-bell-slash fa-2x text-muted mb-2"></i>
                        <p class="text-muted">لا توجد نشاطات حديثة</p>
                    </div>
                <?php else: ?>
                    <div class="timeline" style="max-height: 400px; overflow-y: auto;">
                        <?php foreach($recent_activity as $activity): ?>
                            <div class="d-flex align-items-center p-3 border-bottom">
                                <div class="flex-shrink-0">
                                    <?php if($activity['type'] == 'don'): ?>
                                        <div class="rounded-circle p-2 bg-success bg-opacity-10">
                                            <i class="fas fa-gift text-success"></i>
                                        </div>
                                    <?php elseif($activity['type'] == 'demande'): ?>
                                        <div class="rounded-circle p-2 bg-warning bg-opacity-10">
                                            <i class="fas fa-hand-paper text-warning"></i>
                                        </div>
                                    <?php else: ?>
                                        <div class="rounded-circle p-2 bg-info bg-opacity-10">
                                            <i class="fas fa-user text-info"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1 me-3">
                                    <p class="mb-0">
                                        <strong><?php echo $activity['action']; ?></strong>
                                        <?php if($activity['title']): ?>
                                            - <?php echo htmlspecialchars(substr($activity['title'], 0, 30)); ?>
                                        <?php endif; ?>
                                    </p>
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo time_ago($activity['created_at']); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Quick Stats -->
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-chart-pie me-2"></i>إحصائيات سريعة</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="p-3 bg-light rounded-3 text-center">
                            <h3 class="mb-1"><?php echo $stats['total_donateurs'] ?? 0; ?></h3>
                            <small class="text-muted">متبرع</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-light rounded-3 text-center">
                            <h3 class="mb-1"><?php echo $stats['total_beneficiaires'] ?? 0; ?></h3>
                            <small class="text-muted">مستفيد</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-light rounded-3 text-center">
                            <h3 class="mb-1"><?php echo $stats['nouveaux_dons_today'] ?? 0; ?></h3>
                            <small class="text-muted">تبرع اليوم</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-light rounded-3 text-center">
                            <h3 class="mb-1"><?php echo $stats['nouvelles_demandes_today'] ?? 0; ?></h3>
                            <small class="text-muted">طلب اليوم</small>
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <div class="d-grid gap-2">
                    <a href="dons.php?action=add" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>إضافة تبرع جديد
                    </a>
                    <a href="messages.php?status=new" class="btn btn-danger">
                        <i class="fas fa-envelope me-2"></i>عرض الرسائل الجديدة
                        <?php if($stats['new_messages'] > 0): ?>
                            <span class="badge bg-white text-danger ms-2"><?php echo $stats['new_messages']; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="demandes.php?status=en_attente" class="btn btn-warning">
                        <i class="fas fa-hand-paper me-2"></i>مراجعة الطلبات المعلقة
                        <?php if($stats['demandes_attente'] > 0): ?>
                            <span class="badge bg-white text-warning ms-2"><?php echo $stats['demandes_attente']; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="statistiques.php" class="btn btn-info">
                        <i class="fas fa-chart-bar me-2"></i>عرض التقارير والإحصائيات
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Monthly Chart
const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
const monthlyData = <?php echo json_encode($monthly_data); ?>;

new Chart(monthlyCtx, {
    type: 'line',
    data: {
        labels: monthlyData.map(item => item.month),
        datasets: [{
            label: 'عدد التبرعات',
            data: monthlyData.map(item => item.count),
            borderColor: '#ff6b6b',
            backgroundColor: 'rgba(255, 107, 107, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#ff6b6b',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 5,
            pointHoverRadius: 7
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'top',
                rtl: true,
                labels: {
                    font: {
                        family: 'Cairo'
                    }
                }
            },
            tooltip: {
                rtl: true,
                callbacks: {
                    label: function(context) {
                        return 'عدد التبرعات: ' + context.raw;
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    drawBorder: false
                },
                ticks: {
                    stepSize: 1
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        }
    }
});

// Update time
function updateTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('fr-SA');
    document.getElementById('current-time').textContent = timeString;
    document.getElementById('welcome-time').textContent = timeString;
}

updateTime();
setInterval(updateTime, 1000);

// Auto refresh message count
setInterval(function() {
    fetch('ajax/get_notifications.php')
        .then(response => response.json())
        .then(data => {
            const messageBadge = document.querySelector('a[href="messages.php"] .badge');
            const requestBadge = document.querySelector('a[href="demandes.php"] .badge');
            
            if (data.new_messages > 0) {
                if (messageBadge) messageBadge.textContent = data.new_messages;
                else {
                    const messageLink = document.querySelector('a[href="messages.php"]');
                    messageLink.innerHTML += '<span class="badge bg-danger">' + data.new_messages + '</span>';
                }
            } else if (messageBadge) messageBadge.remove();
            
            if (data.pending_requests > 0) {
                if (requestBadge) requestBadge.textContent = data.pending_requests;
                else {
                    const requestLink = document.querySelector('a[href="demandes.php"]');
                    requestLink.innerHTML += '<span class="badge bg-danger">' + data.pending_requests + '</span>';
                }
            } else if (requestBadge) requestBadge.remove();
        })
        .catch(error => console.error('Error:', error));
}, 30000);
</script>

<?php
// Helper function for time ago
function time_ago($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) return 'منذ لحظات';
    if ($diff < 3600) return 'منذ ' . floor($diff / 60) . ' دقيقة';
    if ($diff < 86400) return 'منذ ' . floor($diff / 3600) . ' ساعة';
    if ($diff < 2592000) return 'منذ ' . floor($diff / 86400) . ' يوم';
    return date('Y/m/d', $timestamp);
}

require_once 'includes/admin_footer.php';
?>