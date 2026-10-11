<header class="carte tete-page">
  <div>
    <h1><?= e(t('menu.carnets')) ?></h1>
    <p class="aide" data-resultats><?= e(t('carnets.compte', ['n' => $total])) ?></p>
  </div>
</header>

<form class="carte filtres" method="get" action="<?= e(url('/carnets')) ?>" data-filtre-auto>
  <label><?= e(t('commun.rechercher')) ?>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('carnets.recherche_aide')) ?>">
  </label>
  <label><?= e(t('utilisateurs.statut')) ?>
    <select name="statut">
      <option value="ACTIF"<?= $statut === 'ACTIF' ? ' selected' : '' ?>><?= e(t('carnets.statut.actif')) ?></option>
      <option value="INACTIF"<?= in_array($statut, ['INACTIF', 'FERME'], true) ? ' selected' : '' ?>><?= e(t('carnets.non_actifs')) ?></option>
      <option value="TOUS"<?= $statut === 'TOUS' ? ' selected' : '' ?>><?= e(t('commun.tous')) ?></option>
    </select>
  </label>
  <noscript><button class="bouton clair"><?= e(t('commun.filtrer')) ?></button></noscript>
</form>

<section class="carte" data-resultats>
  <?php if (!$carnets): ?><p class="aide"><?= e(t('commun.aucun_resultat')) ?></p><?php else: ?>
  <div class="defile"><table>
    <thead><tr>
      <th><?= e(t('carnets.numero')) ?></th><th><?= e(t('clients.client')) ?></th><th class="tel-cache"><?= e(t('carnets.type')) ?></th>
      <th class="tel-cache nombre"><?= e(t('carnets.mise')) ?></th><th class="tel-cache nombre"><?= e(t('carnets.avancement')) ?></th>
      <th class="nombre"><?= e(t('clients.epargne')) ?></th><th class="tel-cache"><?= e(t('utilisateurs.statut')) ?></th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($carnets as $k): ?>
      <tr>
        <td><a href="<?= e(url('/carnets/' . $k['id'])) ?>"><strong>#<?= e($k['numero']) ?></strong></a></td>
        <td><span class="code"><?= e($k['client_code']) ?></span> <?= e($k['client_nom']) ?></td>
        <td><?= e($k['type_nom']) ?></td>
        <td class="nombre"><?= $k['mode'] === 'PARTS' ? e(argent($k['mise'])) : '—' ?></td>
        <td class="nombre"><?= $k['mode'] === 'PARTS' ? e($k['parts_validees'] . ' / ' . $k['nombre_parts']) : e(argent($k['total_cotise'])) ?></td>
        <td class="nombre"><strong><?= e(argent($k['epargne'])) ?></strong></td>
        <td><span class="pastille <?= $k['statut'] === 'ACTIF' ? 'verte' : 'grise' ?>"><?= e(t('carnets.statut.' . strtolower($k['statut']))) ?></span><?= $k['validation'] !== 'VALIDE' ? ' <span class="pastille ambre">' . e(t('carnets.vente_en_attente')) . '</span>' : '' ?></td>
        <td class="actions"><a href="<?= e(url('/carnets/' . $k['id'])) ?>"><?= e(t('commun.ouvrir')) ?></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
  <?php require __DIR__ . '/../commun/pagination.php'; ?>
</section>
