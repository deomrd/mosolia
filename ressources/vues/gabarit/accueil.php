<?php
/** @var string $contenu */
$noyau = \AvecXY\Noyau\Noyau::instance();
$langue = $noyau->traducteur->langue;
?>
<!doctype html>
<html lang="<?= e($langue) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#176B5F">
<meta name="description" content="<?= e(t('accueil.description')) ?>">
<title><?= e($noyau->config['nom']) ?> · <?= e(t('accueil.titre_page')) ?></title>
<link rel="manifest" href="<?= e(url('manifest.webmanifest')) ?>">
<link rel="icon" href="<?= e(url('assets/icone.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(ressource('assets/css/application.css')) ?>">
</head>
<body class="page-accueil">
<a class="saut" href="#contenu"><?= e(t('commun.aller_au_contenu')) ?></a>
<header class="ac-barre">
  <a class="marque" href="<?= e(url('/')) ?>"><img src="<?= e(url('assets/icone.svg')) ?>" alt="" width="32" height="32"> <?= e($noyau->config['nom']) ?></a>
  <nav aria-label="<?= e(t('commun.menu')) ?>">
    <a href="#fonctions"><?= e(t('accueil.nav_fonctions')) ?></a>
    <a href="#forfaits"><?= e(t('accueil.nav_forfaits')) ?></a>
    <a href="<?= e(url('/espace-client/connexion')) ?>"><?= e(t('portail.titre')) ?></a>
  </nav>
  <form method="post" action="<?= e(url('/langue')) ?>" class="langues" aria-label="<?= e(t('commun.langue')) ?>">
    <?= champ_csrf() ?><input type="hidden" name="retour" value="/">
    <button name="langue" value="fr" class="<?= $langue === 'fr' ? 'choisi' : '' ?>">FR</button>
    <button name="langue" value="en" class="<?= $langue === 'en' ? 'choisi' : '' ?>">EN</button>
  </form>
  <a class="bouton" href="<?= e(url('/connexion')) ?>"><?= e(t('connexion.bouton')) ?></a>
</header>
<main id="contenu"><?= $contenu ?></main>
<footer class="ac-pied">
  <span class="marque"><?= e($noyau->config['nom']) ?></span>
  <span><?= e(t('accueil.pied')) ?></span>
  <a href="<?= e(url('/connexion')) ?>"><?= e(t('connexion.bouton')) ?></a>
</footer>
<script src="<?= e(ressource('assets/js/application.js')) ?>"></script>
</body>
</html>
