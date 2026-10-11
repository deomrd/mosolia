#!/usr/bin/env bash
# Parcours complet de l'application, de la plateforme jusqu'a l'espace client.
#   bash tests/parcours.sh
# Il travaille sur une base SQLite temporaire et un serveur local a part :
# il ne lit ni ne modifie config/base.php, ni aucune donnee reelle.
# Il faut : php (avec pdo_sqlite) et curl. Port par defaut 8765 (PORT=xxxx pour changer).
set -u
A=$(cd "$(dirname "$0")/.." && pwd)
D=$(mktemp -d)
PORT=${PORT:-8765}
B=http://127.0.0.1:$PORT
export AVECXY_CONFIG_BASE=$D/base.php
printf "<?php\nreturn ['pilote' => 'sqlite', 'fichier' => '%s/test.sqlite'];\n" "$D" > "$AVECXY_CONFIG_BASE"
OK=0; KO=0
fin() { [ -n "${PID:-}" ] && kill "$PID" 2>/dev/null; rm -rf "$D"; }
trap fin EXIT

php "$A/tests/preparer_sqlite.php" "$D/test.sqlite" > /dev/null || { echo "Base de test impossible a creer"; exit 1; }
php -S 127.0.0.1:$PORT -t "$A/public" "$A/tests/serveur.php" > "$D/serveur.log" 2>&1 &
PID=$!
sleep 1

jeton() { curl -s -L -b "$2" -c "$2" "$B$1" | grep -o 'name="_jeton" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//'; }
# envoyer <cookies> <chemin> donnees... : envoie le formulaire et rend les messages affiches
envoyer() {
  local c=$1 chemin=$2; shift 2
  curl -s -L -b "$c" -c "$c" --data-urlencode "_jeton=$(jeton /connexion "$c")" "$@" "$B$chemin" | tee "$D/page.html" \
    | grep -o 'class="message message-[a-z]*"[^>]*>[^<]*' | sed 's/class="message message-\([a-z]*\)"[^>]*>/[\1] /' | tr '\n' ' '
}
# verifier "ce qu'on teste" "texte attendu" "resultat"
verifier() {
  if printf '%s' "$3" | grep -qF -- "$2"; then OK=$((OK + 1)); else KO=$((KO + 1)); echo "ECHEC  $1"; echo "       attendu : $2"; echo "       obtenu  : $3"; fi
}
page() { curl -s -o "$D/page.html" -w '%{http_code}' -b "$1" "$B$2"; }
sql() { php -r '$p = new PDO("sqlite:" . $argv[1]); echo implode("|", $p->query($argv[2])->fetch(PDO::FETCH_NUM) ?: []);' "$D/test.sqlite" "$1"; }
connecter() { # cookies identifiant provisoire definitif
  envoyer "$1" /connexion --data-urlencode "identifiant=$2" --data-urlencode "mot_de_passe=$3" > /dev/null
  envoyer "$1" /mon-compte -d "nom=X&langue=&pin=${5:-4826}&pin_confirmation=${5:-4826}" --data-urlencode "mot_de_passe_actuel=$3" --data-urlencode "mot_de_passe=$4" --data-urlencode "mot_de_passe_confirmation=$4" > /dev/null
}
JOUR=$(date +%F); MOIS=$(date +%Y-%m); DEBUT=$(date +%Y-%m-01)
P=$D/plateforme.txt; J=$D/admin.txt; M=$D/recolteur.txt; E=$D/client.txt

echo "1. Plateforme : forfait et organisation"
verifier "pas installe : la connexion mene a l'installation" "/installation" "$(curl -s -o /dev/null -w '%{redirect_url}' "$B/connexion")"
verifier "installation : etape du compte" "3" "$(curl -s "$B/installation" | grep -o 'Étape [0-9]' | head -1)"
envoyer "$P" /installation/admin -d "nom=Equipe&email=equipe@exemple.test" --data-urlencode "mot_de_passe=MotDePasse-Long1" > /dev/null
verifier "installation terminee : connecte a l'espace plateforme" 200 "$(page "$P" /plateforme)"
verifier "installation fermee ensuite" "/plateforme/connexion" "$(curl -s -o /dev/null -w '%{redirect_url}' "$B/installation")"
envoyer "$D/intrus.txt" /installation/admin -d "nom=Intrus&email=intrus@exemple.test" --data-urlencode "mot_de_passe=MotDePasse-Long2" > /dev/null
verifier "impossible de creer un second compte par l'installation" "1" "$(sql "select count(*) from avecxy_plateforme_admins")"
envoyer "$P" /plateforme/deconnexion > /dev/null
verifier "page plateforme protegee" 302 "$(page "$P" /plateforme)"
envoyer "$P" /plateforme/connexion -d "email=equipe@exemple.test" --data-urlencode "mot_de_passe=MotDePasse-Long1" > /dev/null
verifier "connexion plateforme" 200 "$(page "$P" /plateforme)"
verifier "forfait cree" "[succes]" "$(envoyer "$P" /plateforme/forfaits -d "id=0&code=PRO&nom=Pro&prix=10&devise_code=USD&duree_jours=30&ordre=1&actif=1" -d "modules[]=credit" -d "modules[]=espace_client")"
verifier "organisation creee" "[succes]" "$(envoyer "$P" /plateforme/organisations -d "nom=avec umoja&devise=CDF&forfait=PRO&langue=fr&admin_nom=jean kasongo&identifiant=jean@exemple.test&mot_de_passe=Provisoire1")"
verifier "page d'accueil publique avec les forfaits de la base" "Pro" "$(curl -s "$B/" | grep -o '<h3>Pro</h3>' | head -1)"
envoyer "$J" /connexion --data-urlencode "identifiant=jean@exemple.test" --data-urlencode "mot_de_passe=Provisoire1" > /dev/null
envoyer "$J" /mon-compte -d "nom=X&langue=" --data-urlencode "mot_de_passe_actuel=Provisoire1" --data-urlencode "mot_de_passe=MonSecret22" --data-urlencode "mot_de_passe_confirmation=MonSecret22" > /dev/null
verifier "sans PIN : renvoye vers ses identifiants" "/mon-compte" "$(curl -s -o /dev/null -w '%{redirect_url}' -b "$J" "$B/clients")"
verifier "PIN trop simple refuse" "[erreur]" "$(envoyer "$J" /mon-compte -d "nom=X&langue=&pin=1111&pin_confirmation=1111" --data-urlencode "mot_de_passe_actuel=MonSecret22")"
verifier "PIN cree" "[succes]" "$(envoyer "$J" /mon-compte -d "nom=X&langue=&pin=4826&pin_confirmation=4826" --data-urlencode "mot_de_passe_actuel=MonSecret22")"
verifier "tableau de bord" 200 "$(page "$J" /)"
verifier "mauvais PIN refuse" "[erreur]" "$(envoyer "$D/pin.txt" /connexion/pin --data-urlencode "identifiant=jean@exemple.test" -d "pin=0000")"
envoyer "$D/pin.txt" /connexion/pin --data-urlencode "identifiant=jean@exemple.test" -d "pin=4826" > /dev/null
verifier "connexion avec le PIN" 200 "$(page "$D/pin.txt" /clients)"

echo "2. Parametres : recolteurs, type de carnet, partage"
verifier "recolteur 1" "[succes]" "$(envoyer "$J" /parametres/recolteurs/nouveau -d "nom=marie salumu&telephone=0811&statut=ACTIF")"
verifier "recolteur 2" "[succes]" "$(envoyer "$J" /parametres/recolteurs/nouveau -d "nom=paul ilunga&telephone=0812&statut=ACTIF")"
verifier "identifiant de 4 caracteres" 4 "$(sql "select length(code) from avecxy_agents where id=1")"
verifier "type de carnet" "[succes]" "$(envoyer "$J" /parametres/types-carnet/nouveau -d "nom=carnet 31&mode=PARTS&duree_jours=31&prix=500&nombre_parts=31&parts_benefice_par_tranche=1&retrait=TOTAL&fermeture=PREMIER_RETRAIT&mise_minimum=500&actif=1&par_defaut=1")"
verifier "partage 45 %" "[succes]" "$(envoyer "$J" /parametres/partage -d "source=PART_BENEFICE&pourcentage=45&date=$DEBUT")"
verifier "partage refuse au-dela de 100" "[erreur]" "$(envoyer "$J" /parametres/partage -d "source=VENTE_CARNET&pourcentage=120&date=$DEBUT")"

echo "3. Clients, carnets, cotisations"
verifier "client 1" "[succes]" "$(envoyer "$J" /clients/nouveau -d "nom=Amina Élodie&agent_id=1&type_id=1&mise=1000&date=$JOUR&versement=5")"
verifier "client 2" "[succes]" "$(envoyer "$J" /clients/nouveau -d "nom=bob&agent_id=2&type_id=1&mise=2000&date=$JOUR&versement=3")"
verifier "nom en majuscules sans accents" "AMINA ELODIE" "$(sql "select nom from avecxy_clients where id=1")"
C1=$(sql "select code from avecxy_clients where id=1"); C2=$(sql "select code from avecxy_clients where id=2")
verifier "code client = code recolteur + numero" "$(sql "select code from avecxy_agents where id=1")-1" "$C1"
verifier "epargne : 5 parts dont 1 de benefice" "4000" "$(sql "select epargne from avecxy_carnets where id=1")"
verifier "partage de depart 50 / 50" "250|250" "$(sql "select part_agent, part_organisation from avecxy_benefices where source='VENTE_CARNET' and agent_id=1")"
verifier "benefice partage 45 / 55" "450|550" "$(sql "select part_agent, part_organisation from avecxy_benefices where source='PART_BENEFICE' and agent_id=1")"

echo "4. Caisse"
verifier "apport" "[succes]" "$(envoyer "$J" /caisse/mouvements -d "type=APPORT&montant=100000&date=$JOUR&source=capital")"
verifier "retrait de fonds trop grand" "[erreur]" "$(envoyer "$J" /caisse/mouvements -d "type=RETRAIT&montant=999999999&date=$JOUR")"
verifier "remise avec manquant" "[succes]" "$(envoyer "$J" /remises -d "recolteur=1&date=$JOUR&attendu=5500&verse=5300")"
verifier "paiement du recolteur (250 + 450 - 200)" "500" "$(envoyer "$J" /paiements-recolteurs -d "recolteur=1&mois=$MOIS&date=$JOUR")"
verifier "pas de double paiement" "[erreur]" "$(envoyer "$J" /paiements-recolteurs -d "recolteur=1&mois=$MOIS&date=$JOUR")"
verifier "emprunt a 5 %" "[succes]" "$(envoyer "$J" /caisse/emprunts -d "preteur=Banque&montant=20000&taux=5&date=$JOUR")"
verifier "emprunt : total du" "21000" "$(sql "select total_du from avecxy_emprunts where id=1")"
verifier "remboursement au-dela du du refuse" "[erreur]" "$(envoyer "$J" /caisse/emprunts/1/rembourser -d "montant=30000&date=$JOUR")"
verifier "remboursement d'emprunt" "[succes]" "$(envoyer "$J" /caisse/emprunts/1/rembourser -d "montant=21000&date=$JOUR")"
verifier "repartition sans regle" "[erreur]" "$(envoyer "$J" /repartition -d "mois=$MOIS&date=$JOUR")"
verifier "regle de repartition" "[succes]" "$(envoyer "$J" /parametres/partage/repartition -d "nom=&date=$DEBUT" -d "libelle[]=Admin" -d "pourcentage[]=30" -d "libelle[]=Loyer" -d "pourcentage[]=33.33" -d "libelle[]=Reserve" -d "pourcentage[]=36.67")"
verifier "repartition payee" "[succes]" "$(envoyer "$J" /repartition -d "mois=$MOIS&date=$JOUR")"
verifier "repartition = part de l'organisation" "1150|1150" "$(sql "select (select montant_base from avecxy_repartitions), (select sum(montant) from avecxy_repartition_lignes)")"
# argent en main = 100000 + 11000 + 1000 - 200 manquant - 500 paiement + 20000 emprunt - 21000 rembourse - 1150 repartition (2150 - 1000 d'interets)
verifier "journal de caisse" "109 150" "$(curl -s -b "$J" "$B/caisse" | grep -o '109 150' | head -1)"
verifier "part des recolteurs intacte" "1850" "$(sql "select sum(part_agent) from avecxy_benefices")"

