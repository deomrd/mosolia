<header class="carte tete-page">
  <div>
    <h1><?= e(t('menu.journal_caisse')) ?><?= $nomAgence !== null ? ' · ' . e($nomAgence) : '' ?></h1>
    <p class="aide"><?= e(t('caisse.aide')) ?></p>
  </div>
</header>

<section class="carte">
  <div class="tuiles">
    <div class="tuile"><span><?= e(t('caisse.en_main')) ?></span><strong><?= e(argent($enMain)) ?></strong></div>
    <div class="tuile"><span><?= e(t('caisse.ouverture', ['date' => jour($du)])) ?></span><strong><?= e(argent($ouverture)) ?></strong></div>
    <div class="tuile"><span><?= e(t('caisse.cloture', ['date' => jour($au)])) ?></span><strong><?= e(argent($cloture)) ?></strong></div>
  </div>
</section>

<form class="carte filtres" method="get" action="<?= e(url('/caisse')) ?>">
  <label><?= e(t('commun.du')) ?><input type="date" name="du" value="<?= e($du) ?>"></label>
  <label><?= e(t('commun.au')) ?><input type="date" name="au" value="<?= e($au) ?>"></label>
  <?php if ($agences): ?>
  <label><?= e(t('agences.caisse')) ?>
    <select name="agence">
      <option value=""><?= e(t('agences.toutes')) ?></option>
      <option value="0"<?= $agence === 0 ? ' selected' : '' ?>><?= e(t('agences.siege')) ?></option>
      <?php foreach ($agences as $ag): ?><option value="<?= e($ag['id']) ?>"<?= $agence === (int) $ag['id'] ? ' selected' : '' ?>><?= e($ag['nom']) ?></option><?php endforeach; ?>
    </select>
  </label>
  <?php endif; ?>
  <button class="bouton clair"><?= e(t('commun.afficher')) ?></button>
</form>

<section class="carte">
  <h2><?= e(t('caisse.par_jour')) ?></h2>
  <?php if (!$jours): ?><p class="aide"><?= e(t('caisse.aucun_mouvement')) ?></p><?php else: ?>
  <div class="defile"><table>
    <thead><tr><th><?= e(t('commun.date')) ?></th><th><?= e(t('commun.operation')) ?></th><th class="tel-cache nombre"><?= e(t('caisse.nombre')) ?></th><th class="nombre"><?= e(t('caisse.entrees')) ?></th><th class="nombre"><?= e(t('caisse.sorties')) ?></th><th class="tel-cache nombre"><?= e(t('caisse.solde')) ?></th></tr></thead>
    <tbody>
    <?php $solde = \AvecXY\Domaine\Montant::enCentimes($ouverture); $te = 0; $ts = 0;
    foreach ($jours as $jour => $lignes): foreach ($lignes as $i => $l):
        $en = \AvecXY\Domaine\Montant::enCentimes($l['entree']); $so = \AvecXY\Domaine\Montant::enCentimes($l['sortie']);
        $solde += $en - $so; $te += $en; $ts += $so; ?>
      <tr>
        <td><?= $i === 0 ? e(jour($jour)) : '' ?></td>
        <td><?= e(t('caisse.type.' . strtolower($l['type']))) ?></td>
        <td class="nombre"><?= e($l['nombre']) ?></td>
        <td class="nombre"><?= $en ? e(argent($en / 100)) : '' ?></td>
        <td class="nombre"><?= $so ? e(argent($so / 100)) : '' ?></td>
        <td class="nombre"><?= $i === count($lignes) - 1 ? '<strong>' . e(argent($solde / 100)) . '</strong>' : '' ?></td>
      </tr>
    <?php endforeach; endforeach; ?>
    </tbody>
    <tfoot><tr><th colspan="3"><?= e(t('caisse.total_periode')) ?></th><th class="nombre"><?= e(argent($te / 100)) ?></th><th class="nombre"><?= e(argent($ts / 100)) ?></th><th class="nombre"><?= e(argent($cloture)) ?></th></tr></tfoot>
  </table></div>
  <?php endif; ?>
</section>

