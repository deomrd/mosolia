<header class="carte tete-page">
  <div>
    <h1><?= e(t('menu.eligibilite')) ?></h1>
    <p class="aide"><?= e(t('eligibilite.aide')) ?></p>
  </div>
</header>

<?php if (peut('eligibilite.proposer')): ?>
<section class="carte">
  <h2><?= e(t('eligibilite.proposer')) ?></h2>
  <?php if (!$produits): ?><p class="aide"><?= e(t('credits.aucune_regle')) ?></p><?php else: ?>
  <form method="post" action="<?= e(url('/eligibilite')) ?>">
    <?= champ_csrf() ?>
    <div class="grille">
      <label><?= e(t('credits.code_client')) ?><input name="client" required data-majuscules placeholder="ABCD-1"></label>
      <label><?= e(t('credits.regles')) ?>
        <select name="produit"><?php foreach ($produits as $p): ?><option value="<?= e($p['id']) ?>"><?= e($p['nom']) ?></option><?php endforeach; ?></select>
      </label>
      <label><?= e(t('eligibilite.montant_demande')) ?><input name="montant" inputmode="decimal" required></label>
      <label><?= e(t('commun.commentaire')) ?><input name="commentaire"></label>
    </div>
    <div class="boutons"><button class="bouton"><?= e(t('eligibilite.envoyer')) ?></button></div>
  </form>
  <?php endif; ?>
</section>
<?php endif; ?>

<form class="carte filtres" method="get" action="<?= e(url('/eligibilite')) ?>" data-filtre-auto>
  <label><?= e(t('commun.rechercher')) ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('eligibilite.recherche')) ?>"></label>
  <label><?= e(t('credits.statut')) ?>
    <select name="etat">
      <?php foreach (['EN_ATTENTE', 'PARTIEL', 'TOTAL', 'TOUTES'] as $v): ?><option value="<?= e($v) ?>"<?= $etat === $v ? ' selected' : '' ?>><?= e(t('eligibilite.etat.' . strtolower($v))) ?></option><?php endforeach; ?>
    </select>
  </label>
  <noscript><button class="bouton clair"><?= e(t('commun.filtrer')) ?></button></noscript>
</form>

<section class="carte" data-resultats>
  <?php if (!$demandes): ?><p class="aide"><?= e(t('eligibilite.aucune')) ?></p><?php else: ?>
  <div class="defile"><table>
    <thead><tr><th><?= e(t('clients.client')) ?></th><th class="tel-cache"><?= e(t('clients.recolteur')) ?></th><th class="nombre"><?= e(t('eligibilite.montant_demande')) ?></th><th class="tel-cache nombre"><?= e(t('eligibilite.accorde')) ?></th><th class="tel-cache"><?= e(t('eligibilite.eligibilite')) ?></th><th><?= e(t('credits.statut')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($demandes as $d):
        $partiel = $d['statut'] === 'VALIDE' && (float) $d['plafond_accorde'] < (float) $d['montant_demande']; ?>
      <tr>
        <td><a href="<?= e(url('/eligibilite/' . $d['id'])) ?>"><span class="code"><?= e($d['client_code']) ?></span> <strong><?= e($d['client_nom']) ?></strong></a></td>
        <td><?= e($d['recolteur_nom']) ?></td>
        <td class="nombre"><?= e(argent($d['montant_demande'])) ?></td>
        <td class="nombre"><?= $d['statut'] === 'VALIDE' ? '<strong>' . e(argent($d['plafond_accorde'])) . '</strong>' : '—' ?></td>
        <td><span class="pastille <?= $d['eligible'] ? 'verte' : 'rouge' ?>"><?= e(t($d['eligible'] ? 'eligibilite.eligible' : 'eligibilite.non_eligible')) ?></span></td>
        <td><span class="pastille <?= $d['statut'] === 'PROPOSE' ? 'ambre' : 'verte' ?>"><?= e(t($d['statut'] === 'PROPOSE' ? 'eligibilite.etat.en_attente' : ($partiel ? 'eligibilite.etat.partiel' : 'eligibilite.etat.total'))) ?></span></td>
        <td class="actions"><a href="<?= e(url('/eligibilite/' . $d['id'])) ?>"><?= e(t('commun.ouvrir')) ?></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
