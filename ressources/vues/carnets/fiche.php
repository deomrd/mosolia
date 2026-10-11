<?php
use AvecXY\Domaine\CalculCarnet;
use AvecXY\Domaine\TypeCarnet;

$parts = $k['mode'] === 'PARTS';
$actif = $k['statut'] === 'ACTIF';
$restantes = $parts ? (int) $k['nombre_parts'] - (int) $k['parts_validees'] - (int) $enAttente : 0;
$echu = $k['date_echeance'] !== null && $k['date_echeance'] < date('Y-m-d');
$bloque = ($k['blocage'] ?? null) !== null;
$messageRetrait = static fn (array $r): string => t((string) $r['raison'], ['n' => $r['n'] ?? $r['tranche'] ?? 0, 'date' => jour($k['date_echeance']), 'montant' => argent(($r['minimum'] ?? 0) / 100)]);
?>
<header class="carte tete-page">
  <div>
    <p class="fil"><a href="<?= e(url('/clients/' . $k['client_id'])) ?>"><?= e($k['client_code'] . ' ' . $k['client_nom']) ?></a></p>
    <h1><?= e(t('carnets.carnet')) ?> #<?= e($k['numero']) ?></h1>
    <p class="aide"><?= e($k['type_nom']) ?> · <?= e(t('carnets.ouvert_le', ['date' => jour($k['date_ouverture'])])) ?> · <?= e($k['date_echeance'] !== null ? t($bloque ? 'carnets.bloque_jusqu_au' : 'carnets.echeance_le', ['date' => jour($k['date_echeance'])]) : t('carnets.sans_duree')) ?></p>
  </div>
  <div class="boutons">
    <?php if ($k['validation'] !== 'VALIDE'): ?><span class="pastille ambre"><?= e(t('carnets.vente_en_attente')) ?></span><?php endif; ?>
    <span class="pastille <?= $actif ? 'verte' : 'grise' ?>"><?= e(t('carnets.statut.' . strtolower($k['statut']))) ?><?= $actif ? '' : ' · ' . e(t('carnets.verrouille_court')) ?><?= $actif && $echu ? ' · ' . e(t('carnets.echu')) : '' ?></span>
  </div>
</header>

<section class="carte">
  <div class="tuiles">
    <div class="tuile fort"><span><?= e(t('carnets.epargne_disponible')) ?></span><strong><?= e(argent($k['epargne'])) ?></strong></div>
    <?php if ($parts): ?>
      <div class="tuile"><span><?= e(t('carnets.parts_cotisees')) ?></span><strong><?= e($k['parts_validees']) ?> / <?= e($k['nombre_parts']) ?></strong><?php if ($enAttente > 0): ?><small><?= e(t('carnets.dont_en_attente', ['n' => $enAttente])) ?></small><?php endif; ?></div>
      <div class="tuile"><span><?= e(t('carnets.mise')) ?></span><strong><?= e(argent($k['mise'])) ?></strong></div>
      <div class="tuile"><span><?= e(t('carnets.tranches')) ?></span><strong><?= e(CalculCarnet::tranchesCompletes($k, (int) $k['parts_validees'])) ?> / <?= e(TypeCarnet::nombreTranches((int) $k['nombre_parts'], (int) $k['taille_tranche'])) ?></strong><small><?= e(t('carnets.tranches_retirees', ['n' => $k['tranches_retirees']])) ?></small></div>
    <?php endif; ?>
    <div class="tuile"><span><?= e(t('carnets.total_cotise')) ?></span><strong><?= e(argent($k['total_cotise'])) ?></strong></div>
    <div class="tuile"><span><?= e(t('carnets.total_retire')) ?></span><strong><?= e(argent($k['total_retire'])) ?></strong></div>
    <?php if ((float) $k['total_frais'] > 0): ?><div class="tuile"><span><?= e(t('carnets.total_frais')) ?></span><strong><?= e(argent($k['total_frais'])) ?></strong></div><?php endif; ?>
    <?php if ($bloque): ?>
      <div class="tuile"><span><?= e(t('carnets.taux_obtenu')) ?></span><strong><?= e(\AvecXY\Domaine\Montant::afficher($k['taux_interet'], 2)) ?> %</strong><small><?= e(t('carnets.taux_base.' . strtolower($k['taux_base'])) . ' · ' . t('carnets.option_resume_court', ['mois' => $k['duree_mois']])) ?></small></div>
      <div class="tuile"><span><?= e(t('carnets.interets_verses')) ?></span><strong><?= e(argent($k['total_interets'])) ?></strong><?php if ($couru > 0): ?><small><?= e(t('carnets.interet_couru', ['montant' => argent($couru / 100)])) ?></small><?php endif; ?></div>
    <?php endif; ?>
    <?php if (($k['solde_minimum'] ?? null) !== null): ?><div class="tuile"><span><?= e(t('carnets.solde_min')) ?></span><strong><?= e(argent($k['solde_minimum'])) ?></strong></div><?php endif; ?>
    <?php if ((int) ($k['parts_reserve'] ?? 0) > 0): ?><div class="tuile"><span><?= e(t('carnets.parts_reserve')) ?></span><strong><?= e(t('carnets.resume_reserve', ['n' => $k['parts_reserve']])) ?></strong><small><?= e(argent((int) $k['parts_reserve'] * (float) $k['mise'])) ?></small></div><?php endif; ?>
  </div>
  <?php if ($bloque && $actif): ?>
    <p class="aide"><?= e(t('carnets.bloque_explication', [
        'blocage' => t('carnets.blocage.' . strtolower($k['blocage'])), 'date' => jour($k['date_echeance']),
        'versement' => t('carnets.versement.' . strtolower($k['interets_versement'])), 'anticipe' => t('carnets.anticipe.' . strtolower($k['retrait_anticipe'])),
    ])) ?>
    <?php if (($k['renouvellement'] ?? 'AUCUN') === 'AUTOMATIQUE'): ?><?= e(t('carnets.renouvele_info', ['n' => (int) ($k['cycle'] ?? 1)])) ?><?php endif; ?></p>
  <?php endif; ?>
  <?php if (!empty($k['reprise_id'])): ?>
  <p class="aide"><strong><?= e(t('reprise.carnet_repris')) ?></strong>
    <?= e($parts ? t('reprise.carnet_repris_parts', ['n' => (int) $k['reprise_parts']]) : t('reprise.carnet_repris_montant', ['montant' => argent($k['reprise_montant'])])) ?>
    <?php if ((float) $k['reprise_retire'] > 0): ?><?= e(t('reprise.carnet_repris_retire', ['montant' => argent($k['reprise_retire'])])) ?><?php endif; ?>
    <?= e(t((int) $k['benefice_deja_pris'] === 1 ? 'reprise.carnet_benefice_pris' : 'reprise.carnet_benefice_a_partager')) ?></p>
  <?php endif; ?>
