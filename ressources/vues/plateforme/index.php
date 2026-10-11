<header class="carte tete-page">
  <div>
    <h1><?= e(t('plateforme.organisations')) ?></h1>
    <p class="aide"><?= e(t('plateforme.resume', ['n' => count($organisations), 'tickets' => $ticketsOuverts])) ?></p>
  </div>
</header>

<section class="carte">
  <div class="tuiles">
    <div class="tuile fort"><span><?= e(t('plateforme.organisations')) ?></span><strong><?= e(count($organisations)) ?></strong></div>
    <div class="tuile"><span><?= e(t('plateforme.stat_actives')) ?></span><strong><?= e(($etats['ACTIF'] ?? 0) + ($etats['RAPPEL'] ?? 0) + ($etats['GRACE'] ?? 0)) ?></strong></div>
    <div class="tuile<?= (($etats['LECTURE_SEULE'] ?? 0) + ($etats['SUSPENDU'] ?? 0)) > 0 ? ' alerte' : '' ?>"><span><?= e(t('plateforme.stat_bloquees')) ?></span><strong><?= e(($etats['LECTURE_SEULE'] ?? 0) + ($etats['SUSPENDU'] ?? 0)) ?></strong></div>
    <div class="tuile<?= $demandes > 0 ? ' alerte' : '' ?>"><span><?= e(t('plateforme.stat_demandes')) ?></span><strong><?= e($demandes) ?></strong></div>
    <a class="tuile<?= $ticketsOuverts > 0 ? ' alerte' : '' ?>" href="<?= e(url('/plateforme/support')) ?>"><span><?= e(t('plateforme.stat_tickets')) ?></span><strong><?= e($ticketsOuverts) ?></strong></a>
    <div class="tuile"><span><?= e(t('plateforme.stat_encaisse')) ?></span><strong><?= e($encaisse ? implode(' · ', array_map(static fn ($l) => \AvecXY\Domaine\Montant::afficher($l['total'], 2) . ' ' . $l['devise_code'], $encaisse)) : '0') ?></strong></div>
  </div>
</section>

<form class="carte filtres" method="get" action="<?= e(url('/plateforme')) ?>" data-filtre-auto>
  <label><?= e(t('commun.rechercher')) ?><input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="<?= e(t('plateforme.recherche')) ?>"></label>
  <label><?= e(t('plateforme.etat')) ?>
    <select name="etat">
      <option value=""><?= e(t('commun.tous')) ?></option>
      <?php foreach (['ACTIF', 'EXPIRE_BIENTOT', 'GRACE', 'BLOQUEE', 'DEMANDE'] as $v): ?><option value="<?= e($v) ?>"<?= $f['etat'] === $v ? ' selected' : '' ?>><?= e(t('plateforme.filtre.' . strtolower($v))) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label><?= e(t('tableau.forfait')) ?>
    <select name="forfait">
      <option value=""><?= e(t('commun.tous')) ?></option>
      <?php foreach ($plans as $pl): ?><option value="<?= e($pl['id']) ?>"<?= $f['forfait'] === (int) $pl['id'] ? ' selected' : '' ?>><?= e($pl['nom']) ?></option><?php endforeach; ?>
    </select>
  </label>
  <noscript><button class="bouton clair"><?= e(t('commun.filtrer')) ?></button></noscript>
</form>

<section class="carte" data-resultats>
  <p class="aide"><?= e(t('plateforme.liste_resume', ['n' => count($organisations), 'total' => $toutes, 'clients' => $totalClients, 'utilisateurs' => $totalUtilisateurs])) ?></p>
  <?php if (!$organisations): ?><p class="aide"><?= e(t('commun.aucun_resultat')) ?></p><?php else: ?>
  <div class="defile"><table>
    <thead><tr><th><?= e(t('plateforme.organisation')) ?></th><th class="tel-cache"><?= e(t('tableau.forfait')) ?></th><th class="tel-cache"><?= e(t('plateforme.fin')) ?></th><th><?= e(t('plateforme.etat')) ?></th><th class="tel-cache nombre"><?= e(t('menu.utilisateurs')) ?></th><th class="tel-cache nombre"><?= e(t('menu.clients')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($organisations as $o): ?>
      <tr>
        <td><a href="<?= e(url('/plateforme/organisations/' . $o['id'])) ?>"><strong><?= e($o['nom']) ?></strong></a><br><small><?= e($o['code'] . ' · ' . $o['devise_code']) ?><?= !empty($o['nom_court']) ? ' · @' . e($o['nom_court']) : '' ?></small></td>
        <td><?= e($o['abonnement']['plan_nom'] ?? '—') ?></td>
        <td><?= e(jour($o['abonnement']['fin'] ?? null)) ?></td>
        <td><?php $etatLigne = $o['etat']; require __DIR__ . '/_etat.php'; ?></td>
        <td class="nombre"><?= e($o['utilisateurs']) ?></td>
        <td class="nombre"><?= e($o['clients']) ?></td>
        <td><?= (int) $o['demandes'] > 0 ? '<span class="pastille ambre">' . e(t('plateforme.demande_a_traiter')) . '</span>' : '' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>

<section class="carte">
  <h2><?= e(t('plateforme.nouvelle_organisation')) ?></h2>
  <form method="post" action="<?= e(url('/plateforme/organisations')) ?>" autocomplete="off">
    <?= champ_csrf() ?>
    <div class="grille">
      <label><?= e(t('organisation.nom')) ?><input name="nom" required maxlength="160" data-majuscules></label>
      <label><?= e(t('organisation.nom_court')) ?><input name="nom_court" maxlength="12" pattern="[a-z0-9]{2,12}" autocapitalize="none" spellcheck="false" placeholder="tuungane"><small><?= e(t('organisation.nom_court_plateforme')) ?></small></label>
      <label><?= e(t('organisation.devise')) ?><select name="devise"><?php foreach ($devises as $d): ?><option><?= e($d['code']) ?></option><?php endforeach; ?></select></label>
      <label><?= e(t('tableau.forfait')) ?><select name="forfait"><?php foreach ($plans as $p): ?><option value="<?= e($p['code']) ?>"><?= e($p['nom']) ?></option><?php endforeach; ?></select></label>
      <label><?= e(t('organisation.langue')) ?><select name="langue"><option value="fr">Français</option><option value="en">English</option></select></label>
      <label><?= e(t('plateforme.admin_nom')) ?><input name="admin_nom" required data-majuscules></label>
      <label><?= e(t('connexion.identifiant')) ?><input name="identifiant" required autocapitalize="none"></label>
      <label><?= e(t('plateforme.mot_de_passe_provisoire')) ?><input name="mot_de_passe" required minlength="8"><small><?= e(t('plateforme.mot_de_passe_aide')) ?></small></label>
    </div>
    <div class="boutons"><button class="bouton"><?= e(t('plateforme.creer_organisation')) ?></button></div>
  </form>
</section>
