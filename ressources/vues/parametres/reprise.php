<?php
use AvecXY\Domaine\Montant;

$dec = (int) \AvecXY\Noyau\Noyau::instance()->contexte->organisation['devise_decimales'];
$centimes = static fn (int $v): string => argent($v / 100);
?>
<header class="carte tete-page">
  <div>
    <h1><?= e(t('menu.reprise')) ?></h1>
    <p class="aide"><?= e(t('reprise.aide')) ?></p>
  </div>
</header>

<?php if ($analyse === null): ?>
<section class="carte">
  <h2><?= e(t('reprise.comment')) ?></h2>
  <ol class="etapes">
    <li><strong><?= e(t('reprise.etape1')) ?></strong><br><span class="aide"><?= e(t('reprise.etape1_aide')) ?></span>
      <div class="boutons espace"><a class="bouton seulement-ordinateur" href="<?= e(url('/parametres/reprise/modele')) ?>"><?= e(t('reprise.telecharger')) ?></a>
        <a class="bouton clair" href="<?= e(url('/parametres/types-carnet')) ?>"><?= e(t('menu.types_carnet')) ?></a>
        <?php if ($creditDansForfait): ?><a class="bouton clair" href="<?= e(url('/parametres/credit')) ?>"><?= e(t('menu.regles_credit')) ?></a><?php endif; ?></div></li>
    <li><strong><?= e(t('reprise.etape2')) ?></strong><br><span class="aide"><?= e(t('reprise.etape2_aide')) ?></span></li>
    <li><strong><?= e(t('reprise.etape3')) ?></strong><br><span class="aide"><?= e(t('reprise.etape3_aide')) ?></span></li>
  </ol>
</section>

<?php if (dans_application()): ?><p class="carte message message-info"><?= e(t('application.sur_ordinateur')) ?></p><?php endif; ?>
<form class="carte seulement-ordinateur" method="post" action="<?= e(url('/parametres/reprise/analyser')) ?>" enctype="multipart/form-data">
  <?= champ_csrf() ?>
  <h2><?= e(t('reprise.envoyer_titre')) ?></h2>
  <div class="grille">
    <label><?= e(t('reprise.fichier')) ?><input type="file" name="fichier" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required></label>
    <label><?= e(t('reprise.date_demarrage')) ?><input type="date" name="date" value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>" required><small><?= e(t('reprise.date_demarrage_aide')) ?></small></label>
    <label><?= e(t('reprise.caisse')) ?><input name="caisse" inputmode="decimal" placeholder="0"><small><?= e(t('reprise.caisse_aide')) ?></small></label>
  </div>
  <p class="espace"><button class="bouton"><?= e(t('reprise.verifier')) ?></button></p>
  <p class="aide"><?= e(t('reprise.rien_enregistre')) ?></p>
</form>

<?php else: $t = $analyse['totaux']; $ok = !$analyse['erreurs']; ?>
<section class="carte">
  <div class="tete-section">
    <h2><?= e(t('reprise.verification', ['fichier' => $fichier])) ?></h2>
    <span class="pastille <?= $ok ? 'verte' : 'rouge' ?>"><?= e($ok ? t('reprise.pret') : t('reprise.nb_erreurs', ['n' => count($analyse['erreurs'])])) ?></span>
  </div>
  <p class="aide"><?= e(t('reprise.verification_aide', ['date' => jour($analyse['date'])])) ?></p>
  <div class="tuiles">
    <div class="tuile"><span><?= e(t('reprise.t_recolteurs')) ?></span><strong><?= e($t['recolteurs']) ?></strong></div>
    <div class="tuile"><span><?= e(t('menu.clients')) ?></span><strong><?= e($t['clients']) ?></strong></div>
    <div class="tuile"><span><?= e(t('menu.carnets')) ?></span><strong><?= e($t['carnets']) ?></strong></div>
    <div class="tuile fort"><span><?= e(t('reprise.t_epargne')) ?></span><strong><?= e($centimes($t['epargne'])) ?></strong></div>
    <div class="tuile"><span><?= e(t('reprise.t_credits')) ?></span><strong><?= e($t['credits']) ?></strong></div>
    <div class="tuile"><span><?= e(t('reprise.t_encours')) ?></span><strong><?= e($centimes($t['encours'])) ?></strong></div>
    <div class="tuile"><span><?= e(t('reprise.caisse')) ?></span><strong><?= e($centimes($t['caisse'])) ?></strong></div>
  </div>
