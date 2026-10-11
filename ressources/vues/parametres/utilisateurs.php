<?php $moi = \AvecXY\Noyau\Noyau::instance()->contexte->utilisateurId(); ?>
<header class="carte tete-page">
  <div>
    <h1><?= e(t('menu.utilisateurs')) ?></h1>
    <p class="aide"><?= e(t('utilisateurs.aide')) ?></p>
  </div>
  <a class="bouton" href="<?= e(url('/parametres/utilisateurs/nouveau')) ?>"><?= e(t('utilisateurs.nouveau')) ?></a>
</header>

<section class="carte">
  <div class="defile">
  <table>
    <thead><tr>
      <th><?= e(t('utilisateurs.nom')) ?></th>
      <th class="tel-cache"><?= e(t('utilisateurs.identifiant')) ?></th>
      <th><?= e(t('utilisateurs.profil')) ?></th>
      <th><?= e(t('utilisateurs.statut')) ?></th>
      <th class="tel-cache"><?= e(t('utilisateurs.derniere_connexion')) ?></th>
      <th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($utilisateurs as $u): ?>
      <tr>
        <td><strong><?= e($u['nom']) ?></strong></td>
        <td><?= e($u['identifiant']) ?></td>
        <td><?= e($u['profil_nom']) ?></td>
        <td><span class="pastille <?= $u['statut'] === 'ACTIF' ? 'verte' : 'grise' ?>"><?= e(t('statut.' . strtolower($u['statut']))) ?></span></td>
        <td><?= $u['derniere_connexion'] ? e(date('d/m/Y H:i', strtotime($u['derniere_connexion']))) : '—' ?></td>
        <td class="actions">
          <a href="<?= e(url('/parametres/utilisateurs/' . $u['id'])) ?>"><?= e(t('commun.modifier')) ?></a>
          <?php if ((int) $u['id'] !== $moi): ?>
            <form method="post" action="<?= e(url('/parametres/utilisateurs/' . $u['id'] . '/supprimer')) ?>" data-confirmer="<?= e(t('utilisateurs.confirmer_suppression', ['nom' => $u['nom']])) ?>">
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

<section class="carte">
  <h2><?= e(t('mobile.appareils')) ?></h2>
  <p class="aide"><?= e(t('mobile.appareils_aide')) ?></p>
  <?php if (!$appareils): ?><p class="aide"><?= e(t('mobile.aucun_appareil')) ?></p><?php else: ?>
  <div class="defile"><table>
    <thead><tr><th><?= e(t('utilisateurs.nom')) ?></th><th><?= e(t('mobile.appareil')) ?></th><th><?= e(t('mobile.vu_le')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($appareils as $a): ?>
      <tr>
        <td><strong><?= e($a['utilisateur_nom']) ?></strong><br><small><?= e($a['identifiant']) ?></small></td>
        <td><?= e($a['appareil_nom'] ?: '—') ?></td>
        <td><?= $a['vu_le'] ? e(date('d/m/Y H:i', strtotime($a['vu_le']))) : '—' ?></td>
        <td class="actions"><form method="post" action="<?= e(url('/parametres/appareils/' . $a['id'] . '/desactiver')) ?>" data-confirmer="<?= e(t('mobile.confirmer_desactivation')) ?>"><?= champ_csrf() ?><button class="lien danger"><?= e(t('mobile.desactiver')) ?></button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>

<?php if (!empty($acces)): $identifiants = $acces; require __DIR__ . '/../commun/_acces_personnel.php'; endif; ?>
