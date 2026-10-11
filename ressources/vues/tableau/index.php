<?php
use AvecXY\Domaine\Montant;

$c = \AvecXY\Noyau\Noyau::instance()->contexte;
$etat = $c->abonnement;
$net = max(0, Montant::enCentimes($x['part_recolteurs']) - ($deduire ? Montant::enCentimes($x['manquants']) : 0));
$totalAttente = array_sum($attente);
$aujourdhui = date('Y-m-d');
$periode = $du === $au ? jour($du) : jour($du) . ' – ' . jour($au);
$peutControler = peut('carnets.valider') || peut('cotisations.valider') || peut('remboursements.valider');
?>
<header class="carte tete-page">
  <div>
    <h1><?= e(t('menu.tableau')) ?></h1>
    <p class="aide"><?= e(t('tableau.bienvenue', ['nom' => $c->utilisateur['nom']])) ?> · <span data-resultats><?= e($periode) ?></span></p>
  </div>
  <?php if (peut('cotisations.saisir')): ?><a class="bouton" href="<?= e(url('/collecte')) ?>"><?= e(t('menu.collecte')) ?></a><?php endif; ?>
</header>

<?php if (($c->organisation['demarrage'] ?? null) === null && peut('parametres.organisation')): ?>
<section class="carte">
  <h2><?= e(t('reprise.choix_titre')) ?></h2>
  <p class="aide"><?= e(t('reprise.choix_aide')) ?></p>
  <div class="choix-demarrage">
    <div class="carte">
      <h3><?= e(t('reprise.choix_reprise')) ?></h3>
      <p class="aide"><?= e(t('reprise.choix_reprise_aide')) ?></p>
      <div class="boutons"><a class="bouton" href="<?= e(url('/parametres/reprise')) ?>"><?= e(t('reprise.choix_reprise_bouton')) ?></a></div>
    </div>
    <form class="carte" method="post" action="<?= e(url('/parametres/reprise/vide')) ?>">
      <?= champ_csrf() ?>
      <h3><?= e(t('reprise.choix_vide')) ?></h3>
      <p class="aide"><?= e(t('reprise.choix_vide_aide')) ?></p>
      <div class="boutons"><button class="bouton clair"><?= e(t('reprise.choix_vide_bouton')) ?></button></div>
    </form>
  </div>
</section>
<?php endif; ?>

<form class="carte filtres" method="get" action="<?= e(url('/')) ?>" data-filtre-auto>
  <label><?= e(t('commun.du')) ?><input type="date" name="du" value="<?= e($du) ?>" max="<?= e($aujourdhui) ?>"></label>
  <label><?= e(t('commun.au')) ?><input type="date" name="au" value="<?= e($au) ?>" max="<?= e($aujourdhui) ?>"></label>
  <a class="bouton clair" href="<?= e(url('/?jour=' . $aujourdhui)) ?>"><?= e(t('tableau.aujourdhui')) ?></a>
  <a class="bouton clair" href="<?= e(url('/')) ?>"><?= e(t('tableau.ce_mois')) ?></a>
  <noscript><button class="bouton clair"><?= e(t('commun.afficher')) ?></button></noscript>
</form>

<div data-resultats class="pile">
<?php if ($recolteur !== null): ?>
<section class="carte">
  <h2><?= e(t('tableau.mon_solde')) ?> <span class="code"><?= e($recolteur['code']) ?></span></h2>
  <div class="tuiles">
    <div class="tuile fort"><span><?= e(t('tableau.ma_part')) ?></span><strong><?= e(argent($x['part_recolteurs'])) ?></strong></div>
    <div class="tuile"><span><?= e(t('paiements.manquants')) ?></span><strong><?= e(argent($x['manquants'])) ?></strong></div>
    <div class="tuile"><span><?= e(t('paiements.net')) ?></span><strong><?= e(argent($net / 100)) ?></strong><?php if ((float) $x['deja_paye'] > 0): ?><small><?= e(t('tableau.deja_paye', ['montant' => argent($x['deja_paye'])])) ?></small><?php endif; ?></div>
  </div>
  <p class="aide"><?= e(t('tableau.solde_valide_seulement')) ?></p>
</section>
<?php endif; ?>

<section class="carte">
  <div class="tete-section">
    <h2><?= e(t('tableau.en_attente')) ?></h2>
    <?php if ($peutControler && $totalAttente > 0): ?><a class="bouton clair" href="<?= e(url('/controle/' . ($attente['cotisations'] > 0 ? 'cotisations' : ($attente['carnets'] > 0 ? 'carnets' : 'paiements')))) ?>"><?= e(t('menu.groupe.controle')) ?></a><?php endif; ?>
  </div>
  <div class="tuiles">
    <div class="tuile"><span><?= e(t('tableau.attente_carnets')) ?></span><strong><?= e(argent($attente['carnets'] / 100)) ?></strong></div>
    <div class="tuile"><span><?= e(t('tableau.attente_cotisations')) ?></span><strong><?= e(argent($attente['cotisations'] / 100)) ?></strong></div>
    <?php if ($credit): ?><div class="tuile"><span><?= e(t('tableau.attente_paiements')) ?></span><strong><?= e(argent($attente['paiements'] / 100)) ?></strong></div><?php endif; ?>
    <div class="tuile<?= $totalAttente > 0 ? ' alerte' : '' ?>"><span><?= e(t($recolteur !== null ? 'tableau.attente_total_recolteur' : 'tableau.attente_total')) ?></span><strong><?= e(argent($totalAttente / 100)) ?></strong></div>
  </div>
  <p class="aide"><?= e(t('tableau.attente_aide')) ?></p>
