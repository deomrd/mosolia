<?php $c = static fn (int $v): string => $v === 0 ? '' : argent($v / 100); $action = '/comptabilite/balance'; $t = $etats['totaux']; ?>
<header class="carte tete-page">
  <div><h1><?= e(t('menu.compta_balance')) ?></h1><p class="aide"><?= e(t('compta.balance_aide')) ?></p></div>
  <?php if ($etats['equilibre']): ?><span class="pastille verte"><?= e(t('compta.equilibre')) ?></span><?php else: ?><span class="pastille rouge"><?= e(t('compta.desequilibre_etat')) ?></span><?php endif; ?>
</header>
<?php require __DIR__ . '/_filtres.php'; ?>
<section class="carte">
  <div class="defile"><table class="etat">
    <thead><tr>
      <th><?= e(t('compta.numero')) ?></th><th><?= e(t('compta.libelle')) ?></th>
      <th class="nombre masque-tel"><?= e(t('compta.report_debit')) ?></th><th class="nombre masque-tel"><?= e(t('compta.report_credit')) ?></th>
      <th class="nombre masque-tel"><?= e(t('compta.debit')) ?></th><th class="nombre masque-tel"><?= e(t('compta.credit')) ?></th>
      <th class="nombre"><?= e(t('compta.solde_debit')) ?></th><th class="nombre"><?= e(t('compta.solde_credit')) ?></th>
    </tr></thead>
    <tbody>
    <?php foreach ($etats['comptes'] as $l): ?>
      <tr>
        <td class="code-compte"><a href="<?= e(url('/comptabilite/grand-livre?' . http_build_query(['compte' => $l['id'], 'du' => $f['du'], 'au' => $f['au'], 'agence' => $f['agence'] ?? '']))) ?>"><?= e($l['numero']) ?></a></td>
        <td><?= e($l['libelle']) ?></td>
        <td class="nombre masque-tel"><?= e($c($l['avant_debit'])) ?></td><td class="nombre masque-tel"><?= e($c($l['avant_credit'])) ?></td>
        <td class="nombre masque-tel"><?= e($c($l['debit'])) ?></td><td class="nombre masque-tel"><?= e($c($l['credit'])) ?></td>
        <td class="nombre"><strong><?= e($c($l['fin_debit'])) ?></strong></td><td class="nombre"><strong><?= e($c($l['fin_credit'])) ?></strong></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr><td></td><td><?= e(t('commun.total')) ?></td>
      <td class="nombre masque-tel"><?= e(argent($t['avant_debit'] / 100)) ?></td><td class="nombre masque-tel"><?= e(argent($t['avant_credit'] / 100)) ?></td>
      <td class="nombre masque-tel"><?= e(argent($t['debit'] / 100)) ?></td><td class="nombre masque-tel"><?= e(argent($t['credit'] / 100)) ?></td>
      <td class="nombre"><?= e(argent($t['fin_debit'] / 100)) ?></td><td class="nombre"><?= e(argent($t['fin_credit'] / 100)) ?></td></tr></tfoot>
  </table></div>
</section>
