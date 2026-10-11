<header class="carte tete-page">
  <div>
    <h1><?= e(t('portail.titre')) ?></h1>
    <p class="aide"><?= e(t('portail.page_aide')) ?></p>
  </div>
  <span class="pastille <?= $actif ? 'verte' : 'grise' ?>"><?= e(t($actif ? 'commun.actif' : 'commun.inactif')) ?></span>
</header>

<?php if (!$dansForfait): ?>
<section class="carte">
  <p class="message message-info"><?= e(t('portail.hors_forfait')) ?></p>
  <?php if (peut('abonnement.gerer')): ?><div class="boutons"><a class="bouton" href="<?= e(url('/abonnement')) ?>"><?= e(t('menu.abonnement')) ?></a></div><?php endif; ?>
</section>
<?php else: ?>

<section class="carte sans-impression">
  <h2><?= e(t('portail.etape_activer')) ?></h2>
  <p class="aide"><?= e(t($actif ? 'portail.actif_aide' : 'portail.inactif_aide')) ?></p>
  <form method="post" action="<?= e(url('/parametres/organisation/espace-client')) ?>"<?= $actif ? ' data-confirmer="' . e(t('portail.confirmer_desactiver')) . '"' : '' ?>>
    <?= champ_csrf() ?><input type="hidden" name="activer" value="<?= $actif ? '0' : '1' ?>">
    <button class="bouton<?= $actif ? ' clair' : '' ?>"><?= e(t($actif ? 'portail.desactiver' : 'portail.activer')) ?></button>
  </form>
</section>

<?php if ($actif): ?>
<section class="carte sans-impression">
  <h2><?= e(t('portail.etape_adresse')) ?></h2>
  <p class="aide"><?= e(t('portail.adresse_aide')) ?></p>
  <p><a href="<?= e($adresse) ?>" target="_blank" rel="noopener"><strong><?= e($adresse) ?></strong></a></p>
</section>

<section class="carte sans-impression">
  <h2><?= e(t('portail.etape_identifiants')) ?></h2>
  <div class="tuiles">
    <div class="tuile"><span><?= e(t('portail.clients_avec')) ?></span><strong><?= e($avec) ?></strong></div>
    <div class="tuile<?= $sans > 0 ? ' fort' : '' ?>"><span><?= e(t('portail.clients_sans')) ?></span><strong><?= e($sans) ?></strong></div>
  </div>
  <p class="aide"><?= e(t('portail.generer_aide')) ?></p>
  <?php if ($sans > 0): ?>
  <form method="post" action="<?= e(url('/parametres/espace-client/generer')) ?>" data-confirmer="<?= e(t('portail.confirmer_generer', ['n' => $sans])) ?>">
    <?= champ_csrf() ?><button class="bouton"><?= e(t('portail.generer')) ?></button>
  </form>
  <?php endif; ?>
</section>

<?php if ($creditDansForfait): ?>
<form class="carte sans-impression" method="post" action="<?= e(url('/parametres/espace-client/demandes')) ?>">
  <?= champ_csrf() ?>
  <div class="tete-section">
    <h2><?= e(t('portail.etape_demandes')) ?></h2>
    <span class="pastille <?= $demandesClient ? 'verte' : 'grise' ?>"><?= e(t($demandesClient ? 'portail.demandes_autorisees' : 'portail.demandes_non_autorisees')) ?></span>
  </div>
  <p class="aide"><?= e(t('portail.demandes_aide')) ?></p>
  <input type="hidden" name="autoriser" value="<?= $demandesClient ? '0' : '1' ?>">
  <button class="bouton clair"><?= e(t($demandesClient ? 'portail.demandes_interdire' : 'portail.demandes_autoriser')) ?></button>
</form>
<?php endif; ?>

<?php if ($liste): ?>
<section class="carte">
  <div class="tete-section">
    <h2><?= e(t('portail.liste_titre', ['n' => count($liste['liste'])])) ?></h2>
    <button type="button" class="bouton sans-impression" data-imprimer><?= e(t('commun.imprimer')) ?></button>
  </div>
  <p class="message message-info sans-impression"><?= e(t('portail.liste_une_fois')) ?><?= $liste['restant'] > 0 ? ' ' . e(t('portail.liste_restant', ['n' => $liste['restant']])) : '' ?></p>
  <div class="defile"><table>
    <thead><tr><th><?= e(t('clients.client')) ?></th><th><?= e(t('menu.recolteurs')) ?></th><th><?= e(t('portail.identifiant')) ?></th><th><?= e(t('portail.pin_defaut')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($liste['liste'] as $l): ?>
      <tr><td><?= e($l['nom']) ?></td><td><?= e($l['recolteur']) ?></td><td><strong class="code"><?= e($l['code']) ?></strong></td><td><strong><?= e($l['pin']) ?></strong></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>
