<?php
/** @var array $lignesControle regle, ok, attendu, constate */
$valeur = static function (string $regle, string $v): string {
    if (in_array($regle, ['montant_minimum', 'montant_maximum', 'multiple_epargne', 'plafond_encours', 'garantie'], true)) { return argent($v); }
    if (in_array($regle, ['carnet_actif', 'sans_retard'], true)) { return t($v === '1' ? 'commun.oui' : 'commun.non'); }
    return $v;
};
?>
<div class="defile"><table>
  <thead><tr><th><?= e(t('controle.regle')) ?></th><th class="nombre"><?= e(t('controle.attendu')) ?></th><th class="nombre"><?= e(t('controle.constate')) ?></th><th></th></tr></thead>
  <tbody>
  <?php foreach ($lignesControle as $l): ?>
    <tr>
      <td><?= e(t('controle.' . $l['regle'])) ?></td>
      <td class="nombre"><?= e($valeur($l['regle'], $l['attendu'])) ?></td>
      <td class="nombre"><?= e($valeur($l['regle'], $l['constate'])) ?></td>
      <td><span class="pastille <?= $l['ok'] ? 'verte' : 'rouge' ?>"><?= e(t($l['ok'] ? 'controle.ok' : 'controle.non')) ?></span></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
