<header class="carte tete-page">
  <div>
    <h1><?= e(t('menu.paiement_agents')) ?></h1>
    <p class="aide"><?= e(t($deduire ? 'paiements.aide' : 'paiements.aide_sans_deduction')) ?></p>
  </div>
</header>

<form class="carte filtres" method="get" action="<?= e(url('/paiements-recolteurs')) ?>">
  <label><?= e(t('commun.mois')) ?><input type="month" name="mois" value="<?= e($periode) ?>"></label>
  <button class="bouton clair"><?= e(t('commun.afficher')) ?></button>
  <span class="aide"><?= e(t('caisse.en_main')) ?> : <strong><?= e(argent($enMain)) ?></strong></span>
</form>

<section class="carte">
  <?php if (!$lignes): ?><p class="aide"><?= e(t('remises.aucun_recolteur')) ?></p><?php else: ?>
  <div class="defile"><table>
    <thead><tr><th><?= e(t('menu.recolteurs')) ?></th><th class="tel-cache nombre"><?= e(t('paiements.part')) ?></th><th class="tel-cache nombre"><?= e(t('paiements.manquants_dus')) ?></th><th class="nombre"><?= e(t('paiements.net')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($lignes as $l):
        $part = \AvecXY\Domaine\Montant::enCentimes($l['part_agent']);
        $du = (int) ($dus[$l['id']] ?? 0);
        $manq = $deduire ? min($part, $du) : 0;
        $net = $part - $manq; ?>
      <tr>
        <td><span class="code"><?= e($l['code']) ?></span> <?= e($l['nom']) ?></td>
        <td class="nombre"><?= e(argent($part / 100)) ?></td>
        <td class="nombre"><?= e(argent($du / 100)) ?></td>
        <td class="nombre"><strong><?= e(argent($l['paiement_id'] !== null ? $l['paye'] : $net / 100)) ?></strong></td>
        <td class="actions">
          <?php if ($l['paiement_id'] !== null): ?>
            <span class="pastille"><?= e(t('paiements.paye_le', ['date' => jour($l['paye_le'])])) ?></span>
            <?php $typeLigne = 'paiements-recolteurs'; $idLigne = $l['paiement_id']; $retourLigne = 'mois=' . $periode; require __DIR__ . '/_supprimer.php'; ?>
          <?php elseif ($net > 0): ?>
            <form method="post" action="<?= e(url('/paiements-recolteurs')) ?>" data-confirmer="<?= e(t('paiements.confirmer', ['nom' => $l['nom'], 'montant' => argent($net / 100)])) ?>">
              <?= champ_csrf() ?><input type="hidden" name="recolteur" value="<?= e($l['id']) ?>"><input type="hidden" name="mois" value="<?= e($periode) ?>"><input type="hidden" name="date" value="<?= e(date('Y-m-d')) ?>">
              <button class="bouton clair"><?= e(t('paiements.payer')) ?></button>
            </form>
          <?php else: ?><span class="aide"><?= e(t('paiements.rien')) ?></span><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
