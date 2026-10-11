<header class="carte tete-page">
  <div>
    <h1><?= e(t('menu.agences')) ?></h1>
    <p class="aide"><?= e(t('agences.aide')) ?></p>
  </div>
  <a class="bouton" href="<?= e(url('/parametres/agences/nouvelle')) ?>"><?= e(t('agences.nouvelle')) ?></a>
</header>

<section class="carte">
  <?php if (!$agences): ?>
    <p class="aide"><?= e(t('agences.aucune')) ?></p>
  <?php else: ?>
  <div class="defile"><table>
    <thead><tr>
      <th><?= e(t('agences.code')) ?></th><th><?= e(t('agences.nom')) ?></th>
      <th class="nombre masque-tel"><?= e(t('menu.recolteurs')) ?></th><th class="nombre masque-tel"><?= e(t('menu.clients')) ?></th>
      <th class="nombre"><?= e(t('caisse.en_main')) ?></th><th class="masque-tel"><?= e(t('utilisateurs.statut')) ?></th><th></th>
    </tr></thead>
    <tbody>
      <tr class="report"><td></td><td><strong><?= e(t('agences.siege')) ?></strong><br><small><?= e(t('agences.siege_aide')) ?></small></td><td class="masque-tel"></td><td class="masque-tel"></td><td class="nombre"><strong><?= e(argent($siege)) ?></strong></td><td class="masque-tel"></td><td></td></tr>
    <?php foreach ($agences as $a): ?>
      <tr>
        <td><span class="code"><?= e($a['code']) ?></span></td>
        <td><strong><?= e($a['nom']) ?></strong><?php if ($a['adresse']): ?><br><small><?= e($a['adresse']) ?></small><?php endif; ?></td>
        <td class="nombre masque-tel"><?= e($a['nb_recolteurs']) ?></td>
        <td class="nombre masque-tel"><?= e($a['nb_clients']) ?></td>
        <td class="nombre"><strong><?= e(argent($a['en_main'])) ?></strong></td>
        <td class="masque-tel"><span class="pastille <?= (int) $a['actif'] === 1 ? 'verte' : 'grise' ?>"><?= e(t((int) $a['actif'] === 1 ? 'statut.actif' : 'statut.inactif')) ?></span></td>
        <td class="actions">
          <a href="<?= e(url('/caisse?agence=' . $a['id'])) ?>"><?= e(t('menu.journal_caisse')) ?></a>
          <a href="<?= e(url('/parametres/agences/' . $a['id'])) ?>"><?= e(t('commun.modifier')) ?></a>
          <?php if ((int) $a['nb_recolteurs'] === 0 && (int) $a['nb_utilisateurs'] === 0): ?>
          <form method="post" action="<?= e(url('/parametres/agences/' . $a['id'] . '/supprimer')) ?>" data-confirmer="<?= e(t('corbeille.confirmer', ['nom' => $a['nom']])) ?>">
            <?= champ_csrf() ?><button class="lien danger"><?= e(t('commun.supprimer')) ?></button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
