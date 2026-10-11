<?php $action = '/comptabilite/ecritures'; ?>
<header class="carte tete-page">
  <div><h1><?= e(t('menu.compta_ecritures')) ?></h1><p class="aide"><?= e(t('compta.ecritures_aide')) ?></p></div>
</header>

<?php if (peut('compta.saisir')): ?>
<form class="carte" method="post" action="<?= e(url('/comptabilite/ecritures')) ?>" data-ecriture
      data-message-ecart="<?= e(t('compta.ecart')) ?>" data-message-ok="<?= e(t('compta.equilibree')) ?>">
  <?= champ_csrf() ?>
  <h2><?= e(t('compta.nouvelle_ecriture')) ?></h2>
  <div class="grille">
    <label><?= e(t('commun.date')) ?><input type="date" name="date" value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>" required></label>
    <label><?= e(t('compta.piece')) ?><input name="piece" maxlength="40"><small><?= e(t('compta.piece_aide')) ?></small></label>
    <label><?= e(t('compta.libelle')) ?><input name="libelle" maxlength="255" required></label>
    <?php $agences = $f['agences']; $agenceChoisie = $f['agence'] ?? 0; $libelle = t('agences.agence'); $aideAgence = ''; $nomChamp = 'agence_id'; require __DIR__ . '/../commun/_choix_agence.php'; ?>
  </div>
  <div class="lignes-ecriture">
    <div class="ligne-ecriture entete" aria-hidden="true"><span><?= e(t('compta.compte')) ?></span><span><?= e(t('compta.debit')) ?></span><span><?= e(t('compta.credit')) ?></span></div>
    <?php for ($i = 0; $i < 6; $i++): ?>
    <div class="ligne-ecriture">
      <select name="compte[]" aria-label="<?= e(t('compta.compte')) ?>">
        <option value="0"></option>
        <?php foreach ($comptes as $co): ?><option value="<?= e($co['id']) ?>"><?= e($co['numero'] . ' · ' . $co['libelle']) ?></option><?php endforeach; ?>
      </select>
      <input name="debit[]" inputmode="decimal" aria-label="<?= e(t('compta.debit')) ?>" placeholder="<?= e(t('compta.debit')) ?>" data-debit>
      <input name="credit[]" inputmode="decimal" aria-label="<?= e(t('compta.credit')) ?>" placeholder="<?= e(t('compta.credit')) ?>" data-credit>
    </div>
    <?php endfor; ?>
  </div>
  <p class="aide" data-equilibre role="status"></p>
  <p class="aide"><?= e(t('compta.exemple_ecriture')) ?></p>
  <p class="espace"><button class="bouton"><?= e(t('commun.enregistrer')) ?></button></p>
</form>
<?php endif; ?>

<?php require __DIR__ . '/_filtres.php'; ?>

<section class="carte">
  <h2><?= e(t('compta.ecritures_saisies')) ?></h2>
  <?php if (!$ecritures): ?><p class="aide"><?= e(t('compta.aucune_ecriture')) ?></p><?php else: ?>
  <div class="defile"><table class="etat">
    <thead><tr><th class="tel-cache"><?= e(t('commun.date')) ?></th><th><?= e(t('compta.libelle')) ?></th><th><?= e(t('compta.compte')) ?></th><th class="nombre"><?= e(t('compta.debit')) ?></th><th class="nombre"><?= e(t('compta.credit')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($ecritures as $ec): foreach ($ec['lignes'] as $i => $l): ?>
      <tr<?= $i === 0 ? ' class="debut-ecriture"' : '' ?>>
        <td><?= $i === 0 ? e(jour($ec['date_ecriture'])) : '' ?></td>
        <td><?php if ($i === 0): ?><strong><?= e($ec['libelle']) ?></strong><?= $ec['piece'] ? ' · ' . e($ec['piece']) : '' ?><br><small><?= e(($ec['auteur'] ?? '') . ($ec['agence_nom'] ? ' · ' . $ec['agence_nom'] : '')) ?></small><?php endif; ?></td>
        <td><span class="code-compte"><?= e($l['numero']) ?></span> <?= e($l['libelle']) ?></td>
        <td class="nombre"><?= (float) $l['debit'] > 0 ? e(argent($l['debit'])) : '' ?></td>
        <td class="nombre"><?= (float) $l['credit'] > 0 ? e(argent($l['credit'])) : '' ?></td>
        <td class="actions"><?php if ($i === 0 && peut('compta.saisir')): ?>
          <form method="post" action="<?= e(url('/comptabilite/ecritures/' . $ec['id'] . '/supprimer')) ?>" data-confirmer="<?= e(t('compta.confirmer_suppression')) ?>"><?= champ_csrf() ?><button class="lien danger"><?= e(t('commun.supprimer')) ?></button></form>
        <?php endif; ?></td>
      </tr>
    <?php endforeach; endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
