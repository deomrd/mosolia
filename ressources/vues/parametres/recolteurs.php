<header class="carte tete-page">
  <div>
    <h1><?= e(t('menu.recolteurs')) ?></h1>
    <p class="aide"><?= e(t('recolteurs.aide')) ?></p>
  </div>
  <a class="bouton" href="<?= e(url('/parametres/recolteurs/nouveau')) ?>"><?= e(t('recolteurs.nouveau')) ?></a>
</header>

<section class="carte">
  <?php if (!$recolteurs): ?>
    <p class="aide"><?= e(t('recolteurs.aucun')) ?></p>
  <?php else: ?>
  <div class="defile">
  <table>
    <thead><tr>
      <th><?= e(t('recolteurs.code')) ?></th>
      <th><?= e(t('recolteurs.nom')) ?></th>
      <th class="tel-cache"><?= e(t('recolteurs.telephone')) ?></th>
      <th class="tel-cache"><?= e(t('recolteurs.compte')) ?></th>
      <th class="nombre"><?= e(t('recolteurs.clients')) ?></th>
      <th class="tel-cache"><?= e(t('utilisateurs.statut')) ?></th>
      <th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($recolteurs as $r): ?>
      <tr>
        <td><span class="code"><?= e($r['code']) ?></span></td>
        <td><strong><?= e($r['nom']) ?></strong><?php if (!empty($r['agence_nom'])): ?><br><small><?= e($r['agence_nom']) ?></small><?php endif; ?></td>
        <td><?= e($r['telephone'] ?? '—') ?></td>
        <td><?= e($r['utilisateur_nom'] ?? t('recolteurs.sans_compte')) ?></td>
        <td class="nombre"><?= e($r['nb_clients']) ?></td>
        <td><span class="pastille <?= $r['statut'] === 'ACTIF' ? 'verte' : 'grise' ?>"><?= e(t('statut.' . strtolower($r['statut']))) ?></span></td>
        <td class="actions">
          <a href="<?= e(url('/parametres/recolteurs/' . $r['id'])) ?>"><?= e(t('commun.modifier')) ?></a>
          <?php if ((int) $r['nb_clients'] === 0): ?>
          <form method="post" action="<?= e(url('/parametres/recolteurs/' . $r['id'] . '/supprimer')) ?>" data-confirmer="<?= e(t('corbeille.confirmer', ['nom' => $r['code'] . ' ' . $r['nom']])) ?>">
            <?= champ_csrf() ?>
            <button class="lien danger"><?= e(t('commun.supprimer')) ?></button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</section>