</section>

<?php foreach (['erreurs' => 'rouge', 'avertissements' => 'ambre'] as $groupe => $couleur): if (!$analyse[$groupe]) { continue; } ?>
<section class="carte">
  <h2><?= e(t('reprise.' . $groupe)) ?> <span class="pastille <?= $couleur ?>"><?= e(count($analyse[$groupe])) ?></span></h2>
  <p class="aide"><?= e(t('reprise.' . $groupe . '_aide')) ?></p>
  <div class="defile"><table>
    <thead><tr><th><?= e(t('reprise.feuille')) ?></th><th class="nombre"><?= e(t('reprise.ligne')) ?></th><th><?= e(t('reprise.probleme')) ?></th></tr></thead>
    <tbody>
    <?php foreach (array_slice($analyse[$groupe], 0, 300) as $l): ?>
      <tr><td><?= e($l['feuille']) ?></td><td class="nombre"><?= e($l['ligne']) ?></td><td><?= e($l['message']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php endforeach; ?>

<?php if ($analyse['clients']): ?>
<section class="carte">
  <h2><?= e(t('reprise.apercu')) ?></h2>
  <div class="defile"><table>
    <thead><tr><th>N°</th><th><?= e(t('clients.client')) ?></th><th><?= e(t('menu.recolteurs')) ?></th><th><?= e(t('carnets.carnet')) ?></th><th class="nombre"><?= e(t('carnets.mise')) ?></th><th class="nombre"><?= e(t('reprise.mises_versees')) ?></th><th><?= e(t('reprise.deja_beneficie')) ?></th><th class="nombre"><?= e(t('clients.epargne')) ?></th></tr></thead>
    <tbody>
    <?php $n = 0; foreach ($analyse['clients'] as $cl): foreach ($cl['carnets'] ?: [null] as $k): if (++$n > 200) { break 2; } ?>
      <tr>
        <td><?= e($cl['ref']) ?></td><td><strong><?= e($cl['nom']) ?></strong><?= $cl['telephone'] ? '<br><small>' . e($cl['telephone']) . '</small>' : '' ?></td><td><?= e($cl['recolteur']) ?></td>
        <?php if ($k === null): ?><td colspan="5" class="aide"><?= e(t('clients.aucun_carnet')) ?></td>
        <?php else: ?>
        <td><?= e($k['type_nom']) ?><br><small><?= e(jour($k['date_ouverture'])) ?></small></td>
        <td class="nombre"><?= $k['mise'] !== null ? e(argent($k['mise'])) : '—' ?></td>
        <td class="nombre"><?= $k['mode'] === 'PARTS' ? e($k['reprise_parts']) : '—' ?></td>
        <td><?= e(t($k['benefice_deja_pris'] ? 'reprise.xl.oui' : 'reprise.xl.non')) ?></td>
        <td class="nombre"><strong><?= e($centimes($k['epargne'])) ?></strong></td>
        <?php endif; ?>
      </tr>
    <?php endforeach; endforeach; ?>
    </tbody>
  </table></div>
  <?php if ($t['carnets'] + $t['clients'] > 200): ?><p class="aide"><?= e(t('reprise.apercu_limite')) ?></p><?php endif; ?>
</section>
<?php endif; ?>

<?php if ($analyse['credits']): ?>
<section class="carte">
  <h2><?= e(t('reprise.t_credits')) ?></h2>
  <div class="defile"><table>
    <thead><tr><th>N°</th><th><?= e(t('clients.client')) ?></th><th><?= e(t('credits.echeance')) ?></th><th class="nombre"><?= e(t('credits.prete')) ?></th><th class="nombre"><?= e(t('credits.interet')) ?></th><th class="nombre"><?= e(t('credits.rembourse')) ?></th><th class="nombre"><?= e(t('credits.restant')) ?></th><th class="nombre"><?= e(t('reprise.echeances')) ?></th></tr></thead>
    <tbody>
    <?php foreach (array_slice($analyse['credits'], 0, 200) as $cr): ?>
      <tr>
        <td><?= e($cr['ref']) ?></td><td><?= e($analyse['clients'][$cr['client']]['nom']) ?></td><td><?= e(jour($cr['date_echeance'])) ?></td>
        <td class="nombre"><?= e($centimes($cr['montant'])) ?></td><td class="nombre"><?= e($centimes($cr['interet'])) ?></td>
        <td class="nombre"><?= e($centimes($cr['deja'])) ?></td><td class="nombre"><strong><?= e($centimes($cr['restant'])) ?></strong></td>
        <td class="nombre"><?= e(count($cr['echeances'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php endif; ?>

<section class="carte">
  <div class="boutons">
    <?php if ($ok): ?>
    <form method="post" action="<?= e(url('/parametres/reprise/importer')) ?>" data-confirmer="<?= e(t('reprise.confirmer', ['clients' => $t['clients'], 'carnets' => $t['carnets']])) ?>">
      <?= champ_csrf() ?><button class="bouton"><?= e(t('reprise.importer')) ?></button>
    </form>
    <?php endif; ?>
    <form method="post" action="<?= e(url('/parametres/reprise/abandonner')) ?>">
      <?= champ_csrf() ?><button class="bouton clair"><?= e(t($ok ? 'commun.annuler' : 'reprise.autre_fichier')) ?></button>
    </form>
  </div>
  <p class="aide"><?= e(t($ok ? 'reprise.importer_aide' : 'reprise.corriger_aide')) ?></p>
</section>
<?php endif; ?>

<?php if ($reprises): ?>
<section class="carte">
  <h2><?= e(t('reprise.historique')) ?></h2>
  <div class="defile"><table>
    <thead><tr><th><?= e(t('commun.date')) ?></th><th><?= e(t('reprise.date_demarrage')) ?></th><th class="nombre"><?= e(t('menu.clients')) ?></th><th class="nombre"><?= e(t('menu.carnets')) ?></th><th class="nombre"><?= e(t('reprise.t_credits')) ?></th><th class="nombre"><?= e(t('reprise.t_epargne')) ?></th><th class="nombre"><?= e(t('reprise.t_encours')) ?></th><th class="nombre"><?= e(t('reprise.caisse')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($reprises as $r): $valide = $r['statut'] === 'VALIDEE'; ?>
      <tr>
        <td><?= e(date('d/m/Y H:i', strtotime($r['cree_le']))) ?><br><small><?= e($r['auteur_nom'] ?? '') ?></small></td>
        <td><?= e(jour($r['date_demarrage'])) ?></td>
        <td class="nombre"><?= e($r['clients']) ?></td><td class="nombre"><?= e($r['carnets']) ?></td><td class="nombre"><?= e($r['credits']) ?></td>
        <td class="nombre"><?= e(argent($r['epargne'])) ?></td><td class="nombre"><?= e(argent($r['encours'])) ?></td><td class="nombre"><?= e(argent($r['caisse'])) ?></td>
        <td class="actions">
          <?php if ($valide): ?>
          <form method="post" action="<?= e(url('/parametres/reprise/' . $r['id'] . '/annuler')) ?>" data-confirmer="<?= e(t('reprise.confirmer_annulation')) ?>"><?= champ_csrf() ?><button class="lien danger"><?= e(t('reprise.annuler')) ?></button></form>
          <?php else: ?><span class="pastille grise"><?= e(t('reprise.statut_annulee')) ?></span><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="aide"><?= e(t('reprise.annuler_aide')) ?><?php if ($espaceClient): ?> <a href="<?= e(url('/parametres/espace-client')) ?>"><?= e(t('reprise.vers_identifiants')) ?></a><?php endif; ?></p>
</section>
<?php endif; ?>
