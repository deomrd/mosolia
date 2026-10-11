<?php $limite = static fn ($v) => $v === null ? '∞' : (string) $v; ?>
<header class="carte tete-page">
  <div>
    <p class="fil"><a href="<?= e(url('/plateforme')) ?>"><?= e(t('plateforme.organisations')) ?></a></p>
    <h1><?= e($o['nom']) ?></h1>
    <p class="aide"><?= e($o['code'] . ' · ' . $o['devise_code'] . ' · ' . t('plateforme.creee_le', ['date' => jour(substr((string) $o['cree_le'], 0, 10))])) ?></p>
  </div>
  <?php $etatLigne = $etat; require __DIR__ . '/_etat.php'; ?>
</header>

<section class="carte">
  <div class="tuiles">
    <div class="tuile"><span><?= e(t('tableau.forfait')) ?></span><strong><?= e($abonnement['plan_nom'] ?? '—') ?></strong></div>
    <div class="tuile"><span><?= e(t('plateforme.fin')) ?></span><strong><?= e(jour($abonnement['fin'] ?? null)) ?></strong></div>
    <div class="tuile"><span><?= e(t('menu.utilisateurs')) ?></span><strong><?= e($usage['utilisateurs']) ?> / <?= e($limite($abonnement['max_utilisateurs'] ?? null)) ?></strong></div>
    <div class="tuile"><span><?= e(t('menu.recolteurs')) ?></span><strong><?= e($usage['agents']) ?> / <?= e($limite($abonnement['max_agents'] ?? null)) ?></strong></div>
    <div class="tuile"><span><?= e(t('menu.clients')) ?></span><strong><?= e($usage['clients']) ?> / <?= e($limite($abonnement['max_clients'] ?? null)) ?></strong></div>
  </div>
</section>

<section class="carte">
  <h2><?= e(t('plateforme.identite')) ?></h2>
  <dl class="fiche">
    <dt><?= e(t('organisation.nom_court')) ?></dt><dd><?= !empty($o['nom_court']) ? '<strong>@' . e($o['nom_court']) . '</strong>' : '<span class="pastille ambre">' . e(t('plateforme.nom_court_absent')) . '</span>' ?></dd>
    <dt><?= e(t('organisation.telephone')) ?></dt><dd><?= e($o['telephone'] ?? '—') ?></dd>
    <dt><?= e(t('organisation.email')) ?></dt><dd><?= e($o['email'] ?? '—') ?></dd>
    <dt><?= e(t('organisation.adresse')) ?></dt><dd><?= e(trim(($o['adresse'] ?? '') . ' ' . ($o['ville_defaut'] ?? '')) ?: '—') ?></dd>
    <dt><?= e(t('commun.langue')) ?></dt><dd><?= e(strtoupper((string) $o['langue'])) ?> · <?= e($o['fuseau'] ?? '') ?></dd>
    <dt><?= e(t('plateforme.demarrage')) ?></dt><dd><?= e(t('plateforme.demarrage.' . strtolower((string) ($o['demarrage'] ?? 'attente')))) ?></dd>
    <dt><?= e(t('plateforme.dernier_acces')) ?></dt><dd><?= $activite['dernier_acces'] ? e(date('d/m/Y H:i', strtotime($activite['dernier_acces']))) : '—' ?></dd>
    <dt><?= e(t('plateforme.derniere_cotisation')) ?></dt><dd><?= e(jour($activite['derniere_cotisation'])) ?></dd>
  </dl>
  <form class="ligne-form espace" method="post" action="<?= e(url('/plateforme/organisations/' . $o['id'] . '/nom-court')) ?>"<?= !empty($o['nom_court']) ? ' data-confirmer="' . e(t('plateforme.confirmer_nom_court')) . '"' : '' ?>>
    <?= champ_csrf() ?>
    <label><?= e(t('organisation.nom_court')) ?><input name="nom_court" value="<?= e($o['nom_court'] ?? $nomCourtPropose) ?>" required maxlength="12" pattern="[a-z0-9]{2,12}" autocapitalize="none" spellcheck="false"></label>
    <button class="bouton clair"><?= e(t('commun.enregistrer')) ?></button>
  </form>
  <p class="aide"><?= e(t('organisation.nom_court_aide')) ?></p>
</section>

<section class="carte">
  <h2><?= e(t('plateforme.activite')) ?></h2>
  <?php $dev = $o['devise_symbole'] ?? $o['devise_code']; $dec = (int) ($o['devise_decimales'] ?? 0); $m = static fn ($v) => \AvecXY\Domaine\Montant::afficher($v, $dec) . ' ' . $dev; ?>
  <div class="tuiles">
    <div class="tuile"><span><?= e(t('plateforme.carnets_actifs')) ?></span><strong><?= e($activite['carnets']) ?></strong></div>
    <div class="tuile fort"><span><?= e(t('plateforme.epargne_totale')) ?></span><strong><?= e($m($activite['epargne'])) ?></strong></div>
    <div class="tuile"><span><?= e(t('reprise.t_credits')) ?></span><strong><?= e($activite['credits']) ?></strong><?php if ((int) $activite['credits_en_retard'] > 0): ?><small><?= e(t('plateforme.dont_retard', ['n' => $activite['credits_en_retard']])) ?></small><?php endif; ?></div>
    <div class="tuile"><span><?= e(t('reprise.t_encours')) ?></span><strong><?= e($m($activite['encours'])) ?></strong></div>
    <div class="tuile"><span><?= e(t('plateforme.argent_en_caisse')) ?></span><strong><?= e($m($caisse)) ?></strong></div>
    <div class="tuile"><span><?= e(t('plateforme.acces_clients')) ?></span><strong><?= e($activite['acces_clients']) ?></strong></div>
  </div>
