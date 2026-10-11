<header class="carte tete-page">
  <div>
    <h1><?= e(t('menu.retraits')) ?></h1>
    <p class="aide" data-resultats><?= e(t('retraits.resume', ['n' => $total, 'somme' => argent($somme)])) ?></p>
  </div>
</header>

<form class="carte filtres" method="get" action="<?= e(url('/retraits')) ?>" data-filtre-auto>
  <label><?= e(t('commun.rechercher')) ?><input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="<?= e(t('credits.recherche')) ?>"></label>
  <label><?= e(t('commun.du')) ?><input type="date" name="du" value="<?= e($f['du']) ?>"></label>
  <label><?= e(t('commun.au')) ?><input type="date" name="au" value="<?= e($f['au']) ?>"></label>
  <?php if ($recolteurs): ?>
  <label><?= e(t('menu.recolteurs')) ?>
    <select name="recolteur">
      <option value=""><?= e(t('commun.tous')) ?></option>
      <?php foreach ($recolteurs as $r): ?><option value="<?= e($r['id']) ?>"<?= $f['recolteur'] === (int) $r['id'] ? ' selected' : '' ?>><?= e($r['code'] . ' · ' . $r['nom']) ?></option><?php endforeach; ?>
    </select>
  </label>
  <?php endif; ?>
  <noscript><button class="bouton clair"><?= e(t('commun.filtrer')) ?></button></noscript>
</form>

<section class="carte" data-resultats>
  <?php if (!$retraits): ?><p class="aide"><?= e(t('commun.aucun_resultat')) ?></p><?php else: ?>
  <div class="defile"><table>
    <thead><tr><th><?= e(t('commun.date')) ?></th><th><?= e(t('clients.client')) ?></th><th class="tel-cache"><?= e(t('carnets.carnet')) ?></th><th class="tel-cache"><?= e(t('commun.operation')) ?></th><th class="nombre"><?= e(t('retraits.remis')) ?></th><th class="tel-cache nombre"><?= e(t('retraits.frais')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($retraits as $r): ?>
      <tr>
        <td><?= e(jour($r['date_retrait'])) ?></td>
        <td><span class="code"><?= e($r['client_code']) ?></span> <?= e($r['client_nom']) ?></td>
        <td><a href="<?= e(url('/carnets/' . $r['carnet_id'])) ?>">#<?= e($r['carnet_numero']) ?></a></td>
        <td><?= e($r['tranche'] ? t('retraits.de_tranche', ['n' => $r['tranche']]) : t('retraits.retrait')) ?><?= (int) $r['ferme_carnet'] === 1 ? ' · ' . e(t('retraits.a_ferme')) : '' ?></td>
        <td class="nombre"><strong><?= e(argent($r['montant_retire'])) ?></strong></td>
        <td class="nombre"><?= e(argent($r['frais'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
  <?php require __DIR__ . '/../commun/pagination.php'; ?>
</section>
