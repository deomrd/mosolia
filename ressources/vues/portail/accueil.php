<?php $compte = \AvecXY\Noyau\Noyau::instance()->comptePortail;
$enAttente = (float) $attente['cotisations'] + (float) $attente['paiements']; ?>
<header class="carte tete-page">
  <div>
    <h1><?= e($compte['nom']) ?></h1>
    <p class="aide"><?= e(t('portail.recolteur', ['nom' => $compte['recolteur_nom']])) ?><?= $compte['recolteur_telephone'] ? ' · ' . e($compte['recolteur_telephone']) : '' ?></p>
  </div>
</header>

<section class="carte">
  <div class="tuiles">
    <div class="tuile fort"><span><?= e(t('portail.mon_epargne')) ?></span><strong><?= e(argent($epargne / 100)) ?></strong><small><?= e(t('portail.epargne_validee')) ?></small></div>
    <?php if ($enAttente > 0): ?><div class="tuile attente"><span><?= e(t('portail.en_attente_validation')) ?></span><strong><?= e(argent($enAttente)) ?></strong><small><?= e(t('portail.en_attente_aide')) ?></small></div><?php endif; ?>
    <?php if ($du > 0): ?><a class="tuile" href="<?= e(url('/espace-client/credits')) ?>"><span><?= e(t('portail.credit_a_rembourser')) ?></span><strong><?= e(argent($du / 100)) ?></strong></a><?php endif; ?>
  </div>
</section>

<section class="carte">
  <div class="tete-section">
    <h2><?= e(t('portail.mes_carnets')) ?></h2>
    <a class="bouton clair" href="<?= e(url('/espace-client/carnets')) ?>"><?= e(t('portail.tous_carnets')) ?></a>
  </div>
  <?php if (!$carnets): ?><p class="aide"><?= e(t('clients.aucun_carnet')) ?></p><?php else: ?>
  <div class="defile"><table>
    <thead><tr><th><?= e(t('carnets.carnet')) ?></th><th class="nombre"><?= e(t('portail.epargne')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($carnetsAffiches ?? $carnets as $k): ?>
      <tr>
        <td><a href="<?= e(url('/espace-client/carnets/' . $k['id'])) ?>"><strong>#<?= e($k['numero']) ?></strong> <?= e($k['type_nom']) ?></a><br><small><?= e(jour($k['date_ouverture'])) ?><?= $k['mode'] === 'PARTS' ? ' · ' . e($k['parts_validees'] . ' / ' . $k['nombre_parts']) : '' ?></small>
          <br><?php if ($k['validation'] !== 'VALIDE'): ?><span class="pastille ambre"><?= e(t('portail.en_attente')) ?></span>
          <?php else: ?><span class="pastille <?= $k['statut'] === 'ACTIF' ? 'verte' : 'grise' ?>"><?= e(t('carnets.statut.' . strtolower($k['statut']))) ?></span><?php endif; ?></td>
        <td class="nombre"><strong><?= e(argent($k['epargne'])) ?></strong><?php if ((float) $k['en_attente'] > 0): ?><br><small class="attente-texte">+ <?= e(argent($k['en_attente'])) ?> <?= e(t('portail.en_attente_court')) ?></small><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>

<section class="carte">
  <div class="tete-section">
    <h2><?= e(t('portail.dernieres_operations')) ?></h2>
    <a class="bouton clair" href="<?= e(url('/espace-client/historique')) ?>"><?= e(t('portail.tout_historique')) ?></a>
  </div>
  <?php $historique = $dernieres; require __DIR__ . '/_operations.php'; ?>
</section>

<?php if ($credit): ?>
<section class="carte">
  <div class="tete-section">
    <h2><?= e(t('portail.mes_credits')) ?></h2>
    <a class="bouton clair" href="<?= e(url('/espace-client/credits')) ?>"><?= e(t('portail.credits_et_demandes')) ?></a>
  </div>
  <?php if (!$credits && !$demandes): ?><p class="aide"><?= e(t('portail.aucun_credit')) ?></p><?php endif; ?>
  <?php foreach ($demandes as $d): if ($d['statut'] !== 'PROPOSE') { continue; } ?>
    <p class="message message-info"><?= e(t('portail.demande_en_attente', ['montant' => argent($d['montant_demande']), 'date' => jour(substr((string) $d['cree_le'], 0, 10))])) ?></p>
  <?php endforeach; ?>
  <?php if ($credits): ?>
  <div class="defile"><table>
    <thead><tr><th>#</th><th><?= e(t('credits.echeance')) ?></th><th class="nombre"><?= e(t('credits.restant')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach (array_slice($credits, 0, 5) as $cr): ?>
      <tr>
        <td><a href="<?= e(url('/espace-client/credits/' . $cr['id'])) ?>"><strong>#<?= e($cr['numero']) ?></strong></a><br><small><?= e(argent($cr['montant'])) ?></small></td>
        <td><?= e(jour($cr['date_echeance'])) ?></td>
        <td class="nombre"><strong><?= e(argent($cr['restant'])) ?></strong></td>
        <td><span class="pastille <?= ['EN_COURS' => 'verte', 'EN_RETARD' => 'rouge'][$cr['statut']] ?? 'grise' ?>"><?= e(t('credits.statut.' . strtolower($cr['statut']))) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
<?php endif; ?>

