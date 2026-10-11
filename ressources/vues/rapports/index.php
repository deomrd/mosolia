<?php use AvecXY\Http\Controleurs\RapportsControleur; ?>
<header class="carte tete-page">
  <div>
    <h1><?= e(t('menu.rapports')) ?></h1>
    <p class="aide"><?= e(t('rapports.aide')) ?></p>
  </div>
  <?php if ($resultat !== null && $resultat['lignes']): ?><a class="bouton clair seulement-ordinateur" href="<?= e(url_avec(['export' => 'csv'])) ?>"><?= e(t('rapports.exporter')) ?></a><?php endif; ?>
</header>

<form class="carte filtres" method="get" action="<?= e(url('/rapports')) ?>">
  <label><?= e(t('rapports.rapport')) ?>
    <select name="rapport" required>
      <option value=""><?= e(t('commun.choisir')) ?></option>
      <?php foreach ($disponibles as $r): ?><option value="<?= e($r) ?>"<?= $rapport === $r ? ' selected' : '' ?>><?= e(t('rapports.type.' . $r)) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label><?= e(t('commun.du')) ?><input type="date" name="du" value="<?= e($du) ?>"></label>
  <label><?= e(t('commun.au')) ?><input type="date" name="au" value="<?= e($au) ?>"></label>
  <?php if ($recolteurs): ?>
  <label><?= e(t('menu.recolteurs')) ?>
    <select name="recolteur"><option value=""><?= e(t('commun.tous')) ?></option>
      <?php foreach ($recolteurs as $r): ?><option value="<?= e($r['id']) ?>"<?= $agent === (int) $r['id'] ? ' selected' : '' ?>><?= e($r['code'] . ' · ' . $r['nom']) ?></option><?php endforeach; ?>
    </select>
  </label>
  <?php endif; ?>
  <button class="bouton"><?= e(t('commun.afficher')) ?></button>
</form>

<?php if ($resultat === null): ?>
<section class="carte">
  <h2><?= e(t('rapports.disponibles')) ?></h2>
  <ul class="liste-rapports">
    <?php foreach ($disponibles as $r): ?>
      <li><a href="<?= e(url('/rapports?rapport=' . $r . '&du=' . date('Y-m-d') . '&au=' . date('Y-m-d'))) ?>"><strong><?= e(t('rapports.type.' . $r)) ?></strong></a><span><?= e(t('rapports.detail.' . $r)) ?></span></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php else: $colonnes = $resultat['colonnes']; ?>
<section class="carte">
  <div class="tete-section">
    <h2><?= e(t('rapports.type.' . $rapport)) ?> · <?= e($rapport === 'soldes' ? t('rapports.a_ce_jour') : ($du === $au ? jour($du) : jour($du) . ' – ' . jour($au))) ?></h2>
    <span class="aide"><?= e(t('rapports.lignes', ['n' => count($resultat['lignes'])])) ?></span>
  </div>
  <?php if ($plein): ?><p class="message message-info"><?= e(t('rapports.tronque', ['n' => count($resultat['lignes'])])) ?></p><?php endif; ?>
  <?php if (!$resultat['lignes']): ?><p class="aide"><?= e(t('commun.aucun_resultat')) ?></p><?php else: ?>
  <div class="defile"><table class="rapport">
    <thead><tr><?php foreach ($colonnes as $col => $type): ?><th<?= in_array($type, ['argent', 'nombre'], true) ? ' class="nombre"' : '' ?>><?= e(t('rapports.colonne.' . $col)) ?></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php foreach ($resultat['lignes'] as $l): ?>
      <tr><?php foreach ($colonnes as $col => $type): ?><td<?= in_array($type, ['argent', 'nombre'], true) ? ' class="nombre"' : '' ?>><?= e(RapportsControleur::cellule($type, $l[$col])) ?></td><?php endforeach; ?></tr>
    <?php endforeach; ?>
    </tbody>
    <?php if ($totaux): ?>
    <tfoot><tr><?php $i = 0; foreach ($colonnes as $col => $type): ?><th<?= $type === 'argent' ? ' class="nombre"' : '' ?>><?= isset($totaux[$col]) ? e(argent($totaux[$col] / 100)) : ($i === 0 ? e(t('rapports.total')) : '') ?></th><?php $i++; endforeach; ?></tr></tfoot>
    <?php endif; ?>
  </table></div>
  <?php endif; ?>
</section>
<?php endif; ?>