<?php if (peut('caisse.mouvements')): ?>
<section class="carte">
  <h2><?= e(t('caisse.fonds_propres')) ?></h2>
  <p class="aide"><?= e(t('caisse.fonds_aide')) ?></p>
  <form method="post" action="<?= e(url('/caisse/mouvements')) ?>">
    <?= champ_csrf() ?>
    <div class="grille">
      <label><?= e(t('commun.operation')) ?>
        <select name="type"><option value="APPORT"><?= e(t('caisse.type.apport')) ?></option><option value="RETRAIT"><?= e(t('caisse.type.retrait_fonds')) ?></option></select>
      </label>
      <label><?= e(t('caisse.montant')) ?><input name="montant" inputmode="decimal" required></label>
      <label><?= e(t('commun.date')) ?><input type="date" name="date" value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>" required></label>
      <label><?= e(t('caisse.origine')) ?><input name="source" maxlength="160"></label>
      <?php $agenceChoisie = $agence ?? 0; $libelle = t('agences.caisse'); $aideAgence = ''; $nomChamp = 'agence_id'; require __DIR__ . '/../commun/_choix_agence.php'; ?>
    </div>
    <label><?= e(t('commun.commentaire')) ?><input name="commentaire"></label>
    <div class="boutons"><button class="bouton"><?= e(t('commun.enregistrer')) ?></button></div>
  </form>
  <?php if ($mouvements): ?>
  <div class="defile"><table>
    <thead><tr><th><?= e(t('commun.date')) ?></th><th><?= e(t('commun.operation')) ?></th><th class="tel-cache"><?= e(t('caisse.origine')) ?></th><th class="nombre"><?= e(t('caisse.montant')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($mouvements as $m): ?>
      <tr>
        <td><?= e(jour($m['date_mouvement'])) ?></td>
        <td><?= e(t('caisse.type.' . ($m['type'] === 'APPORT' ? 'apport' : 'retrait_fonds'))) ?></td>
        <td><?= e(trim(($m['source'] ?? '') . ' ' . ($m['commentaire'] ?? '')) ?: '—') ?></td>
        <td class="nombre"><?= e(argent($m['montant'])) ?></td>
        <td class="actions"><?php $typeLigne = 'mouvements'; $idLigne = $m['id']; $retourLigne = ''; require __DIR__ . '/_supprimer.php'; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if (peut('caisse.mouvements')): ?>
<section class="carte">
  <h2><?= e(t('emprunts.titre')) ?></h2>
  <p class="aide"><?= e(t('emprunts.aide')) ?></p>
  <form method="post" action="<?= e(url('/caisse/emprunts')) ?>">
    <?= champ_csrf() ?>
    <div class="grille">
      <label><?= e(t('emprunts.preteur')) ?><input name="preteur" required maxlength="160"></label>
      <label><?= e(t('caisse.montant')) ?><input name="montant" inputmode="decimal" required></label>
      <label><?= e(t('credits.champ.taux_interet')) ?><input name="taux" inputmode="decimal" value="0"><small><?= e(t('emprunts.taux_aide')) ?></small></label>
      <label><?= e(t('commun.date')) ?><input type="date" name="date" value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>" required></label>
      <label><?= e(t('emprunts.echeance')) ?><input type="date" name="echeance"></label>
      <label><?= e(t('commun.commentaire')) ?><input name="commentaire"></label>
    </div>
    <div class="boutons"><button class="bouton"><?= e(t('emprunts.enregistrer')) ?></button></div>
  </form>
  <?php if ($emprunts): ?>
  <div class="defile"><table>
    <thead><tr><th class="tel-cache"><?= e(t('commun.date')) ?></th><th><?= e(t('emprunts.preteur')) ?></th><th class="tel-cache nombre"><?= e(t('emprunts.emprunte')) ?></th><th class="tel-cache nombre"><?= e(t('credits.interet')) ?></th><th class="nombre"><?= e(t('credits.restant')) ?></th><th><?= e(t('emprunts.rembourser')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($emprunts as $em): $reste = \AvecXY\Domaine\Montant::enCentimes($em['total_du']) - \AvecXY\Domaine\Montant::enCentimes($em['rembourse']); ?>
      <tr>
        <td><?= e(jour($em['date_emprunt'])) ?><?= $em['date_echeance'] ? '<br><small>' . e(t('credits.echeance_le', ['date' => jour($em['date_echeance'])])) . '</small>' : '' ?></td>
        <td><?= e($em['preteur']) ?><?= $em['commentaire'] ? '<br><small>' . e($em['commentaire']) . '</small>' : '' ?></td>
        <td class="nombre"><?= e(argent($em['montant'])) ?></td>
        <td class="nombre"><?= e(argent($em['montant_interet'])) ?> <small>(<?= e((float) $em['taux_interet']) ?> %)</small></td>
        <td class="nombre"><strong><?= e(argent($reste / 100)) ?></strong></td>
        <td>
          <?php if ($reste > 0): ?>
          <form class="ligne-form" method="post" action="<?= e(url('/caisse/emprunts/' . $em['id'] . '/rembourser')) ?>" data-confirmer="<?= e(t('emprunts.confirmer')) ?>">
            <?= champ_csrf() ?><input type="hidden" name="date" value="<?= e(date('Y-m-d')) ?>">
            <input name="montant" inputmode="decimal" required value="<?= e(\AvecXY\Domaine\Montant::pourChamp($reste / 100)) ?>" aria-label="<?= e(t('caisse.montant')) ?>">
            <button class="bouton clair"><?= e(t('emprunts.rembourser')) ?></button>
          </form>
          <?php else: ?><span class="pastille grise"><?= e(t('credits.statut.rembourse')) ?></span><?php endif; ?>
        </td>
        <td class="actions"><?php $typeLigne = 'emprunts'; $idLigne = $em['id']; $retourLigne = ''; require __DIR__ . '/_supprimer.php'; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
  <?php if ($remboursementsEmprunts): ?>
  <h3><?= e(t('emprunts.remboursements')) ?></h3>
  <div class="defile"><table>
    <tbody>
    <?php foreach ($remboursementsEmprunts as $rb): ?>
      <tr><td><?= e(jour($rb['date_paiement'])) ?></td><td><?= e($rb['preteur']) ?></td><td class="nombre"><?= e(argent($rb['montant'])) ?></td>
        <td class="actions"><?php $typeLigne = 'remboursements-emprunt'; $idLigne = $rb['id']; $retourLigne = ''; require __DIR__ . '/_supprimer.php'; ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if (($agences || $restreint) && peut('caisse.transferts')): ?>
<section class="carte">
  <h2><?= e(t('agences.transferts')) ?></h2>
  <p class="aide"><?= e(t('agences.transferts_aide')) ?></p>
  <form method="post" action="<?= e(url('/caisse/transferts')) ?>" data-confirmer="<?= e(t('agences.transfert_confirmer')) ?>">
    <?= champ_csrf() ?>
    <div class="grille">
      <?php if (!$restreint): ?>
        <?php $agenceChoisie = 0; $libelle = t('agences.de'); $aideAgence = ''; $nomChamp = 'de'; require __DIR__ . '/../commun/_choix_agence.php'; ?>
      <?php else: ?><input type="hidden" name="de" value="<?= e((int) $agence) ?>"><?php endif; ?>
      <?php $listeVers = $agences ?: \AvecXY\Noyau\Noyau::instance()->serviceAgences()->liste(\AvecXY\Noyau\Noyau::instance()->contexte->organisationId()); ?>
      <label><?= e(t('agences.vers')) ?>
        <select name="vers">
          <option value="0"><?= e(t('agences.siege')) ?></option>
          <?php foreach ($listeVers as $ag): ?><option value="<?= e($ag['id']) ?>"><?= e($ag['code'] . ' · ' . $ag['nom']) ?></option><?php endforeach; ?>
        </select>
      </label>
      <label><?= e(t('caisse.montant')) ?><input name="montant" inputmode="decimal" required></label>
      <label><?= e(t('commun.date')) ?><input type="date" name="date" value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>" required></label>
      <label><?= e(t('agences.motif')) ?><input name="motif" maxlength="255"></label>
    </div>
    <p class="espace"><button class="bouton"><?= e(t('agences.transferer')) ?></button></p>
  </form>
  <?php if ($transferts): ?>
  <div class="defile"><table>
    <thead><tr><th class="tel-cache"><?= e(t('commun.date')) ?></th><th><?= e(t('agences.de')) ?></th><th><?= e(t('agences.vers')) ?></th><th class="nombre"><?= e(t('caisse.montant')) ?></th><th class="masque-tel"><?= e(t('agences.motif')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($transferts as $tr): ?>
      <tr>
        <td><?= e(jour($tr['date_transfert'])) ?></td>
        <td><?= e($tr['de_nom'] ?? t('agences.siege')) ?></td><td><?= e($tr['vers_nom'] ?? t('agences.siege')) ?></td>
        <td class="nombre"><strong><?= e(argent($tr['montant'])) ?></strong></td>
        <td class="masque-tel"><?= e($tr['motif'] ?? '') ?></td>
        <td class="actions"><form method="post" action="<?= e(url('/caisse/transferts/' . $tr['id'] . '/supprimer')) ?>" data-confirmer="<?= e(t('caisse.confirmer_suppression')) ?>"><?= champ_csrf() ?><button class="lien danger"><?= e(t('commun.supprimer')) ?></button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
<?php endif; ?>
