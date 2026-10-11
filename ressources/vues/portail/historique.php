<?php
$c = static fn (int $v): string => argent($v / 100);
$titreReleve = t('releve.general');
$sousTitre = $du !== '' ? t('releve.periode', ['du' => jour($du), 'au' => jour($au)]) : t('releve.periode_jusqua', ['au' => jour($au)]);
$sortie = ['RETRAIT' => true, 'FRAIS' => true, 'REMBOURSEMENT' => true];
$types = ['COTISATION', 'RETRAIT', 'FRAIS', 'INTERET', 'CARNET'] + ($credit ? [5 => 'REMBOURSEMENT', 6 => 'CREDIT'] : []);
require __DIR__ . '/_entete_releve.php'; ?>

<form class="carte filtres" method="get" action="<?= e(url('/espace-client/historique')) ?>" data-filtre-auto>
  <label><?= e(t('commun.du')) ?><input type="date" name="du" value="<?= e($du) ?>" max="<?= e(date('Y-m-d')) ?>"></label>
  <label><?= e(t('commun.au')) ?><input type="date" name="au" value="<?= e($au) ?>" max="<?= e(date('Y-m-d')) ?>"></label>
  <label><?= e(t('commun.operation')) ?>
    <select name="type"><option value=""><?= e(t('commun.tous')) ?></option>
      <?php foreach ($types as $v): ?><option value="<?= e($v) ?>"<?= $type === $v ? ' selected' : '' ?>><?= e(t('portail.type.' . strtolower($v))) ?></option><?php endforeach; ?>
    </select></label>
  <noscript><button class="bouton clair"><?= e(t('commun.afficher')) ?></button></noscript>
</form>

<div data-resultats class="pile">
<section class="carte">
  <div class="synthese">
    <div><span><?= e(t('releve.total_verse')) ?></span><strong><?= e($c($totaux['COTISATION'])) ?></strong></div>
    <div><span><?= e(t('releve.total_retire')) ?></span><strong><?= e($c($totaux['RETRAIT'])) ?></strong></div>
    <?php if ($totaux['INTERET'] > 0): ?><div><span><?= e(t('releve.total_interets')) ?></span><strong><?= e($c($totaux['INTERET'])) ?></strong></div><?php endif; ?>
    <?php if ($totaux['FRAIS'] > 0): ?><div><span><?= e(t('releve.total_frais')) ?></span><strong><?= e($c($totaux['FRAIS'])) ?></strong></div><?php endif; ?>
    <?php if ($credit && $totaux['REMBOURSEMENT'] > 0): ?><div><span><?= e(t('releve.total_rembourse')) ?></span><strong><?= e($c($totaux['REMBOURSEMENT'])) ?></strong></div><?php endif; ?>
    <?php if ($totaux['attente'] > 0): ?><div class="attente"><span><?= e(t('portail.en_attente_validation')) ?></span><strong><?= e($c($totaux['attente'])) ?></strong></div><?php endif; ?>
  </div>
</section>

<section class="carte">
  <h2><?= e(t('releve.operations', ['n' => count($historique)])) ?></h2>
  <?php if (!$historique): ?><p class="aide"><?= e(t('portail.aucune_operation')) ?></p><?php else: ?>
  <div class="defile"><table class="releve">
    <thead><tr><th><?= e(t('commun.date')) ?></th><th class="masque-tel"><?= e(t('releve.reference')) ?></th><th><?= e(t('releve.libelle')) ?></th><th class="nombre"><?= e(t('releve.entree')) ?></th><th class="nombre"><?= e(t('releve.sortie')) ?></th><th class="masque-tel"><?= e(t('credits.statut')) ?></th></tr></thead>
    <tbody>
    <?php $mois = ''; foreach ($historique as $h): $attente = $h['statut'] !== 'VALIDE'; $m = substr((string) $h['jour'], 0, 7);
      if ($m !== $mois): $mois = $m; ?><tr class="mois"><td colspan="6"><?= e(t('mois.' . (int) substr($m, 5, 2)) . ' ' . substr($m, 0, 4)) ?></td></tr><?php endif; ?>
      <tr<?= $attente ? ' class="en-attente"' : '' ?>>
        <td><span class="masque-tel"><?= e(jour($h['jour'])) ?></span><span class="seul-tel date-courte"><?= e(date('d/m/y', strtotime((string) $h['jour']))) ?></span></td>
        <td class="masque-tel"><?php $credit0 = in_array($h['type'], ['REMBOURSEMENT', 'CREDIT'], true); ?>
          <?php if (!$credit0): ?><a href="<?= e(url('/espace-client/carnets/' . $h['carnet_id'])) ?>"><?= e(t('portail.ref_carnet', ['n' => $h['reference']])) ?></a>
          <?php else: ?><?= e(t('portail.ref_credit', ['n' => $h['reference']])) ?><?php endif; ?></td>
        <td><?= e(t('portail.type.' . strtolower($h['type']))) ?><?= $h['parts'] ? ' · ' . e(t('portail.parts', ['n' => $h['parts']])) : '' ?>
          <small class="seul-tel"><?= e(t($credit0 ? 'portail.ref_credit' : 'portail.ref_carnet', ['n' => $h['reference']])) ?><?= $attente ? ' · ' . e(t('portail.en_attente')) : '' ?></small></td>
        <td class="nombre plus"><?= isset($sortie[$h['type']]) ? '' : e(argent($h['montant'])) ?></td>
        <td class="nombre moins"><?= isset($sortie[$h['type']]) ? e(argent($h['montant'])) : '' ?></td>
        <td class="masque-tel"><span class="pastille <?= $attente ? 'ambre' : 'verte' ?>"><?= e(t($attente ? 'portail.en_attente' : 'portail.valide')) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="note-releve"><?= e(t('releve.note_general')) ?></p>
  <?php endif; ?>
</section>
</div>
