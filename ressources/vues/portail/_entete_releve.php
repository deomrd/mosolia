<?php /** @var string $titreReleve @var string $sousTitre */
$compte = \AvecXY\Noyau\Noyau::instance()->comptePortail; ?>
<section class="carte entete-releve">
  <div class="releve-org">
    <strong><?= e($compte['organisation_nom']) ?></strong>
    <?php if (!empty($compte['organisation_telephone'])): ?><span><?= e($compte['organisation_telephone']) ?></span><?php endif; ?>
  </div>
  <div class="releve-titre">
    <h1><?= e($titreReleve) ?></h1>
    <p><?= e($sousTitre) ?></p>
  </div>
  <dl class="releve-infos">
    <div><dt><?= e(t('releve.titulaire')) ?></dt><dd><?= e($compte['nom']) ?></dd></div>
    <div><dt><?= e(t('portail.identifiant')) ?></dt><dd><?= e($compte['code']) ?></dd></div>
    <div><dt><?= e(t('clients.recolteur')) ?></dt><dd><?= e($compte['recolteur_nom']) ?></dd></div>
    <div><dt><?= e(t('releve.edite_le')) ?></dt><dd><?= e(date('d/m/Y H:i')) ?></dd></div>
  </dl>
  <button type="button" class="bouton clair sans-impression" data-imprimer><?= e(t('releve.imprimer')) ?></button>
</section>
