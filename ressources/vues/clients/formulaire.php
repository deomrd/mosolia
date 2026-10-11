<form method="post" action="<?= e(url('/clients/nouveau')) ?>" data-vente-carnet>
<?= champ_csrf() ?>

<header class="carte tete-page">
  <div>
    <p class="fil"><a href="<?= e(url('/clients')) ?>"><?= e(t('menu.clients')) ?></a></p>
    <h1><?= e(t('clients.nouveau')) ?></h1>
  </div>
  <div class="boutons">
    <a class="bouton clair" href="<?= e(url('/clients')) ?>"><?= e(t('commun.annuler')) ?></a>
    <button class="bouton"><?= e(t('commun.enregistrer')) ?></button>
  </div>
</header>

<?php if ($homonymes): ?>
<section class="carte alerte" role="alert">
  <h2><?= e(t('clients.doublon_titre')) ?></h2>
  <p><?= e(t('clients.doublon_texte')) ?></p>
  <ul>
    <?php foreach ($homonymes as $h): ?><li><span class="code"><?= e($h['code']) ?></span> <?= e($h['nom']) ?></li><?php endforeach; ?>
  </ul>
  <fieldset class="choix">
    <legend><?= e(t('clients.doublon_question')) ?></legend>
    <label class="case"><input type="radio" name="doublon_choix" value="FUSIONNER" required><span><?= e(t('clients.doublon_fusionner')) ?><small><?= e(t('clients.doublon_fusionner_aide')) ?></small></span></label>
    <label class="case"><input type="radio" name="doublon_choix" value="RENOMMER"><span><?= e(t('clients.doublon_renommer')) ?><small><?= e(t('clients.doublon_renommer_aide')) ?></small></span></label>
    <label class="case"><input type="radio" name="doublon_choix" value="GARDER"><span><?= e(t('clients.doublon_garder')) ?><small><?= e(t('clients.doublon_garder_aide')) ?></small></span></label>
  </fieldset>
</section>
<?php endif; ?>

<section class="carte">
  <h2><?= e(t('clients.section_client')) ?></h2>
  <div class="grille">
    <label><?= e(t('clients.nom')) ?>
      <input name="nom" value="<?= e($d['nom']) ?>" required maxlength="160" data-majuscules autofocus>
    </label>
    <?php if ($recolteurs): ?>
    <label><?= e(t('clients.recolteur')) ?>
      <select name="agent_id" required>
        <option value=""><?= e(t('commun.choisir')) ?></option>
        <?php foreach ($recolteurs as $r): ?><option value="<?= e($r['id']) ?>"<?= (int) $d['agent_id'] === (int) $r['id'] ? ' selected' : '' ?>><?= e($r['code'] . ' · ' . $r['nom']) ?></option><?php endforeach; ?>
      </select>
    </label>
    <?php endif; ?>
    <label><?= e(t('clients.telephone')) ?>
      <input name="telephone" value="<?= e($d['telephone']) ?>" maxlength="40" inputmode="tel">
    </label>
    <label><?= e(t('clients.adresse')) ?>
      <input name="adresse" value="<?= e($d['adresse']) ?>" maxlength="255" data-majuscules>
    </label>
    <label><?= e(t('clients.activite')) ?>
      <input name="activite" value="<?= e($d['activite']) ?>" maxlength="160" data-majuscules>
    </label>
  </div>
</section>

<?php require __DIR__ . '/_dossier.php'; ?>

<section class="carte">
  <h2><?= e(t('clients.section_carnet')) ?></h2>
  <?php if (!$types): ?>
    <p class="aide"><?= e(t('carnets.aucun_type')) ?></p>
  <?php else: $carnetDate = $d['date']; $carnetType = (int) $d['type_id']; $carnetMise = $d['mise']; $carnetVersement = $d['versement']; $facultatif = true;
    require __DIR__ . '/../carnets/_champs_vente.php'; endif; ?>
</section>
</form>
