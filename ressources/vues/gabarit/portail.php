<?php
/** @var string $contenu */
$noyau = \AvecXY\Noyau\Noyau::instance();
$compte = $noyau->comptePortail;
$langue = $noyau->traducteur->langue;
$couleur = $compte['couleur_principale'] ?? null;
?>
<!doctype html>
<html lang="<?= e($langue) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="<?= e($couleur ?: '#176B5F') ?>">
<meta name="robots" content="noindex">
<title><?= e($compte['organisation_nom'] ?? $noyau->config['nom']) ?> · <?= e(t('portail.titre')) ?></title>
<link rel="manifest" href="<?= e(url('manifest.webmanifest')) ?>">
<link rel="icon" href="<?= e(url('assets/icone.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(ressource('assets/css/application.css')) ?>">
<?php if ($couleur): ?><style>:root{--vert:<?= e($couleur) ?>}</style><?php endif; ?>
</head>
<body class="<?= $compte === null ? 'page-publique' : 'page-portail' ?><?= dans_application() ? ' dans-application' : '' ?>">
<?php if ($compte === null): ?>
<div class="vitrine">
<main class="vitrine-form" id="contenu">
  <div class="publique-tete">
    <a class="marque" href="<?= e(url('/')) ?>"><img src="<?= e(url('assets/icone.svg')) ?>" alt="" width="32" height="32"> <?= e($noyau->config['nom']) ?></a>
    <form method="post" action="<?= e(url('/langue')) ?>" class="langues" aria-label="<?= e(t('commun.langue')) ?>">
      <?= champ_csrf() ?><input type="hidden" name="retour" value="/espace-client/connexion">
      <button name="langue" value="fr" class="<?= $langue === 'fr' ? 'choisi' : '' ?>">FR</button>
      <button name="langue" value="en" class="<?= $langue === 'en' ? 'choisi' : '' ?>">EN</button>
    </form>
  </div>
  <?php foreach ($noyau->session->messages() as $m): ?>
    <div class="message message-<?= e($m['type']) ?>" role="<?= $m['type'] === 'erreur' ? 'alert' : 'status' ?>"><?= e($m['texte']) ?></div>
  <?php endforeach; ?>
  <?= $contenu ?>
