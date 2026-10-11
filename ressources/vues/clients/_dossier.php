<?php
// Dossier du client : identite, piece, personne a prevenir. Attend $d (valeurs du client ou de la saisie).
$valeur = static fn (string $cle): string => (string) ($d[$cle] ?? '');
?>
<section class="carte">
  <h2><?= e(t('clients.section_dossier')) ?></h2>
  <p class="aide"><?= e(t('clients.dossier_aide')) ?></p>
  <div class="grille">
    <label><?= e(t('clients.sexe')) ?>
      <select name="sexe">
        <option value=""><?= e(t('commun.choisir')) ?></option>
        <?php foreach (['F', 'M'] as $s): ?><option value="<?= e($s) ?>"<?= $valeur('sexe') === $s ? ' selected' : '' ?>><?= e(t('clients.sexe.' . strtolower($s))) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label><?= e(t('clients.date_naissance')) ?><input type="date" name="date_naissance" value="<?= e($valeur('date_naissance')) ?>" max="<?= e(date('Y-m-d')) ?>"></label>
    <label><?= e(t('clients.lieu_naissance')) ?><input name="lieu_naissance" value="<?= e($valeur('lieu_naissance')) ?>" maxlength="120" data-majuscules></label>
    <label><?= e(t('clients.piece_type')) ?>
      <select name="piece_type">
        <option value=""><?= e(t('commun.choisir')) ?></option>
        <?php foreach (\AvecXY\Application\ServiceClients::PIECES as $p): ?><option value="<?= e($p) ?>"<?= $valeur('piece_type') === $p ? ' selected' : '' ?>><?= e(t('clients.piece.' . strtolower($p))) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label><?= e(t('clients.piece_numero')) ?><input name="piece_numero" value="<?= e($valeur('piece_numero')) ?>" maxlength="60" data-majuscules></label>
    <label><?= e(t('clients.piece_expire_le')) ?><input type="date" name="piece_expire_le" value="<?= e($valeur('piece_expire_le')) ?>"></label>
    <label><?= e(t('clients.contact_urgence')) ?><input name="contact_urgence" value="<?= e($valeur('contact_urgence')) ?>" maxlength="160"><small><?= e(t('clients.contact_urgence_aide')) ?></small></label>
  </div>
</section>
