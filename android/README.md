# Mosolia — application Android

Une seule application pour tout le monde. Elle est écrite en Kotlin avec Jetpack Compose et s'installe sur Android 6 et plus récent (minSdk 23).

## Connexion

Il y a un seul écran de connexion, avec deux champs : « Identifiant ou code client », puis le mot de passe ou le code secret. L'adresse du serveur est intégrée à l'application et n'est jamais affichée.

Le serveur reconnaît qui se connecte et ouvre la bonne partie :

| Qui | Ce qui s'ouvre | Réseau |
|---|---|---|
| **Récolteur** | La tournée : saisies gardées dans le téléphone (SQLite), envoyées dès qu'il y a du réseau | Hors ligne possible |
| **Gestion** (caissier, contrôleur, gérant, administrateur…) | Le site Mosolia dans l'application. Chacun ne voit que les pages permises par ses droits | Internet obligatoire |
| **Les deux** (récolteur et gestionnaire) | La tournée, avec un bouton **Gestion**. Depuis la gestion, le bouton **Ma tournée** ramène à la tournée | Tournée hors ligne, gestion en ligne |
| **Client** (code client, par exemple `MEEG-1`) | Son espace client : carnets, crédits, notifications | Internet obligatoire |

Pour le personnel, un **PIN à 4 chiffres** (ou l'empreinte) ouvre ensuite l'application. Le client, lui, n'a pas de PIN du téléphone : il se sert de son code secret.

## Dans la partie en ligne

- Il n'y a pas de fichiers sur le téléphone : modèles Excel, import de la reprise, exports, impression et photo du client restent sur ordinateur. Le serveur cache ces boutons dès qu'il reconnaît l'application, qui ajoute `MosoliaApp` à l'agent du navigateur.
- Sur le téléphone, les tableaux n'affichent que l'essentiel. Le reste se voit en ouvrant la fiche.
- Les liens téléphone, e-mail et WhatsApp s'ouvrent dans l'application qui convient.
- Sans réseau, un écran « Pas de connexion » s'affiche avec un bouton « Réessayer ».

## Tournée du récolteur (hors ligne)

- Accueil : l'argent à déposer, le solde du mois et les crédits de ses clients.
- Cotisations, une par une ou en masse ; nouveau client ; vente de carnet.
- **Compte bloqué** : le récolteur choisit avec le client la durée et le taux proposés.
- Remboursements et demandes de crédit. L'éligibilité indicative tient compte de la garantie, et l'écran affiche le mode de calcul et les échéances.
- Saisies : on peut les corriger ou les annuler tant qu'elles ne sont pas validées. La synchronisation est automatique et une saisie n'est jamais comptée deux fois.

## Adresse du serveur

- L'adresse intégrée est `https://mosolia.boutixy.com`. Elle se trouve dans `app/build.gradle.kts`, champ `ADRESSE_SERVEUR`.
- Si le serveur déménage, il suffit de remplir *Plateforme → Réglages → « Adresse de l'application »*. Les téléphones adoptent la nouvelle adresse (https seulement) à leur prochaine connexion ou synchronisation, sans nouvel APK.

## Construire l'APK

1. Installer **Android Studio** (Koala 2024.1 ou plus récent).
2. Ouvrir ce dossier avec *File → Open* → `android/`. Android Studio télécharge Gradle 8.7 et les bibliothèques ; il recrée lui-même `gradle-wrapper.jar`.
3. Pour un essai : *Build → Build Bundle(s) / APK(s) → Build APK(s)*. L'APK se trouve dans `app/build/outputs/apk/debug/`.
4. Pour distribuer : *Build → Generate Signed Bundle / APK*. Créez une clé et gardez-la précieusement : sans elle, impossible de publier les mises à jour.

L'identifiant de l'application est `com.mosolia.app`. Il remplace l'ancienne application « AvecXY Récolteur », qu'il faut désinstaller.

## Côté serveur

- Passer par *Plateforme → Réglages → « Mettre à jour la base »*, ce qui applique les migrations jusqu'à `0014_application_complete`.
- Le site doit répondre en **https sans redirection vers http**. L'APK signé refuse le http.
- Dans *Utilisateurs*, on trouve la liste des téléphones connectés de chaque personne. Un bouton permet de désactiver un téléphone perdu.

## État

Ce code n'a jamais été compilé ni lancé sur un téléphone. Il a seulement été vérifié avec le compilateur Kotlin (syntaxe), sans le SDK Android. La première ouverture dans Android Studio peut donc signaler quelques erreurs de compilation à corriger. Le serveur (API `/api/v1`, passages, profils) est couvert par `tests/parcours.sh`.
