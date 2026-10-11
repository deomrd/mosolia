<?php $compte = \AvecXY\Noyau\Noyau::instance()->comptePortail; ?>
<section class="carte">
  <h1><?= e(t('portail.mes_comptes')) ?></h1>
  <p class="aide"><?= e(t('portail.comptes_aide')) ?></p>
  <?php if (count($comptes) > 1): ?>
  <div class="boutons">
    <?php foreach ($comptes as $autre): ?>
      <form method="post" action="<?= e(url('/espace-client/basculer')) ?>">
        <?= champ_csrf() ?><input type="hidden" name="compte" value="<?= e($autre['id']) ?>">
        <button class="bouton<?= (int) $autre['id'] === (int) $compte['id'] ? '' : ' clair' ?>"<?= (int) $autre['id'] === (int) $compte['id'] ? ' aria-current="true"' : '' ?>><?= e($autre['code'] . ' · ' . $autre['organisation_nom']) ?></button>
      </form>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <form class="ligne-form" method="post" action="<?= e(url('/espace-client/lier')) ?>" autocomplete="off">
    <?= champ_csrf() ?>
    <label><?= e(t('portail.autre_code')) ?><input name="code" required data-majuscules placeholder="ABCD-1"></label>
    <label><?= e(t('portail.son_code_secret')) ?><input name="secret" type="password" inputmode="numeric" required autocomplete="off"></label>
    <button class="bouton clair"><?= e(t('portail.rattacher')) ?></button>
  </form>
</section>
