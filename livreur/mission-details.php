<!-- Dans missions.php, remplacer la section mission-actions par : -->
<div class="mission-actions">
    <?php if($mission['statut'] == 'en_attente' && !$mission['livreur_id']): ?>
        <a href="?accept=1&mission_id=<?php echo $mission['id']; ?>" 
           class="btn btn-success" 
           onclick="return confirm('هل تريد قبول هذه المهمة؟')">
            <i class="fas fa-check"></i> قبول المهمة
        </a>
    <?php elseif($mission['livreur_id'] == $user_id): ?>
        <?php if($mission['statut'] == 'assignee'): ?>
            <a href="?start=1&mission_id=<?php echo $mission['id']; ?>" 
               class="btn btn-primary"
               onclick="return confirm('بدء هذه المهمة الآن؟')">
                <i class="fas fa-play"></i> بدء التوصيل
            </a>
        <?php elseif($mission['statut'] == 'en_cours'): ?>
            <a href="?complete=1&mission_id=<?php echo $mission['id']; ?>" 
               class="btn btn-warning"
               onclick="return confirm('تأكيد إنجاز المهمة؟')">
                <i class="fas fa-check-circle"></i> إنهاء المهمة
            </a>
        <?php elseif($mission['statut'] == 'livree'): ?>
            <span class="btn btn-success" style="opacity: 0.8; cursor: default;">
                <i class="fas fa-check"></i> تم التسليم
            </span>
        <?php endif; ?>
    <?php endif; ?>
    
    <a href="mission-details.php?id=<?php echo $mission['id']; ?>" 
       class="btn btn-outline">
        <i class="fas fa-eye"></i> تفاصيل
    </a>
    
    <?php if($mission['livreur_id'] == $user_id && $mission['statut'] == 'livree'): ?>
    <a href="note.php?mission_id=<?php echo $mission['id']; ?>" 
       class="btn btn-info">
        <i class="fas fa-star"></i> تقييم
    </a>
    <?php endif; ?>
    
    <?php if($mission['livreur_id'] == $user_id && in_array($mission['statut'], ['assignee', 'en_cours'])): ?>
    <a href="messagerie.php?user_id=<?php echo $mission['beneficiaire_id']; ?>" 
       class="btn btn-outline" title="مراسلة المستفيد">
        <i class="fas fa-comment"></i>
    </a>
    <a href="messagerie.php?user_id=<?php echo $mission['donateur_id']; ?>" 
       class="btn btn-outline" title="مراسلة المتبرع">
        <i class="fas fa-user"></i>
    </a>
    <?php endif; ?>
</div>