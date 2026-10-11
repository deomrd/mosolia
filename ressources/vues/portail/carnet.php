<?php
$parts = $k['mode'] === 'PARTS';
$c = static fn (int $v): string => argent($v / 100);
$titreReleve = t('releve.carnet', ['n' => $k['numero']]);
$sousTitre = $k['type_nom'] . ' · ' . t('carnets.ouvert_le', ['date' => jour($k['date_ouverture'])]) . ' · ' . t('carnets.statut.' . strtolower($k['statut']));
?>
<p class="fil sans-impression"><a href="<?= e(url('/espace-client/carnets')) ?>"><?= e(t('portail.mes_carnets')) ?></a></p>
<?php require __DIR__ . '/_entete_releve.php'; ?>
<?php if (($k['validation'] ?? 'VALIDE') !== 'VALIDE'): ?><p class="message message-info"><?= e(t('portail.carnet_en_attente')) ?></p><?php endif; ?>

<section class="carte">
  <div class="synthese">
    <div><span><?= e(t('releve.solde_actuel')) ?></span><strong><?= e(argent($k['epargne'])) ?></strong></div>
    <?php if ($parts): ?><div><span><?= e(t('carnets.mise')) ?></span><strong><?= e(argent($k['mise'])) ?></strong></div>
    <div><span><?= e(t('releve.mises')) ?></span><strong><?= e($k['parts_validees']) ?> / <?= e($k['nombre_parts']) ?></strong></div><?php endif; ?>
    <div><span><?= e(t('carnets.total_cotise')) ?></span><strong><?= e(argent($k['total_cotise'])) ?></strong></div>
    <div><span><?= e(t('carnets.total_retire')) ?></span><strong><?= e(argent($k['total_retire'])) ?></strong></div>
    <?php if ((float) $k['en_attente'] > 0): ?><div class="attente"><span><?= e(t('portail.en_attente_validation')) ?></span><strong><?= e(argent($k['en_attente'])) ?></strong></div><?php endif; ?>
  </div>
</section>

<section class="carte">
  <h2><?= e(t('releve.mouvements')) ?></h2>
  <div class="defile"><table class="releve">
    <thead><tr><th><?= e(t('commun.date')) ?></th><th><?= e(t('releve.libelle')) ?></th><th class="nombre masque-tel"><?= e(t('releve.verse')) ?></th><th class="nombre"><?= e(t('releve.credit')) ?></th><th class="nombre"><?= e(t('releve.debit')) ?></th><th class="nombre"><?= e(t('releve.solde')) ?></th></tr></thead>
    <tbody>
      <tr class="report"><td><?= e(jour($k['date_ouverture'])) ?></td><td><?= e(t(!empty($k['reprise_id']) ? 'releve.solde_repris' : 'releve.solde_ouverture')) ?></td><td class="masque-tel"></td><td></td><td></td><td class="nombre"><?= e($c($releve['ouverture'])) ?></td></tr>
      <?php foreach ($releve['lignes'] as $l): ?>
      <tr<?= $l['valide'] ? '' : ' class="en-attente"' ?>>
        <td><span class="masque-tel"><?= e(jour($l['jour'])) ?></span><span class="seul-tel date-courte"><?= e(date('d/m/y', strtotime((string) $l['jour']))) ?></span></td>
        <td><?= e(t('portail.type.' . strtolower($l['type']))) ?><?= $l['parts'] ? ' · ' . e(t('portail.parts', ['n' => $l['parts']])) : '' ?>
          <?php if ($l['type'] === 'CARNET'): ?><small class="seul-tel"><?= e(argent($l['montant'])) ?></small><?php endif; ?>
          <?php if (!$l['valide']): ?> <span class="pastille ambre"><?= e(t('portail.en_attente')) ?></span><?php endif; ?></td>
        <td class="nombre masque-tel"><?= in_array($l['type'], ['COTISATION', 'CARNET'], true) ? e(argent($l['montant'])) : '' ?></td>
        <td class="nombre plus"><?= $l['mouvement'] > 0 ? e($c($l['mouvement'])) : '' ?></td>
        <td class="nombre moins"><?= $l['mouvement'] < 0 ? e($c(-$l['mouvement'])) : '' ?></td>
        <td class="nombre solde"><?= $l['valide'] ? e($c($l['solde'])) : '—' ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr><td></td><td><?= e(t('releve.totaux')) ?></td><td class="nombre masque-tel"><?= e($c($releve['verse'])) ?></td><td class="nombre plus"><?= e($c($releve['entrees'])) ?></td><td class="nombre moins"><?= e($c($releve['sorties'])) ?></td><td class="nombre solde"><?= e($c($releve['cloture'])) ?></td></tr>
    </tfoot>
  </table></div>
  <p class="note-releve"><?= e(t($parts ? 'releve.note_carnet_parts' : 'releve.note_carnet')) ?></p>
</section>
