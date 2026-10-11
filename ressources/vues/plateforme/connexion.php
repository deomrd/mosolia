<form class="carte formulaire" method="post" action="<?= e(url('/plateforme/connexion')) ?>">
  <?= champ_csrf() ?>
  <h1><?= e(t('plateforme.titre')) ?></h1>
  <p class="aide"><?= e(t('plateforme.connexion_aide')) ?></p>
  <label><?= e(t('plateforme.email')) ?><input name="email" type="email" value="<?= e($email) ?>" required autocomplete="username"></label>
  <label><?= e(t('connexion.mot_de_passe')) ?><input name="mot_de_passe" type="password" required autocomplete="current-password"></label>
  <button class="bouton large"><?= e(t('connexion.bouton')) ?></button>
</form>
