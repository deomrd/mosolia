<header class="carte tete-page">
  <div>
    <p class="fil"><a href="<?= e(url('/plateforme/support')) ?>"><?= e(t('menu.support')) ?></a> · <a href="<?= e(url('/plateforme/organisations/' . $t['organisation_id'])) ?>"><?= e($t['organisation_nom']) ?></a></p>
    <h1>#<?= e($t['numero']) ?> <?= e($t['sujet']) ?></h1>
    <p class="aide"><?= e(t('support.categorie.' . strtolower($t['categorie']))) ?> · <?= e($t['auteur_nom']) ?></p>
  </div>
  <span class="pastille"><?= e(t('support.statut.' . strtolower($t['statut']))) ?></span>
</header>
<section class="carte">
  <?php $coteOrganisation = false; require __DIR__ . '/../support/_fil.php'; ?>
  <form method="post" action="<?= e(url('/plateforme/support/' . $t['id'])) ?>">
    <?= champ_csrf() ?>
    <label><?= e(t('support.reponse')) ?><textarea name="message" rows="4"></textarea></label>
    <div class="boutons">
      <button class="bouton"><?= e(t('support.envoyer')) ?></button>
      <button class="bouton clair" name="resolu" value="1"><?= e(t('plateforme.marquer_resolu')) ?></button>
    </div>
  </form>
</section>
