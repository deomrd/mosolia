<?php
use AvecXY\Domaine\Montant;

$attente = $d['statut'] === 'PROPOSE';
$partiel = !$attente && (float) $d['plafond_accorde'] < (float) $d['montant_demande'];
$calendrier = ($produit['mode_remboursement'] ?? 'LIBRE') === 'CALENDRIER';
?>
<header class="carte tete-page">
  <div>
    <p class="fil"><a href="<?= e(url('/eligibilite')) ?>"><?= e(t('menu.eligibilite')) ?></a></p>
    <h1><span class="code"><?= e($d['client_code']) ?></span> <?= e($d['client_nom']) ?></h1>
    <p class="aide"><?= e(t('clients.recolteur')) ?> : <?= e($d['recolteur_code'] . ' · ' . $d['recolteur_nom']) ?> · <?= e(t(($d['origine'] ?? '') === 'CLIENT' ? 'eligibilite.demande_du_client' : 'eligibilite.proposee_par', ['nom' => $d['auteur_nom'] ?? '—', 'date' => jour(substr((string) $d['propose_le'], 0, 10))])) ?></p>
  </div>
  <div class="boutons">
    <span class="pastille <?= $eligible ? 'verte' : 'rouge' ?>"><?= e(t($eligible ? 'eligibilite.eligible' : 'eligibilite.non_eligible')) ?></span>
    <span class="pastille <?= $attente ? 'ambre' : 'verte' ?>"><?= e(t($attente ? 'eligibilite.etat.en_attente' : ($partiel ? 'eligibilite.etat.partiel' : 'eligibilite.etat.total'))) ?></span>
  </div>
</header>

<section class="carte">
  <div class="tuiles">
    <div class="tuile fort"><span><?= e(t('eligibilite.montant_demande')) ?></span><strong><?= e(argent($d['montant_demande'])) ?></strong></div>
    <?php if (!$attente): ?><div class="tuile"><span><?= e(t('eligibilite.accorde')) ?></span><strong><?= e(argent($d['plafond_accorde'])) ?></strong></div><?php endif; ?>
    <div class="tuile"><span><?= e(t('credits.regles')) ?></span><strong class="petit"><?= e($d['produit_nom']) ?></strong><small><?= e((float) $produit['taux_interet']) ?> % · <?= e(t($calendrier ? 'credits.mode.calendrier' : 'credits.mode.libre')) ?></small></div>
    <?php if ($faits !== null): ?>
      <div class="tuile"><span><?= e(t('credits.epargne_client')) ?></span><strong><?= e(argent($faits['epargne'] / 100)) ?></strong></div>
      <div class="tuile"><span><?= e(t('caisse.en_main')) ?></span><strong><?= e(argent($faits['caisse'] / 100)) ?></strong></div>
    <?php endif; ?>
  </div>
  <?php if ($d['commentaire']): ?><p class="aide"><?= e($d['commentaire']) ?></p><?php endif; ?>
  <p><a href="<?= e(url('/clients/' . $d['client_id'])) ?>"><?= e(t('eligibilite.voir_client')) ?></a><?php if ($d['credit_id']): ?> · <a href="<?= e(url('/credits/' . $d['credit_id'])) ?>"><?= e(t('eligibilite.voir_credit')) ?></a><?php endif; ?></p>
</section>

<section class="carte">
  <h2><?= e(t('controle.titre')) ?></h2>
  <?php $lignesControle = $controle; require __DIR__ . '/_controle.php'; ?>
  <?php if (!$attente && (int) $d['derogation'] === 1): ?><p class="message message-info"><strong><?= e(t('credits.derogation')) ?> :</strong> <?= e($d['motif_derogation']) ?></p><?php endif; ?>
</section>

<?php if ($attente && $peutDecider): ?>
<div class="deux-colonnes">
  <form class="carte" method="post" action="<?= e(url('/eligibilite/' . $d['id'])) ?>" data-confirmer="<?= e(t('eligibilite.confirmer_accord')) ?>">
    <?= champ_csrf() ?><input type="hidden" name="action" value="ACCORDER">
    <h2><?= e(t('eligibilite.accorder')) ?></h2>
    <p class="aide"><?= e(t('eligibilite.accorder_aide')) ?></p>
    <label><?= e(t('eligibilite.montant_accorde')) ?><input name="montant" inputmode="decimal" required value="<?= e(Montant::pourChamp($d['montant_demande'])) ?>">
      <?php if ($produit['montant_minimum'] !== null || $produit['montant_maximum'] !== null): ?><small><?= e(t('eligibilite.limites', ['min' => $produit['montant_minimum'] !== null ? argent($produit['montant_minimum']) : '—', 'max' => $produit['montant_maximum'] !== null ? argent($produit['montant_maximum']) : '—'])) ?></small><?php endif; ?>
    </label>
    <div class="grille">
      <label><?= e(t('credits.champ.taux_interet')) ?><input name="taux" inputmode="decimal" value="<?= e((float) $produit['taux_interet']) ?>"></label>
      <?php if (!$calendrier): ?><label><?= e(t('credits.champ.duree_jours')) ?><input name="duree" inputmode="numeric" value="<?= e($produit['duree_jours']) ?>"></label><?php endif; ?>
      <label><?= e(t('credits.date_octroi')) ?><input type="date" name="date" value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>" required></label>
    </div>
    <?php if ($calendrier): ?><p class="aide"><?= e(t('eligibilite.calendrier', ['n' => $produit['echeances_nombre'], 'jours' => $produit['echeances_intervalle']])) ?></p><?php endif; ?>
    <?php if (!$eligible): ?>
      <label><?= e(t('credits.motif_derogation')) ?><textarea name="motif_derogation" rows="2" required></textarea><small><?= e(t('eligibilite.derogation_aide')) ?></small></label>
    <?php endif; ?>
    <label><?= e(t('commun.commentaire')) ?><input name="commentaire"></label>
    <div class="boutons"><button class="bouton<?= $eligible ? '' : ' danger' ?>"><?= e(t($eligible ? 'eligibilite.accorder' : 'credits.accorder_par_derogation')) ?></button></div>
  </form>

  <form class="carte" method="post" action="<?= e(url('/eligibilite/' . $d['id'])) ?>" data-confirmer="<?= e(t('eligibilite.confirmer_refus')) ?>">
    <?= champ_csrf() ?><input type="hidden" name="action" value="REFUSER">
    <h2><?= e(t('eligibilite.refuser')) ?></h2>
    <p class="aide"><?= e(t('eligibilite.refuser_aide')) ?></p>
    <label><?= e(t('corbeille.motif')) ?><input name="motif" maxlength="255"></label>
    <div class="boutons"><button class="bouton clair"><?= e(t('eligibilite.refuser')) ?></button></div>
  </form>
</div>
<?php endif; ?>