verifier "manquant deja deduit : plus rien a regler" "[erreur]" "$(envoyer "$J" /remises/manquants -d "recolteur=1&montant=200&date=$JOUR")"
envoyer "$J" /remises -d "recolteur=2&date=$JOUR&attendu=6500&verse=6000" > /dev/null
verifier "remboursement d'un manquant" "[succes]" "$(envoyer "$J" /remises/manquants -d "recolteur=2&montant=300&date=$JOUR")"
verifier "reste du apres remboursement" "20000" "$(php -r 'require $argv[1] . "/src/autoload.php"; $b = AvecXY\Noyau\BaseDeDonnees::depuisConfig(require getenv("AVECXY_CONFIG_BASE")); echo (new AvecXY\Infrastructure\DepotCaisse($b))->manquantsDus(1, 2)[0]["du"];' "$A")"
# 109150 - 500 manquant + 300 regle
verifier "caisse apres les manquants" "108 950" "$(curl -s -b "$J" "$B/caisse" | grep -o '108 950' | head -1)"
verifier "regles de caisse" "[succes]" "$(envoyer "$J" /parametres/partage/manquants -d "regle=DEDUIRE&charge=AUCUNE")"
verifier "regle choisie : interets d'emprunt non retires" "AUCUNE" "$(sql "select valeur from avecxy_parametres where cle='emprunts.charge'")"

