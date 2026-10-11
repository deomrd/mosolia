<header class="carte tete-page">
  <div>
    <p class="fil"><a href="<?= e(url('/clients')) ?>"><?= e(t('menu.clients')) ?></a></p>
    <h1><span class="code"><?= e($client['code']) ?></span> <?= e($client['nom']) ?></h1>
    <p class="aide"><?= e(t('clients.recolteur')) ?> : <?= e($client['recolteur_code'] . ' · ' . $client['recolteur_nom']) ?></p>
  </div>
  <div class="boutons">
    <?php if (peut('clients.modifier')): ?><a class="bouton clair" href="<?= e(url('/clients/' . $client['id'] . '/modifier')) ?>"><?= e(t('commun.modifier')) ?></a><?php endif; ?>
    <?php if (peut('carnets.vendre') && $client['statut'] === 'ACTIF'): ?><a class="bouton" href="<?= e(url('/carnets/nouveau?client=' . $client['id'])) ?>"><?= e(t('carnets.vendre')) ?></a><?php endif; ?>
  </div>
</header>

<section class="carte dossier-client">
  <div class="photo-client">
    <?php if (!empty($client['photo'])): ?>
      <img src="<?= e(url('/clients/' . $client['id'] . '/photo')) ?>" alt="<?= e(t('clients.photo_de', ['nom' => $client['nom']])) ?>">
    <?php else: ?>
      <span class="initiales" aria-hidden="true"><?= e(mb_substr((string) $client['nom'], 0, 1)) ?></span>
    <?php endif; ?>
    <?php if (peut('clients.modifier')): ?>
    <form class="seulement-ordinateur" method="post" action="<?= e(url('/clients/' . $client['id'] . '/photo')) ?>" enctype="multipart/form-data" data-envoi-photo>
      <?= champ_csrf() ?>
      <label class="bouton clair petit"><?= e(t(empty($client['photo']) ? 'clients.ajouter_photo' : 'clients.changer_photo')) ?>
        <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" capture="user" hidden>
      </label>
    </form>
    <?php endif; ?>
  </div>
  <div class="tuiles">
    <div class="tuile"><span><?= e(t('clients.telephone')) ?></span><strong class="petit"><?= e($client['telephone'] ?? '—') ?></strong></div>
    <div class="tuile"><span><?= e(t('clients.adresse')) ?></span><strong class="petit"><?= e($client['adresse'] ?? '—') ?></strong></div>
    <div class="tuile"><span><?= e(t('clients.activite')) ?></span><strong class="petit"><?= e($client['activite'] ?? '—') ?></strong></div>
    <div class="tuile"><span><?= e(t('clients.depuis')) ?></span><strong class="petit"><?= e(jour($client['cree_le'])) ?></strong></div>
    <div class="tuile"><span><?= e(t('clients.identite')) ?></span><strong class="petit"><?= e(implode(' · ', array_filter([
        $client['sexe'] ? t('clients.sexe.' . strtolower($client['sexe'])) : null,
        $client['date_naissance'] ? t('clients.ne_le', ['date' => jour($client['date_naissance'])]) . ($client['lieu_naissance'] ? ' ' . t('clients.a_lieu', ['lieu' => $client['lieu_naissance']]) : '') : null,
    ])) ?: '—') ?></strong></div>
    <div class="tuile"><span><?= e(t('clients.piece')) ?></span><strong class="petit"><?= e($client['piece_type'] ? t('clients.piece.' . strtolower($client['piece_type'])) . ' ' . ($client['piece_numero'] ?? '') : '—') ?></strong>
      <?php if ($client['piece_expire_le']): ?><small class="<?= $client['piece_expire_le'] < date('Y-m-d') ? 'erreur-texte' : '' ?>"><?= e(t($client['piece_expire_le'] < date('Y-m-d') ? 'clients.piece_expiree' : 'clients.piece_valide', ['date' => jour($client['piece_expire_le'])])) ?></small><?php endif; ?></div>
    <?php if ($client['contact_urgence']): ?><div class="tuile"><span><?= e(t('clients.contact_urgence')) ?></span><strong class="petit"><?= e($client['contact_urgence']) ?></strong></div><?php endif; ?>
  </div>
</section>

<section class="carte">
  <h2><?= e(t('menu.carnets')) ?></h2>
  <?php if (!$carnets): ?><p class="aide"><?= e(t('clients.aucun_carnet')) ?></p><?php else: ?>
  <div class="defile"><table>
    <thead><tr>
      <th><?= e(t('carnets.numero')) ?></th><th class="tel-cache"><?= e(t('carnets.type')) ?></th><th class="tel-cache"><?= e(t('carnets.date_ouverture')) ?></th>
      <th class="tel-cache nombre"><?= e(t('carnets.avancement')) ?></th><th class="nombre"><?= e(t('clients.epargne')) ?></th><th><?= e(t('utilisateurs.statut')) ?></th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($carnets as $k): ?>
      <tr>
        <td><a href="<?= e(url('/carnets/' . $k['id'])) ?>"><strong>#<?= e($k['numero']) ?></strong></a></td>
        <td><?= e($k['type_nom']) ?></td>
        <td><?= e(jour($k['date_ouverture'])) ?></td>
        <td class="nombre"><?= $k['mode'] === 'PARTS' ? e($k['parts_validees'] . ' / ' . $k['nombre_parts']) : e(argent($k['total_cotise'])) ?></td>
        <td class="nombre"><strong><?= e(argent($k['epargne'])) ?></strong></td>
        <td><span class="pastille <?= $k['statut'] === 'ACTIF' ? 'verte' : 'grise' ?>"><?= e(t('carnets.statut.' . strtolower($k['statut']))) ?></span></td>
        <td class="actions"><a href="<?= e(url('/carnets/' . $k['id'])) ?>"><?= e(t('commun.ouvrir')) ?></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>

