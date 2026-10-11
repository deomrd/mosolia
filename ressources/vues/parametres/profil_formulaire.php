<?php
$id = (int) $profil['id'];
$admin = (int) $profil['est_admin'] === 1;
$action = $id === 0 ? '/parametres/profils/nouveau' : '/parametres/profils/' . $id;
?>
<form method="post" action="<?= e(url($action)) ?>">
<?= champ_csrf() ?>

<header class="carte tete-page">
  <div>
    <p class="fil"><a href="<?= e(url('/parametres/profils')) ?>"><?= e(t('menu.profils')) ?></a></p>
    <h1><?= e($id === 0 ? t('profils.nouveau') : $profil['nom']) ?></h1>
  </div>
  <div class="boutons">
    <a class="bouton clair" href="<?= e(url('/parametres/profils')) ?>"><?= e(t('commun.annuler')) ?></a>
    <button class="bouton"><?= e(t('commun.enregistrer')) ?></button>
  </div>
</header>

<section class="carte">
  <div class="grille">
    <label><?= e(t('profils.nom')) ?>
      <input name="nom" value="<?= e($profil['nom']) ?>" required maxlength="80" data-majuscules>
    </label>
    <?php if (!$admin): ?>
    <label class="case">
      <input type="checkbox" name="est_agent" value="1"<?= (int) $profil['est_agent'] === 1 ? ' checked' : '' ?>>
      <span><?= e(t('profils.est_recolteur')) ?><small><?= e(t('profils.est_recolteur_aide')) ?></small></span>
    </label>
    <?php endif; ?>
  </div>
</section>

<?php if ($admin): ?>
  <section class="carte"><p><?= e(t('profils.admin_tous_droits')) ?></p></section>
<?php else: ?>
  <?php foreach ($catalogue as $module => $codes): ?>
  <section class="carte">
    <div class="tete-section">
      <h2><?= e(t('module.' . $module)) ?></h2>
      <button type="button" class="lien" data-tout-cocher><?= e(t('commun.tout_cocher')) ?></button>
    </div>
    <div class="droits">
      <?php foreach ($codes as $code): ?>
      <label class="case">
        <input type="checkbox" name="droits[<?= e($code) ?>]" value="1"<?= in_array($code, $droits, true) ? ' checked' : '' ?>>
        <span><?= e(t('droit.' . $code)) ?></span>
      </label>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endforeach; ?>
<?php endif; ?>
</form>
