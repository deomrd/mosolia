<?php use AvecXY\Domaine\Montant; $tv = $td = $tm = $te = 0; ?>
<header class="carte tete-page">
  <div>
    <h1><?= e(t('menu.groupe.controle')) ?> · <?= e(t('menu.controle_historique')) ?></h1>
    <p class="aide"><?= e(t('controle.historique_aide')) ?></p>
  </div>
</header>

<form class="carte filtres" method="get" action="<?= e(url('/controle/historique')) ?>" data-filtre-auto>
  <label><?= e(t('commun.du')) ?><input type="date" name="du" value="<?= e($du) ?>"></label>
  <label><?= e(t('commun.au')) ?><input type="date" name="au" value="<?= e($au) ?>"></label>
  <label><?= e(t('menu.recolteurs')) ?>
    <select name="recolteur"><option value=""><?= e(t('commun.tous')) ?></option>
      <?php foreach ($recolteurs as $r): ?><option value="<?= e($r['id']) ?>"<?= $agent === (int) $r['id'] ? ' selected' : '' ?>><?= e($r['code'] . ' · ' . $r['nom']) ?></option><?php endforeach; ?>
    </select>
  </label>
  <noscript><button class="bouton clair"><?= e(t('commun.afficher')) ?></button></noscript>
</form>

<section class="carte" data-resultats>
  <?php if (!$lignes): ?><p class="aide"><?= e(t('commun.aucun_resultat')) ?></p><?php else: ?>
  <div class="defile"><table>
    <thead><tr><th><?= e(t('rapports.colonne.quand')) ?></th><th><?= e(t('menu.recolteurs')) ?></th><th class="tel-cache"><?= e(t('controle.type')) ?></th><th class="tel-cache nombre"><?= e(t('controle.valides')) ?></th><th class="tel-cache nombre"><?= e(t('controle.rejetes')) ?></th><th class="nombre"><?= e(t('controle.depose')) ?></th><th class="tel-cache nombre"><?= e(t('remises.manquant')) ?></th><th class="tel-cache nombre"><?= e(t('remises.excedent')) ?></th><th class="tel-cache"><?= e(t('controle.par')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($lignes as $l):
        $tv += Montant::enCentimes($l['montant_valide']); $td += Montant::enCentimes($l['montant_depose']); $tm += Montant::enCentimes($l['manquant']); $te += Montant::enCentimes($l['excedent']); ?>
      <tr>
        <td><?= e(date('d/m/Y H:i', strtotime($l['cree_le']))) ?></td>
        <td><span class="code"><?= e($l['recolteur_code']) ?></span> <?= e($l['recolteur_nom']) ?></td>
        <td><?= e(t('menu.controle_' . strtolower($l['type']))) ?></td>
        <td class="nombre"><?= (int) $l['valides'] > 0 ? e($l['valides'] . ' · ' . argent($l['montant_valide'])) : '—' ?></td>
        <td class="nombre"><?= (int) $l['rejetes'] > 0 ? e($l['rejetes'] . ' · ' . argent($l['montant_rejete'])) : '—' ?></td>
        <td class="nombre"><?= $l['montant_depose'] !== null ? e(argent($l['montant_depose'])) : '—' ?></td>
        <td class="nombre"><?= (float) $l['manquant'] > 0 ? '<strong class="danger">' . e(argent($l['manquant'])) . '</strong>' : '—' ?></td>
        <td class="nombre"><?= (float) $l['excedent'] > 0 ? e(argent($l['excedent'])) : '—' ?></td>
        <td><?= e($l['auteur_nom'] ?? '—') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr><th colspan="3"><?= e(t('rapports.total')) ?></th><th class="nombre"><?= e(argent($tv / 100)) ?></th><th></th><th class="nombre"><?= e(argent($td / 100)) ?></th><th class="nombre"><?= e(argent($tm / 100)) ?></th><th class="nombre"><?= e(argent($te / 100)) ?></th><th></th></tr></tfoot>
  </table></div>
  <?php endif; ?>
</section>
