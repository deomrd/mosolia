<form method="post" action="<?= e(url('/clients/' . $client['id'] . '/modifier')) ?>">
<?= champ_csrf() ?>
<header class="carte tete-page">
  <div>
    <p class="fil"><a href="<?= e(url('/clients/' . $client['id'])) ?>"><?= e($client['code']) ?></a></p>
    <h1><?= e($client['nom']) ?></h1>
  </div>
  <div class="boutons">
    <a class="bouton clair" href="<?= e(url('/clients/' . $client['id'])) ?>"><?= e(t('commun.annuler')) ?></a>
    <button class="bouton"><?= e(t('commun.enregistrer')) ?></button>
  </div>
</header>
<section class="carte">
  <div class="grille">
    <label><?= e(t('clients.nom')) ?><input name="nom" value="<?= e($client['nom']) ?>" required maxlength="160" data-majuscules></label>
    <label><?= e(t('clients.telephone')) ?><input name="telephone" value="<?= e($client['telephone'] ?? '') ?>" maxlength="40" inputmode="tel"></label>
    <label><?= e(t('clients.adresse')) ?><input name="adresse" value="<?= e($client['adresse'] ?? '') ?>" maxlength="255" data-majuscules></label>
    <label><?= e(t('clients.activite')) ?><input name="activite" value="<?= e($client['activite'] ?? '') ?>" maxlength="160" data-majuscules></label>
    <label><?= e(t('utilisateurs.statut')) ?>
      <select name="statut">
        <option value="ACTIF"><?= e(t('statut.actif')) ?></option>
        <option value="INACTIF"<?= $client['statut'] === 'INACTIF' ? ' selected' : '' ?>><?= e(t('statut.inactif')) ?></option>
      </select>
    </label>
  </div>
</section>
<?php $d = $client; require __DIR__ . '/_dossier.php'; ?>
</form>
