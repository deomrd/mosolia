<?php
/** @var string $contenu */
$noyau = \AvecXY\Noyau\Noyau::instance();
$langue = $noyau->traducteur->langue;
$retour = preg_match('#^/[A-Za-z0-9/_-]*$#', $noyau->requete->chemin) ? $noyau->requete->chemin : '/connexion';
?>
<!doctype html>
<html lang="<?= e($langue) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#176B5F">
<title><?= e($noyau->config['nom']) ?></title>
<link rel="manifest" href="<?= e(url('manifest.webmanifest')) ?>">
<link rel="icon" href="<?= e(url('assets/icone.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(ressource('assets/css/application.css')) ?>">
</head>
<body class="page-publique<?= dans_application() ? ' dans-application' : '' ?>">
<div class="vitrine">
<main class="vitrine-form">
  <div class="publique-tete">
    <a class="marque" href="<?= e(url('/')) ?>"><img src="<?= e(url('assets/icone.svg')) ?>" alt="" width="32" height="32"> <?= e($noyau->config['nom']) ?></a>
    <form method="post" action="<?= e(url('/langue')) ?>" class="langues" aria-label="<?= e(t('commun.langue')) ?>">
      <?= champ_csrf() ?>
      <input type="hidden" name="retour" value="<?= e($retour) ?>">
      <button name="langue" value="fr" class="<?= $langue === 'fr' ? 'choisi' : '' ?>"<?= $langue === 'fr' ? ' aria-pressed="true"' : '' ?>>FR</button>
      <button name="langue" value="en" class="<?= $langue === 'en' ? 'choisi' : '' ?>"<?= $langue === 'en' ? ' aria-pressed="true"' : '' ?>>EN</button>
    </form>
  </div>
  <?php foreach ($noyau->session->messages() as $m): ?>
    <div class="message message-<?= e($m['type']) ?>" role="<?= $m['type'] === 'erreur' ? 'alert' : 'status' ?>"><?= e($m['texte']) ?></div>
  <?php endforeach; ?>
  <?= $contenu ?>
</main>
<?php require __DIR__ . '/_visuel.php'; ?>
</div>
<script src="<?= e(ressource('assets/js/application.js')) ?>"></script>
</body>
</html>
