<form class="carte formulaire" method="post" action="<?= e(url('/espace-client/connexion')) ?>">
  <?= champ_csrf() ?>
  <h1><?= e(t('portail.titre')) ?></h1>
  <p class="aide"><?= e(t('portail.connexion_aide')) ?></p>
  <label><?= e(t('portail.code_client')) ?>
    <input name="code" value="<?= e($code) ?>" required autocomplete="username" autocapitalize="characters" spellcheck="false" placeholder="ABCD-1" data-majuscules>
  </label>
  <label><?= e(t('portail.code_secret')) ?>
    <input name="secret" type="password" inputmode="numeric" required autocomplete="current-password">
  </label>
  <button class="bouton large"><?= e(t('connexion.bouton')) ?></button>
  <p class="aide"><?= e(t('portail.oubli')) ?></p>
</form>
