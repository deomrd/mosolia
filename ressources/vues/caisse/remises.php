<header class="carte tete-page">
  <div>
    <h1><?= e(t('menu.remises')) ?></h1>
    <p class="aide"><?= e(t('remises.aide')) ?></p>
  </div>
</header>

<form class="carte filtres" method="get" action="<?= e(url('/remises')) ?>">
  <label><?= e(t('remises.jour')) ?><input type="date" name="jour" value="<?= e($jour) ?>" max="<?= e(date('Y-m-d')) ?>"></label>
  <button class="bouton clair"><?= e(t('commun.afficher')) ?></button>
</form>

<section class="carte">
  <h2><?= e(t('remises.controle_du', ['date' => jour($jour)])) ?></h2>
  <?php if (!$collecte): ?><p class="aide"><?= e(t('remises.aucun_recolteur')) ?></p><?php else: ?>
  <div class="defile"><table>
    <thead><tr><th><?= e(t('menu.recolteurs')) ?></th><th class="nombre"><?= e(t('remises.encaisse')) ?></th><th class="nombre"><?= e(t('remises.deja_controle')) ?></th><th><?= e(t('remises.remis')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($collecte as $l):
        $encaisse = \AvecXY\Domaine\Montant::enCentimes($l['cotise']) + \AvecXY\Domaine\Montant::enCentimes($l['ventes']);
        $reste = max(0, $encaisse - \AvecXY\Domaine\Montant::enCentimes($l['controle'])); ?>
      <tr>
        <td><span class="code"><?= e($l['code']) ?></span> <?= e($l['nom']) ?></td>
        <td class="nombre"><?= e(argent($encaisse / 100)) ?></td>
        <td class="nombre"><?= e(argent($l['controle'])) ?></td>
        <td>
          <?php if ($reste > 0): ?>
          <form class="ligne-form" method="post" action="<?= e(url('/remises')) ?>">
            <?= champ_csrf() ?>
            <input type="hidden" name="recolteur" value="<?= e($l['id']) ?>"><input type="hidden" name="date" value="<?= e($jour) ?>">
            <input type="hidden" name="attendu" value="<?= e(\AvecXY\Domaine\Montant::pourChamp($reste / 100)) ?>">
            <input name="verse" inputmode="decimal" required value="<?= e(\AvecXY\Domaine\Montant::pourChamp($reste / 100)) ?>" aria-label="<?= e(t('remises.remis')) ?>">
            <input name="commentaire" placeholder="<?= e(t('commun.commentaire')) ?>" aria-label="<?= e(t('commun.commentaire')) ?>">
            <button class="bouton clair"><?= e(t('remises.enregistrer')) ?></button>
          </form>
          <?php else: ?><span class="aide"><?= e($encaisse > 0 ? t('remises.complet') : t('remises.rien')) ?></span><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>

<section class="carte">
  <h2><?= e(t('remises.du_mois', ['mois' => substr($jour, 5, 2) . '/' . substr($jour, 0, 4)])) ?></h2>
  <?php if (!$remises): ?><p class="aide"><?= e(t('commun.aucun_resultat')) ?></p><?php else: ?>
  <div class="defile"><table>
    <thead><tr><th><?= e(t('commun.date')) ?></th><th><?= e(t('menu.recolteurs')) ?></th><th class="tel-cache nombre"><?= e(t('remises.attendu')) ?></th><th class="nombre"><?= e(t('remises.remis')) ?></th><th class="tel-cache nombre"><?= e(t('remises.manquant')) ?></th><th class="tel-cache nombre"><?= e(t('remises.excedent')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($remises as $r): ?>
      <tr>
        <td><?= e(jour($r['date_remise'])) ?></td>
        <td><span class="code"><?= e($r['recolteur_code']) ?></span> <?= e($r['recolteur_nom']) ?><?= $r['commentaire'] ? '<br><small>' . e($r['commentaire']) . '</small>' : '' ?></td>
        <td class="nombre"><?= e(argent($r['montant_attendu'])) ?></td>
        <td class="nombre"><?= e(argent($r['montant_verse'])) ?></td>
        <td class="nombre"><?= (float) $r['manquant'] > 0 ? '<strong class="danger">' . e(argent($r['manquant'])) . '</strong>' : '—' ?></td>
        <td class="nombre"><?= (float) $r['excedent'] > 0 ? e(argent($r['excedent'])) : '—' ?></td>
        <td class="actions"><?php $typeLigne = 'remises'; $idLigne = $r['id']; $retourLigne = 'jour=' . $jour; require __DIR__ . '/_supprimer.php'; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>

<?php if ($dus): ?>
<section class="carte">
  <h2><?= e(t('manquants.titre')) ?></h2>
  <p class="aide"><?= e(t('manquants.aide')) ?></p>
  <div class="defile"><table>
    <thead><tr><th><?= e(t('menu.recolteurs')) ?></th><th class="tel-cache nombre"><?= e(t('manquants.constates')) ?></th><th class="tel-cache nombre"><?= e(t('manquants.deduits')) ?></th><th class="tel-cache nombre"><?= e(t('manquants.regles')) ?></th><th class="nombre"><?= e(t('manquants.du')) ?></th><th><?= e(t('manquants.regler')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($dus as $l): ?>
      <tr>
        <td><span class="code"><?= e($l['code']) ?></span> <?= e($l['nom']) ?></td>
        <td class="nombre"><?= e(argent($l['constates'])) ?></td>
        <td class="nombre"><?= e(argent($l['deduits'])) ?></td>
        <td class="nombre"><?= e(argent($l['regles'])) ?></td>
        <td class="nombre"><strong<?= $l['du'] > 0 ? ' class="danger"' : '' ?>><?= e(argent($l['du'] / 100)) ?></strong></td>
        <td>
          <?php if ($l['du'] > 0): ?>
          <form class="ligne-form" method="post" action="<?= e(url('/remises/manquants')) ?>">
            <?= champ_csrf() ?><input type="hidden" name="recolteur" value="<?= e($l['id']) ?>"><input type="hidden" name="date" value="<?= e(date('Y-m-d')) ?>">
            <input name="montant" inputmode="decimal" required value="<?= e(\AvecXY\Domaine\Montant::pourChamp($l['du'] / 100)) ?>" aria-label="<?= e(t('caisse.montant')) ?>">
            <button class="bouton clair"><?= e(t('manquants.regler')) ?></button>
          </form>
          <?php else: ?><span class="pastille verte"><?= e(t('manquants.solde')) ?></span><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php if ($reglements): ?>
  <h3><?= e(t('manquants.reglements_du_mois')) ?></h3>
  <div class="defile"><table>
    <tbody>
    <?php foreach ($reglements as $rg): ?>
      <tr><td><?= e(jour($rg['date_paiement'])) ?></td><td><span class="code"><?= e($rg['recolteur_code']) ?></span> <?= e($rg['recolteur_nom']) ?></td><td class="nombre"><?= e(argent($rg['montant'])) ?></td>
        <td class="actions"><?php $typeLigne = 'reglements-manquants'; $idLigne = $rg['id']; $retourLigne = ''; require __DIR__ . '/_supprimer.php'; ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
<?php endif; ?>
