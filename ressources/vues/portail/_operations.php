<?php /** @var array $historique */
$signe = ['COTISATION' => '+', 'INTERET' => '+', 'RETRAIT' => '−', 'FRAIS' => '−', 'CARNET' => '', 'REMBOURSEMENT' => '', 'CREDIT' => ''];
if (!$historique): ?><p class="aide"><?= e(t('portail.aucune_operation')) ?></p><?php else: ?>
<div class="defile"><table>
  <thead><tr><th><?= e(t('commun.date')) ?></th><th><?= e(t('commun.operation')) ?></th><th class="nombre"><?= e(t('caisse.montant')) ?></th></tr></thead>
  <tbody>
  <?php foreach ($historique as $h): $attente = $h['statut'] === 'EN_ATTENTE'; ?>
    <tr<?= $attente ? ' class="en-attente"' : '' ?>>
      <td><?= e(jour($h['jour'])) ?></td>
      <td><?= e(t('portail.type.' . strtolower($h['type']))) ?><?= $h['parts'] ? ' · ' . e(t('portail.parts', ['n' => $h['parts']])) : '' ?>
        <br><small><?= e(t(in_array($h['type'], ['REMBOURSEMENT', 'CREDIT'], true) ? 'portail.ref_credit' : 'portail.ref_carnet', ['n' => $h['reference']])) ?></small></td>
      <td class="nombre"><strong><?= e($signe[$h['type']]) ?> <?= e(argent($h['montant'])) ?></strong>
        <br><span class="pastille <?= $attente ? 'ambre' : 'verte' ?>"><?= e(t($attente ? 'portail.en_attente' : 'portail.valide')) ?></span></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
