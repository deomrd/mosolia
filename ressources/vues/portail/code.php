<form class="carte formulaire" method="post" action="<?= e(url('/espace-client/code')) ?>" autocomplete="off">
  <?= champ_csrf() ?>
  <h1><?= e(t('portail.mon_code')) ?></h1>
  <p class="aide"><?= e(t($oblige ? 'portail.code_obligatoire' : 'portail.code_aide')) ?></p>
  <label><?= e(t('portail.code_actuel')) ?><input name="actuel" type="password" inputmode="numeric" required autocomplete="current-password"></label>
  <label><?= e(t('portail.nouveau_code')) ?><input name="nouveau" type="password" inputmode="numeric" required pattern="\d{4,8}" autocomplete="new-password"><small><?= e(t('portail.code_format')) ?></small></label>
  <label><?= e(t('portail.confirmer_code')) ?><input name="confirmation" type="password" inputmode="numeric" required autocomplete="new-password"></label>
  <button class="bouton large"><?= e(t('commun.enregistrer')) ?></button>
  <?php if (!$oblige): ?><p><a href="<?= e(url('/espace-client')) ?>"><?= e(t('commun.annuler')) ?></a></p><?php endif; ?>
</form>
