<?php
$noyau = \AvecXY\Noyau\Noyau::instance();
$pin = ($mode ?? 'mot_de_passe') === 'pin';
?>
<div class="connexion" data-connexion data-defi="<?= e(url('/connexion/cle/defi')) ?>" data-envoi="<?= e(url('/connexion/cle')) ?>" data-jeton="<?= e($noyau->session->jetonCsrf()) ?>">
  <h1><?= e(t('connexion.titre')) ?></h1>
  <p class="aide"><?= e(t('connexion.aide')) ?></p>

  <form class="formulaire" method="post" action="<?= e(url('/connexion')) ?>" data-zone="mot_de_passe"<?= $pin ? ' hidden' : '' ?>>
    <?= champ_csrf() ?>
    <label><?= e(t('connexion.identifiant')) ?>
      <input name="identifiant" value="<?= e($identifiant) ?>" required autocomplete="username" autocapitalize="none" spellcheck="false" data-identifiant>
    </label>
    <label><?= e(t('connexion.mot_de_passe')) ?>
      <input name="mot_de_passe" type="password" required autocomplete="current-password">
    </label>
    <button class="bouton large"><?= e(t('connexion.bouton')) ?></button>
    <a class="bouton clair large" href="<?= e(url('/connexion?mode=pin')) ?>" data-basculer="pin"><?= e(t('connexion.avec_pin')) ?></a>
  </form>

  <form class="formulaire" method="post" action="<?= e(url('/connexion/pin')) ?>" data-zone="pin"<?= $pin ? '' : ' hidden' ?>>
    <?= champ_csrf() ?>
    <label><?= e(t('connexion.identifiant')) ?>
      <input name="identifiant" value="<?= e($identifiant) ?>" required autocomplete="username" autocapitalize="none" spellcheck="false" data-identifiant>
    </label>
    <label><?= e(t('pin.titre')) ?>
      <input name="pin" type="password" inputmode="numeric" pattern="\d{4,6}" maxlength="6" required autocomplete="off" class="champ-pin">
    </label>
    <button class="bouton large"><?= e(t('connexion.bouton')) ?></button>
    <a class="bouton clair large" href="<?= e(url('/connexion')) ?>" data-basculer="mot_de_passe"><?= e(t('connexion.avec_mot_de_passe')) ?></a>
  </form>

  <button type="button" class="bouton clair large" data-cle-connexion data-echec="<?= e(t('cle.echec_connexion')) ?>" hidden>
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M7 11a5 5 0 0 1 10 0v2M12 11v6M9 14v3M15 14v2M5 12v1M19 12v3"/></svg>
    <?= e(t('connexion.avec_empreinte')) ?>
  </button>
  <p class="message message-erreur" data-cle-erreur hidden role="alert"></p>

  <p class="connexion-liens"><a href="<?= e(url('/espace-client/connexion')) ?>"><?= e(t('connexion.espace_client')) ?></a></p>
</div>