</section>

<?php if (!$actif): $cloturant = array_filter($retraits, static fn ($r) => (int) $r['ferme_carnet'] === 1); ?>
<section class="carte verrou" role="status">
  <h2><?= e(t('carnets.verrouille_titre')) ?></h2>
  <p><?= e(t('carnets.verrouille_aide', ['date' => jour($k['date_fermeture'])])) ?></p>
  <?php if (peut('retraits.annuler')): ?>
    <?php if ($cloturant): ?><p class="aide"><?= e(t('carnets.rouvrir_par_retrait')) ?></p>
    <?php else: ?>
    <form method="post" action="<?= e(url('/carnets/' . $k['id'] . '/rouvrir')) ?>" data-confirmer="<?= e(t('carnets.confirmer_reouverture')) ?>"><?= champ_csrf() ?><button class="bouton clair"><?= e(t('carnets.rouvrir')) ?></button></form>
    <?php endif; ?>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($actif): ?>
<div class="deux-colonnes">
  <?php if (peut('cotisations.saisir')): ?>
  <form class="carte" method="post" action="<?= e(url('/carnets/' . $k['id'] . '/cotiser')) ?>">
    <?= champ_csrf() ?>
    <h2><?= e(t($parts ? 'cotisations.nouvelle' : 'cotisations.nouveau_depot')) ?></h2>
    <?php if ($parts && $restantes <= 0): ?>
      <p class="aide"><?= e(t('cotisations.carnet_plein')) ?></p>
    <?php else: ?>
    <div class="grille">
      <?php if ($parts): ?>
      <label><?= e(t('cotisations.parts')) ?>
        <input name="valeur" type="number" min="1" max="<?= e($restantes) ?>" value="1" required>
        <small><?= e(t('cotisations.parts_restantes', ['n' => $restantes])) ?></small>
      </label>
      <?php else: ?>
      <label><?= e(t('cotisations.montant')) ?>
        <input name="valeur" inputmode="decimal" required>
      </label>
      <?php endif; ?>
      <label><?= e(t('commun.date')) ?>
        <input name="date" type="date" value="<?= e(date('Y-m-d')) ?>" min="<?= e($k['date_ouverture']) ?>" max="<?= e(date('Y-m-d')) ?>" required>
      </label>
    </div>
    <label class="espace"><?= e(t('commun.commentaire')) ?><input name="commentaire" maxlength="255"></label>
    <p class="espace"><button class="bouton"><?= e(t('commun.enregistrer')) ?></button></p>
    <?php if (!peut('cotisations.valider')): ?><p class="aide"><?= e(t('cotisations.sera_controlee')) ?></p><?php endif; ?>
    <?php endif; ?>
  </form>
  <?php endif; ?>

  <?php if (peut('retraits.faire')): ?>
  <form class="carte" method="post" action="<?= e(url('/carnets/' . $k['id'] . '/retirer')) ?>" data-confirmer="<?= e(t('retraits.confirmer')) ?>">
    <?= champ_csrf() ?>
    <h2><?= e(t('retraits.nouveau')) ?></h2>
    <?php if (!$retrait['possible']): ?>
      <p class="aide"><?= e($messageRetrait($retrait)) ?></p>
    <?php else: ?>
      <?php if ($retrait['forme'] === 'LIBRE'): ?>
        <p class="aide"><?= e(t('retraits.libre_aide', ['max' => argent(($retrait['max'] ?? \AvecXY\Domaine\Montant::enCentimes($k['epargne'])) / 100)])) ?></p>
        <div class="grille">
          <label><?= e(t('retraits.montant')) ?><input name="montant" inputmode="decimal" required></label>
          <label><?= e(t('commun.date')) ?><input name="date" type="date" value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>" required></label>
        </div>
      <?php else: ?>
        <p><?= e(t($retrait['anticipe'] ? 'retraits.anticipe_info' : ($retrait['forme'] === 'TRANCHE' ? 'retraits.tranche_info' : 'retraits.total_info'), ['n' => $retrait['tranche'] ?? 0, 'date' => jour($k['date_echeance']), 'penalite' => argent($retrait['frais'] / 100)])) ?></p>
        <div class="tuiles">
          <div class="tuile fort"><span><?= e(t('retraits.a_remettre')) ?></span><strong><?= e(argent($retrait['net'] / 100)) ?></strong><?php if ($retrait['frais'] > 0): ?><small><?= e(t('retraits.dont_frais', ['frais' => argent($retrait['frais'] / 100)])) ?></small><?php endif; ?></div>
        </div>
        <label class="espace"><?= e(t('commun.date')) ?><input name="date" type="date" value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>" required></label>
        <?php if ($retrait['ferme']): ?><p class="aide"><?= e(t('retraits.fermera')) ?></p><?php endif; ?>
      <?php endif; ?>
      <label class="espace"><?= e(t('commun.commentaire')) ?><input name="commentaire" maxlength="255"></label>
      <p class="espace"><button class="bouton"><?= e(t('retraits.bouton')) ?></button></p>
    <?php endif; ?>
  </form>
  <?php endif; ?>
