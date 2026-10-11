<?php
use AvecXY\Application\ServiceInstallation as I;

$etapes = [I::CONFIG => 1, I::TABLES => 2, I::ADMIN => 3];
$n = $etapes[$etat];
?>
<div class="carte formulaire">
  <h1><?= e(t('installation.titre')) ?></h1>
  <p class="aide"><?= e(t('installation.etape', ['n' => $n])) ?> — <?= e(t('installation.etape.' . $n)) ?></p>
  <?php if (!$phpOk): ?><p class="message message-erreur"><?= e(t('installation.php_ancien', ['version' => $php])) ?></p><?php endif; ?>
  <?php if (!$pdoOk): ?><p class="message message-erreur"><?= e(t('installation.pdo_absent')) ?></p><?php endif; ?>
  <?php if (!$ecritureStockage): ?><p class="message message-info"><?= e(t('installation.stockage')) ?></p><?php endif; ?>
</div>

<?php if ($etat === I::CONFIG): ?>
<form class="carte formulaire" method="post" action="<?= e(url('/installation/base')) ?>" autocomplete="off">
  <?= champ_csrf() ?>
  <h2><?= e(t('installation.etape.1')) ?></h2>
  <p class="aide"><?= e(t('installation.base_aide')) ?></p>
  <?php if ($configExiste && $erreurConnexion): ?><p class="message message-erreur"><?= e(t('installation.connexion_impossible', ['detail' => $erreurConnexion])) ?></p><?php endif; ?>
  <?php if (!$ecritureConfig): ?><p class="message message-erreur"><?= e(t('installation.ecriture_impossible')) ?></p><?php endif; ?>
  <label><?= e(t('installation.hote')) ?><input name="hote" value="localhost" required></label>
  <label><?= e(t('installation.port')) ?><input name="port" value="3306" inputmode="numeric"></label>
  <label><?= e(t('installation.base')) ?><input name="base" value="avecxy" required><small><?= e(t('installation.base_vide')) ?></small></label>
  <label><?= e(t('installation.utilisateur')) ?><input name="utilisateur" value="root" required autocapitalize="none"></label>
  <label><?= e(t('connexion.mot_de_passe')) ?><input name="mot_de_passe" type="password" autocomplete="off"></label>
  <button class="bouton large"><?= e(t('installation.continuer')) ?></button>
</form>

<?php elseif ($etat === I::TABLES): ?>
<form class="carte formulaire" method="post" action="<?= e(url('/installation/tables')) ?>">
  <?= champ_csrf() ?>
  <h2><?= e(t('installation.etape.2')) ?></h2>
  <p class="aide"><?= e(t('installation.tables_aide')) ?></p>
  <button class="bouton large"><?= e(t('installation.creer_tables')) ?></button>
</form>

<?php else: ?>
<form class="carte formulaire" method="post" action="<?= e(url('/installation/admin')) ?>" autocomplete="off">
  <?= champ_csrf() ?>
  <h2><?= e(t('installation.etape.3')) ?></h2>
  <p class="aide"><?= e(t('installation.admin_aide')) ?></p>
  <label><?= e(t('installation.votre_nom')) ?><input name="nom" required></label>
  <label><?= e(t('plateforme.email')) ?><input name="email" type="email" required autocomplete="username"></label>
  <label><?= e(t('plateforme.nouveau_mot_de_passe')) ?><input name="mot_de_passe" type="password" required minlength="10" autocomplete="new-password"></label>
  <button class="bouton large"><?= e(t('installation.terminer')) ?></button>
</form>
<?php endif; ?>
