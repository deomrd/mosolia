<?php
use AvecXY\Application\ServiceComptabilite;
use AvecXY\Domaine\Montant;
$action = '/comptabilite/grand-livre';
ob_start(); ?>
<label><?= e(t('compta.compte')) ?>
  <select name="compte">
    <?php foreach ($comptes as $co): ?><option value="<?= e($co['id']) ?>"<?= $compte !== null && (int) $compte['id'] === (int) $co['id'] ? ' selected' : '' ?>><?= e($co['numero'] . ' · ' . $co['libelle']) ?></option><?php endforeach; ?>
  </select>
</label>
<?php $extra = ob_get_clean(); $solde = $report; ?>
<header class="carte tete-page">
  <div><h1><?= e(t('menu.compta_grand_livre')) ?></h1><?php if ($compte): ?><p class="aide"><?= e($compte['numero'] . ' · ' . $compte['libelle'] . ' · ' . t('compta.nature.' . strtolower($compte['nature']))) ?></p><?php endif; ?></div>
</header>
<?php require __DIR__ . '/_filtres.php'; ?>
<?php if ($compte): ?>
<section class="carte">
  <div class="defile"><table class="etat">
    <thead><tr><th><?= e(t('commun.date')) ?></th><th class="tel-cache"><?= e(t('compta.operation')) ?></th><th class="masque-tel"><?= e(t('compta.reference')) ?></th><th class="nombre"><?= e(t('compta.debit')) ?></th><th class="nombre"><?= e(t('compta.credit')) ?></th><th class="nombre"><?= e(t('compta.solde')) ?></th></tr></thead>
    <tbody>
      <tr class="report"><td><?= e(jour($f['du'])) ?></td><td><?= e(t('compta.report')) ?></td><td class="masque-tel"></td><td></td><td></td><td class="nombre"><strong><?= e(argent($solde / 100)) ?></strong></td></tr>
    <?php foreach ($lignes as $l): $d = Montant::enCentimes($l['debit']); $k = Montant::enCentimes($l['credit']); $solde += ServiceComptabilite::sens($compte['nature'], $d, $k); ?>
      <tr>
        <td><?= e(jour((string) $l['jour'])) ?></td><td><?= e(t('compta.type.' . strtolower((string) $l['type']))) ?></td><td class="masque-tel"><?= e((string) $l['ref']) ?></td>
        <td class="nombre"><?= $d ? e(argent($d / 100)) : '' ?></td><td class="nombre"><?= $k ? e(argent($k / 100)) : '' ?></td><td class="nombre"><?= e(argent($solde / 100)) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr><td colspan="5"><?= e(t('compta.solde_au', ['date' => jour($f['au'])])) ?></td><td class="nombre"><?= e(argent($solde / 100)) ?></td></tr></tfoot>
  </table></div>
</section>
<?php endif; ?>
