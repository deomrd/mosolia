<header class="carte tete-page">
  <div>
    <h1><?= e(t('portail.mes_carnets')) ?></h1>
    <p class="aide"><?= e(t('portail.carnets_aide')) ?></p>
  </div>
</header>
<?php if (!$carnets): ?><section class="carte"><p class="aide"><?= e(t('clients.aucun_carnet')) ?></p></section><?php endif; ?>
<div class="cartes-carnet">
<?php foreach ($carnets as $k): $actif = $k['statut'] === 'ACTIF'; $parts = $k['mode'] === 'PARTS';
  $pct = $parts && (int) $k['nombre_parts'] > 0 ? min(100, (int) round((int) $k['parts_validees'] * 100 / (int) $k['nombre_parts'])) : 0; ?>
  <a class="carte carnet-client<?= $actif ? '' : ' ferme' ?>" href="<?= e(url('/espace-client/carnets/' . $k['id'])) ?>">
    <div class="tete-section">
      <strong>#<?= e($k['numero']) ?> <?= e($k['type_nom']) ?></strong>
      <?php if ($k['validation'] !== 'VALIDE'): ?><span class="pastille ambre"><?= e(t('portail.en_attente')) ?></span>
      <?php else: ?><span class="pastille <?= $actif ? 'verte' : 'grise' ?>"><?= e(t('carnets.statut.' . strtolower($k['statut']))) ?></span><?php endif; ?>
    </div>
    <span class="montant"><?= e(argent($k['epargne'])) ?></span>
    <?php if ((float) $k['en_attente'] > 0): ?><span class="attente-texte">+ <?= e(argent($k['en_attente'])) ?> <?= e(t('portail.en_attente_court')) ?></span><?php endif; ?>
    <?php if ($parts): ?>
      <span class="jauge" role="img" aria-label="<?= e($k['parts_validees'] . ' / ' . $k['nombre_parts']) ?>"><i style="width:<?= e($pct) ?>%"></i></span>
      <small><?= e(t('portail.avancement', ['fait' => $k['parts_validees'], 'total' => $k['nombre_parts'], 'mise' => argent($k['mise'])])) ?></small>
    <?php endif; ?>
    <small><?= e(t('carnets.ouvert_le', ['date' => jour($k['date_ouverture'])])) ?></small>
  </a>
<?php endforeach; ?>
</div>