</section>

<section class="carte">
  <h2><?= e(t('menu.utilisateurs')) ?> <span class="pastille grise"><?= e(count($utilisateurs)) ?></span></h2>
  <div class="defile"><table>
    <thead><tr><th><?= e(t('utilisateurs.nom')) ?></th><th class="tel-cache"><?= e(t('utilisateurs.identifiant')) ?></th><th><?= e(t('utilisateurs.profil')) ?></th><th><?= e(t('utilisateurs.statut')) ?></th><th class="tel-cache"><?= e(t('utilisateurs.derniere_connexion')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($utilisateurs as $u): ?>
      <tr>
        <td><strong><?= e($u['nom']) ?></strong><?= $u['recolteur_code'] ? '<br><small>' . e(t('menu.recolteurs') . ' ' . $u['recolteur_code']) . '</small>' : '' ?></td>
        <td><?= e($u['identifiant']) ?></td>
        <td><?= e($u['profil_nom']) ?></td>
        <td><span class="pastille <?= $u['statut'] === 'ACTIF' ? 'verte' : 'grise' ?>"><?= e(t('statut.' . strtolower($u['statut']))) ?></span><?= empty($u['pin']) ? ' <span class="pastille ambre">' . e(t('plateforme.sans_pin')) . '</span>' : '' ?></td>
        <td><?= $u['derniere_connexion'] ? e(date('d/m/Y H:i', strtotime($u['derniere_connexion']))) : e(t('plateforme.jamais')) ?></td>
        <td class="actions"><form method="post" action="<?= e(url('/plateforme/organisations/' . $o['id'] . '/utilisateurs/' . $u['id'] . '/mot-de-passe')) ?>" data-confirmer="<?= e(t('plateforme.confirmer_reinitialisation', ['nom' => $u['nom']])) ?>"><?= champ_csrf() ?><button class="lien"><?= e(t('plateforme.reinitialiser')) ?></button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>

<?php foreach ($paiements as $p): if ($p['statut'] !== 'EN_ATTENTE') { continue; } ?>
<section class="carte alerte">
  <h2><?= e(t('plateforme.demande', ['forfait' => $p['plan_nom'] ?? '—', 'montant' => (float) $p['montant'] . ' ' . $p['devise_code']])) ?></h2>
  <p><?= e(t('plateforme.demande_detail', ['date' => jour(substr((string) $p['cree_le'], 0, 10)), 'moyen' => $p['moyen'] ?? '—'])) ?> <?= e($p['note'] ?? '') ?></p>
  <div class="boutons">
    <form method="post" action="<?= e(url('/plateforme/organisations/' . $o['id'] . '/activer')) ?>" data-confirmer="<?= e(t('plateforme.confirmer_activation')) ?>">
      <?= champ_csrf() ?><input type="hidden" name="paiement" value="<?= e($p['id']) ?>"><input type="hidden" name="plan" value="<?= e($p['plan_id']) ?>"><input type="hidden" name="montant" value="<?= e($p['montant']) ?>">
      <button class="bouton"><?= e(t('plateforme.confirmer_paiement')) ?></button>
    </form>
    <form method="post" action="<?= e(url('/plateforme/organisations/' . $o['id'] . '/refuser')) ?>" data-confirmer="<?= e(t('plateforme.confirmer_refus')) ?>">
      <?= champ_csrf() ?><input type="hidden" name="paiement" value="<?= e($p['id']) ?>">
      <button class="bouton clair"><?= e(t('plateforme.refuser')) ?></button>
    </form>
  </div>
</section>
<?php endforeach; ?>

<div class="deux-colonnes">
  <form class="carte" method="post" action="<?= e(url('/plateforme/organisations/' . $o['id'] . '/activer')) ?>" data-confirmer="<?= e(t('plateforme.confirmer_activation')) ?>">
    <?= champ_csrf() ?>
    <h2><?= e(t('plateforme.activer')) ?></h2>
    <p class="aide"><?= e(t('plateforme.activer_aide')) ?></p>
    <label><?= e(t('tableau.forfait')) ?>
      <select name="plan"><?php foreach ($plans as $pl): ?><option value="<?= e($pl['id']) ?>"<?= (int) ($abonnement['plan_id'] ?? 0) === (int) $pl['id'] ? ' selected' : '' ?>><?= e($pl['nom'] . ' · ' . (float) $pl['prix'] . ' ' . $pl['devise_code'] . ' · ' . $pl['duree_jours'] . ' j') ?></option><?php endforeach; ?></select>
    </label>
    <label><?= e(t('plateforme.montant_recu')) ?><input name="montant" inputmode="decimal"><small><?= e(t('plateforme.montant_aide')) ?></small></label>
    <label><?= e(t('abonnement.moyen')) ?><input name="moyen" maxlength="40"></label>
    <label><?= e(t('commun.commentaire')) ?><input name="note"></label>
    <div class="boutons"><button class="bouton"><?= e(t('plateforme.activer')) ?></button></div>
  </form>

  <form class="carte" method="post" action="<?= e(url('/plateforme/organisations/' . $o['id'] . '/statut')) ?>" data-confirmer="<?= e(t('plateforme.confirmer_statut')) ?>">
    <?= champ_csrf() ?>
    <?php if ($o['statut'] === 'SUSPENDUE'): ?>
      <h2><?= e(t('plateforme.reactiver')) ?></h2>
      <p class="aide"><?= e(t('plateforme.reactiver_aide')) ?></p>
      <input type="hidden" name="action" value="reactiver">
      <div class="boutons"><button class="bouton"><?= e(t('plateforme.reactiver')) ?></button></div>
    <?php else: ?>
      <h2><?= e(t('plateforme.suspendre')) ?></h2>
      <p class="aide"><?= e(t('plateforme.suspendre_aide')) ?></p>
      <input type="hidden" name="action" value="suspendre">
      <label><?= e(t('corbeille.motif')) ?><input name="motif" required minlength="5"></label>
      <div class="boutons"><button class="bouton danger"><?= e(t('plateforme.suspendre')) ?></button></div>
    <?php endif; ?>
  </form>
</div>

<section class="carte">
  <h2><?= e(t('plateforme.historique')) ?></h2>
  <div class="defile"><table>
    <thead><tr><th><?= e(t('tableau.forfait')) ?></th><th><?= e(t('commun.du')) ?></th><th><?= e(t('commun.au')) ?></th><th><?= e(t('credits.statut')) ?></th></tr></thead>
    <tbody><?php foreach ($abonnements as $a): ?><tr><td><?= e($a['plan_nom']) ?></td><td><?= e(jour($a['debut'])) ?></td><td><?= e(jour($a['fin'])) ?></td><td><?= e($a['statut']) ?></td></tr><?php endforeach; ?></tbody>
  </table></div>
  <?php if ($paiements): ?>
  <h3><?= e(t('abonnement.paiements')) ?></h3>
  <div class="defile"><table>
    <thead><tr><th><?= e(t('commun.date')) ?></th><th><?= e(t('tableau.forfait')) ?></th><th class="nombre"><?= e(t('caisse.montant')) ?></th><th class="tel-cache"><?= e(t('abonnement.moyen')) ?></th><th><?= e(t('credits.statut')) ?></th></tr></thead>
    <tbody><?php foreach ($paiements as $p): ?><tr><td><?= e(jour(substr((string) ($p['paye_le'] ?? $p['cree_le']), 0, 10))) ?></td><td><?= e($p['plan_nom'] ?? '—') ?></td><td class="nombre"><?= e((float) $p['montant'] . ' ' . $p['devise_code']) ?></td><td><?= e($p['moyen'] ?? '—') ?></td><td><?= e(t('abonnement.paiement.' . strtolower($p['statut']))) ?></td></tr><?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</section>

<?php if ($tickets): ?>
<section class="carte">
  <h2><?= e(t('menu.support')) ?></h2>
  <ul><?php foreach ($tickets as $t): ?><li><a href="<?= e(url('/plateforme/support/' . $t['id'])) ?>">#<?= e($t['numero']) ?> <?= e($t['sujet']) ?></a> · <?= e(t('support.statut.' . strtolower($t['statut']))) ?></li><?php endforeach; ?></ul>
</section>
<?php endif; ?>

<?php if ($journal): ?>
<section class="carte">
  <h2><?= e(t('plateforme.journal')) ?></h2>
  <div class="defile"><table>
    <thead><tr><th><?= e(t('commun.date')) ?></th><th><?= e(t('plateforme.action')) ?></th><th><?= e(t('plateforme.par')) ?></th><th><?= e(t('corbeille.motif')) ?></th></tr></thead>
    <tbody><?php foreach ($journal as $j): ?><tr><td><?= e(date('d/m/Y H:i', strtotime($j['cree_le']))) ?></td><td><?= e(t('plateforme.journal.' . str_replace('plateforme.', '', $j['action']))) ?></td><td><?= e($j['admin_nom'] ?? '—') ?></td><td><?= e($j['motif'] ?? '') ?></td></tr><?php endforeach; ?></tbody>
  </table></div>
</section>
<?php endif; ?>

<?php if ($acces): $identifiants = $acces; require __DIR__ . '/../commun/_acces_personnel.php'; endif; ?>
