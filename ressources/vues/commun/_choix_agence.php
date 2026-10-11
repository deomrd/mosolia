<?php
// Choix de l'agence (rien quand l'organisation n'a pas d'agence). Attend $agences, $agenceChoisie, $nomChamp, $libelle, $aideAgence.
if (!empty($agences)): ?>
<label><?= e($libelle) ?>
  <select name="<?= e($nomChamp ?? 'agence_id') ?>">
    <option value="0"><?= e(t('agences.siege')) ?></option>
    <?php foreach ($agences as $ag): ?><option value="<?= e($ag['id']) ?>"<?= (int) ($agenceChoisie ?? 0) === (int) $ag['id'] ? ' selected' : '' ?>><?= e($ag['code'] . ' · ' . $ag['nom']) ?></option><?php endforeach; ?>
  </select>
  <?php if (!empty($aideAgence)): ?><small><?= e($aideAgence) ?></small><?php endif; ?>
</label>
<?php endif; ?>
