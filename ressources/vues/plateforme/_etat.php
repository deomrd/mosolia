<?php /** @var \AvecXY\Domaine\EtatAbonnement $etatLigne */
$classe = ['ACTIF' => 'verte', 'RAPPEL' => 'ambre', 'GRACE' => 'ambre', 'LECTURE_SEULE' => 'rouge', 'SUSPENDU' => 'rouge'][$etatLigne->etat]; ?>
<span class="pastille <?= $classe ?>"><?= e(t('abonnement.etat.' . strtolower($etatLigne->etat))) ?><?= $etatLigne->essai ? ' · ' . e(t('abonnement.essai')) : '' ?></span>