echo "5. Credits"
verifier "regles de credit" "[succes]" "$(envoyer "$J" /parametres/credit/nouveau -d "nom=credit court&taux_interet=10&duree_jours=30&taux_penalite=0&base_penalite=RESTANT&frequence_penalite=MANUELLE&reconduction=1&remboursement_epargne=1&jours_tolerance=0&montant_minimum=1000&montant_maximum=200000&multiple_epargne_max=2&credits_simultanes_max=1&sans_retard_anterieur=1&accord_agent_requis=1&derogation_autorisee=1&actif=1")"
verifier "credit direct refuse : une demande est obligatoire" "[erreur]" "$(envoyer "$J" /credits/nouveau -d "client=$C1&produit=1&montant=6000&date=$JOUR")"
verifier "demande au-dessus du maximum refusee" "[erreur]" "$(envoyer "$J" /eligibilite -d "client=$C1&produit=1&montant=300000")"
verifier "demande" "[succes]" "$(envoyer "$J" /eligibilite -d "client=$C1&produit=1&montant=20000")"
verifier "une seule demande en attente par client" "[erreur]" "$(envoyer "$J" /eligibilite -d "client=$C1&produit=1&montant=5000")"
verifier "liste des demandes : non eligible" "Non éligible" "$(curl -s -b "$J" "$B/eligibilite" | grep -o 'Non éligible' | head -1)"
verifier "demande ouverte : details" 200 "$(page "$J" /eligibilite/1)"
verifier "accord hors regles sans motif refuse" "[erreur]" "$(envoyer "$J" /eligibilite/1 -d "action=ACCORDER&montant=20000&date=$JOUR")"
verifier "accord au-dessus du maximum refuse meme par derogation" "[erreur]" "$(envoyer "$J" /eligibilite/1 -d "action=ACCORDER&montant=300000&date=$JOUR" --data-urlencode "motif_derogation=accord du comite")"
verifier "accord partiel : la dette existe tout de suite" "[succes]" "$(envoyer "$J" /eligibilite/1 -d "action=ACCORDER&montant=8000&date=$JOUR")"
verifier "demande accordee partiellement" "VALIDE|8000|20000|1" "$(sql "select statut, plafond_accorde, montant_demande, credit_id from avecxy_credit_eligibilites where id=1")"
verifier "filtre accorde partiellement" "$C1" "$(curl -s -b "$J" "$B/eligibilite?etat=PARTIEL" | grep -o "$C1" | head -1)"
verifier "interet 10 %" "800|8800" "$(sql "select montant_interet, restant from avecxy_credits where id=1")"
verifier "dette en cours : pas de nouvelle demande" "[erreur]" "$(envoyer "$J" /eligibilite -d "client=$C1&produit=1&montant=5000")"
verifier "remboursement" "[succes]" "$(envoyer "$J" /credits/1/rembourser -d "montant=5000&date=$JOUR")"
verifier "remboursement trop grand refuse" "[erreur]" "$(envoyer "$J" /credits/1/rembourser -d "montant=5000&date=$JOUR")"
verifier "solde du credit" "[succes]" "$(envoyer "$J" /credits/1/rembourser -d "montant=3800&date=$JOUR")"
verifier "credit rembourse" "REMBOURSE|0" "$(sql "select statut, restant from avecxy_credits where id=1")"
verifier "interet compte en benefice" "800" "$(sql "select sum(montant_total) from avecxy_benefices where credit_id=1")"
verifier "dette soldee : nouvelle demande possible" "[succes]" "$(envoyer "$J" /eligibilite -d "client=$C1&produit=1&montant=5000")"
verifier "refus d'une demande eligible" "[succes]" "$(envoyer "$J" /eligibilite/2 -d "action=REFUSER&motif=pas maintenant")"
verifier "demande refusee : a la corbeille" "1" "$(sql "select count(*) from avecxy_credit_eligibilites where id=2 and supprime_le is not null")"
envoyer "$J" /eligibilite -d "client=$C2&produit=1&montant=100000" > /dev/null
verifier "credit par derogation" "[succes]" "$(envoyer "$J" /eligibilite/3 -d "action=ACCORDER&montant=100000&taux=10&duree=30&date=$JOUR" --data-urlencode "motif_derogation=accord exceptionnel du comite")"
verifier "derogation notee" "1" "$(sql "select derogation from avecxy_credits where id=2")"
verifier "taux et duree fixes a l'octroi" "10000|110000|30" "$(sql "select montant_interet, restant, duree_jours from avecxy_credits where id=2")"
# 110 000 dus, 60 000 payes, echeance depassee : les 50 000 restants deviennent 55 000 pour 30 jours de plus
envoyer "$J" /credits/2/rembourser -d "montant=60000&date=$JOUR" > /dev/null
php -r '$p = new PDO("sqlite:" . $argv[1]); $p->exec("update avecxy_credits set date_credit = date(\"now\",\"-35 day\"), date_echeance = date(\"now\",\"-5 day\") where id = 2"); $p->exec("update avecxy_credit_remboursements set date_paiement = date(\"now\",\"-10 day\") where credit_id = 2"); $p->exec("delete from avecxy_parametres where cle = \"taches.dernier_jour\"");' "$D/test.sqlite"
page "$J" / > /dev/null
verifier "reconduction : 50 000 deviennent 55 000" "55000|5000|EN_COURS" "$(sql "select restant, total_penalites, statut from avecxy_credits where id=2")"
verifier "reconduction : 30 jours de plus" "25" "$(sql "select cast(julianday(date_echeance) - julianday(date(\"now\")) as integer) from avecxy_credits where id=2")"
page "$J" /credits > /dev/null
verifier "paiement par l'epargne du carnet" "[succes]" "$(envoyer "$J" /credits/2/rembourser-epargne -d "carnet=2&date=$JOUR")"
verifier "epargne retiree selon la regle du carnet (ferme)" "0|FERME" "$(sql "select epargne, statut from avecxy_carnets where id=2")"
verifier "credit diminue de l'epargne" "51000" "$(sql "select restant from avecxy_credits where id=2")"
verifier "reconduction : jamais comptee deux fois" "1" "$(sql "select count(*) from avecxy_credit_penalites where credit_id=2")"

verifier "regles avec calendrier" "[succes]" "$(envoyer "$J" /parametres/credit/nouveau -d "nom=hebdo&taux_interet=10&duree_jours=30&mode_remboursement=CALENDRIER&echeances_nombre=4&echeances_intervalle=7&credits_simultanes_max=5&reconduction=1&derogation_autorisee=1&actif=1")"
verifier "credit avec calendrier" "[succes]" "$(envoyer "$J" /credits/nouveau -d "client=$C1&produit=2&montant=10000&date=$JOUR")"
verifier "4 echeances de 2 750" "4|2750|11000" "$(sql "select count(*), min(montant), sum(montant) from avecxy_credit_echeances where credit_id=(select max(id) from avecxy_credits)")"
verifier "duree = 4 x 7 jours" "28" "$(sql "select duree_jours from avecxy_credits order by id desc limit 1")"
verifier "paiement partiel accepte" "[succes]" "$(envoyer "$J" /credits/$(sql "select max(id) from avecxy_credits")/rembourser -d "montant=1000&date=$JOUR")"
verifier "fiche avec calendrier" 200 "$(page "$J" /credits/$(sql "select max(id) from avecxy_credits"))"

echo "6. Recolteur : portefeuille restreint et saisies au controle"
RID=$(sql "select id from avecxy_roles where code='RECOLTEUR'")
verifier "compte du recolteur" "[succes]" "$(envoyer "$J" /parametres/utilisateurs/nouveau -d "nom=marie salumu&identifiant=marie@exemple.test&role_id=$RID&statut=ACTIF&langue=fr&mot_de_passe=Provisoire2")"
UID2=$(sql "select id from avecxy_utilisateurs where identifiant='marie@exemple.test'")
envoyer "$J" /parametres/recolteurs/1 -d "nom=marie salumu&telephone=0811&statut=ACTIF&utilisateur_id=$UID2" > /dev/null
connecter "$M" marie@exemple.test Provisoire2 MonSecret33
verifier "voit son client" 200 "$(page "$M" /clients/1)"
verifier "ne voit pas le client d'un autre" 404 "$(page "$M" /clients/2)"
verifier "pas d'acces a la caisse" 403 "$(page "$M" /caisse)"
verifier "cotisation envoyee au controle" "EN_ATTENTE" "$(envoyer "$M" /carnets/1/cotiser -d "valeur=2&date=$JOUR" > /dev/null; sql "select statut from avecxy_cotisations order by id desc limit 1")"
envoyer "$M" /carnets/nouveau -d "client_id=1&type_id=1&mise=500&date=$JOUR&versement=1" > /dev/null
KV=$(sql "select id from avecxy_carnets order by id desc limit 1")
verifier "vente de carnet envoyee au controle" "EN_ATTENTE" "$(sql "select validation from avecxy_carnets where id=$KV")"
verifier "vente non validee : pas de benefice" "0" "$(sql "select count(*) from avecxy_benefices where carnet_id=$KV")"
verifier "tableau de bord du recolteur : section en attente" "Argent à présenter au contrôle" "$(curl -s -b "$M" "$B/" | grep -o 'Argent à présenter au contrôle' | head -1)"
verifier "tableau de bord : date precise" 200 "$(page "$M" "/?jour=$JOUR")"
verifier "le recolteur n'accede pas au controle" 403 "$(page "$M" /controle/cotisations)"
verifier "menu controle" 200 "$(page "$J" /controle/cotisations?recolteur=1)"
O1=$(sql "select id from avecxy_cotisations where carnet_id=1 and statut='EN_ATTENTE' limit 1")
O2=$(sql "select id from avecxy_cotisations where carnet_id=$KV limit 1")
verifier "cotisation bloquee tant que la vente n'est pas validee" "[erreur]" "$(envoyer "$J" /controle/cotisations -d "recolteur=1&action=VALIDER" -d "ids[]=$O2")"
verifier "controle des carnets" "[succes]" "$(envoyer "$J" /controle/carnets -d "recolteur=1&action=VALIDER&depose=500" -d "ids[]=$KV")"
verifier "vente validee : benefice compte" "500" "$(sql "select montant_total from avecxy_benefices where carnet_id=$KV and source='VENTE_CARNET'")"
verifier "controle des cotisations avec manquant" "Manquant" "$(envoyer "$J" /controle/cotisations -d "recolteur=1&action=VALIDER&depose=1900" -d "ids[]=$O1")"
verifier "ce qui n'est pas coche reste en attente" "EN_ATTENTE" "$(sql "select statut from avecxy_cotisations where id=$O2")"
verifier "rejet : a la corbeille" "[succes]" "$(envoyer "$J" /controle/cotisations -d "recolteur=1&action=REJETER" -d "ids[]=$O2")"
verifier "cotisation rejetee dans la corbeille" "1" "$(sql "select count(*) from avecxy_cotisations where id=$O2 and supprime_le is not null")"
verifier "epargne apres validation" "6000" "$(sql "select epargne from avecxy_carnets where id=1")"
envoyer "$M" /credits/2/rembourser -d "montant=1000&date=$JOUR" > /dev/null
verifier "le recolteur ne voit pas le credit d'un autre" 404 "$(page "$M" /credits/2)"
verifier "historique des controles" "3" "$(sql "select count(*) from avecxy_controles")"
verifier "page historique" 200 "$(page "$J" "/controle/historique?du=$DEBUT&au=$JOUR")"
verifier "filtre automatique des clients" "AMINA" "$(curl -s -b "$J" "$B/clients?q=ami" | grep -o 'AMINA' | head -1)"
verifier "carnets non actifs" 200 "$(page "$J" "/carnets?statut=INACTIF")"
verifier "retraits filtres par nom" 200 "$(page "$J" "/retraits?q=bob")"
verifier "remboursements filtres par nom" 200 "$(page "$J" "/remboursements?q=bob")"

echo "7. Rapports, abonnement, support"
for r in operations cotisations retraits carnets soldes credits remboursements recolteurs manquants benefices audit; do
  verifier "rapport $r" 200 "$(page "$J" "/rapports?rapport=$r&du=$DEBUT&au=$JOUR")"
done
verifier "export CSV" "$C1" "$(curl -s -b "$J" "$B/rapports?rapport=cotisations&du=$DEBUT&au=$JOUR&export=csv")"
verifier "demande au support" "[succes]" "$(envoyer "$J" /support -d "sujet=Question&categorie=QUESTION&message=Bonjour")"
verifier "base a jour dans les reglages" "[succes]" "$(envoyer "$P" /plateforme/mise-a-jour)"
verifier "reponse du support" "[succes]" "$(envoyer "$P" /plateforme/support/1 -d "message=Bonjour, voici la reponse.")"
verifier "demande d'activation" "[succes]" "$(envoyer "$J" /abonnement -d "plan=2&moyen=Mobile Money&reference=TX1")"
verifier "activation par la plateforme" "[succes]" "$(envoyer "$P" /plateforme/organisations/1/activer -d "paiement=1&plan=2&montant=10")"
php -r '$p = new PDO("sqlite:" . $argv[1]); $p->exec("update avecxy_abonnements set debut = date(\"now\",\"-90 day\"), fin = date(\"now\",\"-30 day\")");' "$D/test.sqlite"
verifier "lecture seule : saisie bloquee" "[erreur]" "$(envoyer "$J" /clients/nouveau -d "nom=zed&agent_id=1")"
verifier "lecture seule : consultation possible" 200 "$(page "$J" /clients)"
verifier "lecture seule : support possible" "[succes]" "$(envoyer "$J" /support/1 -d "message=merci")"
php -r '$p = new PDO("sqlite:" . $argv[1]); $p->exec("update avecxy_abonnements set fin = date(\"now\",\"+30 day\")");' "$D/test.sqlite"
verifier "suspension" "[succes]" "$(envoyer "$P" /plateforme/organisations/1/statut -d "action=suspendre&motif=essai de suspension")"
verifier "suspendu : le personnel est deconnecte" 302 "$(page "$M" /)"
verifier "reactivation" "[succes]" "$(envoyer "$P" /plateforme/organisations/1/statut -d "action=reactiver")"

echo "8. Espace client"
verifier "module a activer d'abord" "[erreur]" "$(envoyer "$J" /clients/1/espace-client)"
envoyer "$J" /parametres/organisation/espace-client -d "activer=1" > /dev/null
pin() { grep -o '<dd>[0-9]\{4\}</dd>' "$D/page.html" | head -1 | tr -dc '0-9'; }
envoyer "$J" /clients/1/espace-client > /dev/null; S1=$(pin)
envoyer "$J" /clients/2/espace-client > /dev/null; S2=$(pin)
verifier "PIN par defaut a 4 chiffres, montre dans une fenetre" 4 "${#S1}"
verifier "PIN montre une seule fois" "0" "$(page "$J" /clients/1 > /dev/null; grep -c '<dd>[0-9]\{4\}</dd>' "$D/page.html")"
verifier "mauvais code secret" "[erreur]" "$(envoyer "$E" /espace-client/connexion -d "code=$C1&secret=0000")"
envoyer "$E" /espace-client/connexion -d "code=$C1&secret=$S1" > /dev/null
verifier "PIN par defaut a changer" 302 "$(page "$E" /espace-client)"
verifier "PIN trop simple refuse" "[erreur]" "$(envoyer "$E" /espace-client/code -d "actuel=$S1&nouveau=1111&confirmation=1111")"
verifier "PIN change" "[succes]" "$(envoyer "$E" /espace-client/code -d "actuel=$S1&nouveau=482913&confirmation=482913")"
verifier "le client voit son carnet" 200 "$(page "$E" /espace-client/carnets/1)"
verifier "pas le carnet d'un autre" 404 "$(page "$E" /espace-client/carnets/2)"
verifier "pas l'application du personnel" 302 "$(page "$E" /clients)"
verifier "rattacher un autre compte" "[succes]" "$(envoyer "$E" /espace-client/lier -d "code=$C2&secret=$S2")"

echo "9. Corbeille"
verifier "suppression d'un credit" "[succes]" "$(envoyer "$J" /credits/credits/1/supprimer -d "motif=essai")"
verifier "ses benefices sortent" "0" "$(sql "select count(*) from avecxy_benefices where credit_id=1")"
verifier "restauration" "[succes]" "$(envoyer "$J" /parametres/corbeille/credits/1/restaurer)"
verifier "ses benefices reviennent" "800" "$(sql "select sum(montant_total) from avecxy_benefices where credit_id=1")"

php -r '$p = new PDO("sqlite:" . $argv[1]); $p->exec("update avecxy_credit_eligibilites set supprime_le = datetime(\"now\",\"-40 day\") where supprime_le is not null"); $p->exec("delete from avecxy_parametres where cle = \"taches.dernier_jour\"");' "$D/test.sqlite"
page "$J" / > /dev/null
verifier "corbeille videe apres 30 jours" "0" "$(sql "select count(*) from avecxy_credit_eligibilites where supprime_le is not null")"

echo "10. Notifications, carnet cloture, retrait annule"
verifier "notification : demande d'activation (plateforme)" "1" "$(sql "select count(*) from avecxy_notifications where destinataire='PLATEFORME' and type='abonnement_demande'")"
verifier "notification : demande envoyee (administrateur)" "1" "$(sql "select count(*) from avecxy_notifications where destinataire='UTILISATEUR' and type='abonnement_demande_envoyee'")"
verifier "notification : forfait active (administrateur)" "1" "$(sql "select count(*) from avecxy_notifications where destinataire='UTILISATEUR' and type='abonnement_active'")"
verifier "notification : activation (plateforme)" "1" "$(sql "select count(*) from avecxy_notifications where destinataire='PLATEFORME' and type='abonnement_active_plateforme'")"
verifier "notification du controle au recolteur" "1" "$(sql "select count(*) from avecxy_notifications where destinataire='UTILISATEUR' and destinataire_id=$UID2 and type='controle_carnets_valide'")"
verifier "notification du controle au client, la sienne seulement" "1|0" "$(sql "select (select count(*) from avecxy_notifications where destinataire='CLIENT' and destinataire_id=1 and type='client_carnets_valide'), (select count(*) from avecxy_notifications where destinataire='CLIENT' and destinataire_id=2 and type='client_carnets_valide')")"
verifier "cloche avec compteur" "compteur" "$(curl -s -b "$J" "$B/clients" | grep -o 'class="compteur"' | head -1)"
verifier "page des notifications" 200 "$(page "$J" /notifications)"
verifier "notifications lues a l'ouverture" "0" "$(sql "select count(*) from avecxy_notifications where destinataire='UTILISATEUR' and destinataire_id=1 and lu_le is null")"
verifier "notifications de la plateforme" 200 "$(page "$P" /plateforme/notifications)"
verifier "notifications du client" 200 "$(page "$E" /espace-client/notifications)"
envoyer "$J" /clients/nouveau -d "nom=carine verrou&agent_id=1&type_id=1&mise=1000&date=$JOUR&versement=6" > /dev/null
KF=$(sql "select id from avecxy_carnets order by id desc limit 1"); CF=$(sql "select client_id from avecxy_carnets where id=$KF")
verifier "nouveau client : fenetre des identifiants" 4 "$(page "$J" /clients/$CF > /dev/null; envoyer "$J" /clients/$CF/espace-client > /dev/null; S3=$(pin); echo ${#S3})"
verifier "retrait qui cloture le carnet" "FERME" "$(envoyer "$J" /carnets/$KF/retirer -d "date=$JOUR" > /dev/null; sql "select statut from avecxy_carnets where id=$KF")"
verifier "retrait : le recolteur est prevenu" "1" "$(sql "select count(*) from avecxy_notifications where destinataire_id=$UID2 and type='retrait_fait'")"
verifier "retrait : le client est prevenu" "1" "$(sql "select count(*) from avecxy_notifications where destinataire='CLIENT' and destinataire_id=$CF and type='client_retrait_fait'")"
OF=$(sql "select id from avecxy_cotisations where carnet_id=$KF limit 1")
verifier "carnet cloture : pas de cotisation" "[erreur]" "$(envoyer "$J" /carnets/$KF/cotiser -d "valeur=1&date=$JOUR")"
verifier "carnet cloture : pas de correction de montant" "[erreur]" "$(envoyer "$J" /cotisations/$OF -d "valeur=2&date=$JOUR")"
verifier "carnet cloture : pas de suppression de cotisation" "[erreur]" "$(envoyer "$J" /cotisations/$OF/supprimer -d "motif=x")"
verifier "carnet cloture : pas de suppression du carnet" "[erreur]" "$(envoyer "$J" /carnets/$KF/supprimer -d "motif=x")"
verifier "carnet cloture : affiche verrouille" "verrouillé" "$(curl -s -b "$J" "$B/carnets/$KF" | grep -o 'verrouillé' | head -1)"
verifier "carnet cloture : plus dans les carnets actifs" "0" "$(curl -s -b "$J" "$B/carnets?statut=ACTIF&q=VERROU" | grep -c "/carnets/$KF\"")"
RF=$(sql "select id from avecxy_retraits where carnet_id=$KF")
envoyer "$M" /connexion --data-urlencode "identifiant=marie@exemple.test" --data-urlencode "mot_de_passe=MonSecret33" > /dev/null
verifier "le recolteur ne peut pas annuler un retrait" 403 "$(curl -s -o /dev/null -w '%{http_code}' -b "$M" -c "$M" --data-urlencode "_jeton=$(jeton /mon-compte "$M")" -d "motif=x" "$B/retraits/$RF/supprimer")"
verifier "annulation du retrait par une personne autorisee" "[succes]" "$(envoyer "$J" /retraits/$RF/supprimer -d "motif=erreur de carnet")"
verifier "carnet rouvert avec ses donnees" "ACTIF|5000" "$(sql "select statut, epargne from avecxy_carnets where id=$KF")"
verifier "annulation : le recolteur est prevenu" "1" "$(sql "select count(*) from avecxy_notifications where destinataire_id=$UID2 and type='retrait_annule'")"
verifier "annulation : le client est prevenu" "1" "$(sql "select count(*) from avecxy_notifications where destinataire='CLIENT' and destinataire_id=$CF and type='client_retrait_annule'")"
verifier "carnet rouvert : cotisation de nouveau possible" "[succes]" "$(envoyer "$J" /carnets/$KF/cotiser -d "valeur=1&date=$JOUR")"

echo "11. Credits et remboursements : listes"
verifier "remboursements : seulement ceux qui ont commence a payer" "$(sql "select count(distinct credit_id) from avecxy_credit_remboursements where supprime_le is null")" "$(curl -s -b "$J" "$B/remboursements" | grep -c 'class="actions"')"
verifier "credits : le client ouvre son credit" "/credits/1\"><span class=\"code\">" "$(curl -s -b "$J" "$B/credits?statut=TOUS" | grep -o '/credits/1"><span class="code">' | head -1)"
verifier "fiche du credit : historique et paiement" 200 "$(page "$J" /credits/1)"

echo "12. Espace client : identifiants des anciens clients, clients en masse"
verifier "page de l'espace client : adresse a donner aux clients" "/espace-client</strong>" "$(curl -s -b "$J" "$B/parametres/espace-client" | grep -o '/espace-client</strong>' | head -1)"
verifier "clients en masse" "2 client(s)" "$(envoyer "$J" /clients-en-masse -d "date=$JOUR" -d "lignes[0][nom]=masse un&lignes[0][agent_id]=1&lignes[0][type_id]=1&lignes[0][mise]=1000" -d "lignes[1][nom]=masse deux&lignes[1][agent_id]=2&lignes[1][type_id]=0&lignes[1][mise]=" -d "lignes[2][nom]=&lignes[2][agent_id]=1" > /dev/null; grep -o '2 client(s)' "$D/page.html" | head -1)"
verifier "clients en masse : PIN affiches" "2" "$(grep -c '<td><strong>[0-9]\{4\}</strong></td>' "$D/page.html")"
php -r '$p = new PDO("sqlite:" . $argv[1]); $p->exec("delete from avecxy_client_acces where client_id in (select id from avecxy_clients where nom like \"MASSE%\")");' "$D/test.sqlite"
verifier "anciens clients sans identifiants : bouton pour les generer" "Générer les identifiants" "$(curl -s -b "$J" "$B/parametres/espace-client" | grep -o 'Générer les identifiants' | head -1)"
verifier "generer les identifiants des anciens clients" "[succes]" "$(envoyer "$J" /parametres/espace-client/generer)"
verifier "liste a imprimer : identifiant et PIN" "2" "$(grep -c '<td><strong>[0-9]\{4\}</strong></td>' "$D/page.html")"
verifier "plus aucun client sans identifiants" "[info]" "$(envoyer "$J" /parametres/espace-client/generer)"

echo "13. Reprise de l'existant par fichier Excel"
verifier "choix au demarrage sur le tableau de bord" "1" "$(curl -s -b "$J" "$B/" | grep -c 'Comment voulez-vous démarrer')"
verifier "partir de vide" "[succes]" "$(envoyer "$J" /parametres/reprise/vide)"
verifier "choix fait : la question disparait" "0" "$(curl -s -b "$J" "$B/" | grep -c 'Comment voulez-vous démarrer')"
curl -s -b "$J" -o "$D/modele.xlsx" "$B/parametres/reprise/modele"
verifier "modele Excel telecharge" "6" "$(php -r 'require $argv[1] . "/src/autoload.php"; echo count(AvecXY\Infrastructure\Tableur::lire($argv[2]));' "$A" "$D/modele.xlsx")"
verifier "modele : recolteurs deja presents" "MARIE SALUMU" "$(php -r 'require $argv[1] . "/src/autoload.php"; echo AvecXY\Infrastructure\Tableur::lire($argv[2])[1]["lignes"][2][0];' "$A" "$D/modele.xlsx")"
televerser() { curl -s -L -b "$J" -c "$J" -F "_jeton=$(jeton /connexion "$J")" -F "fichier=@$1" -F "date=$JOUR" -F "caisse=$2" "$B/parametres/reprise/analyser" > "$D/page.html"; }
php "$A/tests/fichier_reprise.php" "$D/faux.xlsx" "marie salumu" "carnet 31" "credit court" erreur
televerser "$D/faux.xlsx" 40000
verifier "fichier avec erreurs : lignes a corriger" "2 ligne(s) à corriger" "$(grep -o '2 ligne(s) à corriger' "$D/page.html" | head -1)"
verifier "fichier avec erreurs : import refuse" "[erreur]" "$(envoyer "$J" /parametres/reprise/importer)"
verifier "rien n'est enregistre" "0" "$(sql "select count(*) from avecxy_clients where reprise_id is not null")"
php "$A/tests/fichier_reprise.php" "$D/bon.xlsx" "marie salumu" "carnet 31" "credit court"
solde() { php -r 'require $argv[1] . "/src/autoload.php"; $b = AvecXY\Noyau\BaseDeDonnees::depuisConfig(require getenv("AVECXY_CONFIG_BASE")); echo (int) round((float) (new AvecXY\Infrastructure\DepotCaisse($b))->solde(1));' "$A"; }
AVANT=$(solde); BEN=$(sql "select coalesce(sum(part_agent),0) from avecxy_benefices")
televerser "$D/bon.xlsx" 40000
verifier "verification : pret a importer" "Prêt à importer" "$(grep -o 'Prêt à importer' "$D/page.html" | head -1)"
verifier "verification : epargne totale" "15 000" "$(grep -o '15 000' "$D/page.html" | head -1)"
verifier "import" "[succes]" "$(envoyer "$J" /parametres/reprise/importer)"
verifier "3 clients, 1 recolteur cree" "3|1" "$(sql "select (select count(*) from avecxy_clients where reprise_id=1), (select count(*) from avecxy_agents where reprise_id=1)")"
K1=$(sql "select k.id from avecxy_carnets k join avecxy_clients c on c.id=k.client_id where c.nom='KABILA ROSE'"); K2=$(sql "select k.id from avecxy_carnets k join avecxy_clients c on c.id=k.client_id where c.nom='MUTOMBO JEAN'")
verifier "carnet repris : 10 mises, epargne 9000, carnet deja paye" "10|9000|0" "$(sql "select parts_validees, epargne, prix from avecxy_carnets where id=$K1")"
verifier "deja beneficie : benefice non repartage" "0" "$(sql "select count(*) from avecxy_benefices where carnet_id=$K1")"
verifier "pas encore beneficie : benefice partage 45 / 55" "900|1100" "$(sql "select part_agent, part_organisation from avecxy_benefices where carnet_id=$K2")"
verifier "date lue au format jour/mois/annee" "$(date -d '-5 day' +%F)" "$(sql "select date_ouverture from avecxy_carnets where id=$K2")"
verifier "credit repris : reste du 35000" "55000|20000|35000" "$(sql "select total_du, total_rembourse, restant from avecxy_credits where reprise_id=1")"
verifier "credit repris : interets deja payes non repartages" "0" "$(sql "select count(*) from avecxy_benefices where credit_id=(select id from avecxy_credits where reprise_id=1)")"
verifier "caisse : seul le solde d'ouverture entre" "$((AVANT + 40000))" "$(solde)"
verifier "le carnet repris vit normalement : cotisation" "11|10000" "$(envoyer "$J" /carnets/$K1/cotiser -d "valeur=1&date=$JOUR" > /dev/null; sql "select parts_validees, epargne from avecxy_carnets where id=$K1")"
verifier "reprise utilisee : annulation refusee" "[erreur]" "$(envoyer "$J" /parametres/reprise/1/annuler)"
OR=$(sql "select id from avecxy_cotisations where carnet_id=$K1"); envoyer "$J" /cotisations/$OR/supprimer -d "motif=essai" > /dev/null
php -r '$p = new PDO("sqlite:" . $argv[1]); $p->exec("delete from avecxy_cotisations where carnet_id = " . (int) $argv[2]);' "$D/test.sqlite" "$K1"
verifier "annulation de la reprise" "[succes]" "$(envoyer "$J" /parametres/reprise/1/annuler)"
verifier "tout ce qu'elle a cree est retire" "0|0|0|0" "$(sql "select (select count(*) from avecxy_clients where reprise_id=1), (select count(*) from avecxy_carnets where reprise_id=1), (select count(*) from avecxy_credits where reprise_id=1), (select count(*) from avecxy_agents where nom='SIFA NOUVELLE')")"
verifier "caisse et benefices comme avant" "$AVANT|$BEN" "$(solde)|$(sql "select coalesce(sum(part_agent),0) from avecxy_benefices")"

echo "14. Espace client complet : historique, attente, demandes de credit"
F=$D/client2.txt
envoyer "$J" /clients/$CF/espace-client > /dev/null; S3=$(pin); CC=$(sql "select code from avecxy_clients where id=$CF")
envoyer "$F" /espace-client/connexion -d "code=$CC&secret=$S3" > /dev/null
envoyer "$F" /espace-client/code -d "actuel=$S3&nouveau=739152&confirmation=739152" > /dev/null
verifier "espace client : menu en accordeon et onglets" "groupe-titre|onglets-bas" "$(curl -s -b "$F" "$B/espace-client" | grep -o 'class="groupe-titre"' | head -1 | cut -d'"' -f2)|$(curl -s -b "$F" "$B/espace-client" | grep -o 'onglets-bas' | head -1)"
verifier "espace client : mes carnets" 200 "$(page "$F" /espace-client/carnets)"
verifier "espace client : mes autres comptes" 200 "$(page "$F" /espace-client/comptes)"
verifier "historique complet du client" "Versement" "$(curl -s -b "$F" "$B/espace-client/historique" | grep -o 'Versement' | head -1)"
verifier "pas d'attente au depart" "0" "$(curl -s -b "$F" "$B/espace-client" | grep -c 'En attente de validation')"
envoyer "$M" /carnets/$KF/cotiser -d "valeur=2&date=$JOUR" > /dev/null
verifier "saisie du recolteur : le client est prevenu tout de suite" "1" "$(sql "select count(*) from avecxy_notifications where destinataire='CLIENT' and destinataire_id=$CF and type='client_cotisation_saisie'")"
verifier "le client voit son argent en attente" "2 000" "$(curl -s -b "$F" "$B/espace-client" | grep -A1 'En attente de validation' | grep -o '2 000' | head -1)"
verifier "releve du carnet : solde apres chaque mouvement" "Relevé du carnet" "$(curl -s -b "$F" "$B/espace-client/carnets/$KF" | grep -o 'Relevé du carnet' | head -1)"
verifier "releve : le solde final est l'epargne du carnet" "$(php -r 'echo number_format((float) $argv[1], 0, ",", " ");' "$(sql "select epargne from avecxy_carnets where id=$KF")")" "$(curl -s -b "$F" "$B/espace-client/carnets/$KF" | tr -d '\n' | grep -o '<tfoot>.*</tfoot>' | grep -o 'solde">[^<]*' | sed 's/solde">//;s/\xc2\xa0/ /g;s/\xe2\x80\xaf/ /g')"
verifier "releve general avec periode" 200 "$(page "$F" "/espace-client/historique?du=$DEBUT&au=$JOUR&type=COTISATION")"
verifier "en attente aussi dans l'historique du carnet" "En attente" "$(curl -s -b "$F" "$B/espace-client/carnets/$KF" | grep -o 'pastille ambre">En attente' | head -1)"
OA=$(sql "select id from avecxy_cotisations where carnet_id=$KF and statut='EN_ATTENTE'")
envoyer "$J" /controle/cotisations -d "recolteur=1&action=VALIDER" -d "ids[]=$OA" > /dev/null
verifier "apres validation : plus d'attente, epargne a jour" "0|8000" "$(curl -s -b "$F" "$B/espace-client" | grep -c 'En attente de validation')|$(sql "select epargne from avecxy_carnets where id=$KF")"
verifier "page credits : eligibilite visible" "Vous êtes éligible" "$(curl -s -b "$F" "$B/espace-client/credits" | grep -o 'Vous êtes éligible' | head -1)"
verifier "l'etat de la caisse n'est pas montre au client" "0" "$(curl -s -b "$F" "$B/espace-client/credits" | grep -c 'caisse')"
verifier "demande au-dessus du maximum refusee" "[erreur]" "$(envoyer "$F" /espace-client/credits -d "produit=1&montant=900000")"
verifier "demande au-dessus de ce que l'epargne permet refusee" "[erreur]" "$(envoyer "$F" /espace-client/credits -d "produit=1&montant=50000")"
verifier "demande de credit depuis l'espace client" "[succes]" "$(envoyer "$F" /espace-client/credits -d "produit=1&montant=5000")"
DC=$(sql "select id from avecxy_credit_eligibilites where client_id=$CF and origine='CLIENT' and statut='PROPOSE'")
verifier "une seule demande a la fois" "[erreur]" "$(envoyer "$F" /espace-client/credits -d "produit=1&montant=4000")"
verifier "le recolteur est prevenu de la demande" "1" "$(sql "select count(*) from avecxy_notifications where destinataire_id=$UID2 and type='demande_credit_client'")"
verifier "le client suit le statut : en attente" "pastille ambre\">En attente" "$(curl -s -b "$F" "$B/espace-client/credits" | grep -o 'pastille ambre">En attente' | head -1)"
verifier "la demande arrive chez le personnel" 200 "$(page "$J" /eligibilite/$DC)"
verifier "accord par la personne autorisee" "[succes]" "$(envoyer "$J" /eligibilite/$DC -d "action=ACCORDER&montant=5000&date=$JOUR")"
CRC=$(sql "select credit_id from avecxy_credit_eligibilites where id=$DC")
verifier "le client est prevenu de l'accord" "1" "$(sql "select count(*) from avecxy_notifications where destinataire='CLIENT' and destinataire_id=$CF and type='client_credit_accorde'")"
verifier "statut de la demande : accordee" "Accordée" "$(curl -s -b "$F" "$B/espace-client/credits" | grep -o 'Accordée' | head -1)"
verifier "releve du credit : ecritures et reste du" "Capital prêté" "$(curl -s -b "$F" "$B/espace-client/credits/$CRC" | grep -o 'Capital prêté' | head -1)"
verifier "le client voit son credit" 200 "$(page "$F" /espace-client/credits/$CRC)"
verifier "pas le credit d'un autre" 404 "$(page "$F" /espace-client/credits/1)"
envoyer "$M" /credits/$CRC/rembourser -d "montant=1000&date=$JOUR" > /dev/null
verifier "remboursement saisi par le recolteur : en attente chez le client" "En attente" "$(curl -s -b "$F" "$B/espace-client/credits/$CRC" | grep -o 'pastille ambre">En attente' | head -1)"
verifier "dette en cours : plus eligible, et il voit pourquoi" "Pas éligible pour le moment" "$(curl -s -b "$F" "$B/espace-client/credits" | grep -o 'Pas éligible pour le moment' | head -1)"
verifier "l'organisation peut fermer les demandes des clients" "0" "$(envoyer "$J" /parametres/espace-client/demandes -d "autoriser=0" > /dev/null; sql "select valeur from avecxy_parametres where cle='credits.demande_client'")"

echo "15. Identifiants du personnel et espace plateforme"
verifier "sans nom court : pas d'identifiant automatique" "[erreur]" "$(envoyer "$J" /parametres/utilisateurs/nouveau -d "nom=paul ilunga&identifiant=&role_id=$RID&statut=ACTIF&langue=fr&mot_de_passe=")"
verifier "nom court refuse s'il est trop long" "[erreur]" "$(envoyer "$P" /plateforme/organisations/1/nom-court -d "nom_court=lightvisionbusiness")"
verifier "nom court choisi" "umoja" "$(envoyer "$P" /plateforme/organisations/1/nom-court -d "nom_court=umoja" > /dev/null; sql "select nom_court from avecxy_organisations where id=1")"
verifier "compte cree sans saisir d'identifiant" "[succes]" "$(envoyer "$J" /parametres/utilisateurs/nouveau -d "nom=paul ilunga&identifiant=&role_id=$RID&statut=ACTIF&langue=fr&mot_de_passe=")"
verifier "identifiant genere : prenom@nomcourt" "paul@umoja" "$(sql "select identifiant from avecxy_utilisateurs where nom='PAUL ILUNGA'")"
MP=$(grep -o '<dd class="petit">[^<]*</dd>' "$D/page.html" | sed -n 2p | sed 's/<[^>]*>//g')
verifier "mot de passe provisoire montre dans une fenetre" 10 "${#MP}"
envoyer "$D/paul.txt" /connexion --data-urlencode "identifiant=paul@umoja" --data-urlencode "mot_de_passe=$MP" > /dev/null
verifier "connexion avec l'identifiant genere" 200 "$(page "$D/paul.txt" /mon-compte)"
envoyer "$J" /parametres/utilisateurs/nouveau -d "nom=paul kabongo&identifiant=&role_id=$RID&statut=ACTIF&langue=fr&mot_de_passe=" > /dev/null
verifier "meme prenom : un chiffre s'ajoute" "paul2@umoja" "$(sql "select identifiant from avecxy_utilisateurs where nom='PAUL KABONGO'")"
verifier "le telephone ou l'e-mail restent possibles" "[succes]" "$(envoyer "$J" /parametres/utilisateurs/nouveau -d "nom=rita&identifiant=0899123456&role_id=$RID&statut=ACTIF&langue=fr&mot_de_passe=Provisoire9")"
verifier "plateforme : fiche detaillee de l'organisation" "paul@umoja" "$(curl -s -b "$P" "$B/plateforme/organisations/1" | grep -o 'paul@umoja' | head -1)"
verifier "plateforme : activite de l'organisation" "Épargne des clients" "$(curl -s -b "$P" "$B/plateforme/organisations/1" | grep -o 'Épargne des clients' | head -1)"
UP=$(sql "select id from avecxy_utilisateurs where identifiant='paul@umoja'")
envoyer "$P" /plateforme/organisations/1/utilisateurs/$UP/mot-de-passe > /dev/null
MP2=$(grep -o '<dd class="petit">[^<]*</dd>' "$D/page.html" | sed -n 2p | sed 's/<[^>]*>//g')
verifier "plateforme : mot de passe reinitialise" "10|1" "${#MP2}|$(sql "select doit_changer_mot_de_passe from avecxy_utilisateurs where id=$UP")"
verifier "l'ancien mot de passe ne marche plus" "[erreur]" "$(envoyer "$D/paul2.txt" /connexion --data-urlencode "identifiant=paul@umoja" --data-urlencode "mot_de_passe=$MP")"
verifier "plateforme : journal des actions" "Mot de passe réinitialisé" "$(curl -s -b "$P" "$B/plateforme/organisations/1" | grep -o 'Mot de passe réinitialisé' | head -1)"
verifier "plateforme : recherche par nom court" "1" "$(curl -s -b "$P" "$B/plateforme?q=umoja" | grep -c '/plateforme/organisations/1"')"
verifier "plateforme : filtre sans resultat" "0" "$(curl -s -b "$P" "$B/plateforme?etat=BLOQUEE" | grep -c '/plateforme/organisations/1"')"

echo "16. Application mobile : connexion, portefeuille, synchronisation hors ligne"
api() { local jeton=$1 methode=$2 chemin=$3 corps=${4:-}; if [ "$methode" = GET ]; then curl -s -H "Authorization: Bearer $jeton" "$B$chemin"; else curl -s -H "Authorization: Bearer $jeton" -H "Content-Type: application/json" -d "$corps" "$B$chemin"; fi; }
champ() { php -r '$d = json_decode(stream_get_contents(STDIN), true); foreach (explode(".", $argv[1]) as $k) { $d = is_array($d) ? ($d[$k] ?? null) : null; } echo is_array($d) ? count($d) : (is_bool($d) ? ($d ? "true" : "false") : $d);' "$1"; }
verifier "api : en service" "1" "$(curl -s "$B/api/v1" | champ version)"
verifier "api : sans jeton, refus" "JETON" "$(api "" GET /api/v1/portefeuille | champ code)"
verifier "api : mauvais mot de passe" "REFUS" "$(api "" POST /api/v1/connexion '{"identifiant":"marie@exemple.test","mot_de_passe":"faux","appareil_id":"tel-1"}' | champ code)"
api "" POST /api/v1/connexion '{"identifiant":"jean@exemple.test","mot_de_passe":"MonSecret22","appareil_id":"tel-0"}' > "$D/gestion.json"
verifier "api : un compte sans recolteur ouvre la gestion" "GESTION" "$(champ profil < "$D/gestion.json")"
verifier "api : gestion, pas de portefeuille hors ligne" "[vide]" "[$(champ portefeuille < "$D/gestion.json")vide]"
JG=$(champ jeton < "$D/gestion.json")
verifier "api : gestion, portefeuille refuse" "GESTION" "$(api "$JG" GET /api/v1/portefeuille | champ code)"
JT=$(api "" POST /api/v1/connexion '{"identifiant":"marie@exemple.test","mot_de_passe":"MonSecret33","appareil_id":"tel-1","appareil_nom":"Tecno Spark 10"}' | champ jeton)
verifier "api : connexion du recolteur, jeton recu" 64 "${#JT}"
api "$JT" GET /api/v1/portefeuille > "$D/pf.json"
verifier "portefeuille : seulement ses clients" "$(sql "select count(*) from avecxy_clients where agent_id=1 and supprime_le is null")" "$(champ clients < "$D/pf.json")"
verifier "portefeuille : montants en centimes" "$(sql "select cast(epargne*100 as integer) from avecxy_carnets where id=$KF")" "$(php -r '$d=json_decode(file_get_contents($argv[1]),true); foreach($d["carnets"] as $k) if($k["id"]==$argv[2]) echo $k["epargne_c"];' "$D/pf.json" "$KF")"
verifier "portefeuille : solde du mois" "$(sql "select cast(sum(part_agent)*100 as integer) from avecxy_benefices where agent_id=1 and periode='$MOIS'")" "$(champ solde_mois.part_c < "$D/pf.json")"
U1=11111111-1111-4111-8111-111111111111; U2=22222222-2222-4222-8222-222222222222; U3=33333333-3333-4333-8333-333333333333; U4=44444444-4444-4444-8444-444444444444; U5=55555555-5555-4555-8555-555555555555; U6=66666666-6666-4666-8666-666666666666
LOT="{\"operations\":[{\"uuid\":\"$U1\",\"type\":\"client.creer\",\"donnees\":{\"nom\":\"hors ligne un\",\"telephone\":\"0977000001\",\"type_id\":1,\"mise\":\"1000\",\"jour\":\"$JOUR\"}},{\"uuid\":\"$U2\",\"type\":\"cotisation.saisir\",\"donnees\":{\"carnet_uuid\":\"$U1\",\"valeur\":\"4\",\"jour\":\"$JOUR\"}},{\"uuid\":\"$U3\",\"type\":\"cotisation.saisir\",\"donnees\":{\"carnet_id\":$KF,\"valeur\":\"99\",\"jour\":\"$JOUR\"}},{\"uuid\":\"$U4\",\"type\":\"cotisation.saisir\",\"donnees\":{\"carnet_id\":2,\"valeur\":\"1\",\"jour\":\"$JOUR\"}}]}"
api "$JT" POST /api/v1/synchroniser "$LOT" > "$D/sync.json"
verifier "synchro : client cree hors ligne, avec son carnet" "APPLIQUEE" "$(champ operations.0.statut < "$D/sync.json")"
verifier "synchro : cotisation sur le carnet cree dans le meme envoi" "APPLIQUEE" "$(champ operations.1.statut < "$D/sync.json")"
verifier "synchro : toujours au controle" "EN_ATTENTE|4000" "$(sql "select statut, montant_total from avecxy_cotisations where sync_uuid='$U2'")"
verifier "synchro : saisie impossible refusee avec la raison" "REJETEE" "$(champ operations.2.statut < "$D/sync.json")"
verifier "synchro : pas le carnet d'un autre recolteur" "REJETEE" "$(champ operations.3.statut < "$D/sync.json")"
NB=$(sql "select count(*) from avecxy_cotisations")
api "$JT" POST /api/v1/synchroniser "$LOT" > "$D/sync2.json"
verifier "synchro : renvoyee, la saisie n'est pas enregistree deux fois" "$NB|APPLIQUEE|REJETEE" "$(sql "select count(*) from avecxy_cotisations")|$(champ operations.1.statut < "$D/sync2.json")|$(champ operations.2.statut < "$D/sync2.json")"
verifier "synchro : un seul client cree" "1" "$(sql "select count(*) from avecxy_clients where nom='HORS LIGNE UN'")"
api "$JT" POST /api/v1/synchroniser "{\"operations\":[{\"uuid\":\"$U5\",\"type\":\"cotisation.corriger\",\"donnees\":{\"cotisation_uuid\":\"$U2\",\"valeur\":\"2\",\"jour\":\"$JOUR\"}}]}" > "$D/sync3.json"
verifier "correction d'une saisie pas encore validee" "APPLIQUEE|2000" "$(champ operations.0.statut < "$D/sync3.json")|$(sql "select montant_total from avecxy_cotisations where sync_uuid='$U2'")"
verifier "a deposer : par jour et par type" "2000" "$(php -r '$d=json_decode(file_get_contents($argv[1]),true); $t=0; foreach($d["portefeuille"]["a_deposer"] as $j) $t+=$j["cotisations_c"]; echo $t/100;' "$D/sync3.json")"
OV=$(sql "select id from avecxy_cotisations where sync_uuid='$U2'"); KN=$(sql "select carnet_id from avecxy_cotisations where id=$OV")
envoyer "$J" /controle/carnets -d "recolteur=1&action=VALIDER" -d "ids[]=$KN" > /dev/null; envoyer "$J" /controle/cotisations -d "recolteur=1&action=VALIDER" -d "ids[]=$OV" > /dev/null
api "$JT" POST /api/v1/synchroniser "{\"operations\":[{\"uuid\":\"$U6\",\"type\":\"cotisation.corriger\",\"donnees\":{\"cotisation_id\":$OV,\"valeur\":\"1\",\"jour\":\"$JOUR\"}}]}" > "$D/sync4.json"
verifier "une fois validee, le recolteur ne peut plus corriger" "REJETEE|2000" "$(champ operations.0.statut < "$D/sync4.json")|$(sql "select montant_total from avecxy_cotisations where id=$OV")"
verifier "saisie validee : marquee non modifiable dans le telephone" "VALIDEE|false" "$(php -r '$d=json_decode(file_get_contents($argv[1]),true); foreach($d["portefeuille"]["saisies"] as $s) if($s["type"]=="cotisation" && $s["id"]==$argv[2]) echo $s["etat"],"|",$s["modifiable"]?"true":"false";' "$D/sync4.json" "$OV")"
verifier "telephones connectes visibles par l'administrateur" "Tecno Spark 10" "$(curl -s -b "$J" "$B/parametres/utilisateurs" | grep -o 'Tecno Spark 10' | head -1)"
AP=$(sql "select id from avecxy_api_jetons where appareil_id='tel-1' and revoque_le is null")
verifier "telephone desactive par l'administrateur" "[succes]" "$(envoyer "$J" /parametres/appareils/$AP/desactiver)"
verifier "telephone desactive : plus aucun acces" "JETON" "$(api "$JT" GET /api/v1/portefeuille | champ code)"

echo "17. Microfinance : comptes annuel, courant, bloque ; credits degressifs, frais, garantie"
J100=$(date -d '100 days ago' +%F); J65=$(date -d '65 days ago' +%F)
verifier "compte annuel : 372 parts, reserve de 62, sans duree" "[succes]" "$(envoyer "$J" /parametres/types-carnet/nouveau -d "nom=compte annuel&mode=PARTS&duree_jours=&prix=0&nombre_parts=372&taille_tranche=31&parts_benefice_par_tranche=1&prelevement_benefice=DEBUT_TRANCHE&retrait=LIBRE&fermeture=MANUELLE&parts_reserve=62&mise_minimum=100&frais_tenue_type=AUCUN&actif=1")"
verifier "sans duree : pas de fermeture a l'echeance" "[erreur]" "$(envoyer "$J" /parametres/types-carnet/nouveau -d "nom=mauvais&mode=PARTS&duree_jours=&nombre_parts=31&retrait=LIBRE&fermeture=ECHEANCE&frais_tenue_type=AUCUN&actif=1")"
verifier "compte courant : solde minimum, frais mensuels" "[succes]" "$(envoyer "$J" /parametres/types-carnet/nouveau -d "nom=compte courant&mode=LIBRE&duree_jours=&prix=1000&solde_minimum=5000&fermeture=MANUELLE&frais_tenue_type=FIXE&frais_tenue_valeur=500&frais_tenue_frequence=MENSUEL&actif=1")"
verifier "compte bloque sans duree proposee refuse" "[erreur]" "$(envoyer "$J" /parametres/types-carnet/nouveau -d "nom=bloque vide&mode=BLOQUE&blocage=RETRAITS&taux_base=AN&interets_versement=FIN&retrait_anticipe=INTERDIT&frais_tenue_type=AUCUN&actif=1" -d "options_mois[]=" -d "options_taux[]=")"
verifier "compte bloque : 3 mois a 3 %, 6 mois a 7 % (pour la duree)" "[succes]" "$(envoyer "$J" /parametres/types-carnet/nouveau -d "nom=depot a terme&mode=BLOQUE&blocage=RETRAITS&taux_base=DUREE&interets_versement=FIN&retrait_anticipe=PERTE_INTERETS&fermeture=MANUELLE&frais_tenue_type=AUCUN&actif=1" -d "options_mois[]=6" -d "options_taux[]=7" -d "options_mois[]=3" -d "options_taux[]=3")"
verifier "compte bloque : enregistre comme compte libre bloque, durees triees" "LIBRE|RETRAITS|0|[{\"mois\":3,\"taux\":\"3.00\"},{\"mois\":6,\"taux\":\"7.00\"}]" "$(sql "select mode, blocage, duree_jours, options_blocage from avecxy_carnet_types where nom='DEPOT A TERME'")"
verifier "compte tout bloque : 12 % par an, interets chaque mois, penalite 5 %" "[succes]" "$(envoyer "$J" /parametres/types-carnet/nouveau -d "nom=epargne bloquee&mode=BLOQUE&blocage=TOUT&taux_base=AN&interets_versement=MENSUEL&retrait_anticipe=PENALITE&penalite_anticipe=5&fermeture=MANUELLE&frais_tenue_type=AUCUN&actif=1" -d "options_mois[]=12" -d "options_taux[]=12")"
verifier "liste des produits d'epargne" "Compte bloqué" "$(curl -s -b "$J" "$B/parametres/types-carnet" | grep -o 'Compte bloqué' | head -1)"
TA=$(sql "select id from avecxy_carnet_types where nom='COMPTE ANNUEL'"); TC=$(sql "select id from avecxy_carnet_types where nom='COMPTE COURANT'")
TB=$(sql "select id from avecxy_carnet_types where nom='DEPOT A TERME'"); TT=$(sql "select id from avecxy_carnet_types where nom='EPARGNE BLOQUEE'")
envoyer "$J" /carnets/nouveau -d "client_id=1&type_id=$TA&mise=1000&date=$JOUR&versement=100" > /dev/null
KA=$(sql "select max(id) from avecxy_carnets where carnet_type_id=$TA")
verifier "compte annuel : 100 mises dont 4 premieres de mois a la maison" "100|96000|" "$(sql "select parts_validees, epargne, coalesce(date_echeance,'') from avecxy_carnets where id=$KA")"
verifier "compte annuel : retrait qui entame les 62 mises de reserve refuse" "[erreur]" "$(envoyer "$J" /carnets/$KA/retirer -d "montant=40000&date=$JOUR")"
verifier "compte annuel : retrait partiel qui garde la reserve" "[succes]" "$(envoyer "$J" /carnets/$KA/retirer -d "montant=30000&date=$JOUR")"
verifier "compte annuel : fiche indique ce qui peut sortir" "4 000" "$(curl -s -b "$J" "$B/carnets/$KA" | grep -o 'jusqu[^<]*4[  ]000' | grep -o '4[  ]000' | head -1 | sed 's/ / /')"
envoyer "$J" /carnets/nouveau -d "client_id=1&type_id=$TC&date=$JOUR&versement=20000" > /dev/null
KC=$(sql "select max(id) from avecxy_carnets where carnet_type_id=$TC")
verifier "compte courant : sans echeance, solde minimum copie" "20000|5000" "$(sql "select cast(epargne as integer), cast(solde_minimum as integer) from avecxy_carnets where id=$KC and date_echeance is null")"
verifier "compte courant : le solde minimum doit rester" "[erreur]" "$(envoyer "$J" /carnets/$KC/retirer -d "montant=16000&date=$JOUR")"
verifier "compte courant : retrait jusqu'au solde minimum" "[succes]" "$(envoyer "$J" /carnets/$KC/retirer -d "montant=15000&date=$JOUR")"
verifier "compte bloque : la duree est a choisir" "[erreur]" "$(envoyer "$J" /carnets/nouveau -d "client_id=1&type_id=$TB&date=$JOUR&versement=1000&option_blocage=9")"
envoyer "$J" /carnets/nouveau -d "client_id=1&type_id=$TB&date=$J100&versement=100000&option_blocage=3" > /dev/null
KB=$(sql "select max(id) from avecxy_carnets where carnet_type_id=$TB")
verifier "compte bloque ouvert il y a 100 jours pour 3 mois : taux et echeance copies" "3|300|$(date -d "$J100 +3 months" +%F)" "$(sql "select duree_mois, cast(taux_interet*100 as integer), date_echeance from avecxy_carnets where id=$KB")"
verifier "compte bloque echu : la fiche verse les interets dus et les montre" "Intérêts versés|1" "$(curl -s -b "$J" "$B/carnets/$KB" | grep -o 'Intérêts versés' | head -1)|$(sql "select count(*) from avecxy_interets_epargne where carnet_id=$KB")"
verifier "a l'echeance : 3 % verses avant le retrait, puis retrait libre" "[succes]" "$(envoyer "$J" /carnets/$KB/retirer -d "montant=50000&date=$JOUR")"
verifier "interet de 3 000 verse et compte comme charge de l'organisation" "3000|53000|-3000" "$(sql "select cast(sum(i.montant) as integer), (select cast(epargne as integer) from avecxy_carnets where id=$KB), (select cast(sum(montant_total) as integer) from avecxy_benefices where source='INTERET_EPARGNE' and carnet_id=$KB) from avecxy_interets_epargne i where i.carnet_id=$KB")"
envoyer "$J" /carnets/nouveau -d "client_id=1&type_id=$TB&date=$JOUR&versement=50000&option_blocage=6" > /dev/null
KB2=$(sql "select max(id) from avecxy_carnets where carnet_type_id=$TB")
verifier "bloque pour les retraits seulement : depot accepte" "[succes]" "$(envoyer "$J" /carnets/$KB2/cotiser -d "valeur=10000&date=$JOUR")"
verifier "sortie avant l'echeance : tout le compte, sans interet, compte ferme" "FERME|60000" "$(envoyer "$J" /carnets/$KB2/retirer -d "date=$JOUR" > /dev/null; sql "select k.statut, cast(r.montant_retire as integer) from avecxy_carnets k join avecxy_retraits r on r.carnet_id=k.id where k.id=$KB2")"
envoyer "$J" /carnets/nouveau -d "client_id=1&type_id=$TT&date=$J65&versement=100000&option_blocage=12" > /dev/null
KT=$(sql "select max(id) from avecxy_carnets where carnet_type_id=$TT")
verifier "tout bloque : nouveau depot refuse avant l'echeance" "[erreur]" "$(envoyer "$J" /carnets/$KT/cotiser -d "valeur=5000&date=$JOUR")"
sql "update avecxy_parametres set valeur='2000-01-01' where cle='taches.dernier_jour'" > /dev/null; page "$J" / > /dev/null
ATTENDU=$(php -r '$o=new DateTime($argv[1]); $s=10000000; $t=0; for($k=1;$k<=2;$k++){ $du=(clone $o)->modify("+".($k-1)." months"); $au=(clone $o)->modify("+$k months"); $j=$du->diff($au)->days; $i=(int) round(round($s*0.12/365*$j)/100)*100; $s+=$i; $t+=$i; } echo $t/100;' "$J65")
verifier "interets mensuels verses par les travaux du jour (2 mois, interets composes)" "2|$ATTENDU" "$(sql "select count(*), cast(sum(montant) as integer) from avecxy_interets_epargne where carnet_id=$KT")"
verifier "travaux du jour relances : rien n'est verse deux fois" "2" "$(sql "update avecxy_parametres set valeur='2000-01-01' where cle='taches.dernier_jour'" > /dev/null; page "$J" / > /dev/null; sql "select count(*) from avecxy_interets_epargne where carnet_id=$KT")"
EPT=$(sql "select cast(epargne*100 as integer) from avecxy_carnets where id=$KT")
verifier "sortie anticipee : penalite de 5 % du solde" "$(( (EPT * 5 / 100 + 50) / 100 * 100 / 100 ))|PENALITE|FERME" "$(envoyer "$J" /carnets/$KT/retirer -d "date=$JOUR" > /dev/null; sql "select cast(f.montant as integer), f.origine, k.statut from avecxy_frais_tenue f join avecxy_carnets k on k.id=f.carnet_id where f.carnet_id=$KT")"

envoyer "$J" /caisse/mouvements -d "type=APPORT&montant=1000000&date=$JOUR&source=capital microfinance" > /dev/null
verifier "taux degressif sans calendrier refuse" "[erreur]" "$(envoyer "$J" /parametres/credit/nouveau -d "nom=mauvais&taux_interet=2&duree_jours=30&mode_calcul=DEGRESSIF&mode_remboursement=LIBRE&credits_simultanes_max=5&actif=1")"
verifier "credit degressif mensuel, frais de dossier 2 % retenus, garantie 10 %" "[succes]" "$(envoyer "$J" /parametres/credit/nouveau -d "nom=pme 12 mois&taux_interet=2&duree_jours=30&mode_calcul=DEGRESSIF&mode_remboursement=CALENDRIER&echeances_nombre=12&echeances_intervalle=1&echeances_unite=MOIS&frais_dossier_type=POURCENTAGE&frais_dossier_valeur=2&frais_dossier_mode=RETENU&garantie_pct=10&credits_simultanes_max=5&derogation_autorisee=1&actif=1")"
PD=$(sql "select id from avecxy_credit_produits where nom='PME 12 MOIS'")
verifier "regles : exemple chiffre pour 100 000 (9 456 par mois)" "9 456" "$(curl -s -b "$J" "$B/parametres/credit/$PD" | grep -o '9[  ]456' | head -1 | sed 's/ / /')"
verifier "credit a capital fixe" "[succes]" "$(envoyer "$J" /parametres/credit/nouveau -d "nom=capital fixe&taux_interet=2&duree_jours=30&mode_calcul=DEGRESSIF_CAPITAL&mode_remboursement=CALENDRIER&echeances_nombre=12&echeances_intervalle=1&echeances_unite=MOIS&frais_dossier_type=FIXE&frais_dossier_valeur=1500&frais_dossier_mode=AJOUTE&credits_simultanes_max=5&derogation_autorisee=1&actif=1")"
PK=$(sql "select id from avecxy_credit_produits where nom='CAPITAL FIXE'")
verifier "client garanti : compte courant de 20 000" "[succes]" "$(envoyer "$J" /clients/nouveau -d "nom=client garanti&agent_id=1&type_id=$TC&date=$JOUR&versement=20000")"
CG=$(sql "select code from avecxy_clients where nom='CLIENT GARANTI'"); IG=$(sql "select id from avecxy_clients where nom='CLIENT GARANTI'"); KG=$(sql "select id from avecxy_carnets where client_id=$IG")
verifier "apercu avant octroi : tableau d'amortissement" "Capital restant" "$(curl -s -b "$J" "$B/credits/nouveau?client=$CG&produit=$PD&montant=100000" | grep -o 'Capital restant' | head -1)"
verifier "garantie trop forte pour l'epargne : refusee sans derogation" "[erreur]" "$(envoyer "$J" /credits/nouveau -d "client=$CG&produit=$PD&montant=300000&date=$JOUR")"
verifier "credit degressif accorde" "[succes]" "$(envoyer "$J" /credits/nouveau -d "client=$CG&produit=$PD&montant=100000&date=$JOUR")"
CR=$(sql "select max(id) from avecxy_credits")
verifier "credit : interets 13 470, total 113 470, frais 2 000, garantie 10 000" "DEGRESSIF|13470|113470|2000|10000" "$(sql "select mode_calcul, cast(montant_interet as integer), cast(total_du as integer), cast(frais_dossier as integer), cast(garantie as integer) from avecxy_credits where id=$CR")"
verifier "12 echeances mensuelles : la premiere 9 456 (capital 7 456 + interet 2 000)" "12|9456|7456|2000|$(date -d "$JOUR +1 month" +%F)" "$(sql "select (select count(*) from avecxy_credit_echeances where credit_id=$CR), cast(montant as integer), cast(capital as integer), cast(interet as integer), date_prevue from avecxy_credit_echeances where credit_id=$CR and numero=1")"
verifier "frais de dossier retenus : benefice a l'octroi" "2000" "$(sql "select cast(montant_total as integer) from avecxy_benefices where source='FRAIS_DOSSIER' and credit_id=$CR")"
verifier "fiche du credit : tableau d'amortissement" "Capital restant" "$(curl -s -b "$J" "$B/credits/$CR" | grep -o 'Capital restant' | head -1)"
verifier "garantie : l'epargne qui couvre le credit ne sort pas" "[erreur]" "$(envoyer "$J" /carnets/$KG/retirer -d "montant=12000&date=$JOUR")"
verifier "remboursement anticipe : tous les interets restent dus" "REMBOURSE|113470" "$(envoyer "$J" /credits/$CR/rembourser -d "montant=113470&date=$JOUR" > /dev/null; sql "select statut, cast(total_rembourse as integer) from avecxy_credits where id=$CR")"
verifier "remboursement selon le tableau : 13 470 d'interets comptes en benefice" "13470" "$(sql "select cast(sum(montant_total) as integer) from avecxy_benefices where source='INTERET_CREDIT' and credit_id=$CR")"
verifier "credit rembourse : la garantie est liberee" "[succes]" "$(envoyer "$J" /carnets/$KG/retirer -d "montant=12000&date=$JOUR")"
verifier "capital fixe : 10 000 de capital par mois, echeances qui baissent, frais de 1 500 avec la premiere" "13900|12200|10200" "$(envoyer "$J" /credits/nouveau -d "client=$CG&produit=$PK&montant=120000&date=$JOUR" > /dev/null; C2R=$(sql "select max(id) from avecxy_credits"); echo "$(sql "select cast(montant as integer) from avecxy_credit_echeances where credit_id=$C2R and numero=1")|$(sql "select cast(montant as integer) from avecxy_credit_echeances where credit_id=$C2R and numero=2")|$(sql "select cast(montant as integer) from avecxy_credit_echeances where credit_id=$C2R and numero=12")")"

echo "18. Microfinance : dossier client, renouvellement, agences, comptabilite, indicateurs"
solde() { php -r 'require $argv[1] . "/src/autoload.php"; $bd = AvecXY\Noyau\BaseDeDonnees::depuisConfig(["pilote" => "sqlite", "fichier" => $argv[2]]); echo (int) round((float) (new AvecXY\Infrastructure\DepotCaisse($bd))->solde(1, null, $argv[3] === "" ? null : (int) $argv[3]));' "$A" "$D/test.sqlite" "${1:-}"; }
verifier "dossier : date de naissance dans le futur refusee" "[erreur]" "$(envoyer "$J" /clients/nouveau -d "nom=dossier futur&agent_id=1&date_naissance=2999-01-01")"
verifier "dossier complet du client" "[succes]" "$(envoyer "$J" /clients/nouveau -d "nom=neema dossier&agent_id=1&sexe=F&date_naissance=1990-05-04&lieu_naissance=goma&piece_type=CARTE_ELECTEUR&piece_numero=ab 123 45&piece_expire_le=2030-01-01&contact_urgence=papa 0990")"
CD=$(sql "select id from avecxy_clients where nom='NEEMA DOSSIER'")
verifier "dossier enregistre et normalise" "F|1990-05-04|GOMA|CARTE_ELECTEUR|AB12345" "$(sql "select sexe, date_naissance, lieu_naissance, piece_type, piece_numero from avecxy_clients where id=$CD")"
php -r 'file_put_contents($argv[1], base64_decode("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=="));' "$D/photo.png"; echo "pas une image" > "$D/faux.png"
photo() { curl -s -L -b "$J" -c "$J" -F "_jeton=$(jeton /connexion "$J")" -F "photo=@$1;type=image/png" "$B/clients/$CD/photo" | grep -o 'class="message message-[a-z]*"' | head -1; }
verifier "photo qui n'est pas une image refusee" "message-erreur" "$(photo "$D/faux.png")"
verifier "photo du client envoyee" "message-succes" "$(photo "$D/photo.png")"
verifier "photo servie seulement a qui voit le client" "200|image/png" "$(curl -s -o /dev/null -w '%{http_code}|%{content_type}' -b "$J" "$B/clients/$CD/photo")"
verifier "compte bloque renouvele automatiquement" "[succes]" "$(envoyer "$J" /parametres/types-carnet/nouveau -d "nom=terme renouvele&mode=BLOQUE&blocage=RETRAITS&taux_base=AN&interets_versement=FIN&retrait_anticipe=INTERDIT&renouvellement=AUTOMATIQUE&fermeture=MANUELLE&frais_tenue_type=AUCUN&actif=1" -d "options_mois[]=1" -d "options_taux[]=12")"
TR=$(sql "select id from avecxy_carnet_types where nom='TERME RENOUVELE'")
envoyer "$J" /carnets/nouveau -d "client_id=$CD&type_id=$TR&date=$J100&versement=100000&option_blocage=1" > /dev/null
KR=$(sql "select max(id) from avecxy_carnets where carnet_type_id=$TR"); page "$J" /carnets/$KR > /dev/null
CY=$(sql "select cycle from avecxy_carnets where id=$KR")
verifier "renouvellement : une periode d'un mois par cycle jusqu'a aujourd'hui, interets verses a chaque echeance" "ok|$((CY - 1))" "$(sql "select case when date_echeance >= '$JOUR' and cycle >= 4 then 'ok' else date_echeance || '/' || cycle end from avecxy_carnets where id=$KR")|$(sql "select count(*) from avecxy_interets_epargne where carnet_id=$KR")"
verifier "renouvellement : le compte reste bloque" "[erreur]" "$(envoyer "$J" /carnets/$KR/retirer -d "montant=1000&date=$JOUR")"

verifier "agence de Goma" "[succes]" "$(envoyer "$J" /parametres/agences/nouvelle -d "code=goma1&nom=agence de goma&actif=1")"
verifier "agence de Bukavu" "[succes]" "$(envoyer "$J" /parametres/agences/nouvelle -d "code=BKV&nom=agence de bukavu&actif=1")"
verifier "code d'agence deja pris" "[erreur]" "$(envoyer "$J" /parametres/agences/nouvelle -d "code=GOMA1&nom=autre&actif=1")"
AG=$(sql "select id from avecxy_agences where code='GOMA1'"); AB=$(sql "select id from avecxy_agences where code='BKV'")
SIEGE0=$(solde 0)
verifier "recolteur rattache a l'agence de Goma" "[succes]" "$(envoyer "$J" /parametres/recolteurs/nouveau -d "nom=rachel goma&telephone=0997&statut=ACTIF&agence_id=$AG&utilisateur_id=0")"
RG=$(sql "select id from avecxy_agents where nom='RACHEL GOMA'")
envoyer "$J" /clients/nouveau -d "nom=client de goma&agent_id=$RG&type_id=$TC&date=$JOUR&versement=50000" > /dev/null
verifier "la caisse de Goma recoit ce que ses clients versent" "51000" "$(solde $AG)"
verifier "le siege n'a pas bouge" "$SIEGE0" "$(solde 0)"
verifier "transfert impossible au-dela de la caisse de depart" "[erreur]" "$(envoyer "$J" /caisse/transferts -d "de=$AG&vers=$AB&montant=100000&date=$JOUR")"
verifier "transfert du siege vers Goma" "[succes]" "$(envoyer "$J" /caisse/transferts -d "de=0&vers=$AG&montant=20000&date=$JOUR&motif=fonds de credit")"
verifier "transfert : Goma + 20 000, siege - 20 000, organisation inchangee" "71000|$((SIEGE0 - 20000))|$((SIEGE0 + 51000))" "$(solde $AG)|$(solde 0)|$(solde)"
verifier "journal de la caisse de Goma" "AGENCE DE GOMA" "$(curl -s -b "$J" "$B/caisse?agence=$AG" | grep -o 'AGENCE DE GOMA' | head -1)"
CGC=$(sql "select code from avecxy_clients where nom='CLIENT DE GOMA'")
verifier "credit refuse : plus que la caisse de son agence" "[erreur]" "$(envoyer "$J" /credits/nouveau -d "client=$CGC&produit=$PK&montant=80000&date=$JOUR&motif_derogation=essai")"
CRID=$(sql "select id from avecxy_roles where code='COMPTABLE' and organisation_id=1")
verifier "comptable rattache a l'agence de Goma" "[succes]" "$(envoyer "$J" /parametres/utilisateurs/nouveau -d "nom=chef goma&identifiant=chef.goma@exemple.test&role_id=$CRID&statut=ACTIF&langue=fr&agence_id=$AG&mot_de_passe=Provisoire3")"
G=$D/goma.txt; connecter "$G" chef.goma@exemple.test Provisoire3 MonSecretGoma9 5719
verifier "utilisateur d'agence : seulement les clients de son agence" "CLIENT DE GOMA|0" "$(curl -s -b "$G" "$B/clients" | grep -o 'CLIENT DE GOMA' | head -1)|$(curl -s -b "$G" "$B/clients" | grep -c 'AMINA ELODIE')"
verifier "utilisateur d'agence : la fiche d'un client d'ailleurs est introuvable" "404" "$(page "$G" /clients/1)"
verifier "utilisateur d'agence : sa caisse seulement" "AGENCE DE GOMA" "$(curl -s -b "$G" "$B/caisse" | grep -o 'AGENCE DE GOMA' | head -1)"
NT=$(sql "select count(*) from avecxy_transferts_fonds")
verifier "utilisateur d'agence : pas de transfert depuis la caisse du siege" "$NT" "$(envoyer "$G" /caisse/transferts -d "de=0&vers=$AG&montant=100&date=$JOUR" > /dev/null; sql "select count(*) from avecxy_transferts_fonds")"

verifier "comptabilite : bilan et resultat" "Comptes équilibrés" "$(curl -s -b "$J" "$B/comptabilite/etats?du=2000-01-01" | grep -o 'Comptes équilibrés' | head -1)"
verifier "comptabilite : equilibre, caisse = journal de caisse, epargne = carnets, credits = capital restant" "EQUILIBRE|caisse OK|depots OK|credits OK" "$(php "$A/tests/controle_comptable.php" "$D/test.sqlite" 1)"
C622=$(sql "select id from avecxy_compta_comptes where organisation_id=1 and numero='622'"); C571=$(sql "select id from avecxy_compta_comptes where organisation_id=1 and numero='571'")
verifier "ecriture desequilibree refusee" "[erreur]" "$(envoyer "$J" /comptabilite/ecritures -d "date=$JOUR&libelle=loyer&compte[]=$C622&debit[]=5000&credit[]=&compte[]=$C571&debit[]=&credit[]=4000")"
SIEGE1=$(solde 0)
verifier "ecriture manuelle : loyer paye en especes" "[succes]" "$(envoyer "$J" /comptabilite/ecritures -d "date=$JOUR&piece=R-12&libelle=loyer d'octobre&compte[]=$C622&debit[]=5000&credit[]=&compte[]=$C571&debit[]=&credit[]=5000&compte[]=0&debit[]=&credit[]=")"
verifier "le loyer sort de la caisse du siege" "$((SIEGE1 - 5000))" "$(solde 0)"
verifier "comptabilite toujours equilibree apres l'ecriture" "EQUILIBRE|caisse OK|depots OK|credits OK" "$(php "$A/tests/controle_comptable.php" "$D/test.sqlite" 1)"
verifier "grand livre du compte de caisse" "Écriture manuelle" "$(curl -s -b "$J" "$B/comptabilite/grand-livre?compte=$C571" | grep -o 'Écriture manuelle' | head -1)"
CPD=$(sql "select id from avecxy_compta_comptes where organisation_id=1 and numero='758'")
verifier "nouveau compte de produits" "[succes]" "$(envoyer "$J" /comptabilite/plan/0 -d "numero=7581&libelle=recuperations de manquants&nature=PRODUIT&actif=1")"
C7581=$(sql "select id from avecxy_compta_comptes where organisation_id=1 and numero='7581'")
verifier "role donne a un compte de mauvaise nature refuse" "[erreur]" "$(envoyer "$J" /comptabilite/plan/roles -d "role[produit_divers]=$C622")"
verifier "role deplace vers le nouveau compte" "[succes] Rôles des comptes enregistrés. |7581" "$(envoyer "$J" /comptabilite/plan/roles -d "role[produit_divers]=$C7581")|$(sql "select numero from avecxy_compta_comptes where cle='produit_divers' and organisation_id=1")"
verifier "un compte avec un role ne se supprime pas" "[erreur]" "$(envoyer "$J" /comptabilite/plan/$C7581/supprimer)"
verifier "toujours equilibree apres le changement de role" "EQUILIBRE|caisse OK|depots OK|credits OK" "$(php "$A/tests/controle_comptable.php" "$D/test.sqlite" 1)"
curl -s -b "$J" -o "$D/compta.xlsx" "$B/comptabilite/export?du=2000-01-01"
verifier "export Excel : balance, resultat, bilan, journal" "4" "$(unzip -l "$D/compta.xlsx" 2>/dev/null | grep -c 'xl/worksheets/sheet')"
verifier "journal comptable" "Remboursement (intérêts)" "$(curl -s -b "$J" "$B/comptabilite/journal?du=2000-01-01" | grep -o 'Remboursement (intérêts)' | head -1)"

verifier "credit en retard (accorde il y a 70 jours, echeances chaque semaine)" "[succes]" "$(envoyer "$J" /credits/nouveau -d "client=$C1&produit=2&montant=10000&date=$(date -d '70 days ago' +%F)&motif_derogation=essai de retard")"
verifier "indicateurs : PAR 30 et retard de 31 a 90 jours" "oui|1" "$(php -r 'require $argv[1] . "/src/autoload.php"; $bd = AvecXY\Noyau\BaseDeDonnees::depuisConfig(["pilote" => "sqlite", "fichier" => $argv[2]]); $i = (new AvecXY\Application\ServiceIndicateurs($bd))->calculer(1, date("Y-m-01"), date("Y-m-d"), null); echo $i["par"][30] >= 1000000 ? "oui" : "non", "|", $i["tranches"]["j31_90"]["nombre"];' "$A" "$D/test.sqlite")"
verifier "page des indicateurs" "PAR 30 jours" "$(curl -s -b "$J" "$B/indicateurs" | grep -o 'PAR 30 jours' | head -1)"
curl -s -b "$J" -o "$D/indic.xlsx" "$B/indicateurs/export"
verifier "indicateurs exportes en Excel" "1" "$(unzip -l "$D/indic.xlsx" 2>/dev/null | grep -c 'xl/worksheets/sheet1.xml')"
verifier "agence de Goma : pas supprimable tant qu'elle a des recolteurs" "[erreur]" "$(envoyer "$J" /parametres/agences/$AG/supprimer)"
verifier "agences : liste avec l'argent en main" "AGENCE DE BUKAVU" "$(curl -s -b "$J" "$B/parametres/agences" | grep -o 'AGENCE DE BUKAVU' | head -1)"

echo "19. Application Mosolia : une seule connexion, passages, profils, ecran du telephone"
verifier "api : adresse du serveur donnee par la plateforme (vide par defaut)" "[vide]" "[$(curl -s "$B/api/v1" | champ adresse)vide]"
verifier "plateforme : adresse non https refusee" "[erreur]" "$(envoyer "$P" /plateforme/adresse-mobile -d "adresse=http://mosolia.exemple.test")"
verifier "plateforme : adresse de l'application enregistree" "[succes]" "$(envoyer "$P" /plateforme/adresse-mobile -d "adresse=https://mosolia.exemple.test")"
verifier "api : l'application recoit la nouvelle adresse" "https://mosolia.exemple.test" "$(curl -s "$B/api/v1" | champ adresse)"
envoyer "$P" /plateforme/adresse-mobile -d "adresse=" > /dev/null
api "" POST /api/v1/connexion "{\"identifiant\":\"$C1\",\"mot_de_passe\":\"482913\",\"appareil_id\":\"tel-9\"}" > "$D/client.json"
verifier "client : connexion par code client dans l'application" "CLIENT" "$(champ profil < "$D/client.json")"
verifier "client : mauvais code secret refuse" "REFUS" "$(api "" POST /api/v1/connexion "{\"identifiant\":\"$C1\",\"mot_de_passe\":\"000000\",\"appareil_id\":\"tel-9\"}" | champ code)"
PC=$(champ passage < "$D/client.json")
EA=$D/client_app.txt
verifier "client : passage vers l'espace client" "/espace-client" "$(curl -s -c "$EA" -b "$EA" -o /dev/null -w '%{redirect_url}' "$B/passage/$PC" | sed 's#^https\?://[^/]*##')"
verifier "client : espace client ouvert dans l'application" 200 "$(page "$EA" /espace-client/carnets/1)"
verifier "client : le passage ne sert qu'une fois" "/connexion" "$(curl -s -o /dev/null -w '%{redirect_url}' "$B/passage/$PC" | sed 's#^https\?://[^/]*##')"
verifier "client : pas acces a la gestion" 302 "$(page "$EA" /clients)"
api "$JG" POST /api/v1/passage '{"cible":"/clients"}' > "$D/passage.json"
verifier "gestion : passage cree" "/passage/" "$(champ chemin < "$D/passage.json" | grep -o '/passage/')"
GA=$D/gestion_app.txt
verifier "gestion : passage vers la page demandee" "/clients" "$(curl -s -c "$GA" -b "$GA" -o /dev/null -w '%{redirect_url}' "$B$(champ chemin < "$D/passage.json")" | sed 's#^https\?://[^/]*##')"
verifier "gestion : site ouvert dans l'application" 200 "$(page "$GA" /clients)"
verifier "gestion : marque application sur la page" "dans-application" "$(grep -o 'dans-application' "$D/page.html" | head -1)"
verifier "gestion : cible externe ignoree" "/" "$(api "$JG" POST /api/v1/passage '{"cible":"https://ailleurs.test/x"}' | champ chemin | sed 's#.*#/#')"
P2=$(api "$JG" POST /api/v1/passage '{"cible":"//ailleurs.test"}' | champ passage)
verifier "gestion : pas de redirection hors du site" "/" "$(curl -s -o /dev/null -w '%{redirect_url}' "$B/passage/$P2" | sed 's#^https\?://[^/]*##')"
verifier "application : exports caches (marques ordinateur)" "seulement-ordinateur" "$(curl -s -b "$GA" "$B/comptabilite/balance" | grep -o 'seulement-ordinateur' | head -1)"
verifier "ordinateur : pas de marque application" "0" "$(curl -s -b "$J" "$B/clients" | grep -c 'dans-application')"
verifier "application : reconnue par le navigateur de l'application" "dans-application" "$(curl -s -b "$J" -A 'Mozilla/5.0 MosoliaApp/1.0' "$B/clients" | grep -o 'dans-application' | head -1)"
verifier "application : reste reconnue pour la session" "dans-application" "$(curl -s -b "$J" "$B/clients" | grep -o 'dans-application' | head -1)"
verifier "telephone : colonnes secondaires marquees" "oui" "$(curl -s -b "$J" "$B/clients" | grep -q 'tel-cache' && echo oui)"
verifier "application : connexion avec le PIN du compte" "RECOLTEUR" "$(api "" POST /api/v1/connexion '{"identifiant":"marie@exemple.test","mot_de_passe":"4826","appareil_id":"tel-1"}' | champ profil)"
verifier "application : mauvais PIN refuse" "REFUS" "$(api "" POST /api/v1/connexion '{"identifiant":"marie@exemple.test","mot_de_passe":"9999","appareil_id":"tel-1"}' | champ code)"
verifier "recolteur : profil hors ligne" "RECOLTEUR" "$(api "" POST /api/v1/connexion '{"identifiant":"marie@exemple.test","mot_de_passe":"MonSecret33","appareil_id":"tel-1"}' | champ profil)"
verifier "nom de l'application : Mosolia" "Mosolia" "$(curl -s "$B/manifest.webmanifest" | grep -o 'Mosolia' | head -1)"

[ -s "$A/stockage/erreurs.log" ] && grep -q "$(date +%Y-%m-%d)" "$A/stockage/erreurs.log" && echo "Attention : des erreurs serveur ont ete journalisees dans stockage/erreurs.log"
echo
echo "$OK verification(s) reussie(s), $KO echec(s)."
[ "$KO" -eq 0 ]
