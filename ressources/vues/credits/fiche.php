<?php $ouvert = in_array($cr['statut'], ['EN_COURS', 'EN_RETARD'], true);
$supprimer = static function (string $type, int $id, int $credit, string $texte): string {
    return '<form method="post" action="' . e(url('/credits/' . $type . '/' . $id . '/supprimer')) . '" data-confirmer="' . e(t('caisse.confirmer_suppression')) . '">' . champ_csrf()
        . '<input type="hidden" name="credit" value="' . e($credit) . '"><button class="lien danger">' . e($texte) . '</button></form>';
}; ?>
<header class="carte tete-page">
  <div>
    <p class="fil"><a href="<?= e(url('/credits')) ?>"><?= e(t('menu.credits')) ?></a> · <a href="<?= e(url('/clients/' . $cr['client_id'])) ?>"><?= e($cr['client_code'] . ' ' . $cr['client_nom']) ?></a></p>
    <h1><?= e(t('credits.credit')) ?> #<?= e($cr['numero']) ?></h1>
    <p class="aide"><?= e($cr['produit_nom']) ?> · <?= e(t('credits.accorde_le', ['date' => jour($cr['date_credit'])])) ?> · <?= e(t('credits.echeance_le', ['date' => jour($cr['date_echeance'])])) ?> · <?= e($cr['recolteur_code'] . ' ' . $cr['recolteur_nom']) ?></p>
  </div>
  <span class="pastille <?= ['EN_COURS' => 'verte', 'EN_RETARD' => 'rouge'][$cr['statut']] ?? 'grise' ?>"><?= e(t('credits.statut.' . strtolower($cr['statut']))) ?></span>
</header>

<section class="carte">
  <div class="tuiles">
    <div class="tuile fort"><span><?= e(t('credits.restant')) ?></span><strong><?= e(argent($cr['restant'])) ?></strong></div>
    <div class="tuile"><span><?= e(t('credits.prete')) ?></span><strong><?= e(argent($cr['montant'])) ?></strong></div>
    <div class="tuile"><span><?= e(t('credits.interet')) ?> (<?= e((float) $cr['taux_interet']) ?> %)</span><strong><?= e(argent($cr['montant_interet'])) ?></strong><small><?= e(t('credits.calcul.' . strtolower($cr['mode_calcul'] ?? 'FIXE'))) ?></small></div>
    <?php if ((float) ($cr['frais_dossier'] ?? 0) > 0): ?><div class="tuile"><span><?= e(t('credits.exemple_frais')) ?></span><strong><?= e(argent($cr['frais_dossier'])) ?></strong><small><?= e(t('credits.frais_mode.' . strtolower($cr['frais_dossier_mode']))) ?></small></div><?php endif; ?>
    <?php if ((float) ($cr['garantie'] ?? 0) > 0): ?><div class="tuile"><span><?= e(t('credits.exemple_garantie')) ?></span><strong><?= e(argent($cr['garantie'])) ?></strong><small><?= e(t($ouvert ? 'credits.garantie_bloquee' : 'credits.garantie_liberee')) ?></small></div><?php endif; ?>
    <div class="tuile"><span><?= e(t('credits.penalites')) ?></span><strong><?= e(argent($cr['total_penalites'])) ?></strong></div>
    <div class="tuile"><span><?= e(t('credits.rembourse')) ?></span><strong><?= e(argent($cr['total_rembourse'])) ?></strong></div>
  </div>
  <?php if ((int) $cr['reconduction'] === 1 && $ouvert): ?><p class="aide"><?= e(t('credits.reconduction_rappel', ['taux' => (float) $cr['taux_interet'], 'jours' => (int) $cr['duree_jours']])) ?></p><?php endif; ?>
  <?php if (!empty($cr['reprise_id'])): ?><p class="aide"><strong><?= e(t('reprise.credit_repris')) ?></strong> <?= e(t('reprise.credit_repris_detail', ['montant' => argent($cr['reprise_rembourse'])])) ?></p><?php endif; ?>
  <?php if ((int) $cr['derogation'] === 1): ?><p class="message message-info"><strong><?= e(t('credits.derogation')) ?> :</strong> <?= e($cr['motif_derogation']) ?></p><?php endif; ?>
  <?php if ($cr['commentaire']): ?><p class="aide"><?= e($cr['commentaire']) ?></p><?php endif; ?>
