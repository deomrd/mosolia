<header class="carte tete-page">
  <div>
    <h1><?= e(t('menu.remboursements')) ?></h1>
    <p class="aide"><?= e(t('remboursements.aide')) ?></p>
  </div>
</header>

<form class="carte filtres" method="get" action="<?= e(url('/remboursements')) ?>" data-filtre-auto>
  <label><?= e(t('commun.rechercher')) ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('credits.recherche')) ?>"></label>
  <noscript><button class="bouton clair"><?= e(t('commun.afficher')) ?></button></noscript>
</form>

<section class="carte" data-resultats>
  <?php if (!$lignes): ?><p class="aide"><?= e(t('remboursements.personne')) ?></p><?php else: ?>
  <div class="defile"><table>
    <thead><tr><th><?= e(t('clients.client')) ?></th><th class="tel-cache"><?= e(t('credits.credit')) ?></th><th class="tel-cache"><?= e(t('remboursements.dernier')) ?></th><th class="tel-cache nombre"><?= e(t('credits.rembourse')) ?></th><th class="nombre"><?= e(t('credits.restant')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($lignes as $l): $reste = (float) $l['restant'] > 0 && in_array($l['statut'], ['EN_COURS', 'EN_RETARD'], true); ?>
      <tr>
        <td><a href="<?= e(url('/credits/' . $l['id'])) ?>"><span class="code"><?= e($l['client_code']) ?></span> <strong><?= e($l['client_nom']) ?></strong></a></td>
        <td>#<?= e($l['numero']) ?><?= $l['statut'] === 'EN_RETARD' ? ' <span class="pastille rouge">' . e(t('credits.statut.en_retard')) . '</span>' : '' ?></td>
        <td><?= e(jour($l['dernier_paiement'])) ?><br><small><?= e(t('remboursements.nombre', ['n' => $l['paiements']])) ?></small></td>
        <td class="nombre"><?= e(argent($l['verse'])) ?></td>
        <td class="nombre"><strong><?= e(argent($l['restant'])) ?></strong></td>
        <td class="actions"><a class="bouton <?= $reste ? '' : 'clair' ?>" href="<?= e(url('/credits/' . $l['id'])) ?>#paiement"><?= e(t($reste ? 'remboursements.payer_reste' : 'remboursements.voir')) ?></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
