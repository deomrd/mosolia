<?php use AvecXY\Application\ServiceComptabilite; $id = (int) $compte['id']; ?>
<form method="post" action="<?= e(url('/comptabilite/plan/' . $id)) ?>">
<?= champ_csrf() ?>
<header class="carte tete-page">
  <div>
    <p class="fil"><a href="<?= e(url('/comptabilite/plan')) ?>"><?= e(t('menu.compta_plan')) ?></a></p>
    <h1><?= e($id === 0 ? t('compta.nouveau_compte') : $compte['numero'] . ' ' . $compte['libelle']) ?></h1>
    <?php if ($compte['cle']): ?><p class="aide"><?= e(t('compta.compte_porte_role', ['role' => t('compta.role.' . $compte['cle'])])) ?></p><?php endif; ?>
  </div>
  <div class="boutons">
    <a class="bouton clair" href="<?= e(url('/comptabilite/plan')) ?>"><?= e(t('commun.annuler')) ?></a>
    <button class="bouton"><?= e(t('commun.enregistrer')) ?></button>
  </div>
</header>
<section class="carte">
  <div class="grille">
    <label><?= e(t('compta.numero')) ?><input name="numero" value="<?= e($compte['numero']) ?>" required maxlength="20"></label>
    <label><?= e(t('compta.libelle')) ?><input name="libelle" value="<?= e($compte['libelle']) ?>" required maxlength="160" data-majuscules></label>
    <label><?= e(t('compta.nature')) ?>
      <select name="nature"<?= $compte['cle'] ? ' disabled' : '' ?>>
        <?php foreach (ServiceComptabilite::NATURES as $n): ?><option value="<?= e($n) ?>"<?= $compte['nature'] === $n ? ' selected' : '' ?>><?= e(t('compta.nature.' . strtolower($n))) ?></option><?php endforeach; ?>
      </select>
      <?php if ($compte['cle']): ?><input type="hidden" name="nature" value="<?= e($compte['nature']) ?>"><?php endif; ?>
    </label>
  </div>
  <label class="case"><input type="checkbox" name="actif" value="1"<?= (string) $compte['actif'] === '1' ? ' checked' : '' ?><?= $compte['cle'] ? ' disabled' : '' ?>><span><?= e(t('compta.compte_actif')) ?></span></label>
  <?php if ($compte['cle']): ?><input type="hidden" name="actif" value="1"><?php endif; ?>
</section>
</form>