</section>

<?php if ($ouvert): ?>
<div class="deux-colonnes">
  <?php if (peut('remboursements.saisir') || peut('remboursements.valider')): ?>
  <form class="carte" id="paiement" method="post" action="<?= e(url('/credits/' . $cr['id'] . '/rembourser')) ?>">
    <?= champ_csrf() ?>
    <h2><?= e(t('remboursements.nouveau')) ?></h2>
    <label><?= e(t('caisse.montant')) ?><input name="montant" inputmode="decimal" required><small><?= e(t('remboursements.reste_a_payer', ['montant' => argent($cr['restant'])])) ?></small></label>
    <label><?= e(t('commun.date')) ?><input type="date" name="date" value="<?= e(date('Y-m-d')) ?>" min="<?= e($cr['date_credit']) ?>" max="<?= e(date('Y-m-d')) ?>" required></label>
    <label><?= e(t('commun.commentaire')) ?><input name="commentaire"></label>
    <div class="boutons"><button class="bouton"><?= e(t('commun.enregistrer')) ?></button></div>
  </form>
  <?php endif; ?>
  <?php if (peut('credits.accorder')): ?>
  <form class="carte" method="post" action="<?= e(url('/credits/' . $cr['id'] . '/penaliser')) ?>" data-confirmer="<?= e(t('credits.confirmer_penalite')) ?>">
    <?= champ_csrf() ?>
    <h2><?= e(t('credits.ajouter_penalite')) ?></h2>
    <p class="aide"><?= e(t('credits.penalite_aide', ['taux' => (float) $cr['taux_penalite'], 'base' => t('credits.base.' . strtolower($cr['base_penalite']))])) ?></p>
    <label><?= e(t('credits.montant_penalite')) ?><input name="montant" inputmode="decimal"></label>
    <label><?= e(t('commun.date')) ?><input type="date" name="date" value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>" required></label>
    <div class="boutons"><button class="bouton clair"><?= e(t('credits.ajouter_penalite')) ?></button></div>
  </form>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($calendrier['lignes']): ?>
<section class="carte">
  <div class="tete-section">
    <h2><?= e(t('credits.calendrier')) ?></h2>
    <?php if ($ouvert && $calendrier['attendu'] > 0): ?><span class="pastille <?= $calendrier['retard'] > 0 ? 'rouge' : 'ambre' ?>"><?= e(t('credits.attendu_a_ce_jour', ['montant' => argent($calendrier['attendu'] / 100)])) ?></span><?php endif; ?>
  </div>
  <?php $lignesAmortissement = $calendrier['lignes']; require __DIR__ . '/_amortissement.php'; ?>
</section>
<?php endif; ?>

