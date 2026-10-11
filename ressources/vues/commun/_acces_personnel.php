<?php /** @var array{nom:string,identifiant:string,mot_de_passe:string} $identifiants */ ?>
<dialog class="fenetre" data-ouvrir aria-labelledby="titre-acces">
  <div class="fenetre-corps">
    <h2 id="titre-acces"><?= e(t('utilisateurs.acces_titre')) ?></h2>
    <p><?= e(t('portail.identifiants_aide', ['nom' => $identifiants['nom']])) ?></p>
    <dl class="identifiants">
      <dt><?= e(t('utilisateurs.identifiant')) ?></dt><dd class="petit"><?= e($identifiants['identifiant']) ?></dd>
      <dt><?= e(t('utilisateurs.mot_de_passe_provisoire')) ?></dt><dd class="petit"><?= e($identifiants['mot_de_passe']) ?></dd>
    </dl>
    <p class="aide"><?= e(t('utilisateurs.acces_note', ['adresse' => url_absolue('/connexion')])) ?></p>
    <div class="boutons sans-impression">
      <button type="button" class="bouton" data-fermer-fenetre><?= e(t('portail.identifiants_note_ok')) ?></button>
      <button type="button" class="bouton clair" data-imprimer><?= e(t('commun.imprimer')) ?></button>
    </div>
  </div>
</dialog>
