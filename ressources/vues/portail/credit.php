<?php
$ouvert = in_array($cr['statut'], ['EN_COURS', 'EN_RETARD'], true);
$c = static fn (int $v): string => argent($v / 100);
$titreReleve = t('releve.credit_titre', ['n' => $cr['numero']]);
$sousTitre = $cr['produit_nom'] . ' · ' . t('credits.statut.' . strtolower($cr['statut']));
?>
<p class="fil sans-impression"><a href="<?= e(url('/espace-client/credits')) ?>"><?= e(t('portail.mes_credits')) ?></a></p>
<?php require __DIR__ . '/_entete_releve.php'; ?>

<section class="carte">
  <div class="synthese">
    <div class="<?= $cr['statut'] === 'EN_RETARD' ? 'alerte' : '' ?>"><span><?= e(t('credits.restant')) ?></span><strong><?= e(argent($cr['restant'])) ?></strong></div>
    <div><span><?= e(t('credits.total_du')) ?></span><strong><?= e(argent($cr['total_du'])) ?></strong></div>
    <div><span><?= e(t('credits.rembourse')) ?></span><strong><?= e(argent($cr['total_rembourse'])) ?></strong></div>
    <?php if ($attente > 0): ?><div class="attente"><span><?= e(t('portail.en_attente_validation')) ?></span><strong><?= e($c($attente)) ?></strong></div><?php endif; ?>
  </div>
  <?php $pct = (float) $cr['total_du'] > 0 ? min(100, (int) round((float) $cr['total_rembourse'] * 100 / (float) $cr['total_du'])) : 0; ?>
  <span class="jauge espace" role="img" aria-label="<?= e($pct) ?> %"><i style="width:<?= e($pct) ?>%"></i></span>
  <p class="aide"><?= e(t('releve.rembourse_pct', ['n' => $pct])) ?></p>
</section>

<section class="carte">
  <h2><?= e(t('releve.conditions')) ?></h2>
  <dl class="fiche">
    <dt><?= e(t('credits.prete')) ?></dt><dd><?= e(argent($cr['montant'])) ?></dd>
    <dt><?= e(t('releve.taux')) ?></dt><dd><?= e((float) $cr['taux_interet']) ?> % · <?= e(argent($cr['montant_interet'])) ?></dd>
    <dt><?= e(t('releve.date_octroi')) ?></dt><dd><?= e(jour($cr['date_credit'])) ?></dd>
    <dt><?= e(t('credits.echeance')) ?></dt><dd><?= e(jour($cr['date_echeance'])) ?> (<?= e(t('releve.duree', ['n' => (int) $cr['duree_jours']])) ?>)</dd>
    <?php if ((float) $cr['total_penalites'] > 0): ?><dt><?= e(t('credits.penalites')) ?></dt><dd><?= e(argent($cr['total_penalites'])) ?></dd><?php endif; ?>
  </dl>
  <?php if ((int) $cr['reconduction'] === 1 && $ouvert): ?><p class="note-releve"><?= e(t('credits.reconduction_rappel', ['taux' => (float) $cr['taux_interet'], 'jours' => (int) $cr['duree_jours']])) ?></p><?php endif; ?>
</section>

<?php if ($calendrier['lignes']): ?>
<section class="carte">
  <div class="tete-section">
    <h2><?= e(t('credits.calendrier')) ?></h2>
    <?php if ($ouvert && $calendrier['attendu'] > 0): ?><span class="pastille <?= $calendrier['retard'] > 0 ? 'rouge' : 'ambre' ?>"><?= e(t('credits.attendu_a_ce_jour', ['montant' => argent($calendrier['attendu'] / 100)])) ?></span><?php endif; ?>
  </div>
  <div class="defile"><table class="releve">
    <thead><tr><th>N°</th><th><?= e(t('commun.date')) ?></th><th class="nombre"><?= e(t('releve.a_payer')) ?></th><th class="nombre"><?= e(t('releve.paye')) ?></th><th class="nombre masque-tel"><?= e(t('releve.reste')) ?></th><th><?= e(t('credits.statut')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($calendrier['lignes'] as $e): $du = (int) round((float) $e['montant'] * 100); ?>
      <tr><td><?= e($e['numero']) ?></td><td><span class="masque-tel"><?= e(jour($e['date_prevue'])) ?></span><span class="seul-tel date-courte"><?= e(date('d/m/y', strtotime((string) $e['date_prevue']))) ?></span></td><td class="nombre"><?= e(argent($e['montant'])) ?></td><td class="nombre plus"><?= e($c((int) $e['paye'])) ?></td><td class="nombre solde masque-tel"><?= e($c(max(0, $du - (int) $e['paye']))) ?></td>
        <td><span class="pastille <?= ['PAYEE' => 'verte', 'EN_RETARD' => 'rouge', 'PARTIELLE' => 'ambre'][$e['etat']] ?? 'grise' ?>"><?= e(t('credits.echeance.' . strtolower($e['etat']))) ?></span></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php endif; ?>

<section class="carte">
  <h2><?= e(t('releve.ecritures')) ?></h2>
  <div class="defile"><table class="releve">
    <thead><tr><th><?= e(t('commun.date')) ?></th><th><?= e(t('releve.libelle')) ?></th><th class="nombre"><?= e(t('releve.du')) ?></th><th class="nombre"><?= e(t('releve.paye')) ?></th><th class="nombre"><?= e(t('releve.reste')) ?></th></tr></thead>
    <tbody>
    <?php $totalDu = 0; $totalPaye = 0; foreach ($ecritures as $e): if ($e['valide']) { $totalDu += max(0, $e['montant']); $totalPaye += max(0, -$e['montant']); } ?>
      <tr<?= $e['valide'] ? '' : ' class="en-attente"' ?>>
        <td><span class="masque-tel"><?= e(jour($e['jour'])) ?></span><span class="seul-tel date-courte"><?= e(date('d/m/y', strtotime((string) $e['jour']))) ?></span></td>
        <td><?= e(t('releve.ecriture.' . $e['type'], ['taux' => (float) $cr['taux_interet']])) ?><?php if (!$e['valide']): ?> <span class="pastille ambre"><?= e(t('portail.en_attente')) ?></span><?php endif; ?></td>
        <td class="nombre moins"><?= $e['montant'] > 0 ? e($c($e['montant'])) : '' ?></td>
        <td class="nombre plus"><?= $e['montant'] < 0 ? e($c(-$e['montant'])) : '' ?></td>
        <td class="nombre solde"><?= $e['valide'] ? e($c($e['reste'])) : '—' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr><td colspan="2"><?= e(t('releve.totaux')) ?></td><td class="nombre moins"><?= e($c($totalDu)) ?></td><td class="nombre plus"><?= e($c($totalPaye)) ?></td><td class="nombre solde"><?= e(argent($cr['restant'])) ?></td></tr></tfoot>
  </table></div>
  <p class="note-releve"><?= e(t('releve.note_credit')) ?></p>
</section>
