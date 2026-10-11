<header class="carte tete-page">
  <div>
    <h1><?= e(t('menu.cotisations')) ?></h1>
    <p class="aide" data-resultats><?= e(t('cotisations.resume', ['n' => $total, 'somme' => argent($somme)])) ?></p>
  </div>
  <?php if (peut('cotisations.saisir')): ?><a class="bouton" href="<?= e(url('/collecte')) ?>"><?= e(t('menu.collecte')) ?></a><?php endif; ?>
</header>

<form class="carte filtres" method="get" action="<?= e(url('/cotisations')) ?>" data-filtre-auto>
  <label><?= e(t('commun.du')) ?><input type="date" name="du" value="<?= e($f['du']) ?>"></label>
  <label><?= e(t('commun.au')) ?><input type="date" name="au" value="<?= e($f['au']) ?>"></label>
  <label><?= e(t('utilisateurs.statut')) ?>
    <select name="statut">
      <option value=""><?= e(t('commun.tous')) ?></option>
      <?php foreach (['EN_ATTENTE', 'VALIDE'] as $s): ?><option value="<?= e($s) ?>"<?= $f['statut'] === $s ? ' selected' : '' ?>><?= e(t('operation.statut.' . strtolower($s))) ?></option><?php endforeach; ?>
    </select>
  </label>
  <?php if ($recolteurs): ?>
  <label><?= e(t('menu.recolteurs')) ?>
    <select name="recolteur">
      <option value=""><?= e(t('commun.tous')) ?></option>
      <?php foreach ($recolteurs as $r): ?><option value="<?= e($r['id']) ?>"<?= $f['recolteur'] === (int) $r['id'] ? ' selected' : '' ?>><?= e($r['code'] . ' · ' . $r['nom']) ?></option><?php endforeach; ?>
    </select>
  </label>
  <?php endif; ?>
  <label><?= e(t('commun.rechercher')) ?><input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="<?= e(t('clients.recherche_aide')) ?>"></label>
  <noscript><button class="bouton clair"><?= e(t('commun.filtrer')) ?></button></noscript>
</form>

<section class="carte" data-resultats>
  <?php if (!$operations): ?><p class="aide"><?= e(t('commun.aucun_resultat')) ?></p><?php else: ?>
  <div class="defile"><table>
    <thead><tr>
      <th class="tel-cache"><?= e(t('commun.date')) ?></th><th><?= e(t('clients.client')) ?></th><th class="tel-cache"><?= e(t('carnets.carnet')) ?></th>
      <th class="tel-cache nombre"><?= e(t('cotisations.parts')) ?></th><th class="nombre"><?= e(t('cotisations.montant')) ?></th>
      <th><?= e(t('utilisateurs.statut')) ?></th><th class="tel-cache"><?= e(t('cotisations.saisie_par')) ?></th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($operations as $o): ?>
      <tr>
        <td><?= e(jour($o['date_cotisation'])) ?></td>
        <td><span class="code"><?= e($o['client_code']) ?></span> <?= e($o['client_nom']) ?></td>
        <td><a href="<?= e(url('/carnets/' . $o['carnet_id'])) ?>">#<?= e($o['carnet_numero']) ?></a></td>
        <td class="nombre"><?= e($o['nombre_parts'] ?? '—') ?></td>
        <td class="nombre"><strong><?= e(argent($o['montant_total'])) ?></strong></td>
        <td><span class="pastille <?= ['VALIDE' => 'verte', 'EN_ATTENTE' => 'ambre', 'REJETE' => 'rouge'][$o['statut']] ?? '' ?>"><?= e(t('operation.statut.' . strtolower($o['statut']))) ?></span></td>
        <td><?= e($o['auteur_nom'] ?? '—') ?></td>
        <td class="actions"><?php if ($o['modifiable']): ?><a href="<?= e(url('/cotisations/' . $o['id'])) ?>"><?= e(t('commun.corriger')) ?></a><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
  <?php require __DIR__ . '/../commun/pagination.php'; ?>
</section>
