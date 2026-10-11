<header class="carte tete-page">
  <div>
    <p class="fil"><a href="<?= e(url('/support')) ?>"><?= e(t('menu.support')) ?></a></p>
    <h1>#<?= e($t['numero']) ?> <?= e($t['sujet']) ?></h1>
  </div>
  <span class="pastille"><?= e(t('support.statut.' . strtolower($t['statut']))) ?></span>
</header>
<section class="carte">
  <?php $coteOrganisation = true; require __DIR__ . '/_fil.php'; ?>
  <?php if ($t['statut'] !== 'FERME'): ?>
  <form method="post" action="<?= e(url('/support/' . $t['id'])) ?>">
    <?= champ_csrf() ?>
    <label><?= e(t('support.reponse')) ?><textarea name="message" rows="4"></textarea></label>
    <div class="boutons">
      <button class="bouton"><?= e(t('support.envoyer')) ?></button>
      <button class="bouton clair" name="fermer" value="1"><?= e(t('support.fermer')) ?></button>
    </div>
  </form>
  <?php endif; ?>
</section>
