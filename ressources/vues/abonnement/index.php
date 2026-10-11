<?php
$c = \AvecXY\Noyau\Noyau::instance()->contexte;
$etat = $c->abonnement;
$f = $c->forfait;
$limite = static fn ($v) => $v === null ? t('abonnement.illimite') : (string) $v;
$classe = ['ACTIF' => 'verte', 'RAPPEL' => 'ambre', 'GRACE' => 'ambre', 'LECTURE_SEULE' => 'rouge', 'SUSPENDU' => 'rouge'][$etat->etat];
?>
<header class="carte tete-page">
  <div>
    <h1><?= e(t('menu.abonnement')) ?></h1>
    <p class="aide"><?= e(t('abonnement.aide')) ?></p>
  </div>
  <span class="pastille <?= $classe ?>"><?= e(t('abonnement.etat.' . strtolower($etat->etat))) ?><?= $etat->essai ? ' · ' . e(t('abonnement.essai')) : '' ?></span>
</header>

<section class="carte">
  <div class="tuiles">
    <div class="tuile fort"><span><?= e(t('tableau.forfait')) ?></span><strong><?= e($f['plan_nom'] ?? '—') ?></strong></div>
    <div class="tuile"><span><?= e(t('tableau.fin_periode')) ?></span><strong><?= e(jour($etat->fin)) ?></strong><?php if ($etat->joursRestants !== null): ?><small><?= e($etat->joursRestants >= 0 ? t('abonnement.jours_restants', ['n' => $etat->joursRestants]) : t('abonnement.jours_depasses', ['n' => -$etat->joursRestants])) ?></small><?php endif; ?></div>
    <div class="tuile"><span><?= e(t('menu.utilisateurs')) ?></span><strong><?= e($usage['utilisateurs']) ?> / <?= e($limite($f['max_utilisateurs'] ?? null)) ?></strong></div>
    <div class="tuile"><span><?= e(t('menu.recolteurs')) ?></span><strong><?= e($usage['agents']) ?> / <?= e($limite($f['max_agents'] ?? null)) ?></strong></div>
    <div class="tuile"><span><?= e(t('menu.clients')) ?></span><strong><?= e($usage['clients']) ?> / <?= e($limite($f['max_clients'] ?? null)) ?></strong></div>
  </div>
</section>

<section class="carte">
  <h2><?= e(t('abonnement.blocages')) ?></h2>
  <p class="aide"><?= e(t('abonnement.blocages_aide')) ?></p>
  <div class="defile"><table>
    <thead><tr><th><?= e(t('abonnement.situation')) ?></th><th><?= e(t('abonnement.ce_qui_marche')) ?></th><th><?= e(t('abonnement.ce_qui_est_bloque')) ?></th></tr></thead>
    <tbody>
    <?php foreach (['actif', 'rappel', 'grace', 'lecture_seule', 'suspendu'] as $e): ?>
      <tr<?= strtolower($etat->etat) === $e ? ' class="ligne-actuelle"' : '' ?>>
        <td><strong><?= e(t('abonnement.etat.' . $e)) ?></strong><?= strtolower($etat->etat) === $e ? '<br><small>' . e(t('abonnement.vous_etes_ici')) . '</small>' : '' ?></td>
        <td><?= e(t('abonnement.marche.' . $e)) ?></td>
        <td><?= e(t('abonnement.bloque.' . $e)) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>

<section class="carte">
  <h2><?= e(t('abonnement.forfaits')) ?></h2>
  <?php if ($enAttente): ?><p class="message message-info"><?= e(t('abonnement.demande_en_cours')) ?></p><?php endif; ?>
  <?php if (!$plans): ?><p class="aide"><?= e(t('abonnement.aucun_forfait')) ?></p><?php endif; ?>
  <div class="forfaits">
  <?php foreach ($plans as $p): $m = json_decode((string) $p['modules'], true) ?: []; ?>
    <form class="forfait" method="post" action="<?= e(url('/abonnement')) ?>" data-confirmer="<?= e(t('abonnement.confirmer_demande', ['nom' => $p['nom']])) ?>">
      <?= champ_csrf() ?><input type="hidden" name="plan" value="<?= e($p['id']) ?>">
      <h3><?= e($p['nom']) ?><?= (int) ($f['plan_id'] ?? 0) === (int) $p['id'] ? ' <span class="pastille verte">' . e(t('abonnement.actuel')) . '</span>' : '' ?></h3>
      <p class="prix"><strong><?= e(\AvecXY\Domaine\Montant::afficher($p['prix'], 2) . ' ' . $p['devise_code']) ?></strong> / <?= e(t('abonnement.jours', ['n' => $p['duree_jours']])) ?></p>
      <?php if ($p['description']): ?><p class="aide"><?= e($p['description']) ?></p><?php endif; ?>
      <ul>
        <li><?= e(t('abonnement.inclus_utilisateurs', ['n' => $limite($p['max_utilisateurs'])])) ?></li>
        <li><?= e(t('abonnement.inclus_recolteurs', ['n' => $limite($p['max_agents'])])) ?></li>
        <li><?= e(t('abonnement.inclus_clients', ['n' => $limite($p['max_clients'])])) ?></li>
        <?php foreach (array_keys(array_filter($m)) as $mod): ?><li><?= e(t('plateforme.module.' . $mod)) ?></li><?php endforeach; ?>
      </ul>
      <?php if (!$enAttente): ?>
      <label><?= e(t('abonnement.moyen')) ?><input name="moyen" maxlength="40" required placeholder="<?= e(t('abonnement.moyen_exemple')) ?>"></label>
      <label><?= e(t('abonnement.reference')) ?><input name="reference" maxlength="200"></label>
      <button class="bouton"><?= e(t('abonnement.demander')) ?></button>
      <?php endif; ?>
    </form>
  <?php endforeach; ?>
  </div>
  <p class="aide"><?= e(t('abonnement.paiement_manuel')) ?></p>
</section>

<?php if ($paiements): ?>
<section class="carte">
  <h2><?= e(t('abonnement.paiements')) ?></h2>
  <div class="defile"><table>
    <thead><tr><th><?= e(t('commun.date')) ?></th><th><?= e(t('tableau.forfait')) ?></th><th class="nombre"><?= e(t('caisse.montant')) ?></th><th class="tel-cache"><?= e(t('abonnement.moyen')) ?></th><th><?= e(t('credits.statut')) ?></th></tr></thead>
    <tbody><?php foreach ($paiements as $p): ?><tr><td><?= e(jour(substr((string) ($p['paye_le'] ?? $p['cree_le']), 0, 10))) ?></td><td><?= e($p['plan_nom'] ?? '—') ?></td><td class="nombre"><?= e(\AvecXY\Domaine\Montant::afficher($p['montant'], 2) . ' ' . $p['devise_code']) ?></td><td><?= e($p['moyen'] ?? '—') ?></td><td><?= e(t('abonnement.paiement.' . strtolower($p['statut']))) ?></td></tr><?php endforeach; ?></tbody>
  </table></div>
</section>
<?php endif; ?>
