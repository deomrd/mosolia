<?php
$id = (int) $r['id'];
$action = $id === 0 ? '/parametres/recolteurs/nouveau' : '/parametres/recolteurs/' . $id;
?>
<form method="post" action="<?= e(url($action)) ?>">
<?= champ_csrf() ?>

<header class="carte tete-page">
  <div>
    <p class="fil"><a href="<?= e(url('/parametres/recolteurs')) ?>"><?= e(t('menu.recolteurs')) ?></a></p>
    <h1><?= e($id === 0 ? t('recolteurs.nouveau') : $r['nom']) ?></h1>
  </div>
  <div class="boutons">
    <a class="bouton clair" href="<?= e(url('/parametres/recolteurs')) ?>"><?= e(t('commun.annuler')) ?></a>
    <button class="bouton"><?= e(t('commun.enregistrer')) ?></button>
  </div>
</header>

<section class="carte">
  <div class="grille">
    <label><?= e(t('recolteurs.code')) ?>
      <input value="<?= e($id === 0 ? t('recolteurs.code_auto') : $r['code']) ?>" disabled>
      <small><?= e(t('recolteurs.code_aide')) ?></small>
    </label>
    <label><?= e(t('recolteurs.nom')) ?>
      <input name="nom" value="<?= e($r['nom']) ?>" required maxlength="160" data-majuscules>
    </label>
    <label><?= e(t('recolteurs.telephone')) ?>
      <input name="telephone" value="<?= e($r['telephone'] ?? '') ?>" maxlength="40" inputmode="tel">
    </label>
    <label><?= e(t('utilisateurs.statut')) ?>
      <select name="statut">
        <option value="ACTIF"><?= e(t('statut.actif')) ?></option>
        <option value="INACTIF"<?= ($r['statut'] ?? '') === 'INACTIF' ? ' selected' : '' ?>><?= e(t('statut.inactif')) ?></option>
      </select>
    </label>
    <label><?= e(t('recolteurs.adresse')) ?>
      <input name="adresse" value="<?= e($r['adresse'] ?? '') ?>" maxlength="255" data-majuscules>
    </label>
    <?php $agenceChoisie = $r['agence_id'] ?? 0; $libelle = t('agences.agence'); $aideAgence = t('agences.recolteur_aide'); $nomChamp = 'agence_id'; require __DIR__ . '/../commun/_choix_agence.php'; ?>
    <label><?= e(t('recolteurs.compte')) ?>
      <select name="utilisateur_id">
        <option value="0"><?= e(t('recolteurs.sans_compte')) ?></option>
        <?php foreach ($comptes as $u): ?>
          <option value="<?= e($u['id']) ?>"<?= (int) $utilisateurId === (int) $u['id'] ? ' selected' : '' ?>><?= e($u['nom'] . ' · ' . $u['profil_nom']) ?></option>
        <?php endforeach; ?>
      </select>
      <small><?= e(t('recolteurs.compte_aide')) ?></small>
    </label>
  </div>
</section>
</form>