</div>
<?php endif; ?>

<section class="carte">
  <h2><?= e(t($parts ? 'menu.cotisations_court' : 'cotisations.depots')) ?></h2>
  <?php if (!$cotisations): ?><p class="aide"><?= e(t('cotisations.aucune')) ?></p><?php else: ?>
  <div class="defile"><table>
    <thead><tr>
      <th><?= e(t('commun.date')) ?></th><?php if ($parts): ?><th class="nombre"><?= e(t('cotisations.parts')) ?></th><?php endif; ?>
      <th class="nombre"><?= e(t('cotisations.montant')) ?></th><th class="tel-cache nombre"><?= e(t('cotisations.benefice')) ?></th>
      <th class="tel-cache nombre"><?= e(t('clients.epargne')) ?></th><th><?= e(t('utilisateurs.statut')) ?></th><th class="tel-cache"><?= e(t('cotisations.saisie_par')) ?></th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($cotisations as $o): ?>
      <tr>
        <td><?= e(jour($o['date_cotisation'])) ?></td>
        <?php if ($parts): ?><td class="nombre"><?= e($o['nombre_parts']) ?></td><?php endif; ?>
        <td class="nombre"><?= e(argent($o['montant_total'])) ?></td>
        <td class="nombre"><?= $o['statut'] === 'VALIDE' ? e(argent($o['montant_benefice'])) : '—' ?></td>
        <td class="nombre"><?= $o['statut'] === 'VALIDE' ? e(argent($o['montant_epargne'])) : '—' ?></td>
        <td><span class="pastille <?= ['VALIDE' => 'verte', 'EN_ATTENTE' => 'ambre', 'REJETE' => 'rouge'][$o['statut']] ?? '' ?>"><?= e(t('operation.statut.' . strtolower($o['statut']))) ?></span></td>
        <td><?= e($o['auteur_nom'] ?? '—') ?></td>
        <td class="actions"><?php if ($o['modifiable']): ?><a href="<?= e(url('/cotisations/' . $o['id'])) ?>"><?= e(t('commun.corriger')) ?></a><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>

