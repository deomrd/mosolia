<header class="carte tete-page">
  <div>
    <h1><?= e(t('menu.support')) ?></h1>
    <p class="aide"><?= e(t('support.aide')) ?></p>
  </div>
</header>

<section class="carte">
  <h2><?= e(t('support.nouvelle')) ?></h2>
  <form method="post" action="<?= e(url('/support')) ?>">
    <?= champ_csrf() ?>
    <div class="grille">
      <label><?= e(t('support.sujet')) ?><input name="sujet" required maxlength="190"></label>
      <label><?= e(t('support.categorie')) ?>
        <select name="categorie"><?php foreach ($categories as $cat): ?><option value="<?= e($cat) ?>"><?= e(t('support.categorie.' . strtolower($cat))) ?></option><?php endforeach; ?></select>
      </label>
    </div>
    <label><?= e(t('support.message')) ?><textarea name="message" rows="4" required></textarea></label>
    <label class="case"><input type="checkbox" name="urgent" value="1"><span><strong><?= e(t('support.urgent')) ?></strong><small><?= e(t('support.urgent_aide')) ?></small></span></label>
    <div class="boutons"><button class="bouton"><?= e(t('support.envoyer')) ?></button></div>
  </form>
</section>

<section class="carte">
  <h2><?= e(t('support.mes_demandes')) ?></h2>
  <?php if (!$tickets): ?><p class="aide"><?= e(t('support.aucun')) ?></p><?php else: ?>
  <div class="defile"><table>
    <thead><tr><th>#</th><th><?= e(t('support.sujet')) ?></th><th class="tel-cache"><?= e(t('support.categorie')) ?></th><th><?= e(t('credits.statut')) ?></th><th class="tel-cache"><?= e(t('support.dernier_message')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($tickets as $t): ?>
      <tr>
        <td><?= e($t['numero']) ?></td>
        <td><a href="<?= e(url('/support/' . $t['id'])) ?>"><strong><?= e($t['sujet']) ?></strong></a></td>
        <td><?= e(t('support.categorie.' . strtolower($t['categorie']))) ?></td>
        <td><span class="pastille <?= $t['statut'] === 'EN_ATTENTE_CLIENT' ? 'ambre' : (in_array($t['statut'], ['RESOLU', 'FERME'], true) ? 'grise' : 'verte') ?>"><?= e(t('support.statut.' . strtolower($t['statut']))) ?></span></td>
        <td><?= e($t['dernier_message_le'] ? date('d/m/Y H:i', strtotime($t['dernier_message_le'])) : '—') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