</section>

<section class="carte">
  <h2><?= e(t($recolteur !== null ? 'tableau.mon_portefeuille' : 'tableau.activite')) ?></h2>
  <div class="tuiles">
    <?php if ($enMain !== null): ?><a class="tuile fort" href="<?= e(url('/caisse')) ?>"><span><?= e(t('caisse.en_main')) ?></span><strong><?= e(argent($enMain)) ?></strong></a><?php endif; ?>
    <div class="tuile"><span><?= e(t('tableau.epargne')) ?></span><strong><?= e(argent($x['epargne'])) ?></strong></div>
    <div class="tuile"><span><?= e(t('tableau.collecte_validee')) ?></span><strong><?= e(argent($x['collecte'])) ?></strong></div>
    <div class="tuile"><span><?= e(t('tableau.ventes_validees')) ?></span><strong><?= e(argent($x['ventes'])) ?></strong></div>
    <a class="tuile" href="<?= e(url('/clients')) ?>"><span><?= e(t('menu.clients')) ?></span><strong><?= e($x['clients']) ?></strong></a>
    <a class="tuile" href="<?= e(url('/carnets')) ?>"><span><?= e(t('tableau.carnets_ouverts')) ?></span><strong><?= e($x['carnets']) ?></strong></a>
  </div>
</section>

<?php if ($credit && peut('credits.voir')): ?>
<section class="carte">
  <h2><?= e(t('menu.credits')) ?></h2>
  <div class="tuiles">
    <a class="tuile" href="<?= e(url('/credits')) ?>"><span><?= e(t('tableau.encours')) ?></span><strong><?= e(argent($x['encours'])) ?></strong></a>
    <a class="tuile<?= (float) $x['en_retard'] > 0 ? ' alerte' : '' ?>" href="<?= e(url('/credits?statut=EN_RETARD')) ?>"><span><?= e(t('tableau.en_retard')) ?></span><strong><?= e(argent($x['en_retard'])) ?></strong></a>
  </div>
  <?php if ($echeances): ?>
  <h3><?= e(t('tableau.echeances')) ?></h3>
  <div class="defile"><table>
    <tbody>
    <?php foreach ($echeances as $cr): ?>
      <tr>
        <td><a href="<?= e(url('/credits/' . $cr['id'])) ?>"><strong>#<?= e($cr['numero']) ?></strong></a></td>
        <td><span class="code"><?= e($cr['client_code']) ?></span> <?= e($cr['client_nom']) ?></td>
        <td><?= e(jour($cr['date_echeance'])) ?><?= $cr['statut'] === 'EN_RETARD' ? ' <span class="pastille rouge">' . e(t('credits.statut.en_retard')) . '</span>' : '' ?></td>
        <td class="nombre"><strong><?= e(argent($cr['restant'])) ?></strong></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if (peut('montants.benefices') && $recolteur === null): ?>
<section class="carte">
  <h2><?= e(t('tableau.benefices')) ?></h2>
  <div class="tuiles">
    <div class="tuile"><span><?= e(t('tableau.benefices_total')) ?></span><strong><?= e(argent($x['benefices'])) ?></strong></div>
    <div class="tuile"><span><?= e(t('tableau.part_organisation')) ?></span><strong><?= e(argent($x['part_organisation'])) ?></strong></div>
    <div class="tuile"><span><?= e(t('tableau.part_recolteurs')) ?></span><strong><?= e(argent($x['part_recolteurs'])) ?></strong></div>
  </div>
</section>
<?php endif; ?>
</div>

<?php if ($c->estAdmin() || peut('abonnement.gerer')): ?>
<section class="carte">
  <h2><?= e(t('tableau.abonnement')) ?></h2>
  <div class="tuiles">
    <div class="tuile"><span><?= e(t('tableau.forfait')) ?></span><strong><?= e($c->forfait['plan_nom'] ?? '—') ?></strong></div>
    <div class="tuile"><span><?= e(t('tableau.fin_periode')) ?></span><strong><?= $etat->fin ? e(jour($etat->fin)) : '—' ?></strong></div>
    <div class="tuile"><span><?= e(t('tableau.jours_restants')) ?></span><strong><?= $etat->joursRestants !== null ? e(max(0, $etat->joursRestants)) : '—' ?></strong></div>
  </div>
</section>
<?php endif; ?>
