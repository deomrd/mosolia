<header class="carte tete-page">
  <div>
    <h1><?= e(t('menu.profils')) ?></h1>
    <p class="aide"><?= e(t('profils.aide')) ?></p>
  </div>
  <a class="bouton" href="<?= e(url('/parametres/profils/nouveau')) ?>"><?= e(t('profils.nouveau')) ?></a>
</header>

<section class="carte">
  <div class="defile">
  <table>
    <thead><tr>
      <th><?= e(t('profils.nom')) ?></th>
      <th class="tel-cache"><?= e(t('profils.type')) ?></th>
      <th class="tel-cache nombre"><?= e(t('profils.nb_droits')) ?></th>
      <th class="nombre"><?= e(t('profils.nb_utilisateurs')) ?></th>
      <th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($profils as $p): ?>
      <tr>
        <td><strong><?= e($p['nom']) ?></strong></td>
        <td>
          <?php if ((int) $p['est_admin'] === 1): ?><span class="pastille verte"><?= e(t('profils.type_admin')) ?></span>
          <?php elseif ((int) $p['est_agent'] === 1): ?><span class="pastille"><?= e(t('profils.type_recolteur')) ?></span>
          <?php else: ?><span class="pastille"><?= e(t('profils.type_bureau')) ?></span><?php endif; ?>
        </td>
        <td class="nombre"><?= (int) $p['est_admin'] === 1 ? e(t('profils.tous')) : e($p['nb_droits']) ?></td>
        <td class="nombre"><?= e($p['nb_utilisateurs']) ?></td>
        <td class="actions">
          <a href="<?= e(url('/parametres/profils/' . $p['id'])) ?>"><?= e(t('commun.modifier')) ?></a>
          <?php if ((int) $p['est_admin'] !== 1 && (int) $p['nb_utilisateurs'] === 0): ?>
            <form method="post" action="<?= e(url('/parametres/profils/' . $p['id'] . '/supprimer')) ?>" data-confirmer="<?= e(t('profils.confirmer_suppression', ['nom' => $p['nom']])) ?>">
              <?= champ_csrf() ?>
              <button class="lien danger"><?= e(t('commun.supprimer')) ?></button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</section>
