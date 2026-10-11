<?php use AvecXY\Domaine\Montant; ?>
<header class="carte tete-page">
  <div>
    <h1><?= e(t('menu.groupe.controle')) ?> · <?= e(t('menu.controle_' . $type)) ?></h1>
    <p class="aide"><?= e(t('controle.aide')) ?></p>
  </div>
  <a class="bouton clair" href="<?= e(url('/controle/historique')) ?>"><?= e(t('menu.controle_historique')) ?></a>
</header>

<section class="carte">
  <h2><?= e(t('controle.par_recolteur')) ?></h2>
  <?php if (!$resume): ?><p class="aide"><?= e(t('validation.rien')) ?></p><?php else: ?>
  <div class="tuiles">
    <?php foreach ($resume as $r): ?>
      <a class="tuile<?= (int) $r['id'] === $agent ? ' fort' : '' ?>" href="<?= e(url('/controle/' . $type . '?recolteur=' . $r['id'])) ?>"<?= (int) $r['id'] === $agent ? ' aria-current="true"' : '' ?>>
        <span><span class="code"><?= e($r['code']) ?></span> <?= e($r['nom']) ?></span>
        <strong><?= e(argent($r['montant'])) ?></strong>
        <small><?= e(t('controle.en_attente_n', ['n' => $r['nombre']])) ?></small>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>

<?php if ($recolteur !== null && $lignes): ?>
<form class="carte" method="post" action="<?= e(url('/controle/' . $type)) ?>" data-controle data-symbole="<?= e(\AvecXY\Noyau\Noyau::instance()->contexte->organisation['devise_symbole']) ?>">
  <?= champ_csrf() ?><input type="hidden" name="recolteur" value="<?= e($agent) ?>">
  <div class="tete-section">
    <h2><span class="code"><?= e($recolteur['code']) ?></span> <?= e($recolteur['nom']) ?></h2>
    <label class="case"><input type="checkbox" data-controle-tout checked><span><?= e(t('commun.tout_cocher')) ?></span></label>
  </div>
  <p class="aide"><?= e(t('controle.cocher_aide')) ?></p>
  <div class="defile"><table>
    <thead><tr><th></th><th class="tel-cache"><?= e(t('commun.date')) ?></th><th><?= e(t('clients.client')) ?></th><th class="tel-cache"><?= e(t($type === 'paiements' ? 'credits.credit' : 'carnets.carnet')) ?></th><th><?= e(t('controle.detail')) ?></th><th class="nombre"><?= e(t('caisse.montant')) ?></th><th class="tel-cache"><?= e(t('cotisations.saisie_par')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($lignes as $l): $bloque = (int) $l['bloque'] === 1; ?>
      <tr>
        <td><input type="checkbox" name="ids[]" value="<?= e($l['id']) ?>" data-montant="<?= e(Montant::enCentimes($l['montant'])) ?>"<?= $bloque ? '' : ' checked' ?> aria-label="<?= e($l['client_code'] . ' ' . argent($l['montant'])) ?>"></td>
        <td><?= e(jour($l['jour'])) ?></td>
        <td><span class="code"><?= e($l['client_code']) ?></span> <?= e($l['client_nom']) ?></td>
        <td>#<?= e($l['reference']) ?></td>
        <td><?= e($type === 'cotisations' ? ($l['detail'] !== null ? t('portail.parts', ['n' => $l['detail']]) : t('cotisations.depot')) : ($l['detail'] ?? '')) ?><?= $bloque ? ' <span class="pastille ambre">' . e(t('controle.vente_a_valider')) . '</span>' : '' ?></td>
        <td class="nombre"><strong><?= e(argent($l['montant'])) ?></strong></td>
        <td><?= e($l['auteur'] ?? '—') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>

  <div class="controle-pied">
    <div class="tuile"><span><?= e(t('controle.total_coche')) ?></span><strong data-controle-total>0</strong></div>
    <label><?= e(t('controle.depose')) ?>
      <input name="depose" inputmode="decimal" data-controle-depose autocomplete="off">
      <small><?= e(t('controle.depose_aide')) ?></small>
    </label>
    <div class="tuile" data-controle-ecart hidden><span data-libelle data-manquant="<?= e(t('remises.manquant')) ?>" data-excedent="<?= e(t('remises.excedent')) ?>" data-juste="<?= e(t('controle.juste')) ?>"></span><strong></strong></div>
  </div>
  <div class="boutons">
    <button class="bouton" name="action" value="VALIDER"><?= e(t('controle.valider_coches')) ?></button>
    <button class="bouton danger" name="action" value="REJETER" data-confirmer-clic="<?= e(t('controle.confirmer_rejet')) ?>"><?= e(t('controle.rejeter_coches')) ?></button>
  </div>
</form>
<?php elseif ($recolteur !== null): ?>
<section class="carte"><p class="aide"><?= e(t('controle.rien_pour_lui', ['nom' => $recolteur['nom']])) ?></p></section>
<?php endif; ?>