<?php if ($carnetsEpargne): ?>
<section class="carte">
  <h2><?= e(t('credits.payer_par_epargne')) ?></h2>
  <p class="aide"><?= e(t('credits.payer_par_epargne_aide')) ?></p>
  <div class="defile"><table>
    <thead><tr><th><?= e(t('carnets.carnet')) ?></th><th class="nombre"><?= e(t('clients.epargne')) ?></th><th><?= e(t('credits.regle_du_carnet')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($carnetsEpargne as $k): $p = $k['proposition']; ?>
      <tr>
        <td><a href="<?= e(url('/carnets/' . $k['id'])) ?>"><strong>#<?= e($k['numero']) ?></strong></a> <?= e($k['type_nom']) ?></td>
        <td class="nombre"><?= e(argent($k['epargne'])) ?></td>
        <td><?php if (!$p['possible']): ?><?= e(t($p['raison'])) ?>
          <?php elseif ($p['forme'] === 'TOTAL'): ?><?= e(t('credits.epargne_total', ['montant' => argent($p['net'] / 100)])) ?>
          <?php elseif ($p['forme'] === 'TRANCHE'): ?><?= e(t('credits.epargne_tranche', ['n' => $p['tranche'], 'montant' => argent($p['net'] / 100)])) ?><?= $p['ferme'] ? ' ' . e(t('credits.epargne_ferme')) : '' ?>
          <?php else: ?><?= e(t('credits.epargne_libre')) ?><?php endif; ?></td>
        <td>
          <?php if ($p['possible']): ?>
          <form class="ligne-form" method="post" action="<?= e(url('/credits/' . $cr['id'] . '/rembourser-epargne')) ?>" data-confirmer="<?= e(t('credits.confirmer_epargne')) ?>">
            <?= champ_csrf() ?><input type="hidden" name="carnet" value="<?= e($k['id']) ?>"><input type="hidden" name="date" value="<?= e(date('Y-m-d')) ?>">
            <?php if ($p['forme'] === 'LIBRE'): ?><input name="montant" inputmode="decimal" required aria-label="<?= e(t('caisse.montant')) ?>" placeholder="<?= e(t('caisse.montant')) ?>"><?php endif; ?>
            <button class="bouton clair"><?= e(t('credits.utiliser_epargne')) ?></button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php endif; ?>

<section class="carte">
  <h2><?= e(t('menu.remboursements')) ?></h2>
  <?php if (!$remboursements): ?><p class="aide"><?= e(t('remboursements.aucun')) ?></p><?php else: ?>
  <div class="defile"><table>
    <thead><tr><th><?= e(t('commun.date')) ?></th><th class="nombre"><?= e(t('caisse.montant')) ?></th><th><?= e(t('credits.statut')) ?></th><th class="tel-cache"><?= e(t('cotisations.saisie_par')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($remboursements as $r): ?>
      <tr>
        <td><?= e(jour($r['date_paiement'])) ?></td>
        <td class="nombre"><strong><?= e(argent($r['montant'])) ?></strong></td>
        <td><span class="pastille <?= ['VALIDE' => 'verte', 'EN_ATTENTE' => 'ambre', 'REJETE' => 'rouge'][$r['statut']] ?? 'grise' ?>"><?= e(t('remboursements.statut.' . strtolower($r['statut']))) ?></span></td>
        <td><?= e($r['auteur_nom'] ?? '—') ?><?= $r['commentaire'] ? '<br><small>' . e($r['commentaire']) . '</small>' : '' ?></td>
        <td class="actions"><?= peut('remboursements.valider') ? $supprimer('remboursements', (int) $r['id'], (int) $cr['id'], t('commun.supprimer')) : '' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>

<?php if ($penalites): ?>
<section class="carte">
  <h2><?= e(t('credits.penalites')) ?></h2>
  <div class="defile"><table>
    <thead><tr><th><?= e(t('commun.date')) ?></th><th class="tel-cache nombre"><?= e(t('credits.base_calcul')) ?></th><th class="tel-cache nombre">%</th><th class="nombre"><?= e(t('caisse.montant')) ?></th><th></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($penalites as $p): ?>
      <tr>
        <td><?= e(jour($p['date_penalite'])) ?></td><td class="nombre"><?= e(argent($p['base'])) ?></td><td class="nombre"><?= e((float) $p['taux']) ?></td>
        <td class="nombre"><strong><?= e(argent($p['montant'])) ?></strong></td>
        <td><?= (int) $p['automatique'] === 1 ? '<span class="pastille grise">' . e(t((int) $cr['reconduction'] === 1 ? 'credits.reconduit' : 'credits.automatique')) . '</span>' : '' ?></td>
        <td class="actions"><?= peut('credits.accorder') ? $supprimer('penalites', (int) $p['id'], (int) $cr['id'], t('commun.supprimer')) : '' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php endif; ?>

<?php if (peut('credits.accorder')): ?>
<section class="carte">
  <h2><?= e(t('commun.zone_suppression')) ?></h2>
  <p class="aide"><?= e(t('credits.suppression_aide')) ?></p>
  <form class="ligne-form" method="post" action="<?= e(url('/credits/credits/' . $cr['id'] . '/supprimer')) ?>" data-confirmer="<?= e(t('caisse.confirmer_suppression')) ?>">
    <?= champ_csrf() ?>
    <input name="motif" required placeholder="<?= e(t('corbeille.motif')) ?>" aria-label="<?= e(t('corbeille.motif')) ?>">
    <button class="bouton danger"><?= e(t('credits.supprimer')) ?></button>
  </form>
</section>
<?php endif; ?>
