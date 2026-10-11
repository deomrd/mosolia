<?php use AvecXY\Application\ServiceComptabilite; ?>
<header class="carte tete-page">
  <div><h1><?= e(t('menu.compta_plan')) ?></h1><p class="aide"><?= e(t('compta.plan_aide')) ?></p></div>
  <?php if (peut('compta.plan')): ?><a class="bouton" href="<?= e(url('/comptabilite/plan/0')) ?>"><?= e(t('compta.nouveau_compte')) ?></a><?php endif; ?>
</header>

<section class="carte">
  <div class="defile"><table class="etat">
    <thead><tr><th><?= e(t('compta.numero')) ?></th><th><?= e(t('compta.libelle')) ?></th><th class="masque-tel"><?= e(t('compta.nature')) ?></th><th><?= e(t('compta.role')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($comptes as $co): ?>
      <tr<?= (int) $co['actif'] === 1 ? '' : ' class="inactif"' ?>>
        <td class="code-compte"><?= e($co['numero']) ?></td>
        <td><?= e($co['libelle']) ?><?php if ((int) $co['actif'] !== 1): ?> <span class="pastille grise"><?= e(t('statut.inactif')) ?></span><?php endif; ?></td>
        <td class="masque-tel"><?= e(t('compta.nature.' . strtolower($co['nature']))) ?></td>
        <td><?= $co['cle'] ? '<span class="pastille verte">' . e(t('compta.role.' . $co['cle'])) . '</span>' : '' ?></td>
        <td class="actions"><?php if (peut('compta.plan')): ?>
          <a href="<?= e(url('/comptabilite/plan/' . $co['id'])) ?>"><?= e(t('commun.modifier')) ?></a>
          <?php if (!$co['cle'] && (int) $co['nb_lignes'] === 0): ?><form method="post" action="<?= e(url('/comptabilite/plan/' . $co['id'] . '/supprimer')) ?>" data-confirmer="<?= e(t('compta.confirmer_suppression_compte', ['numero' => $co['numero']])) ?>"><?= champ_csrf() ?><button class="lien danger"><?= e(t('commun.supprimer')) ?></button></form><?php endif; ?>
        <?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>

<?php if (peut('compta.plan')): ?>
<form class="carte" method="post" action="<?= e(url('/comptabilite/plan/roles')) ?>">
  <?= champ_csrf() ?>
  <h2><?= e(t('compta.roles')) ?></h2>
  <p class="aide"><?= e(t('compta.roles_aide')) ?></p>
  <div class="grille">
    <?php foreach (ServiceComptabilite::ROLES as $role => $nature): ?>
    <label><?= e(t('compta.role.' . $role)) ?>
      <select name="role[<?= e($role) ?>]">
        <?php foreach ($comptes as $co): if ($co['nature'] !== $nature || (int) $co['actif'] !== 1) { continue; } ?>
          <option value="<?= e($co['id']) ?>"<?= $co['cle'] === $role ? ' selected' : '' ?>><?= e($co['numero'] . ' · ' . $co['libelle']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <?php endforeach; ?>
  </div>
  <p class="espace"><button class="bouton"><?= e(t('commun.enregistrer')) ?></button></p>
</form>
<?php endif; ?>
