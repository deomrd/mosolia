<header class="carte tete-page">
  <div>
    <p class="fil"><a href="<?= e(url('/credits')) ?>"><?= e(t('menu.credits')) ?></a></p>
    <h1><?= e(t('credits.accorder')) ?></h1>
    <p class="aide"><?= e(t('credits.aide_octroi')) ?></p>
  </div>
</header>

<?php if (!$produits): ?>
  <section class="carte"><p class="aide"><?= e(t('credits.aucune_regle')) ?></p></section>
<?php else: ?>
<form class="carte filtres" method="get" action="<?= e(url('/credits/nouveau')) ?>">
  <label><?= e(t('credits.code_client')) ?><input name="client" value="<?= e($s['client']) ?>" required data-majuscules placeholder="ABCD-1"></label>
  <label><?= e(t('credits.regles')) ?>
    <select name="produit"><?php foreach ($produits as $p): ?><option value="<?= e($p['id']) ?>"<?= $s['produit'] === (int) $p['id'] ? ' selected' : '' ?>><?= e($p['nom'] . ' · ' . (float) $p['taux_interet'] . ' % · ' . $p['duree_jours'] . ' j') ?></option><?php endforeach; ?></select>
  </label>
  <label><?= e(t('caisse.montant')) ?><input name="montant" value="<?= e($s['montant']) ?>" inputmode="decimal" required></label>
  <label><?= e(t('credits.champ.taux_interet')) ?><input name="taux" value="<?= e($s['taux']) ?>" inputmode="decimal" placeholder="<?= e(t('credits.selon_regles')) ?>"></label>
  <label><?= e(t('credits.champ.duree_jours')) ?><input name="duree" value="<?= e($s['duree']) ?>" inputmode="numeric" placeholder="<?= e(t('credits.selon_regles')) ?>"></label>
  <button class="bouton clair"><?= e(t('credits.controler')) ?></button>
</form>

<?php if ($apercu !== null): $a = $apercu; ?>
<section class="carte">
  <h2><span class="code"><?= e($a['client']['code']) ?></span> <?= e($a['client']['nom']) ?></h2>
  <div class="tuiles">
    <div class="tuile"><span><?= e(t('credits.prete')) ?></span><strong><?= e(argent($a['montant'] / 100)) ?></strong></div>
    <div class="tuile"><span><?= e(t('credits.interet')) ?> (<?= e((float) $a['taux']) ?> %)</span><strong><?= e(argent($a['interet'] / 100)) ?></strong><small><?= e(t('credits.calcul.' . strtolower($a['plan']['mode']))) ?></small></div>
    <?php if ($a['plan']['frais'] > 0): ?><div class="tuile"><span><?= e(t('credits.exemple_frais')) ?></span><strong><?= e(argent($a['plan']['frais'] / 100)) ?></strong><small><?= e(t('credits.frais_mode.' . strtolower($a['plan']['frais_mode']))) ?></small></div><?php endif; ?>
    <div class="tuile fort"><span><?= e(t('credits.total_du')) ?></span><strong><?= e(argent($a['plan']['total'] / 100)) ?></strong></div>
    <div class="tuile"><span><?= e(t('credits.exemple_recu')) ?></span><strong><?= e(argent($a['plan']['verse'] / 100)) ?></strong></div>
    <?php if ($a['plan']['garantie'] > 0): ?><div class="tuile"><span><?= e(t('credits.exemple_garantie')) ?></span><strong><?= e(argent($a['plan']['garantie'] / 100)) ?></strong></div><?php endif; ?>
    <div class="tuile"><span><?= e(t('credits.echeance')) ?></span><strong><?= e(jour($a['calendrier'] ? end($a['calendrier'])['date_prevue'] : \AvecXY\Domaine\Dates::ajouterJours($s['date'], $a['duree']))) ?></strong><small><?= e(t('abonnement.jours', ['n' => $a['duree']])) ?></small></div>
    <div class="tuile"><span><?= e(t('credits.epargne_client')) ?></span><strong><?= e(argent($a['faits']['epargne'] / 100)) ?></strong></div>
    <div class="tuile"><span><?= e(t('caisse.en_main')) ?></span><strong><?= e(argent($a['faits']['caisse'] / 100)) ?></strong></div>
  </div>
  <?php if ($a['calendrier']): ?>
  <h3><?= e(t('credits.calendrier')) ?></h3>
  <?php $lignesAmortissement = $a['calendrier']; require __DIR__ . '/_amortissement.php'; ?>
  <?php endif; ?>
  <h3><?= e(t('controle.titre')) ?></h3>
  <?php $lignesControle = $a['lignes']; require __DIR__ . '/_controle.php'; ?>
  <?php $sansDemande = (int) $a['produit']['accord_agent_requis'] === 1; $bloque = !$a['conforme']; ?>
  <?php if ($sansDemande): ?><p class="message message-info"><?= e(t('credits.demande_requise')) ?> <a href="<?= e(url('/eligibilite')) ?>"><?= e(t('menu.eligibilite')) ?></a></p><?php endif; ?>
  <?php if (!$a['conforme']): ?><p class="message message-erreur"><?= e(t('credits.regles_non_respectees')) ?></p><?php endif; ?>

  <?php if (!$sansDemande): ?>
  <form method="post" action="<?= e(url('/credits/nouveau')) ?>" data-confirmer="<?= e(t('credits.confirmer', ['montant' => argent($a['montant'] / 100), 'nom' => $a['client']['nom']])) ?>">
    <?= champ_csrf() ?>
    <input type="hidden" name="client" value="<?= e($s['client']) ?>"><input type="hidden" name="produit" value="<?= e($s['produit']) ?>"><input type="hidden" name="montant" value="<?= e($s['montant']) ?>"><input type="hidden" name="taux" value="<?= e($a['taux']) ?>"><input type="hidden" name="duree" value="<?= e($a['duree']) ?>">
    <div class="grille">
      <label><?= e(t('credits.date_octroi')) ?><input type="date" name="date" value="<?= e($s['date']) ?>" max="<?= e(date('Y-m-d')) ?>" required></label>
      <label><?= e(t('commun.commentaire')) ?><input name="commentaire" value="<?= e($s['commentaire']) ?>"></label>
    </div>
    <?php if ($bloque): ?>
      <?php if (peut('credits.deroger') && (int) $a['produit']['derogation_autorisee'] === 1): ?>
        <label><?= e(t('credits.motif_derogation')) ?>
          <textarea name="motif_derogation" rows="2" required><?= e($s['motif_derogation']) ?></textarea>
          <small><?= e(t('credits.motif_derogation_aide')) ?></small>
        </label>
        <div class="boutons"><button class="bouton danger"><?= e(t('credits.accorder_par_derogation')) ?></button></div>
      <?php else: ?>
        <p class="aide"><?= e(t((int) $a['produit']['derogation_autorisee'] === 1 ? 'credits.derogation_sans_droit' : 'credits.derogation_interdite')) ?></p>
      <?php endif; ?>
    <?php else: ?>
      <div class="boutons"><button class="bouton"><?= e(t('credits.accorder')) ?></button></div>
    <?php endif; ?>
  </form>
  <?php endif; ?>
</section>
<?php endif; ?>
<?php endif; ?>
