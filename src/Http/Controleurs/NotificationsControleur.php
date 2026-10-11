<?php
namespace AvecXY\Http\Controleurs;

use AvecXY\Application\ServiceNotifications;
use AvecXY\Http\Controleur;
use AvecXY\Noyau\Reponse;

/** Liste des notifications : personnel, plateforme et espace client. Ouvrir la liste les marque comme lues. */
final class NotificationsControleur extends Controleur
{
    private function liste(string $destinataire, int $id, string $gabarit): Reponse
    {
        $n = $this->noyau->notifications();
        $lignes = $n->liste($destinataire, $id);
        $n->toutLire($destinataire, $id);
        return $this->page('commun/notifications', ['titre' => t('notif.titre'), 'lignes' => $lignes], $gabarit);
    }

    public function personnel(): Reponse
    {
        return $this->liste(ServiceNotifications::UTILISATEUR, $this->contexte()->utilisateurId(), 'gabarit/application');
    }

    public function plateforme(): Reponse
    {
        return $this->liste(ServiceNotifications::PLATEFORME, 0, 'gabarit/plateforme');
    }

    public function client(): Reponse
    {
        return $this->liste(ServiceNotifications::CLIENT, (int) $this->noyau->comptePortail['id'], 'gabarit/portail');
    }
}
