<header class="carte tete-page">
  <div>
    <h1><?= e(t('notif.titre')) ?></h1>
    <p class="aide"><?= e(t('notif.aide')) ?></p>
  </div>
</header>

<section class="carte">
  <?php if (!$lignes): ?><p class="aide"><?= e(t('notif.aucune')) ?></p><?php else: ?>
  <ul class="notifs">
    <?php foreach ($lignes as $l): ?>
    <li class="notif<?= $l['lu_le'] === null ? ' nouvelle' : '' ?>">
      <div>
        <p><?= e($l['texte']) ?></p>
        <small><?= e(date('d/m/Y H:i', strtotime($l['cree_le']))) ?><?= $l['lu_le'] === null ? ' · ' . e(t('notif.nouvelle')) : '' ?></small>
      </div>
      <?php if ($l['lien']): ?><a class="bouton clair" href="<?= e(url($l['lien'])) ?>"><?= e(t('notif.ouvrir')) ?></a><?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</section>
