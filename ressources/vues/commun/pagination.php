<?php
// Attend : $total (lignes), $page (courante). 50 lignes par page.
$pages = (int) ceil($total / 50);
if ($pages > 1): ?>
<nav class="pagination" aria-label="<?= e(t('commun.pages')) ?>">
  <?php if ($page > 1): ?><a class="bouton clair" href="<?= e(url_avec(['page' => $page - 1])) ?>"><?= e(t('commun.precedent')) ?></a><?php endif; ?>
  <span><?= e(t('commun.page_sur', ['page' => $page, 'pages' => $pages])) ?></span>
  <?php if ($page < $pages): ?><a class="bouton clair" href="<?= e(url_avec(['page' => $page + 1])) ?>"><?= e(t('commun.suivant')) ?></a><?php endif; ?>
</nav>
<?php endif; ?>
