<?php /** @var array $messages @var bool $coteOrganisation */ ?>
<div class="fil-messages">
<?php foreach ($messages as $m): $deNous = $coteOrganisation ? $m['utilisateur_id'] !== null : $m['plateforme_admin_id'] !== null; ?>
  <div class="bulle<?= $deNous ? ' moi' : '' ?>">
    <small><strong><?= e($m['plateforme_admin_id'] !== null ? t('support.equipe') : ($m['utilisateur_nom'] ?? '—')) ?></strong> · <?= e(date('d/m/Y H:i', strtotime($m['cree_le']))) ?></small>
    <p><?= nl2br(e($m['message'])) ?></p>
  </div>
<?php endforeach; ?>
</div>
