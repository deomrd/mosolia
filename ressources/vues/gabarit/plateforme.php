<?php
/** @var string $contenu */
$noyau = \AvecXY\Noyau\Noyau::instance();
$admin = $noyau->adminPlateforme;
$chemin = $noyau->requete->chemin;
$liens = ['/plateforme' => 'plateforme.organisations', '/plateforme/forfaits' => 'plateforme.forfaits', '/plateforme/support' => 'menu.support', '/plateforme/reglages' => 'plateforme.reglages'];
?>
<!doctype html>
<html lang="<?= e($noyau->traducteur->langue) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($noyau->config['nom']) ?> · <?= e(t('plateforme.titre')) ?></title>
<link rel="icon" href="<?= e(url('assets/icone.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(ressource('assets/css/application.css')) ?>">
</head>
<?php if ($admin === null): ?>
<body class="page-publique">
<div class="vitrine">
<main class="vitrine-form" id="contenu">
  <div class="publique-tete"><a class="marque" href="<?= e(url('/')) ?>"><img src="<?= e(url('assets/icone.svg')) ?>" alt="" width="32" height="32"> <?= e($noyau->config['nom']) ?></a></div>
  <?php foreach ($noyau->session->messages() as $m): ?>
    <div class="message message-<?= e($m['type']) ?>" role="<?= $m['type'] === 'erreur' ? 'alert' : 'status' ?>"><?= e($m['texte']) ?></div>
  <?php endforeach; ?>
  <?= $contenu ?>
</main>
<?php require __DIR__ . '/_visuel.php'; ?>
</div>
<?php else: ?>
<body class="page-plateforme">
<a class="saut" href="#contenu"><?= e(t('commun.aller_au_contenu')) ?></a>
<div class="appli">
<div class="voile" data-menu-fermer></div>
<nav class="menu menu-sombre" id="menu" aria-label="<?= e(t('commun.menu')) ?>">
  <div class="menu-tete">
    <span class="marque"><?= e($noyau->config['nom']) ?></span>
    <button type="button" class="bouton-icone menu-fermer" data-menu-fermer aria-label="<?= e(t('commun.fermer_menu')) ?>">&times;</button>
  </div>
  <p class="menu-etiquette"><?= e(t('plateforme.titre')) ?></p>
  <?php foreach ($liens as $lien => $cle): $actif = $lien === '/plateforme' ? ($chemin === '/plateforme' || str_starts_with($chemin, '/plateforme/organisations')) : str_starts_with($chemin, $lien); ?>
    <a class="menu-lien<?= $actif ? ' actif' : '' ?>" href="<?= e(url($lien)) ?>"<?= $actif ? ' aria-current="page"' : '' ?>><?= e(t($cle)) ?></a>
  <?php endforeach; ?>
  <a class="menu-lien" href="<?= e(url('/bienvenue')) ?>"><?= e(t('plateforme.voir_accueil')) ?></a>
  <form class="menu-pied" method="post" action="<?= e(url('/plateforme/deconnexion')) ?>">
    <?= champ_csrf() ?>
    <button type="submit" class="menu-lien deconnexion"><?= e(t('menu.deconnexion')) ?></button>
  </form>
</nav>
<div class="principal">
  <header class="barre">
    <button type="button" class="bouton-icone" data-menu-ouvrir aria-controls="menu" aria-expanded="false" aria-label="<?= e(t('commun.ouvrir_menu')) ?>">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
    <div class="barre-droite">
      <?php $nonLues = $noyau->notifications()->nonLues('PLATEFORME', 0); ?>
      <a class="cloche" href="<?= e(url('/plateforme/notifications')) ?>" aria-label="<?= e(t('notif.titre')) ?><?= $nonLues > 0 ? ' (' . $nonLues . ')' : '' ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg><?php if ($nonLues > 0): ?><span class="compteur"><?= e($nonLues > 99 ? '99+' : $nonLues) ?></span><?php endif; ?></a>
      <a class="identite" href="<?= e(url('/plateforme/reglages')) ?>"><strong><?= e($admin['nom']) ?></strong><small><?= e($admin['email']) ?></small></a>
    </div>
  </header>
  <main class="contenu" id="contenu">
    <?php if ($chemin !== '/plateforme/reglages' && $noyau->serviceInstallation()->enAttente()): ?>
      <div class="message message-info" role="status"><a href="<?= e(url('/plateforme/reglages')) ?>"><?= e(t('installation.bandeau')) ?></a></div>
    <?php endif; ?>
    <?php foreach ($noyau->session->messages() as $m): ?>
      <div class="message message-<?= e($m['type']) ?>" role="<?= $m['type'] === 'erreur' ? 'alert' : 'status' ?>"><?= e($m['texte']) ?></div>
    <?php endforeach; ?>
    <?= $contenu ?>
  </main>
</div>
</div>
<?php endif; ?>
<script src="<?= e(ressource('assets/js/application.js')) ?>"></script>
</body>
</html>
