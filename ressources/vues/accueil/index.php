<?php
use AvecXY\Domaine\Montant;

$fonctions = ['carnets', 'collecte', 'controle', 'credits', 'caisse', 'rapports'];
$limite = static fn ($v) => $v === null ? t('abonnement.illimite') : (string) $v;
$aContact = array_filter($contact);
?>
<section class="ac-hero">
  <div>
    <p class="ac-sur"><?= e(t('accueil.sur_titre')) ?></p>
    <h1><?= e(t('accueil.titre')) ?></h1>
    <p class="ac-chapo"><?= e(t('accueil.chapo')) ?></p>
    <div class="boutons">
      <a class="bouton large" href="#forfaits"><?= e(t('accueil.voir_forfaits')) ?></a>
      <a class="bouton clair large" href="<?= e(url('/connexion')) ?>"><?= e(t('connexion.bouton')) ?></a>
    </div>
    <ul class="ac-preuves">
      <li><?= e(t('accueil.preuve1')) ?></li>
      <li><?= e(t('accueil.preuve2')) ?></li>
      <li><?= e(t('accueil.preuve3')) ?></li>
    </ul>
  </div>
  <div class="ac-ecran" aria-hidden="true">
    <div class="ac-ecran-barre"><i></i><i></i><i></i></div>
    <div class="ac-ecran-corps">
      <div class="ac-mini fort"><span><?= e(t('caisse.en_main')) ?></span><strong>1 248 500</strong></div>
      <div class="ac-mini"><span><?= e(t('tableau.epargne')) ?></span><strong>3 906 000</strong></div>
      <div class="ac-mini alerte"><span><?= e(t('tableau.en_attente')) ?></span><strong>86 000</strong></div>
      <svg viewBox="0 0 320 110" class="ac-courbe"><path d="M0 92 C40 84 60 70 96 66 S150 44 190 40 S262 18 320 10 L320 110 L0 110 Z" fill="#E4F2EE"/><path d="M0 92 C40 84 60 70 96 66 S150 44 190 40 S262 18 320 10" fill="none" stroke="#176B5F" stroke-width="4" stroke-linecap="round"/></svg>
      <div class="ac-lignes"><p><b>7MXW-1</b><span>AMINA</span><em>+ 5 000</em></p><p><b>RZFA-4</b><span>JOSEPH</span><em>+ 2 000</em></p><p><b>7MXW-9</b><span>GRACE</span><em>+ 10 000</em></p></div>
    </div>
  </div>
</section>

<section class="ac-section" id="fonctions">
  <h2><?= e(t('accueil.fonctions_titre')) ?></h2>
  <p class="ac-intro"><?= e(t('accueil.fonctions_intro')) ?></p>
  <div class="ac-grille">
    <?php foreach ($fonctions as $i => $f): ?>
      <article class="ac-carte">
        <span class="ac-numero"><?= e(str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT)) ?></span>
        <h3><?= e(t('accueil.f.' . $f)) ?></h3>
        <p><?= e(t('accueil.f.' . $f . '_texte')) ?></p>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<section class="ac-section ac-bande">
  <h2><?= e(t('accueil.regles_titre')) ?></h2>
  <p class="ac-intro"><?= e(t('accueil.regles_intro')) ?></p>
  <ul class="ac-regles">
    <?php foreach (['carnets', 'partage', 'credit', 'droits'] as $r): ?><li><strong><?= e(t('accueil.r.' . $r)) ?></strong><span><?= e(t('accueil.r.' . $r . '_texte')) ?></span></li><?php endforeach; ?>
  </ul>
</section>

<section class="ac-section" id="forfaits">
  <h2><?= e(t('accueil.forfaits_titre')) ?></h2>
  <p class="ac-intro"><?= e(t('accueil.forfaits_intro')) ?></p>
  <?php if (!$plans): ?><p class="aide"><?= e(t('accueil.forfaits_bientot')) ?></p><?php endif; ?>
  <div class="forfaits">
  <?php foreach ($plans as $p): $m = json_decode((string) $p['modules'], true) ?: []; $essai = (float) $p['prix'] <= 0; ?>
    <article class="forfait">
      <h3><?= e($p['nom']) ?></h3>
      <p class="prix"><?php if ($essai): ?><strong><?= e(t('accueil.gratuit')) ?></strong> · <?= e(t('abonnement.jours', ['n' => $p['duree_jours']])) ?><?php else: ?><strong><?= e(Montant::afficher($p['prix'], 2) . ' ' . $p['devise_code']) ?></strong> / <?= e(t('abonnement.jours', ['n' => $p['duree_jours']])) ?><?php endif; ?></p>
      <?php if ($p['description']): ?><p class="aide"><?= e($p['description']) ?></p><?php endif; ?>
      <ul>
        <li><?= e(t('abonnement.inclus_utilisateurs', ['n' => $limite($p['max_utilisateurs'])])) ?></li>
        <li><?= e(t('abonnement.inclus_recolteurs', ['n' => $limite($p['max_agents'])])) ?></li>
        <li><?= e(t('abonnement.inclus_clients', ['n' => $limite($p['max_clients'])])) ?></li>
        <?php foreach (array_keys(array_filter($m)) as $mod): ?><li><?= e(t('plateforme.module.' . $mod)) ?></li><?php endforeach; ?>
      </ul>
    </article>
  <?php endforeach; ?>
  </div>
</section>

<section class="ac-section ac-appel">
  <h2><?= e(t('accueil.appel_titre')) ?></h2>
  <p class="ac-intro"><?= e(t('accueil.appel_texte')) ?></p>
  <div class="boutons">
    <?php if ($contact['whatsapp'] !== ''): ?><a class="bouton large" href="https://wa.me/<?= e(preg_replace('/\D/', '', $contact['whatsapp'])) ?>" rel="noopener">WhatsApp</a><?php endif; ?>
    <?php if ($contact['telephone'] !== ''): ?><a class="bouton clair large" href="tel:<?= e(preg_replace('/[^\d+]/', '', $contact['telephone'])) ?>"><?= e($contact['telephone']) ?></a><?php endif; ?>
    <?php if ($contact['email'] !== ''): ?><a class="bouton clair large" href="mailto:<?= e($contact['email']) ?>"><?= e($contact['email']) ?></a><?php endif; ?>
    <?php if (!$aContact): ?><a class="bouton large" href="<?= e(url('/connexion')) ?>"><?= e(t('connexion.bouton')) ?></a><?php endif; ?>
  </div>
</section>
