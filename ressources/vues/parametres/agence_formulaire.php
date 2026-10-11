<?php $id = (int) $a['id']; ?>
<form method="post" action="<?= e(url($id === 0 ? '/parametres/agences/nouvelle' : '/parametres/agences/' . $id)) ?>">
<?= champ_csrf() ?>
<header class="carte tete-page">
  <div>
    <p class="fil"><a href="<?= e(url('/parametres/agences')) ?>"><?= e(t('menu.agences')) ?></a></p>
    <h1><?= e($id === 0 ? t('agences.nouvelle') : $a['nom']) ?></h1>
  </div>
  <div class="boutons">
    <a class="bouton clair" href="<?= e(url('/parametres/agences')) ?>"><?= e(t('commun.annuler')) ?></a>
    <button class="bouton"><?= e(t('commun.enregistrer')) ?></button>
  </div>
</header>
<section class="carte">
  <div class="grille">
    <label><?= e(t('agences.code')) ?><input name="code" value="<?= e($a['code']) ?>" required maxlength="10" data-majuscules><small><?= e(t('agences.code_aide')) ?></small></label>
    <label><?= e(t('agences.nom')) ?><input name="nom" value="<?= e($a['nom']) ?>" required maxlength="120" data-majuscules></label>
    <label><?= e(t('agences.adresse')) ?><input name="adresse" value="<?= e($a['adresse'] ?? '') ?>" maxlength="255" data-majuscules></label>
    <label><?= e(t('agences.telephone')) ?><input name="telephone" value="<?= e($a['telephone'] ?? '') ?>" maxlength="40" inputmode="tel"></label>
  </div>
  <label class="case"><input type="checkbox" name="actif" value="1"<?= (string) $a['actif'] === '1' ? ' checked' : '' ?>><span><?= e(t('agences.active')) ?></span></label>
</section>
</form>
