<header class="carte tete-page">
  <div>
    <h1><?= e(t('menu.regles_credit')) ?></h1>
    <p class="aide"><?= e(t('credits.regles_aide')) ?></p>
  </div>
  <a class="bouton" href="<?= e(url('/parametres/credit/nouveau')) ?>"><?= e(t('credits.nouvelles_regles')) ?></a>
</header>

<section class="carte">
  <?php if (!$produits): ?><p class="aide"><?= e(t('credits.aucune_regle')) ?></p><?php else: ?>
  <div class="defile"><table>
    <thead><tr><th><?= e(t('credits.nom')) ?></th><th class="nombre"><?= e(t('credits.interet')) ?></th><th class="tel-cache nombre"><?= e(t('credits.duree')) ?></th><th class="tel-cache"><?= e(t('credits.limites')) ?></th><th></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($produits as $p): ?>
      <tr>
        <td><a href="<?= e(url('/parametres/credit/' . $p['id'])) ?>"><strong><?= e($p['nom']) ?></strong></a></td>
        <td class="nombre"><?= e((float) $p['taux_interet']) ?> %</td>
        <td class="nombre"><?= e($p['duree_jours']) ?> j</td>
        <td><?= e(implode(' · ', array_filter([
            $p['montant_maximum'] !== null ? t('credits.max_court', ['montant' => argent($p['montant_maximum'])]) : '',
            $p['multiple_epargne_max'] !== null ? t('credits.multiple_court', ['n' => (float) $p['multiple_epargne_max']]) : '',
            (int) $p['validation_manuelle_requise'] === 1 ? t('credits.validation_court') : '',
        ])) ?: '—') ?></td>
        <td><span class="pastille <?= (int) $p['actif'] === 1 ? 'verte' : 'grise' ?>"><?= e(t((int) $p['actif'] === 1 ? 'commun.actif' : 'commun.inactif')) ?></span></td>
        <td class="actions">
          <form method="post" action="<?= e(url('/credits/produits-credit/' . $p['id'] . '/supprimer')) ?>" data-confirmer="<?= e(t('corbeille.confirmer', ['nom' => $p['nom']])) ?>"><?= champ_csrf() ?><button class="lien danger"><?= e(t('commun.supprimer')) ?></button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
