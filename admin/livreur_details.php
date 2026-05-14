<?php
require_once 'includes/admin_header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header('Location: livreurs.php');
    exit();
}

// Get helper details
$query = "SELECT u.*, l.vehicule_type, l.plaque_immatriculation, l.zone_intervention, 
          l.statut as livreur_statut, l.note_moyenne, l.total_livraisons
          FROM users u
          INNER JOIN livreurs l ON u.id = l.user_id
          WHERE u.id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $id);
$stmt->execute();
$helper = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$helper) {
    header('Location: livreurs.php');
    exit();
}

// Get delivery history
$history_query = "SELECT * FROM livraisons WHERE livreur_id = :id ORDER BY created_at DESC LIMIT 10";
$history_stmt = $db->prepare($history_query);
$history_stmt->bindParam(':id', $id);
$history_stmt->execute();
$deliveries = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-user-circle me-2"></i>تفاصيل المساعد</h5>
        <a href="livreurs.php" class="btn btn-sm btn-secondary">
            <i class="fas fa-arrow-right me-1"></i>رجوع
        </a>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr>
                        <th width="150">الاسم:</th>
                        <td><?php echo htmlspecialchars($helper['nom']); ?></td>
                    </tr>
                    <tr>
                        <th>البريد الإلكتروني:</th>
                        <td><?php echo htmlspecialchars($helper['email']); ?></td>
                    </tr>
                    <tr>
                        <th>رقم الهاتف:</th>
                        <td><?php echo htmlspecialchars($helper['telephone'] ?? '—'); ?></td>
                    </tr>
                    <tr>
                        <th>وسيلة النقل:</th>
                        <td>
                            <?php 
                            $veh_types = ['velo' => 'دراجة', 'moto' => 'دراجة نارية', 'voiture' => 'سيارة', 'camion' => 'شاحنة'];
                            echo $veh_types[$helper['vehicule_type']] ?? $helper['vehicule_type'];
                            ?>
                        </td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr>
                        <th width="150">رقم اللوحة:</th>
                        <td><?php echo htmlspecialchars($helper['plaque_immatriculation'] ?? '—'); ?></td>
                    </tr>
                    <tr>
                        <th>منطقة التدخل:</th>
                        <td><?php echo htmlspecialchars($helper['zone_intervention'] ?? '—'); ?></td>
                    </tr>
                    <tr>
                        <th>متوسط التقييم:</th>
                        <td>
                            <?php echo number_format($helper['note_moyenne'] ?? 0, 1); ?> / 5
                            <i class="fas fa-star text-warning"></i>
                        </td>
                    </tr>
                    <tr>
                        <th>عدد التوصيلات:</th>
                        <td><?php echo $helper['total_livraisons'] ?? 0; ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <hr>
        
        <h6 class="mt-4"><i class="fas fa-history me-2"></i>آخر التوصيلات</h6>
        <?php if(empty($deliveries)): ?>
            <p class="text-muted">لا توجد توصيلات بعد</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>الموقع</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($deliveries as $delivery): ?>
                            <tr>
                                <td><?php echo date('Y/m/d H:i', strtotime($delivery['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($delivery['adresse'] ?? '—'); ?></td>
                                <td>
                                    <span class="badge bg-success">مكتملة</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/admin_footer.php'; ?>