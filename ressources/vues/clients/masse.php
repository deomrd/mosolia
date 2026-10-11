<header class="carte tete-page">
  <div>
    <p class="fil"><a href="<?= e(url('/clients')) ?>"><?= e(t('menu.clients')) ?></a></p>
    <h1><?= e(t('clients.masse')) ?></h1>
    <p class="aide"><?= e(t('clients.masse_aide')) ?></p>
  </div>
  <?php if ($peutImporter): ?><a class="bouton clair sans-impression" href="<?= e(url('/parametres/reprise')) ?>"><?= e(t('clients.masse_excel')) ?></a><?php endif; ?>
</header>

<?php if ($crees): $avecPin = (bool) array_filter(array_column($crees, 'pin')); ?>
<section class="carte">
  <div class="tete-section">
    <h2><?= e(t('clients.masse_crees', ['n' => count($crees)])) ?></h2>
    <?php if ($avecPin): ?><button type="button" class="bouton sans-impression" data-imprimer><?= e(t('commun.imprimer')) ?></button><?php endif; ?>
  </div>
  <?php if ($avecPin): ?><p class="message message-info sans-impression"><?= e(t('portail.liste_une_fois')) ?></p><?php endif; ?>
  <div class="defile"><table>
    <thead><tr><th><?= e(t('clients.client')) ?></th><th><?= e(t('portail.identifiant')) ?></th><?php if ($avecPin): ?><th><?= e(t('portail.pin_defaut')) ?></th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($crees as $l): ?>
      <tr><td><a href="<?= e(url('/clients/' . $l['id'])) ?>"><?= e($l['nom']) ?></a><?= $l['fusionne'] ? ' <span class="pastille grise">' . e(t('clients.masse_fusionne')) . '</span>' : '' ?></td>
        <td><strong class="code"><?= e($l['code']) ?></strong></td><?php if ($avecPin): ?><td><strong><?= e($l['pin'] !== '' ? $l['pin'] : '—') ?></strong></td><?php endif; ?></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php endif; ?>

<form class="carte sans-impression" method="post" action="<?= e(url('/clients-en-masse')) ?>">
  <?= champ_csrf() ?>
  <?php if ($crees !== null && array_filter(array_column($lignes, 'erreur'))): ?><p class="message message-erreur"><?= e(t('clients.masse_a_corriger')) ?></p><?php endif; ?>
  <label class="court"><?= e(t('clients.masse_date')) ?><input type="date" name="date" value="<?= e($date) ?>" max="<?= e(date('Y-m-d')) ?>" required></label>
  <div class="defile espace"><table class="saisie" data-lignes>
    <thead><tr><th>#</th><th><?= e(t('clients.nom')) ?></th><th><?= e(t('clients.telephone')) ?></th><?php if ($recolteurs): ?><th><?= e(t('menu.recolteurs')) ?></th><?php endif; ?><th><?= e(t('carnets.type')) ?></th><th><?= e(t('carnets.mise')) ?></th></tr></thead>
    <tbody>
    <?php foreach (array_values($lignes) as $i => $l): ?>
      <tr>
        <td><?= e($i + 1) ?></td>
        <td><input name="lignes[<?= e($i) ?>][nom]" value="<?= e($l['nom']) ?>" data-majuscules aria-label="<?= e(t('clients.nom')) ?> <?= e($i + 1) ?>">
          <?php if (!empty($l['erreur'])): ?><small class="erreur-champ"><?= e($l['erreur']) ?></small><?php endif; ?></td>
        <td><input name="lignes[<?= e($i) ?>][telephone]" value="<?= e($l['telephone']) ?>" inputmode="tel" aria-label="<?= e(t('clients.telephone')) ?> <?= e($i + 1) ?>"></td>
        <?php if ($recolteurs): ?>
        <td><select name="lignes[<?= e($i) ?>][agent_id]" aria-label="<?= e(t('menu.recolteurs')) ?> <?= e($i + 1) ?>" data-recopier>
          <option value="0">—</option>
          <?php foreach ($recolteurs as $r): ?><option value="<?= e($r['id']) ?>"<?= (int) $l['agent_id'] === (int) $r['id'] ? ' selected' : '' ?>><?= e($r['nom']) ?></option><?php endforeach; ?>
        </select></td>
        <?php endif; ?>
        <td><select name="lignes[<?= e($i) ?>][type_id]" aria-label="<?= e(t('carnets.type')) ?> <?= e($i + 1) ?>" data-recopier>
          <option value="0"><?= e(t('clients.masse_sans_carnet')) ?></option>
          <?php foreach ($types as $ty): ?><option value="<?= e($ty['id']) ?>"<?= (int) $l['type_id'] === (int) $ty['id'] ? ' selected' : '' ?>><?= e($ty['nom']) ?></option><?php endforeach; ?>
        </select></td>
        <td><input name="lignes[<?= e($i) ?>][mise]" value="<?= e($l['mise']) ?>" inputmode="decimal" aria-label="<?= e(t('carnets.mise')) ?> <?= e($i + 1) ?>"></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="aide"><?= e(t('clients.masse_recopie')) ?></p>
  <div class="boutons espace">
    <button class="bouton"><?= e(t('clients.masse_enregistrer')) ?></button>
    <button type="button" class="bouton clair" data-ajouter-lignes><?= e(t('clients.masse_plus')) ?></button>
  </div>
</form>
