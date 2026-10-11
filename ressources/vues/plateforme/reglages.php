<header class="carte tete-page"><div><h1><?= e(t('plateforme.reglages')) ?></h1></div></header>
<section class="carte<?= $enAttente ? ' alerte' : '' ?>">
  <h2><?= e(t('installation.mise_a_jour')) ?></h2>
  <?php if ($enAttente): ?>
    <p><?= e(t('installation.a_appliquer', ['n' => count($enAttente)])) ?></p>
    <form method="post" action="<?= e(url('/plateforme/mise-a-jour')) ?>" data-confirmer="<?= e(t('installation.confirmer_mise_a_jour')) ?>"><?= champ_csrf() ?><button class="bouton"><?= e(t('installation.mettre_a_jour')) ?></button></form>
  <?php else: ?><p class="aide"><?= e(t('installation.deja_a_jour')) ?> <?= e(t('installation.mise_a_jour_aide')) ?></p><?php endif; ?>
</section>

<div class="deux-colonnes">
  <form class="carte" method="post" action="<?= e(url('/plateforme/reglages')) ?>">
    <?= champ_csrf() ?>
    <h2><?= e(t('plateforme.delais')) ?></h2>
    <label><?= e(t('plateforme.jours_rappel')) ?><input name="rappel" value="<?= e($rappel) ?>" inputmode="numeric" required><small><?= e(t('plateforme.jours_rappel_aide')) ?></small></label>
    <label><?= e(t('plateforme.jours_grace')) ?><input name="grace" value="<?= e($grace) ?>" inputmode="numeric" required><small><?= e(t('plateforme.jours_grace_aide')) ?></small></label>
    <div class="boutons"><button class="bouton"><?= e(t('commun.enregistrer')) ?></button></div>
  </form>
  <form class="carte" method="post" action="<?= e(url('/plateforme/adresse-mobile')) ?>">
    <?= champ_csrf() ?>
    <h2><?= e(t('plateforme.adresse_mobile')) ?></h2>
    <label><?= e(t('plateforme.adresse_mobile_champ')) ?><input name="adresse" value="<?= e($adresseMobile) ?>" placeholder="https://mosolia.boutixy.com" inputmode="url" autocapitalize="none" spellcheck="false"><small><?= e(t('plateforme.adresse_mobile_aide')) ?></small></label>
    <p class="espace"><button class="bouton"><?= e(t('commun.enregistrer')) ?></button></p>
  </form>
  <form class="carte" method="post" action="<?= e(url('/plateforme/mot-de-passe')) ?>" autocomplete="off">
    <?= champ_csrf() ?>
    <h2><?= e(t('compte.mot_de_passe')) ?></h2>
    <label><?= e(t('compte.mot_de_passe_actuel')) ?><input name="actuel" type="password" required autocomplete="current-password"></label>
    <label><?= e(t('plateforme.nouveau_mot_de_passe')) ?><input name="nouveau" type="password" required minlength="10" autocomplete="new-password"></label>
    <div class="boutons"><button class="bouton"><?= e(t('commun.enregistrer')) ?></button></div>
  </form>
</div>

<form class="carte" method="post" action="<?= e(url('/plateforme/contact')) ?>">
  <?= champ_csrf() ?>
  <h2><?= e(t('plateforme.contact')) ?></h2>
  <p class="aide"><?= e(t('plateforme.contact_aide')) ?></p>
  <div class="grille">
    <label><?= e(t('plateforme.email')) ?><input name="email" type="email" value="<?= e($contact['email']) ?>" maxlength="160"></label>
    <label><?= e(t('organisation.telephone')) ?><input name="telephone" value="<?= e($contact['telephone']) ?>" maxlength="40" inputmode="tel"></label>
    <label>WhatsApp<input name="whatsapp" value="<?= e($contact['whatsapp']) ?>" maxlength="40" inputmode="tel"></label>
  </div>
  <div class="boutons"><button class="bouton"><?= e(t('commun.enregistrer')) ?></button></div>
</form>
