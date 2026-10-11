<header class="carte tete-page">
  <div>
    <h1><?= e(t('menu.corbeille')) ?></h1>
    <p class="aide"><?= e(t('corbeille.aide')) ?></p>
  </div>
</header>

<?php if (!$sections): ?>
  <section class="carte"><p class="aide"><?= e(t('corbeille.vide')) ?></p></section>
<?php endif; ?>

<?php foreach ($sections as $type => $elements): ?>
<section class="carte">
  <h2><?= e(t('corbeille.section.' . $type)) ?></h2>
  <div class="defile">
  <table>
    <thead><tr>
      <th><?= e(t('corbeille.element')) ?></th>
      <th class="tel-cache"><?= e(t('corbeille.detail')) ?></th>
      <th><?= e(t('corbeille.supprime_le')) ?></th>
      <th class="tel-cache"><?= e(t('corbeille.motif')) ?></th>
      <th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($elements as $el): ?>
      <tr>
        <td><strong><?= e($el['nom']) ?></strong></td>
        <td><?= e($el['detail']) ?></td>
        <td><?= e(date('d/m/Y H:i', strtotime($el['le']))) ?><br><small><?= e(t('corbeille.efface_le', ['date' => date('d/m/Y', strtotime($el['le'] . ' +' . \AvecXY\Application\ServiceTaches::JOURS_CORBEILLE . ' days'))])) ?></small></td>
        <td><?= e($el['motif'] ?? '—') ?></td>
        <td class="actions">
          <?php if (peut('corbeille.restaurer')): ?>
          <form method="post" action="<?= e(url('/parametres/corbeille/' . $type . '/' . $el['id'] . '/restaurer')) ?>">
            <?= champ_csrf() ?>
            <button class="lien"><?= e(t('corbeille.restaurer')) ?></button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</section>
<?php endforeach; ?>