<?php if ($retraits || $frais): ?>
<section class="carte">
  <h2><?= e(t('carnets.retraits_et_frais')) ?></h2>
  <div class="defile"><table>
    <thead><tr><th><?= e(t('commun.date')) ?></th><th><?= e(t('commun.operation')) ?></th><th class="nombre"><?= e(t('cotisations.montant')) ?></th><th class="tel-cache nombre"><?= e(t('retraits.frais')) ?></th><th class="tel-cache"><?= e(t('commun.commentaire')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($retraits as $r): ?>
      <tr>
        <td><?= e(jour($r['date_retrait'])) ?></td>
        <td><?= e($r['tranche'] ? t('retraits.de_tranche', ['n' => $r['tranche']]) : t('retraits.retrait')) ?><?= (int) $r['ferme_carnet'] === 1 ? ' · ' . e(t('retraits.a_ferme')) : '' ?></td>
        <td class="nombre"><strong><?= e(argent($r['montant_retire'])) ?></strong></td>
        <td class="nombre"><?= e(argent($r['frais'])) ?></td>
        <td><?= e($r['commentaire'] ?? '') ?></td>
        <td class="actions"><?php if (peut('retraits.annuler') && ($actif || (int) $r['ferme_carnet'] === 1)): ?>
          <form class="ligne-form" method="post" action="<?= e(url('/retraits/' . $r['id'] . '/supprimer')) ?>" data-confirmer="<?= e(t((int) $r['ferme_carnet'] === 1 ? 'retraits.confirmer_reouverture' : 'retraits.confirmer_suppression')) ?>"><?= champ_csrf() ?>
            <input name="motif" required maxlength="255" placeholder="<?= e(t('corbeille.motif')) ?>" aria-label="<?= e(t('corbeille.motif')) ?>">
            <button class="lien danger"><?= e(t((int) $r['ferme_carnet'] === 1 ? 'retraits.annuler_et_rouvrir' : 'retraits.annuler')) ?></button></form>
        <?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    <?php foreach ($frais as $f): if ($f['retrait_id'] !== null) { continue; } ?>
      <tr><td><?= e(jour($f['date_frais'])) ?></td><td><?= e(t('carnets.frais_origine.' . strtolower($f['origine']))) ?></td><td class="nombre">—</td><td class="nombre"><?= e(argent($f['montant'])) ?></td><td></td><td></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php endif; ?>

<?php if (!empty($interets)): ?>
<section class="carte">
  <h2><?= e(t('carnets.interets_verses')) ?></h2>
  <div class="defile"><table>
    <thead><tr><th><?= e(t('commun.date')) ?></th><th><?= e(t('carnets.periode_interet')) ?></th><th class="nombre"><?= e(t('cotisations.montant')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($interets as $i): ?>
      <tr><td><?= e(jour($i['date_interet'])) ?></td><td><?= e(jour($i['du_jour']) . ' → ' . jour($i['au_jour'])) ?></td><td class="nombre"><strong><?= e(argent($i['montant'])) ?></strong></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php endif; ?>

<?php if ($actif && (peut('retraits.faire') || peut('carnets.supprimer'))): ?>
<section class="carte">
  <h2><?= e(t('commun.autres_actions')) ?></h2>
  <div class="deux-colonnes">
  <?php if ($actif && peut('retraits.faire')): ?>
  <form method="post" action="<?= e(url('/carnets/' . $k['id'] . '/fermer')) ?>" data-confirmer="<?= e(t('carnets.confirmer_fermeture')) ?>">
    <?= champ_csrf() ?><input type="hidden" name="date" value="<?= e(date('Y-m-d')) ?>">
    <p class="aide"><?= e(t('carnets.fermer_aide')) ?></p>
    <button class="bouton clair"><?= e(t('carnets.fermer')) ?></button>
  </form>
  <?php endif; ?>
  <?php if (peut('carnets.supprimer')): ?>
  <form method="post" action="<?= e(url('/carnets/' . $k['id'] . '/supprimer')) ?>" data-confirmer="<?= e(t('carnets.confirmer_suppression')) ?>">
    <?= champ_csrf() ?>
    <p class="aide"><?= e(t('carnets.supprimer_aide')) ?></p>
    <label><?= e(t('corbeille.motif')) ?><input name="motif" maxlength="255"></label>
    <p class="espace"><button class="bouton danger"><?= e(t('commun.supprimer')) ?></button></p>
  </form>
  <?php endif; ?>
  </div>
</section>
<?php endif; ?>