<?php if ($credits): ?>
<section class="carte">
  <h2><?= e(t('menu.credits')) ?></h2>
  <div class="defile"><table>
    <thead><tr><th>#</th><th class="tel-cache"><?= e(t('commun.date')) ?></th><th class="tel-cache"><?= e(t('credits.echeance')) ?></th><th class="tel-cache nombre"><?= e(t('credits.prete')) ?></th><th class="nombre"><?= e(t('credits.restant')) ?></th><th><?= e(t('credits.statut')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($credits as $cr): ?>
      <tr>
        <td><a href="<?= e(url('/credits/' . $cr['id'])) ?>"><strong>#<?= e($cr['numero']) ?></strong></a></td>
        <td><?= e(jour($cr['date_credit'])) ?></td><td><?= e(jour($cr['date_echeance'])) ?></td>
        <td class="nombre"><?= e(argent($cr['montant'])) ?></td><td class="nombre"><strong><?= e(argent($cr['restant'])) ?></strong></td>
        <td><span class="pastille <?= ['EN_COURS' => 'verte', 'EN_RETARD' => 'rouge'][$cr['statut']] ?? 'grise' ?>"><?= e(t('credits.statut.' . strtolower($cr['statut']))) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php endif; ?>

<?php if ($espaceClient): $ouvert = $acces !== null && (int) $acces['actif'] === 1; ?>
<section class="carte">
  <div class="tete-section">
    <h2><?= e(t('portail.titre')) ?></h2>
    <span class="pastille <?= $ouvert ? 'verte' : 'grise' ?>"><?= e(t($ouvert ? 'portail.acces_actif' : 'portail.acces_inactif')) ?></span>
  </div>
  <p class="aide"><?= e(t('portail.fiche_aide', ['code' => $client['code']])) ?><?= $ouvert && $acces['derniere_connexion'] ? ' ' . e(t('portail.derniere_connexion', ['date' => date('d/m/Y H:i', strtotime($acces['derniere_connexion']))])) : '' ?></p>
  <div class="boutons">
    <form method="post" action="<?= e(url('/clients/' . $client['id'] . '/espace-client')) ?>" data-confirmer="<?= e(t($ouvert ? 'portail.confirmer_reinitialiser' : 'portail.confirmer_ouvrir')) ?>">
      <?= champ_csrf() ?><button class="bouton clair"><?= e(t($ouvert ? 'portail.reinitialiser' : 'portail.ouvrir')) ?></button>
    </form>
    <?php if ($ouvert): ?>
    <form method="post" action="<?= e(url('/clients/' . $client['id'] . '/espace-client/fermer')) ?>" data-confirmer="<?= e(t('portail.confirmer_fermer')) ?>">
      <?= champ_csrf() ?><button class="lien danger"><?= e(t('portail.fermer')) ?></button>
    </form>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php if (peut('clients.supprimer')): ?>
<section class="carte">
  <h2><?= e(t('commun.zone_suppression')) ?></h2>
  <p class="aide"><?= e(t('clients.suppression_aide')) ?></p>
  <form method="post" action="<?= e(url('/clients/' . $client['id'] . '/supprimer')) ?>" class="ligne-form" data-confirmer="<?= e(t('corbeille.confirmer', ['nom' => $client['code'] . ' ' . $client['nom']])) ?>">
    <?= champ_csrf() ?>
    <label><?= e(t('corbeille.motif')) ?><input name="motif" maxlength="255"></label>
    <button class="bouton danger"><?= e(t('commun.supprimer')) ?></button>
  </form>
</section>
<?php endif; ?>

<?php if (!empty($identifiants)): ?>
<dialog class="fenetre" data-ouvrir aria-labelledby="titre-identifiants">
  <div class="fenetre-corps">
    <h2 id="titre-identifiants"><?= e(t('portail.identifiants_titre')) ?></h2>
    <p><?= e(t('portail.identifiants_aide', ['nom' => $identifiants['nom']])) ?></p>
    <dl class="identifiants">
      <dt><?= e(t('portail.identifiant')) ?></dt><dd><?= e($identifiants['code']) ?></dd>
      <dt><?= e(t('portail.pin_defaut')) ?></dt><dd><?= e($identifiants['pin']) ?></dd>
    </dl>
    <p class="aide"><?= e(t('portail.identifiants_note', ['adresse' => url_absolue('/espace-client')])) ?></p>
    <div class="boutons sans-impression">
      <button type="button" class="bouton" data-fermer-fenetre><?= e(t('portail.identifiants_note_ok')) ?></button>
      <button type="button" class="bouton clair" data-imprimer><?= e(t('commun.imprimer')) ?></button>
    </div>
  </div>
</dialog>
<?php endif; ?>