</main>
<?php require __DIR__ . '/_visuel.php'; ?>
</div>
<?php else:
$chemin = $noyau->requete->chemin;
$avecCredit = !empty($noyau->portail()->modulesDuForfait((int) $compte['organisation_id'])['credit']);
$nonLues = $noyau->notifications()->nonLues('CLIENT', (int) $compte['id']);
$est = static fn (string $lien): bool => $lien === '/espace-client' ? $chemin === $lien : ($chemin === $lien || str_starts_with($chemin, $lien . '/'));
// menu en accordeon, comme l'application du personnel
$groupes = [
    'epargne' => ['/espace-client/carnets' => 'portail.mes_carnets', '/espace-client/historique' => 'portail.historique'],
    'credit' => $avecCredit ? ['/espace-client/credits' => 'portail.credits_et_demandes'] : [],
    'compte' => ['/espace-client/notifications' => 'notif.titre', '/espace-client/code' => 'portail.mon_code', '/espace-client/comptes' => 'portail.mes_comptes'],
];
$icones = ['accueil' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/></svg>', 'carnets' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4h12a2 2 0 0 1 2 2v14H7a2 2 0 0 1-2-2z"/><path d="M9 8h6M9 12h6"/></svg>', 'historique' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>', 'credits' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18M7 15h4"/></svg>', 'notifs' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>'];
$onglets = ['/espace-client' => ['accueil', 'portail.accueil'], '/espace-client/carnets' => ['carnets', 'portail.onglet_carnets'], '/espace-client/historique' => ['historique', 'portail.historique']]
    + ($avecCredit ? ['/espace-client/credits' => ['credits', 'portail.onglet_credits']] : []) + ['/espace-client/notifications' => ['notifs', 'portail.onglet_notifs']];
?>
<a class="saut" href="#contenu"><?= e(t('commun.aller_au_contenu')) ?></a>
<div class="appli">
<div class="voile" data-menu-fermer></div>
<nav class="menu" id="menu" aria-label="<?= e(t('commun.menu')) ?>">
  <div class="menu-tete">
    <span class="marque"><?= e($compte['organisation_nom']) ?></span>
    <button type="button" class="bouton-icone menu-fermer" data-menu-fermer aria-label="<?= e(t('commun.fermer_menu')) ?>">&times;</button>
  </div>
  <div class="menu-client"><strong><?= e($compte['nom']) ?></strong><span class="code"><?= e($compte['code']) ?></span></div>

  <a class="menu-lien<?= $est('/espace-client') ? ' actif' : '' ?>" href="<?= e(url('/espace-client')) ?>"<?= $est('/espace-client') ? ' aria-current="page"' : '' ?>><?= e(t('portail.accueil')) ?></a>
  <?php foreach ($groupes as $cle => $liens): if (!$liens) { continue; }
      $contientActif = (bool) array_filter(array_keys($liens), $est); ?>
  <div class="groupe" data-groupe="ec-<?= e($cle) ?>"<?= $contientActif ? ' data-actif' : '' ?>>
    <button type="button" class="groupe-titre" aria-expanded="<?= $contientActif ? 'true' : 'false' ?>" aria-controls="groupe-ec-<?= e($cle) ?>">
      <span><?= e(t('portail.groupe.' . $cle)) ?></span>
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
    </button>
    <div class="groupe-corps" id="groupe-ec-<?= e($cle) ?>"<?= $contientActif ? '' : ' hidden' ?>>
      <?php foreach ($liens as $lien => $texte): ?>
        <a class="menu-lien sous<?= $est($lien) ? ' actif' : '' ?>" href="<?= e(url($lien)) ?>"<?= $est($lien) ? ' aria-current="page"' : '' ?>><?= e(t($texte)) ?><?php if ($lien === '/espace-client/notifications' && $nonLues > 0): ?> <span class="compteur-menu"><?= e($nonLues > 99 ? '99+' : $nonLues) ?></span><?php endif; ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>

  <form class="menu-pied" method="post" action="<?= e(url('/espace-client/deconnexion')) ?>">
    <?= champ_csrf() ?>
    <button type="submit" class="menu-lien deconnexion"><?= e(t('menu.deconnexion')) ?></button>
  </form>
</nav>

<div class="principal">
  <header class="barre">
    <button type="button" class="bouton-icone" data-menu-ouvrir aria-controls="menu" aria-expanded="false" aria-label="<?= e(t('commun.ouvrir_menu')) ?>">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
    <span class="barre-titre"><?= e($compte['organisation_nom']) ?></span>
    <div class="barre-droite">
      <a class="cloche" href="<?= e(url('/espace-client/notifications')) ?>" aria-label="<?= e(t('notif.titre')) ?><?= $nonLues > 0 ? ' (' . $nonLues . ')' : '' ?>"><?= $icones['notifs'] ?><?php if ($nonLues > 0): ?><span class="compteur"><?= e($nonLues > 99 ? '99+' : $nonLues) ?></span><?php endif; ?></a>
      <a class="identite" href="<?= e(url('/espace-client/code')) ?>"><strong><?= e($compte['nom']) ?></strong><small><?= e($compte['code']) ?></small></a>
    </div>
  </header>
  <main class="contenu portail" id="contenu">
    <?php foreach ($noyau->session->messages() as $m): ?>
      <div class="message message-<?= e($m['type']) ?>" role="<?= $m['type'] === 'erreur' ? 'alert' : 'status' ?>"><?= e($m['texte']) ?></div>
    <?php endforeach; ?>
    <?= $contenu ?>
  </main>
</div>
</div>
<nav class="onglets-bas" aria-label="<?= e(t('portail.raccourcis')) ?>">
  <?php foreach ($onglets as $lien => [$icone, $texte]): $actif = $est($lien); ?>
    <a href="<?= e(url($lien)) ?>"<?= $actif ? ' class="actif" aria-current="page"' : '' ?>><?= $icones[$icone] ?><?php if ($icone === 'notifs' && $nonLues > 0): ?><span class="compteur"><?= e($nonLues > 99 ? '99+' : $nonLues) ?></span><?php endif; ?><span><?= e(t($texte)) ?></span></a>
  <?php endforeach; ?>
</nav>
<?php endif; ?>
<script src="<?= e(ressource('assets/js/application.js')) ?>"></script>
</body>
</html>
