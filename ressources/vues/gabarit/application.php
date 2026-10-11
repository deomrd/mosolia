<?php
/** @var string $contenu */
$noyau = \AvecXY\Noyau\Noyau::instance();
$c = $noyau->contexte;
$chemin = $noyau->requete->chemin;
$menu = require $noyau->racine . '/config/menu.php';
$langue = $noyau->traducteur->langue;
$etat = $c->abonnement;
$couleur = $c->organisation['couleur_principale'] ?? null;
?>
<!doctype html>
<html lang="<?= e($langue) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="<?= e($couleur ?: '#176B5F') ?>">
<title><?= e(($titre ?? '') !== '' ? $titre . ' · ' : '') ?><?= e($c->organisation['nom']) ?></title>
<link rel="manifest" href="<?= e(url('manifest.webmanifest')) ?>">
<link rel="icon" href="<?= e(url('assets/icone.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(ressource('assets/css/application.css')) ?>">
<?php if ($couleur): ?><style>:root{--vert:<?= e($couleur) ?>}</style><?php endif; ?>
</head>
<body<?= dans_application() ? ' class="dans-application"' : '' ?>>
<a class="saut" href="#contenu"><?= e(t('commun.aller_au_contenu')) ?></a>
<div class="appli">
<div class="voile" data-menu-fermer></div>

<nav class="menu" id="menu" aria-label="<?= e(t('commun.menu')) ?>">
  <div class="menu-tete">
    <span class="marque"><?= e($c->organisation['nom']) ?></span>
    <button type="button" class="bouton-icone menu-fermer" data-menu-fermer aria-label="<?= e(t('commun.fermer_menu')) ?>">&times;</button>
  </div>

  <a class="menu-lien<?= $chemin === '/' ? ' actif' : '' ?>" href="<?= e(url('/')) ?>"<?= $chemin === '/' ? ' aria-current="page"' : '' ?>><?= e(t('menu.tableau')) ?></a>

  <?php foreach ($menu as $groupe):
      if (isset($groupe['module']) && !$c->module($groupe['module'])) { continue; }
      $elements = array_filter($groupe['elements'], static fn ($el) => (!isset($el['droit']) || array_filter((array) $el['droit'], [$c, 'peut'])) && (!isset($el['module']) || $c->module($el['module'])));
      if (!$elements) { continue; }
      $contientActif = false;
      foreach ($elements as $el) {
          if (isset($el['chemin']) && ($chemin === $el['chemin'] || str_starts_with($chemin, $el['chemin'] . '/'))) { $contientActif = true; }
      }
  ?>
  <div class="groupe" data-groupe="<?= e($groupe['cle']) ?>"<?= $contientActif ? ' data-actif' : '' ?>>
    <button type="button" class="groupe-titre" aria-expanded="<?= $contientActif ? 'true' : 'false' ?>" aria-controls="groupe-<?= e($groupe['cle']) ?>">
      <span><?= e(t('menu.groupe.' . $groupe['cle'])) ?></span>
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
    </button>
    <div class="groupe-corps" id="groupe-<?= e($groupe['cle']) ?>"<?= $contientActif ? '' : ' hidden' ?>>
      <?php foreach ($elements as $el): ?>
        <?php if (isset($el['chemin'])): $actif = $chemin === $el['chemin'] || str_starts_with($chemin, $el['chemin'] . '/'); ?>
          <a class="menu-lien sous<?= $actif ? ' actif' : '' ?>" href="<?= e(url($el['chemin'])) ?>"<?= $actif ? ' aria-current="page"' : '' ?>><?= e(t('menu.' . $el['cle'])) ?></a>
        <?php else: ?>
          <span class="menu-lien sous bientot"><?= e(t('menu.' . $el['cle'])) ?> <small><?= e(t('commun.bientot')) ?></small></span>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>

  <?php if (dans_application() && \AvecXY\Application\ServiceMobile::profil($c) === 'LES_DEUX'): ?>
    <?php /* l'application Android intercepte ce lien et revient a la tournee hors ligne */ ?>
    <a class="menu-lien" href="<?= e(url('/application/tournee')) ?>"><?= e(t('application.ma_tournee')) ?></a>
  <?php endif; ?>
  <form class="menu-pied" method="post" action="<?= e(url('/deconnexion')) ?>">
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
      <?php $nonLues = $noyau->notifications()->nonLues('UTILISATEUR', $c->utilisateurId()); ?>
      <a class="cloche" href="<?= e(url('/notifications')) ?>" aria-label="<?= e(t('notif.titre')) ?><?= $nonLues > 0 ? ' (' . $nonLues . ')' : '' ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg><?php if ($nonLues > 0): ?><span class="compteur"><?= e($nonLues > 99 ? '99+' : $nonLues) ?></span><?php endif; ?></a>
      <form method="post" action="<?= e(url('/langue')) ?>" class="langues" aria-label="<?= e(t('commun.langue')) ?>">
        <?= champ_csrf() ?>
        <input type="hidden" name="retour" value="<?= e($chemin) ?>">
        <button name="langue" value="fr" class="<?= $langue === 'fr' ? 'choisi' : '' ?>"<?= $langue === 'fr' ? ' aria-pressed="true"' : '' ?>>FR</button>
        <button name="langue" value="en" class="<?= $langue === 'en' ? 'choisi' : '' ?>"<?= $langue === 'en' ? ' aria-pressed="true"' : '' ?>>EN</button>
      </form>
      <a class="identite" href="<?= e(url('/mon-compte')) ?>">
        <strong><?= e($c->utilisateur['nom']) ?></strong>
        <small><?= e($c->profil['nom']) ?></small>
      </a>
    </div>
  </header>

  <?php if ($etat->etat !== 'ACTIF'): ?>
    <div class="bandeau bandeau-<?= e(strtolower($etat->etat)) ?>" role="status">
      <?= e(t('abonnement.bandeau.' . strtolower($etat->etat), ['jours' => abs((int) $etat->joursRestants), 'date' => $etat->fin ? date('d/m/Y', strtotime($etat->fin)) : ''])) ?>
    </div>
  <?php endif; ?>

  <main class="contenu" id="contenu">
    <?php foreach ($noyau->session->messages() as $m): ?>
      <div class="message message-<?= e($m['type']) ?>" role="<?= $m['type'] === 'erreur' ? 'alert' : 'status' ?>"><?= e($m['texte']) ?></div>
    <?php endforeach; ?>
    <?= $contenu ?>
  </main>
</div>
</div>
<script src="<?= e(ressource('assets/js/application.js')) ?>"></script>
</body>
</html>
