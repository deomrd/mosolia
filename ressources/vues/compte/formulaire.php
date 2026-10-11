<?php $doitChanger = (int) $u['doit_changer_mot_de_passe'] === 1; $sansPin = empty($u['a_un_pin']); ?>
<form method="post" action="<?= e(url('/mon-compte')) ?>">
<?= champ_csrf() ?>

<header class="carte tete-page">
  <div>
    <h1><?= e(t('menu.mon_compte')) ?></h1>
    <p class="aide"><?= e(t('compte.aide', ['identifiant' => $u['identifiant']])) ?></p>
  </div>
  <button class="bouton"><?= e(t('commun.enregistrer')) ?></button>
</header>

<section class="carte">
  <div class="grille">
    <label><?= e(t('utilisateurs.nom')) ?>
      <input name="nom" value="<?= e($u['nom']) ?>" required maxlength="160" data-majuscules>
    </label>
    <label><?= e(t('commun.langue')) ?>
      <select name="langue">
        <option value=""><?= e(t('utilisateurs.langue_organisation')) ?></option>
        <option value="fr"<?= ($u['langue'] ?? '') === 'fr' ? ' selected' : '' ?>>Français</option>
        <option value="en"<?= ($u['langue'] ?? '') === 'en' ? ' selected' : '' ?>>English</option>
      </select>
    </label>
  </div>
</section>

<section class="carte">
  <h2><?= e(t('compte.mot_de_passe')) ?></h2>
  <p class="aide"><?= e(t($doitChanger ? 'compte.mot_de_passe_obligatoire' : 'compte.mot_de_passe_facultatif')) ?></p>
  <div class="grille">
    <label><?= e(t('compte.mot_de_passe_actuel')) ?>
      <input name="mot_de_passe_actuel" type="password" autocomplete="current-password"<?= $doitChanger ? ' required' : '' ?>>
    </label>
    <label><?= e(t('compte.nouveau_mot_de_passe')) ?>
      <input name="mot_de_passe" type="password" autocomplete="new-password" minlength="8"<?= $doitChanger ? ' required' : '' ?>>
    </label>
    <label><?= e(t('compte.confirmation')) ?>
      <input name="mot_de_passe_confirmation" type="password" autocomplete="new-password" minlength="8"<?= $doitChanger ? ' required' : '' ?>>
    </label>
  </div>
</section>

<section class="carte">
  <div class="tete-section">
    <h2><?= e(t('pin.titre')) ?></h2>
    <span class="pastille <?= $sansPin ? 'ambre' : 'verte' ?>"><?= e(t($sansPin ? 'pin.absent' : 'pin.present')) ?></span>
  </div>
  <p class="aide"><?= e(t($sansPin ? 'pin.aide_creation' : 'pin.aide_changement')) ?></p>
  <div class="grille">
    <label><?= e(t($sansPin ? 'pin.nouveau' : 'pin.changer')) ?>
      <input name="pin" type="password" inputmode="numeric" pattern="\d{4,6}" maxlength="6" autocomplete="off"<?= $sansPin ? ' required' : '' ?>>
      <small><?= e(t('pin.format')) ?></small>
    </label>
    <label><?= e(t('pin.confirmer')) ?>
      <input name="pin_confirmation" type="password" inputmode="numeric" maxlength="6" autocomplete="off"<?= $sansPin ? ' required' : '' ?>>
    </label>
  </div>
  <?php if (!$doitChanger): ?><p class="aide"><?= e(t('pin.mot_de_passe_requis')) ?></p><?php endif; ?>
</section>
</form>

<?php if ($clesPossibles && !$doitChanger && !$sansPin): ?>
<section class="carte" data-cle-appareil data-defi="<?= e(url('/mon-compte/cle/defi')) ?>" data-envoi="<?= e(url('/mon-compte/cle')) ?>" data-jeton="<?= e(\AvecXY\Noyau\Noyau::instance()->session->jetonCsrf()) ?>">
  <h2><?= e(t('cle.titre')) ?></h2>
  <p class="aide"><?= e(t('cle.aide')) ?></p>
  <?php if ($cles): ?>
  <div class="defile"><table>
    <thead><tr><th><?= e(t('cle.appareil')) ?></th><th><?= e(t('cle.ajoute_le')) ?></th><th><?= e(t('cle.derniere_utilisation')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($cles as $cle): ?>
      <tr>
        <td><strong><?= e($cle['nom'] ?? t('cle.appareil')) ?></strong></td>
        <td><?= e(jour(substr((string) $cle['cree_le'], 0, 10))) ?></td>
        <td><?= e($cle['derniere_utilisation'] ? date('d/m/Y H:i', strtotime($cle['derniere_utilisation'])) : '—') ?></td>
        <td class="actions"><form method="post" action="<?= e(url('/mon-compte/cle/' . $cle['id'] . '/supprimer')) ?>" data-confirmer="<?= e(t('cle.confirmer_retrait')) ?>"><?= champ_csrf() ?><button class="lien danger"><?= e(t('cle.retirer')) ?></button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
  <div class="ligne-form" data-cle-zone hidden>
    <label><?= e(t('cle.nom_appareil')) ?><input data-cle-nom maxlength="120" placeholder="<?= e(t('cle.nom_exemple')) ?>"></label>
    <button type="button" class="bouton clair" data-cle-ajouter><?= e(t('cle.ajouter')) ?></button>
  </div>
  <p class="aide" data-cle-indisponible><?= e(t('cle.indisponible')) ?></p>
  <p class="message message-erreur" data-cle-erreur hidden role="alert"></p>
</section>
<?php endif; ?>
