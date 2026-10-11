<?php use AvecXY\Domaine\Montant; ?>
<header class="carte tete-page">
  <div>
    <h1><?= e(t('menu.collecte')) ?></h1>
    <p class="aide"><?= e(t('collecte.aide')) ?></p>
  </div>
</header>

<?php if (!$restreint): ?>
<form class="carte filtres" method="get" action="<?= e(url('/collecte')) ?>">
  <label><?= e(t('clients.recolteur')) ?>
    <select name="recolteur" required>
      <option value=""><?= e(t('commun.choisir')) ?></option>
      <?php foreach ($recolteurs as $r): ?><option value="<?= e($r['id']) ?>"<?= $agentId === (int) $r['id'] ? ' selected' : '' ?>><?= e($r['code'] . ' · ' . $r['nom']) ?></option><?php endforeach; ?>
    </select>
  </label>
  <button class="bouton clair"><?= e(t('commun.afficher')) ?></button>
</form>
<?php endif; ?>

<?php if ($agentId === 0): ?>
  <section class="carte"><p class="aide"><?= e(t($restreint ? 'clients.sans_fiche_recolteur' : 'collecte.choisir_recolteur')) ?></p></section>
<?php elseif (!$carnets): ?>
  <section class="carte"><p class="aide"><?= e(t('collecte.aucun_carnet')) ?></p></section>
<?php else: ?>
<form method="post" action="<?= e(url('/collecte')) ?>" data-collecte>
  <?= champ_csrf() ?>
  <input type="hidden" name="agent_id" value="<?= e($agentId) ?>">
  <section class="carte">
    <div class="grille">
      <label><?= e(t('commun.date')) ?><input name="date" type="date" value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>" required></label>
      <label><?= e(t('collecte.filtrer')) ?><input type="search" data-collecte-filtre placeholder="<?= e(t('clients.recherche_aide')) ?>"></label>
    </div>
  </section>
  <section class="carte collecte-liste">
    <?php foreach ($carnets as $k):
        $parts = $k['mode'] === 'PARTS';
        $restantes = $parts ? (int) $k['nombre_parts'] - (int) $k['parts_validees'] - (int) $k['parts_en_attente'] : 0; ?>
    <div class="collecte-ligne" data-ligne data-texte="<?= e($k['client_code'] . ' ' . $k['client_nom']) ?>">
      <div class="collecte-client">
        <strong><span class="code"><?= e($k['client_code']) ?></span> <?= e($k['client_nom']) ?></strong>
        <small>#<?= e($k['numero']) ?> · <?= e($k['type_nom']) ?> ·
          <?= $parts ? e(t('collecte.ligne_parts', ['mise' => argent($k['mise']), 'faites' => $k['parts_validees'], 'total' => $k['nombre_parts']])) : e(t('collecte.ligne_libre', ['solde' => argent($k['epargne'])])) ?></small>
      </div>
      <?php if ($parts && $restantes <= 0): ?>
        <span class="pastille grise"><?= e(t('collecte.plein')) ?></span>
      <?php elseif ($parts): ?>
        <div class="compteur">
          <button type="button" class="bouton-icone" data-moins aria-label="<?= e(t('collecte.moins')) ?>">−</button>
          <input name="valeurs[<?= e($k['id']) ?>]" inputmode="numeric" value="" placeholder="0" data-mise="<?= e(Montant::enCentimes($k['mise'])) ?>" data-max="<?= e($restantes) ?>" aria-label="<?= e(t('cotisations.parts') . ' ' . $k['client_code']) ?>">
          <button type="button" class="bouton-icone plein" data-plus aria-label="<?= e(t('collecte.plus')) ?>">+</button>
        </div>
      <?php else: ?>
        <input class="collecte-montant" name="valeurs[<?= e($k['id']) ?>]" inputmode="decimal" placeholder="0" data-libre aria-label="<?= e(t('cotisations.montant') . ' ' . $k['client_code']) ?>">
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </section>
  <section class="carte collecte-pied">
    <div class="collecte-total"><span><?= e(t('collecte.total')) ?></span><strong data-collecte-total data-symbole="<?= e(\AvecXY\Noyau\Noyau::instance()->contexte->organisation['devise_symbole']) ?>">0</strong></div>
    <button class="bouton large"><?= e(t('collecte.enregistrer')) ?></button>
  </section>
</form>
<?php endif; ?>
