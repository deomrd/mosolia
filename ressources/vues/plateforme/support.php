<header class="carte tete-page">
  <div><h1><?= e(t('menu.support')) ?></h1></div>
  <a class="bouton clair" href="<?= e(url('/plateforme/support' . ($tous ? '' : '?tous=1'))) ?>"><?= e(t($tous ? 'plateforme.tickets_ouverts' : 'plateforme.tickets_tous')) ?></a>
</header>
<section class="carte">
  <?php if (!$tickets): ?><p class="aide"><?= e(t('support.aucun')) ?></p><?php else: ?>
  <div class="defile"><table>
    <thead><tr><th>#</th><th><?= e(t('support.sujet')) ?></th><th class="tel-cache"><?= e(t('plateforme.organisation')) ?></th><th class="tel-cache"><?= e(t('support.categorie')) ?></th><th><?= e(t('credits.statut')) ?></th><th class="tel-cache"><?= e(t('support.dernier_message')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($tickets as $t): ?>
      <tr>
        <td><?= e($t['numero']) ?></td>
        <td><a href="<?= e(url('/plateforme/support/' . $t['id'])) ?>"><strong><?= e($t['sujet']) ?></strong></a><?= $t['priorite'] === 'URGENTE' ? ' <span class="pastille rouge">' . e(t('support.urgent')) . '</span>' : '' ?></td>
        <td><?= e($t['organisation_nom']) ?><br><small><?= e($t['auteur_nom']) ?></small></td>
        <td><?= e(t('support.categorie.' . strtolower($t['categorie']))) ?></td>
        <td><?= e(t('support.statut.' . strtolower($t['statut']))) ?></td>
        <td><?= e($t['dernier_message_le'] ? date('d/m/Y H:i', strtotime($t['dernier_message_le'])) : '—') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
