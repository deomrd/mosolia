<?php
// Filtres communs : periode et agence. Attend $f (du, au, agence, agences) et $action ; $extra = champs en plus.
?>
<form class="carte filtres" method="get" action="<?= e(url($action)) ?>">
  <?= $extra ?? '' ?>
  <label><?= e(t('commun.du')) ?><input type="date" name="du" value="<?= e($f['du']) ?>"></label>
  <label><?= e(t('commun.au')) ?><input type="date" name="au" value="<?= e($f['au']) ?>"></label>
  <?php if ($f['agences']): ?>
  <label><?= e(t('agences.agence')) ?>
    <select name="agence">
      <option value=""><?= e(t('agences.toutes')) ?></option>
      <option value="0"<?= $f['agence'] === 0 ? ' selected' : '' ?>><?= e(t('agences.siege')) ?></option>
      <?php foreach ($f['agences'] as $ag): ?><option value="<?= e($ag['id']) ?>"<?= $f['agence'] === (int) $ag['id'] ? ' selected' : '' ?>><?= e($ag['nom']) ?></option><?php endforeach; ?>
    </select>
  </label>
  <?php endif; ?>
  <button class="bouton clair"><?= e(t('commun.afficher')) ?></button>
  <a class="bouton clair seulement-ordinateur" href="<?= e(url('/comptabilite/export?' . http_build_query(['du' => $f['du'], 'au' => $f['au'], 'agence' => $f['agence'] ?? '']))) ?>"><?= e(t('compta.exporter')) ?></a>
</form>
