<header class="carte tete-page">
  <div>
    <h1><?= e(t('menu.credits')) ?></h1>
    <p class="aide"><?= e(t('credits.resume', ['n' => $totaux['nombre'], 'encours' => argent($totaux['encours']), 'retard' => argent($totaux['en_retard'])])) ?></p>
  </div>
  <?php if (peut('credits.accorder')): ?><a class="bouton" href="<?= e(url('/credits/nouveau')) ?>"><?= e(t('credits.accorder')) ?></a><?php endif; ?>
</header>

<form class="carte filtres" method="get" action="<?= e(url('/credits')) ?>" data-filtre-auto>
  <label><?= e(t('commun.rechercher')) ?><input name="q" value="<?= e($q) ?>" placeholder="<?= e(t('credits.recherche')) ?>"></label>
  <label><?= e(t('credits.statut')) ?>
    <select name="statut">
      <?php foreach (['OUVERTS', 'EN_RETARD', 'REMBOURSE', 'TOUS'] as $v): /* TOUS = historique complet */ ?><option value="<?= e($v) ?>"<?= $statut === $v ? ' selected' : '' ?>><?= e(t('credits.filtre.' . strtolower($v))) ?></option><?php endforeach; ?>
    </select>
  </label>
  <noscript><button class="bouton clair"><?= e(t('commun.filtrer')) ?></button></noscript>
</form>

<section class="carte" data-resultats>
  <?php if (!$credits): ?><p class="aide"><?= e(t('commun.aucun_resultat')) ?></p><?php else: ?>
  <div class="defile"><table>
    <thead><tr><th>#</th><th><?= e(t('clients.client')) ?></th><th class="tel-cache"><?= e(t('commun.date')) ?></th><th class="tel-cache"><?= e(t('credits.echeance')) ?></th><th class="tel-cache nombre"><?= e(t('credits.prete')) ?></th><th class="tel-cache nombre"><?= e(t('credits.total_du')) ?></th><th class="nombre"><?= e(t('credits.restant')) ?></th><th><?= e(t('credits.statut')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($credits as $cr): ?>
      <tr>
        <td><a href="<?= e(url('/credits/' . $cr['id'])) ?>"><strong>#<?= e($cr['numero']) ?></strong></a></td>
        <td><a href="<?= e(url('/credits/' . $cr['id'])) ?>"><span class="code"><?= e($cr['client_code']) ?></span> <?= e($cr['client_nom']) ?></a></td>
        <td><?= e(jour($cr['date_credit'])) ?></td>
        <td><?= e(jour($cr['date_echeance'])) ?></td>
        <td class="nombre"><?= e(argent($cr['montant'])) ?></td>
        <td class="nombre"><?= e(argent($cr['total_du'])) ?></td>
        <td class="nombre"><strong><?= e(argent($cr['restant'])) ?></strong></td>
        <td><span class="pastille <?= ['EN_COURS' => 'verte', 'EN_RETARD' => 'rouge', 'REMBOURSE' => 'grise'][$cr['statut']] ?? 'grise' ?>"><?= e(t('credits.statut.' . strtolower($cr['statut']))) ?></span><?= (int) $cr['derogation'] === 1 ? ' <span class="pastille ambre">' . e(t('credits.derogation')) . '</span>' : '' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
