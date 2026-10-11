<header class="carte tete-page">
  <div>
    <h1><?= e(t('menu.partage')) ?></h1>
    <p class="aide"><?= e(t('partage.aide')) ?></p>
  </div>
</header>

<section class="carte">
  <h2><?= e(t('partage.titre_partage')) ?></h2>
  <p class="aide"><?= e(t('partage.aide_partage')) ?></p>
  <div class="defile"><table>
    <thead><tr><th><?= e(t('partage.source')) ?></th><th class="nombre"><?= e(t('partage.recolteur')) ?></th><th class="nombre"><?= e(t('partage.organisation')) ?></th><th class="tel-cache"><?= e(t('partage.depuis')) ?></th><th><?= e(t('partage.changer')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($parSource as $source => $regles): $actuelle = null;
        foreach ($regles as $r) { if ($r['applicable_du'] <= date('Y-m-d')) { $actuelle = $r; break; } } ?>
      <tr>
        <td><strong><?= e(t('partage.source.' . strtolower($source))) ?></strong>
          <?php if (count($regles) > 1): ?><br><small><?= e(implode(' · ', array_map(static fn ($r) => jour($r['applicable_du']) . ' : ' . (float) $r['pourcentage_agent'] . ' %', $regles))) ?></small><?php endif; ?></td>
        <td class="nombre"><?= e($actuelle ? (float) $actuelle['pourcentage_agent'] : 0) ?> %</td>
        <td class="nombre"><?= e($actuelle ? (float) $actuelle['pourcentage_organisation'] : 100) ?> %</td>
        <td><?= e($actuelle ? jour($actuelle['applicable_du']) : '—') ?></td>
        <td>
          <form class="ligne-form" method="post" action="<?= e(url('/parametres/partage')) ?>" data-confirmer="<?= e(t('partage.confirmer')) ?>">
            <?= champ_csrf() ?><input type="hidden" name="source" value="<?= e($source) ?>">
            <input name="pourcentage" inputmode="decimal" required size="5" placeholder="%" aria-label="<?= e(t('partage.recolteur')) ?>">
            <input type="date" name="date" value="<?= e(date('Y-m-01')) ?>" required aria-label="<?= e(t('partage.depuis')) ?>">
            <button class="bouton clair"><?= e(t('commun.enregistrer')) ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>

<form class="carte" method="post" action="<?= e(url('/parametres/partage/manquants')) ?>">
  <?= champ_csrf() ?>
  <h2><?= e(t('partage.manquants')) ?></h2>
  <p class="aide"><?= e(t('partage.manquants_aide')) ?></p>
  <fieldset class="segments">
    <legend><?= e(t('partage.manquants_regle')) ?></legend>
    <?php foreach (['DEDUIRE', 'SUIVRE'] as $v): ?>
      <label><input type="radio" name="regle" value="<?= e($v) ?>"<?= $regleManquants === $v ? ' checked' : '' ?>><span><?= e(t('partage.manquants.' . strtolower($v))) ?></span></label>
    <?php endforeach; ?>
  </fieldset>
  <h2><?= e(t('partage.emprunts')) ?></h2>
  <p class="aide"><?= e(t('partage.emprunts_aide')) ?></p>
  <fieldset class="segments">
    <legend><?= e(t('partage.manquants_regle')) ?></legend>
    <?php foreach (['ORGANISATION', 'AUCUNE'] as $v): ?>
      <label><input type="radio" name="charge" value="<?= e($v) ?>"<?= $chargeEmprunts === $v ? ' checked' : '' ?>><span><?= e(t('partage.emprunts.' . strtolower($v))) ?></span></label>
    <?php endforeach; ?>
  </fieldset>
  <label class="case"><input type="checkbox" name="report" value="1"<?= $reportEmprunts === '1' ? ' checked' : '' ?>><span><strong><?= e(t('partage.emprunts_report')) ?></strong><small><?= e(t('partage.emprunts_report_aide')) ?></small></span></label>
  <div class="boutons"><button class="bouton"><?= e(t('commun.enregistrer')) ?></button> <a class="bouton clair" href="<?= e(url('/rapports?rapport=manquants&du=' . date('Y-m-01') . '&au=' . date('Y-m-d'))) ?>"><?= e(t('rapports.type.manquants')) ?></a></div>
</form>

<section class="carte">
  <h2><?= e(t('partage.titre_repartition')) ?></h2>
  <p class="aide"><?= e(t('partage.aide_repartition')) ?></p>
  <?php foreach ($repartitions as $i => $r): ?>
    <p><strong><?= e($r['nom']) ?></strong> — <?= e(t('partage.depuis')) ?> <?= e(jour($r['applicable_du'])) ?> :
      <?= e(implode(' · ', array_map(static fn ($l) => $l['libelle'] . ' ' . (float) $l['pourcentage'] . ' %', $r['lignes']))) ?></p>
  <?php endforeach; ?>
  <form method="post" action="<?= e(url('/parametres/partage/repartition')) ?>">
    <?= champ_csrf() ?>
    <h3><?= e(t('partage.nouvelle_repartition')) ?></h3>
    <div class="grille">
      <label><?= e(t('partage.nom_regle')) ?><input name="nom" maxlength="120"></label>
      <label><?= e(t('partage.depuis')) ?><input type="date" name="date" value="<?= e(date('Y-m-01')) ?>" required></label>
    </div>
    <?php $modele = $repartitions[0]['lignes'] ?? []; for ($n = 0; $n < max(6, count($modele) + 2); $n++): ?>
    <div class="ligne-form">
      <input name="libelle[]" maxlength="120" value="<?= e($modele[$n]['libelle'] ?? '') ?>" placeholder="<?= e(t('repartition.beneficiaire')) ?>" aria-label="<?= e(t('repartition.beneficiaire')) ?> <?= $n + 1 ?>">
      <input name="pourcentage[]" inputmode="decimal" size="6" value="<?= e(isset($modele[$n]) ? (float) $modele[$n]['pourcentage'] : '') ?>" placeholder="%" aria-label="% <?= $n + 1 ?>">
    </div>
    <?php endfor; ?>
    <p class="aide"><?= e(t('partage.total_cent')) ?></p>
    <div class="boutons"><button class="bouton"><?= e(t('commun.enregistrer')) ?></button></div>
  </form>
</section>
