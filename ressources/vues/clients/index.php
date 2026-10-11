<header class="carte tete-page">
  <div>
    <h1><?= e(t('menu.clients')) ?></h1>
    <p class="aide" data-resultats><?= e(t('clients.compte', ['n' => $total])) ?></p>
  </div>
  <?php if (peut('clients.creer')): ?><div class="boutons"><a class="bouton" href="<?= e(url('/clients/nouveau')) ?>"><?= e(t('clients.nouveau')) ?></a>
    <a class="bouton clair" href="<?= e(url('/clients-en-masse')) ?>"><?= e(t('clients.masse')) ?></a></div><?php endif; ?>
</header>

<form class="carte filtres" method="get" action="<?= e(url('/clients')) ?>" data-filtre-auto>
  <label><?= e(t('commun.rechercher')) ?>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('clients.recherche_aide')) ?>">
  </label>
  <?php if ($recolteurs): ?>
  <label><?= e(t('menu.recolteurs')) ?>
    <select name="recolteur">
      <option value=""><?= e(t('commun.tous')) ?></option>
      <?php foreach ($recolteurs as $r): ?><option value="<?= e($r['id']) ?>"<?= $filtre === (int) $r['id'] ? ' selected' : '' ?>><?= e($r['code'] . ' · ' . $r['nom']) ?></option><?php endforeach; ?>
    </select>
  </label>
  <?php endif; ?>
  <noscript><button class="bouton clair"><?= e(t('commun.filtrer')) ?></button></noscript>
</form>

<section class="carte" data-resultats>
  <?php if (!$clients): ?><p class="aide"><?= e(t('commun.aucun_resultat')) ?></p><?php else: ?>
  <div class="defile"><table>
    <thead><tr>
      <th><?= e(t('clients.code')) ?></th><th><?= e(t('clients.nom')) ?></th><th class="tel-cache"><?= e(t('clients.recolteur')) ?></th>
      <th class="tel-cache nombre"><?= e(t('clients.carnets_actifs')) ?></th><th class="nombre"><?= e(t('clients.epargne')) ?></th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($clients as $cl): ?>
      <tr>
        <td><span class="code"><?= e($cl['code']) ?></span></td>
        <td><a href="<?= e(url('/clients/' . $cl['id'])) ?>"><strong><?= e($cl['nom']) ?></strong></a><?php if ($cl['statut'] !== 'ACTIF'): ?> <span class="pastille grise"><?= e(t('statut.inactif')) ?></span><?php endif; ?></td>
        <td><?= e($cl['recolteur_code'] . ' · ' . $cl['recolteur_nom']) ?></td>
        <td class="nombre"><?= e($cl['carnets_actifs']) ?></td>
        <td class="nombre"><?= e(argent($cl['epargne'])) ?></td>
        <td class="actions"><a href="<?= e(url('/clients/' . $cl['id'])) ?>"><?= e(t('commun.ouvrir')) ?></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
  <?php require __DIR__ . '/../commun/pagination.php'; ?>
</section>
