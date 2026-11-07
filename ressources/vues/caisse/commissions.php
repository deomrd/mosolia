<header class="carte tete-page">
  <div><h1><?= e(t('menu.commissions')) ?></h1><p class="aide"><?= e(t('commissions.aide')) ?></p></div>
</header>
<section class="carte">
  <?php if (!$lignes): ?><p class="aide"><?= e(t('commissions.vide')) ?></p><?php else: ?>
  <div class="defile"><table>
    <thead><tr>
      <th><?= e(t('commun.mois')) ?></th><th class="nombre"><?= e(t('paiements.part')) ?></th>
      <th class="nombre"><?= e(t('commissions.deductions')) ?></th><th class="nombre"><?= e(t('commissions.verse')) ?></th>
      <th><?= e(t('commissions.statut')) ?></th><th><?= e(t('commissions.date')) ?></th>
    </tr></thead>
    <tbody><?php foreach ($lignes as $l): $paye = $l['paiement_id'] !== null; ?>
      <tr>
        <td><?= e(substr($l['periode'], 5, 2) . '/' . substr($l['periode'], 0, 4)) ?></td>
        <td class="nombre"><?= e(argent($l['part_agent'])) ?></td>
        <td class="nombre"><?= $paye ? e(argent($l['manquants_deduits'])) : '—' ?></td>
        <td class="nombre"><?= $paye ? e(argent($l['montant_paye'])) : '—' ?></td>
        <td><span class="pastille"><?= e(t($paye ? 'commissions.paye' : 'commissions.non_paye')) ?></span></td>
        <td><?= $paye ? e(jour($l['date_paiement'])) : '—' ?></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</section>
