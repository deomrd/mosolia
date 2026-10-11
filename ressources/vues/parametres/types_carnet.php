<?php
use AvecXY\Domaine\Montant;
use AvecXY\Domaine\TypeCarnet;

$org = \AvecXY\Noyau\Noyau::instance()->contexte->organisation;
$dec = (int) $org['devise_decimales'];
$sym = $org['devise_symbole'];
?>
<header class="carte tete-page">
  <div>
    <h1><?= e(t('menu.types_carnet')) ?></h1>
    <p class="aide"><?= e(t('carnets.aide')) ?></p>
  </div>
  <a class="bouton" href="<?= e(url('/parametres/types-carnet/nouveau')) ?>"><?= e(t('carnets.nouveau')) ?></a>
</header>

<section class="carte">
  <?php if (!$types): ?>
    <p class="aide"><?= e(t('carnets.aucun')) ?></p>
  <?php else: ?>
  <div class="defile">
  <table>
    <thead><tr>
      <th><?= e(t('carnets.nom')) ?></th>
      <th><?= e(t('carnets.mode')) ?></th>
      <th class="tel-cache nombre"><?= e(t('carnets.prix')) ?></th>
      <th class="tel-cache"><?= e(t('carnets.regle')) ?></th>
      <th class="tel-cache nombre"><?= e(t('carnets.vendus')) ?></th>
      <th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($types as $ty): ?>
      <tr>
        <td>
          <strong><?= e($ty['nom']) ?></strong>
          <?php if ((int) $ty['par_defaut'] === 1): ?> <span class="pastille verte"><?= e(t('carnets.par_defaut_court')) ?></span><?php endif; ?>
          <?php if ((int) $ty['actif'] !== 1): ?> <span class="pastille grise"><?= e(t('statut.inactif')) ?></span><?php endif; ?>
        </td>
        <?php $modeVu = TypeCarnet::modeAffiche($ty); ?>
        <td><?= e(t('carnets.mode.' . strtolower($modeVu))) ?></td>
        <td class="nombre"><?= e(Montant::afficher($ty['prix'], $dec)) ?> <?= e($sym) ?></td>
        <td>
          <?php if ($ty['mode'] === 'PARTS'): ?>
            <?= e(t('carnets.resume_parts', [
                'parts' => $ty['nombre_parts'],
                'tranches' => TypeCarnet::nombreTranches((int) $ty['nombre_parts'], (int) $ty['taille_tranche']),
                'benefice' => count(TypeCarnet::partsBenefice((int) $ty['nombre_parts'], (int) $ty['taille_tranche'], (int) $ty['parts_benefice_par_tranche'], $ty['prelevement_benefice'])),
            ])) ?><br>
            <small><?= e(t('carnets.retrait.' . strtolower($ty['retrait']))) ?> · <?= e(t('carnets.fermeture.' . strtolower($ty['fermeture']))) ?>
              <?php if ((int) $ty['parts_reserve'] > 0): ?> · <?= e(t('carnets.resume_reserve', ['n' => $ty['parts_reserve']])) ?><?php endif; ?>
              · <?= e((int) $ty['duree_jours'] > 0 ? t('carnets.resume_jours', ['jours' => $ty['duree_jours']]) : t('carnets.sans_duree')) ?></small>
          <?php elseif ($modeVu === 'BLOQUE'): ?>
            <?= e(implode(' · ', array_map(static fn ($o) => t('carnets.option_resume', ['mois' => $o['mois'], 'taux' => Montant::afficher($o['taux'], 2)]), TypeCarnet::options($ty)))) ?>
            <?= e(t('carnets.taux_base.' . strtolower($ty['taux_base']))) ?><br>
            <small><?= e(t('carnets.blocage.' . strtolower($ty['blocage']))) ?> · <?= e(t('carnets.anticipe.' . strtolower($ty['retrait_anticipe']))) ?> · <?= e(t('carnets.renouvellement.' . strtolower($ty['renouvellement'] ?? 'AUCUN'))) ?></small>
          <?php else: ?>
            <?= e((int) $ty['duree_jours'] > 0 ? t('carnets.resume_libre', ['jours' => $ty['duree_jours']]) : t('carnets.resume_libre_sans')) ?><br>
            <small><?= e(t('carnets.frais.' . strtolower($ty['frais_tenue_type']))) ?>
              <?php if ($ty['solde_minimum'] !== null): ?> · <?= e(t('carnets.resume_solde_min', ['montant' => argent($ty['solde_minimum'])])) ?><?php endif; ?></small>
          <?php endif; ?>
        </td>
        <td class="nombre"><?= e($ty['nb_carnets']) ?></td>
        <td class="actions">
          <a href="<?= e(url('/parametres/types-carnet/' . $ty['id'])) ?>"><?= e(t('commun.modifier')) ?></a>
          <form method="post" action="<?= e(url('/parametres/types-carnet/' . $ty['id'] . '/supprimer')) ?>" data-confirmer="<?= e(t('corbeille.confirmer', ['nom' => $ty['nom']])) ?>">
            <?= champ_csrf() ?>
            <button class="lien danger"><?= e(t('commun.supprimer')) ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</section>
