<header class="carte tete-page">
  <div>
    <h1><?= e(t('portail.mes_credits')) ?></h1>
    <p class="aide"><?= e(t('portail.credits_aide')) ?></p>
  </div>
</header>

<section class="carte">
  <h2><?= e(t('portail.credits_en_cours')) ?></h2>
  <?php if (!$credits): ?><p class="aide"><?= e(t('portail.aucun_credit')) ?></p><?php else: ?>
  <div class="defile"><table>
    <thead><tr><th>#</th><th><?= e(t('credits.echeance')) ?></th><th class="nombre"><?= e(t('credits.total_du')) ?></th><th class="nombre"><?= e(t('credits.rembourse')) ?></th><th class="nombre"><?= e(t('credits.restant')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($credits as $cr): ?>
      <tr>
        <td><a href="<?= e(url('/espace-client/credits/' . $cr['id'])) ?>"><strong>#<?= e($cr['numero']) ?></strong> <?= e($cr['produit_nom']) ?></a><br><small><?= e(jour($cr['date_credit'])) ?></small></td>
        <td><?= e(jour($cr['date_echeance'])) ?></td>
        <td class="nombre"><?= e(argent($cr['total_du'])) ?></td>
        <td class="nombre"><?= e(argent($cr['total_rembourse'])) ?></td>
        <td class="nombre"><strong><?= e(argent($cr['restant'])) ?></strong></td>
        <td><span class="pastille <?= ['EN_COURS' => 'verte', 'EN_RETARD' => 'rouge'][$cr['statut']] ?? 'grise' ?>"><?= e(t('credits.statut.' . strtolower($cr['statut']))) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>

<section class="carte">
  <h2><?= e(t('portail.mes_demandes')) ?></h2>
  <?php if (!$demandes): ?><p class="aide"><?= e(t('portail.aucune_demande')) ?></p><?php else: ?>
  <div class="defile"><table>
    <thead><tr><th><?= e(t('commun.date')) ?></th><th><?= e(t('portail.demande')) ?></th><th class="nombre"><?= e(t('caisse.montant')) ?></th><th><?= e(t('credits.statut')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($demandes as $d): $etat = ['PROPOSE' => ['ambre', 'attente'], 'VALIDE' => ['verte', 'accordee'], 'REJETE' => ['rouge', 'refusee']][$d['statut']] ?? ['grise', 'expiree']; ?>
      <tr>
        <td><?= e(jour(substr((string) $d['cree_le'], 0, 10))) ?></td>
        <td><?= e($d['produit_nom']) ?><br><small><?= e(t($d['origine'] === 'CLIENT' ? 'portail.demande_par_moi' : 'portail.demande_par_recolteur')) ?></small></td>
        <td class="nombre"><strong><?= e(argent($d['statut'] === 'VALIDE' && $d['plafond_accorde'] !== null ? $d['plafond_accorde'] : $d['montant_demande'])) ?></strong>
          <?php if ($d['statut'] === 'VALIDE' && $d['plafond_accorde'] !== null && (float) $d['plafond_accorde'] !== (float) $d['montant_demande']): ?><br><small><?= e(t('portail.demande_initiale', ['montant' => argent($d['montant_demande'])])) ?></small><?php endif; ?></td>
        <td><span class="pastille <?= $etat[0] ?>"><?= e(t('portail.demande_statut.' . $etat[1])) ?></span>
          <?php if ($d['statut'] === 'REJETE' && $d['motif_suppression']): ?><br><small><?= e($d['motif_suppression']) ?></small><?php endif; ?>
          <?php if ($d['statut'] === 'VALIDE' && $d['credit_id']): ?><br><a href="<?= e(url('/espace-client/credits/' . $d['credit_id'])) ?>"><?= e(t('portail.voir_credit')) ?></a><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>

<?php foreach ($produits as $p): $el = $p['eligibilite']; $lignesControle = $el['lignes']; ?>
<section class="carte">
  <div class="tete-section">
    <h2><?= e(t('portail.eligibilite_titre', ['nom' => $p['nom']])) ?></h2>
    <span class="pastille <?= $el['eligible'] ? 'verte' : 'rouge' ?>"><?= e(t($el['eligible'] ? 'portail.eligible' : 'portail.pas_eligible')) ?></span>
  </div>
  <p class="aide"><?= e(t('portail.conditions', ['taux' => (float) $p['taux_interet'], 'jours' => (int) $p['duree_jours']])) ?></p>
  <?php if ($lignesControle): require __DIR__ . '/../credits/_controle.php'; endif; ?>
  <?php if (!$el['eligible']): ?>
    <p class="message message-info"><?= e(t((string) $el['raison'])) ?></p>
  <?php elseif (!$demandesPermises): ?>
    <p class="aide"><?= e(t('portail.demandes_fermees')) ?></p>
  <?php else: ?>
    <form class="ligne-form espace" method="post" action="<?= e(url('/espace-client/credits')) ?>" data-confirmer="<?= e(t('portail.confirmer_demande')) ?>">
      <?= champ_csrf() ?><input type="hidden" name="produit" value="<?= e($p['id']) ?>">
      <label><?= e(t('portail.montant_souhaite')) ?><input name="montant" inputmode="decimal" required>
        <small><?= e($el['maximum'] > 0 ? t('portail.entre', ['min' => argent($el['minimum'] / 100), 'max' => argent($el['maximum'] / 100)]) : t('portail.a_partir_de', ['min' => argent($el['minimum'] / 100)])) ?></small></label>
      <button class="bouton"><?= e(t('portail.demander')) ?></button>
    </form>
  <?php endif; ?>
</section>
<?php endforeach; ?>
