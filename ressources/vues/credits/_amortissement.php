<?php
/**
 * Tableau d'amortissement : une ligne par echeance (capital, interet, montant, capital restant du).
 * @var array $lignesAmortissement montants en centimes ; facultatif : paye (centimes) et etat de chaque echeance
 */
$avecSuivi = isset($lignesAmortissement[0]['etat']);
$somme = static fn (string $cle): int => array_sum(array_map(static fn ($l) => (int) ($l[$cle] ?? 0), $lignesAmortissement));
?>
<div class="defile"><table class="amortissement">
  <thead><tr>
    <th class="tel-cache nombre">N°</th><th><?= e(t('credits.echeance_date')) ?></th>
    <th class="nombre masque-tel"><?= e(t('credits.echeance_capital')) ?></th><th class="nombre masque-tel"><?= e(t('credits.echeance_interet')) ?></th>
    <th class="nombre"><?= e(t('credits.echeance_montant')) ?></th><th class="nombre masque-tel"><?= e(t('credits.echeance_restant')) ?></th>
    <?php if ($avecSuivi): ?><th class="nombre"><?= e(t('credits.echeance_paye')) ?></th><th></th><?php endif; ?>
  </tr></thead>
  <tbody>
  <?php foreach ($lignesAmortissement as $l): ?>
    <tr>
      <td class="nombre"><?= e($l['numero']) ?></td>
      <td><?= e(jour($l['date_prevue'])) ?></td>
      <td class="nombre masque-tel"><?= $l['capital'] === null ? '—' : e(argent($l['capital'] / 100)) ?></td>
      <td class="nombre masque-tel"><?= $l['interet'] === null ? '—' : e(argent($l['interet'] / 100)) ?></td>
      <td class="nombre"><strong><?= e(argent($l['montant'] / 100)) ?></strong></td>
      <td class="nombre masque-tel"><?= ($l['restant'] ?? null) === null ? '—' : e(argent($l['restant'] / 100)) ?></td>
      <?php if ($avecSuivi): ?>
        <td class="nombre"><?= e(argent($l['paye'] / 100)) ?></td>
        <td><span class="pastille <?= ['PAYEE' => 'verte', 'EN_RETARD' => 'rouge', 'PARTIELLE' => 'ambre'][$l['etat']] ?? '' ?>"><?= e(t('credits.echeance.' . strtolower($l['etat']))) ?></span></td>
      <?php endif; ?>
    </tr>
  <?php endforeach; ?>
  </tbody>
  <tfoot><tr>
    <td></td><td><?= e(t('commun.total')) ?></td>
    <td class="nombre masque-tel"><?= e(argent($somme('capital') / 100)) ?></td><td class="nombre masque-tel"><?= e(argent($somme('interet') / 100)) ?></td>
    <td class="nombre"><?= e(argent($somme('montant') / 100)) ?></td><td class="masque-tel"></td>
    <?php if ($avecSuivi): ?><td class="nombre"><?= e(argent($somme('paye') / 100)) ?></td><td></td><?php endif; ?>
  </tr></tfoot>
</table></div>
