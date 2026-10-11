<form method="post" action="<?= e(url('/parametres/organisation')) ?>">
<?= champ_csrf() ?>

<header class="carte tete-page">
  <div>
    <h1><?= e(t('menu.organisation')) ?></h1>
    <p class="aide"><?= e(t('organisation.aide')) ?></p>
  </div>
  <button class="bouton"><?= e(t('commun.enregistrer')) ?></button>
</header>

<section class="carte">
  <h2><?= e(t('organisation.identite')) ?></h2>
  <div class="grille">
    <label><?= e(t('organisation.nom')) ?>
      <input name="nom" value="<?= e($o['nom']) ?>" required maxlength="160" data-majuscules>
    </label>
    <label><?= e(t('organisation.nom_court')) ?>
      <?php $propose = empty($o['nom_court']) ? \AvecXY\Noyau\Noyau::instance()->serviceOrganisation()->nomCourtLibre((string) $o['nom'], (int) $o['id']) : ''; ?>
      <input name="nom_court" value="<?= e($o['nom_court'] ?? $propose) ?>" maxlength="12" pattern="[a-z0-9]{2,12}" autocapitalize="none" spellcheck="false" required>
      <small><?= e(t(empty($o['nom_court']) ? 'organisation.nom_court_a_choisir' : 'organisation.nom_court_aide')) ?></small>
    </label>
    <label><?= e(t('organisation.telephone')) ?>
      <input name="telephone" value="<?= e($o['telephone'] ?? '') ?>" maxlength="40" inputmode="tel">
    </label>
    <label><?= e(t('organisation.email')) ?>
      <input name="email" type="email" value="<?= e($o['email'] ?? '') ?>" maxlength="160">
    </label>
    <label><?= e(t('organisation.ville')) ?>
      <input name="ville_defaut" value="<?= e($o['ville_defaut'] ?? '') ?>" maxlength="80" data-majuscules>
      <small><?= e(t('organisation.ville_aide')) ?></small>
    </label>
  </div>
  <label><?= e(t('organisation.adresse')) ?>
    <textarea name="adresse" rows="2"><?= e($o['adresse'] ?? '') ?></textarea>
  </label>
</section>

<section class="carte">
  <h2><?= e(t('organisation.reglages')) ?></h2>
  <div class="grille">
    <label><?= e(t('organisation.devise')) ?>
      <input value="<?= e($o['devise_code']) ?>" disabled>
      <small><?= e(t('organisation.devise_aide')) ?></small>
    </label>
    <label><?= e(t('organisation.langue')) ?>
      <select name="langue">
        <option value="fr"<?= $o['langue'] === 'fr' ? ' selected' : '' ?>>Français</option>
        <option value="en"<?= $o['langue'] === 'en' ? ' selected' : '' ?>>English</option>
      </select>
    </label>
    <label><?= e(t('organisation.fuseau')) ?>
      <select name="fuseau">
        <?php foreach ($fuseaux as $f): ?>
          <option<?= $o['fuseau'] === $f ? ' selected' : '' ?>><?= e($f) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label><?= e(t('organisation.couleur')) ?>
      <input name="couleur_principale" type="color" value="<?= e($o['couleur_principale'] ?: '#176B5F') ?>">
    </label>
  </div>
</section>

<section class="carte">
  <h2><?= e(t('organisation.doublons')) ?></h2>
  <p class="aide"><?= e(t('organisation.doublons_aide')) ?></p>
  <div class="grille">
    <label><?= e(t('organisation.doublon_portee')) ?>
      <select name="doublon_portee">
        <?php foreach (\AvecXY\Application\ServiceClients::PORTEES as $v): ?>
          <option value="<?= e($v) ?>"<?= $doublonPortee === $v ? ' selected' : '' ?>><?= e(t('organisation.portee.' . strtolower($v))) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label><?= e(t('organisation.doublon_action')) ?>
      <select name="doublon_action">
        <?php foreach (\AvecXY\Application\ServiceClients::ACTIONS as $v): ?>
          <option value="<?= e($v) ?>"<?= $doublonAction === $v ? ' selected' : '' ?>><?= e(t('organisation.action.' . strtolower($v))) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>
</section>
</form>

<?php if (peut('espace_client.gerer')): ?>
<section class="carte">
  <h2><?= e(t('portail.titre')) ?></h2>
  <p class="aide"><?= e(t('portail.renvoi')) ?></p>
  <div class="boutons"><a class="bouton clair" href="<?= e(url('/parametres/espace-client')) ?>"><?= e(t('menu.espace_client')) ?></a></div>
</section>
<?php endif; ?>
