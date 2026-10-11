<?php
$id = (int) $utilisateur['id'];
$action = $id === 0 ? '/parametres/utilisateurs/nouveau' : '/parametres/utilisateurs/' . $id;
?>
<form method="post" action="<?= e(url($action)) ?>" data-droits-profils='<?= e(json_encode($droitsParProfil)) ?>' data-texte-oui="<?= e(t('utilisateurs.regle_profil_oui')) ?>" data-texte-non="<?= e(t('utilisateurs.regle_profil_non')) ?>">
<?= champ_csrf() ?>

<header class="carte tete-page">
  <div>
    <p class="fil"><a href="<?= e(url('/parametres/utilisateurs')) ?>"><?= e(t('menu.utilisateurs')) ?></a></p>
    <h1><?= e($id === 0 ? t('utilisateurs.nouveau') : $utilisateur['nom']) ?></h1>
  </div>
  <div class="boutons">
    <a class="bouton clair" href="<?= e(url('/parametres/utilisateurs')) ?>"><?= e(t('commun.annuler')) ?></a>
    <button class="bouton"><?= e(t('commun.enregistrer')) ?></button>
  </div>
</header>

<section class="carte">
  <h2><?= e(t('utilisateurs.compte')) ?></h2>
  <div class="grille">
    <label><?= e(t('utilisateurs.nom')) ?>
      <input name="nom" value="<?= e($utilisateur['nom']) ?>" required maxlength="160" data-majuscules>
    </label>
    <label><?= e(t('utilisateurs.identifiant')) ?>
      <?php $court = (string) (\AvecXY\Noyau\Noyau::instance()->contexte->organisation['nom_court'] ?? ''); ?>
      <input name="identifiant" value="<?= e($utilisateur['identifiant']) ?>"<?= $id === 0 && $court !== '' ? ' placeholder="' . e(t('utilisateurs.identifiant_auto', ['court' => $court])) . '"' : ' required' ?> maxlength="160" autocapitalize="none" spellcheck="false">
      <small><?= e($id === 0 && $court !== '' ? t('utilisateurs.identifiant_auto_aide', ['court' => $court]) : t('utilisateurs.identifiant_aide')) ?></small>
      <?php if ($id === 0 && $court === ''): ?><small class="erreur-champ"><?= e(t('utilisateurs.nom_court_requis')) ?> <a href="<?= e(url('/parametres/organisation')) ?>"><?= e(t('menu.organisation')) ?></a></small><?php endif; ?>
    </label>
    <label><?= e(t('utilisateurs.profil')) ?>
      <select name="role_id" required data-profil>
        <option value=""><?= e(t('commun.choisir')) ?></option>
        <?php foreach ($profils as $p): ?>
          <option value="<?= e($p['id']) ?>"<?= (int) $utilisateur['role_id'] === (int) $p['id'] ? ' selected' : '' ?>><?= e($p['nom']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <?php $agenceChoisie = $utilisateur['agence_id'] ?? 0; $libelle = t('agences.agence'); $aideAgence = t('agences.utilisateur_aide'); $nomChamp = 'agence_id'; require __DIR__ . '/../commun/_choix_agence.php'; ?>
    <label><?= e(t('utilisateurs.statut')) ?>
      <select name="statut">
        <option value="ACTIF"><?= e(t('statut.actif')) ?></option>
        <option value="INACTIF"<?= ($utilisateur['statut'] ?? '') === 'INACTIF' ? ' selected' : '' ?>><?= e(t('statut.inactif')) ?></option>
      </select>
    </label>
    <label><?= e(t('commun.langue')) ?>
      <select name="langue">
        <option value=""><?= e(t('utilisateurs.langue_organisation')) ?></option>
        <option value="fr"<?= ($utilisateur['langue'] ?? '') === 'fr' ? ' selected' : '' ?>>Français</option>
        <option value="en"<?= ($utilisateur['langue'] ?? '') === 'en' ? ' selected' : '' ?>>English</option>
      </select>
    </label>
    <label><?= e(t($id === 0 ? 'utilisateurs.mot_de_passe_initial' : 'utilisateurs.nouveau_mot_de_passe')) ?>
      <input name="mot_de_passe" type="password" autocomplete="new-password" minlength="8">
      <small><?= e(t($id === 0 ? 'utilisateurs.mot_de_passe_initial_aide' : 'utilisateurs.nouveau_mot_de_passe_aide')) ?></small>
    </label>
  </div>
</section>

<section class="carte">
  <h2><?= e(t('utilisateurs.regles')) ?></h2>
  <p class="aide"><?= e(t('utilisateurs.regles_aide')) ?></p>
  <?php foreach ($catalogue as $module => $codes):
      $nb = count(array_intersect($codes, array_keys($regles)));
  ?>
  <details class="module"<?= $nb > 0 ? ' open' : '' ?>>
    <summary><?= e(t('module.' . $module)) ?><?php if ($nb > 0): ?> <span class="pastille"><?= e($nb) ?></span><?php endif; ?></summary>
    <div class="regles">
      <?php foreach ($codes as $code): $valeur = $regles[$code] ?? ''; ?>
      <label class="regle">
        <span><?= e(t('droit.' . $code)) ?></span>
        <select name="regles[<?= e($code) ?>]" data-regle="<?= e($code) ?>">
          <option value=""><?= e(t('utilisateurs.regle_profil')) ?></option>
          <option value="ACCORDE"<?= $valeur === 'ACCORDE' ? ' selected' : '' ?>><?= e(t('utilisateurs.regle_accorde')) ?></option>
          <option value="REFUSE"<?= $valeur === 'REFUSE' ? ' selected' : '' ?>><?= e(t('utilisateurs.regle_refuse')) ?></option>
        </select>
      </label>
      <?php endforeach; ?>
    </div>
  </details>
  <?php endforeach; ?>
</section>
</form>
