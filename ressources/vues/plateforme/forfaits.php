<?php $mods = json_decode((string) $f['modules'], true) ?: []; ?>
<header class="carte tete-page">
  <div>
    <h1><?= e(t('plateforme.forfaits')) ?></h1>
    <p class="aide"><?= e(t('plateforme.forfaits_aide')) ?></p>
  </div>
</header>

<section class="carte">
  <div class="defile"><table>
    <thead><tr><th><?= e(t('plateforme.code')) ?></th><th><?= e(t('credits.nom')) ?></th><th class="nombre"><?= e(t('rapports.colonne.prix')) ?></th><th class="tel-cache nombre"><?= e(t('credits.duree')) ?></th><th class="tel-cache"><?= e(t('credits.limites')) ?></th><th class="tel-cache"><?= e(t('plateforme.modules')) ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($plans as $p): $m = json_decode((string) $p['modules'], true) ?: []; ?>
      <tr>
        <td><a href="<?= e(url('/plateforme/forfaits?modifier=' . $p['id'])) ?>"><strong><?= e($p['code']) ?></strong></a></td>
        <td><?= e($p['nom']) ?></td>
        <td class="nombre"><?= e((float) $p['prix'] . ' ' . $p['devise_code']) ?></td>
        <td class="nombre"><?= e($p['duree_jours']) ?> j</td>
        <td><?= e(t('plateforme.limites_court', ['u' => $p['max_utilisateurs'] ?? '∞', 'r' => $p['max_agents'] ?? '∞', 'c' => $p['max_clients'] ?? '∞'])) ?></td>
        <td><?= e(implode(', ', array_map(static fn ($k) => t('plateforme.module.' . $k), array_keys(array_filter($m)))) ?: '—') ?></td>
        <td><span class="pastille <?= (int) $p['actif'] === 1 ? 'verte' : 'grise' ?>"><?= e(t((int) $p['actif'] === 1 ? 'commun.actif' : 'commun.inactif')) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>

<section class="carte">
  <h2><?= e((int) $f['id'] === 0 ? t('plateforme.nouveau_forfait') : t('plateforme.modifier_forfait', ['nom' => $f['nom']])) ?></h2>
  <form method="post" action="<?= e(url('/plateforme/forfaits')) ?>">
    <?= champ_csrf() ?><input type="hidden" name="id" value="<?= e($f['id']) ?>">
    <div class="grille">
      <label><?= e(t('plateforme.code')) ?><input name="code" value="<?= e($f['code']) ?>" required maxlength="40" data-majuscules></label>
      <label><?= e(t('credits.nom')) ?><input name="nom" value="<?= e($f['nom']) ?>" required maxlength="80"></label>
      <label><?= e(t('rapports.colonne.prix')) ?><input name="prix" value="<?= e(\AvecXY\Domaine\Montant::pourChamp($f['prix'] === '' ? null : (string) $f['prix'])) ?>" inputmode="decimal"><small><?= e(t('plateforme.prix_aide')) ?></small></label>
      <label><?= e(t('organisation.devise')) ?><select name="devise_code"><?php foreach ($devises as $d): ?><option<?= $f['devise_code'] === $d['code'] ? ' selected' : '' ?>><?= e($d['code']) ?></option><?php endforeach; ?></select></label>
      <label><?= e(t('credits.champ.duree_jours')) ?><input name="duree_jours" value="<?= e($f['duree_jours']) ?>" inputmode="numeric" required></label>
      <label><?= e(t('plateforme.max_utilisateurs')) ?><input name="max_utilisateurs" value="<?= e($f['max_utilisateurs'] ?? '') ?>" inputmode="numeric"></label>
      <label><?= e(t('plateforme.max_recolteurs')) ?><input name="max_agents" value="<?= e($f['max_agents'] ?? '') ?>" inputmode="numeric"></label>
      <label><?= e(t('plateforme.max_clients')) ?><input name="max_clients" value="<?= e($f['max_clients'] ?? '') ?>" inputmode="numeric"><small><?= e(t('plateforme.vide_illimite')) ?></small></label>
      <label><?= e(t('plateforme.ordre')) ?><input name="ordre" value="<?= e($f['ordre']) ?>" inputmode="numeric"></label>
    </div>
    <label><?= e(t('plateforme.description')) ?><textarea name="description" rows="2"><?= e($f['description'] ?? '') ?></textarea></label>
    <?php foreach ($modules as $m): ?>
      <label class="case"><input type="checkbox" name="modules[]" value="<?= e($m) ?>"<?= !empty($mods[$m]) ? ' checked' : '' ?>><span><strong><?= e(t('plateforme.module.' . $m)) ?></strong></span></label>
    <?php endforeach; ?>
    <label class="case"><input type="checkbox" name="actif" value="1"<?= (int) $f['actif'] === 1 ? ' checked' : '' ?>><span><strong><?= e(t('plateforme.forfait_actif')) ?></strong></span></label>
    <div class="boutons"><button class="bouton"><?= e(t('commun.enregistrer')) ?></button><?php if ((int) $f['id'] !== 0): ?><a class="bouton clair" href="<?= e(url('/plateforme/forfaits')) ?>"><?= e(t('commun.annuler')) ?></a><?php endif; ?></div>
  </form>
</section>
