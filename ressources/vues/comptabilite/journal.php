<?php
use AvecXY\Domaine\Montant;
$action = '/comptabilite/journal';
$td = 0; $tc = 0;
?>
<header class="carte tete-page">
  <div><h1><?= e(t('menu.compta_journal')) ?></h1><p class="aide"><?= e(t('compta.journal_aide')) ?></p></div>
</header>
<?php require __DIR__ . '/_filtres.php'; ?>
<section class="carte">
  <?php if (!$lignes): ?><p class="aide"><?= e(t('compta.aucune_ligne')) ?></p><?php else: ?>
  <div class="defile"><table class="etat journal">
    <thead><tr><th><?= e(t('commun.date')) ?></th><th class="tel-cache"><?= e(t('compta.operation')) ?></th><th class="masque-tel"><?= e(t('compta.reference')) ?></th><th><?= e(t('compta.compte')) ?></th>
      <?php if ($f['agences']): ?><th class="masque-tel"><?= e(t('agences.agence')) ?></th><?php endif; ?>
      <th class="nombre"><?= e(t('compta.debit')) ?></th><th class="nombre"><?= e(t('compta.credit')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($lignes as $l): $d = Montant::enCentimes($l['debit']); $k = Montant::enCentimes($l['credit']); $td += $d; $tc += $k; ?>
      <tr>
        <td><?= e(jour((string) $l['jour'])) ?></td>
        <td><?= e(t('compta.type.' . strtolower((string) $l['type']))) ?></td>
        <td class="masque-tel"><?= e((string) $l['ref']) ?></td>
        <td class="<?= $k > 0 ? 'compte-credit' : '' ?>"><span class="code-compte"><?= e($l['numero']) ?></span> <?= e($l['compte_libelle']) ?></td>
        <?php if ($f['agences']): ?><td class="masque-tel"><?= e($l['agence_nom'] ?? t('agences.siege')) ?></td><?php endif; ?>
        <td class="nombre"><?= $d ? e(argent($d / 100)) : '' ?></td>
        <td class="nombre"><?= $k ? e(argent($k / 100)) : '' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr><td colspan="<?= $f['agences'] ? 5 : 4 ?>"><?= e(t('commun.total')) ?></td><td class="nombre"><?= e(argent($td / 100)) ?></td><td class="nombre"><?= e(argent($tc / 100)) ?></td></tr></tfoot>
  </table></div>
  <?php if (count($lignes) >= 3000): ?><p class="aide"><?= e(t('compta.journal_limite')) ?></p><?php endif; ?>
  <?php endif; ?>
</section>
