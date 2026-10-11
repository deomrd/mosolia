<?php /** @var string $typeLigne @var int $idLigne @var string $retourLigne */ ?>
<form method="post" action="<?= e(url('/caisse/' . $typeLigne . '/' . $idLigne . '/supprimer')) ?>" data-confirmer="<?= e(t('caisse.confirmer_suppression')) ?>">
  <?= champ_csrf() ?><input type="hidden" name="retour" value="<?= e($retourLigne ?? '') ?>">
  <button class="lien danger"><?= e(t('commun.supprimer')) ?></button>
</form>
