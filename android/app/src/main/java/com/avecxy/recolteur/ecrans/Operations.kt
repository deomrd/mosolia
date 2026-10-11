package com.avecxy.recolteur.ecrans

import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.avecxy.recolteur.donnees.Depot
import com.avecxy.recolteur.donnees.ProduitCredit
import com.avecxy.recolteur.donnees.TypeCarnet

@Composable
fun Champ(valeur: String, auChangement: (String) -> Unit, libelle: String, clavier: KeyboardType = KeyboardType.Text, suffixe: String? = null, actif: Boolean = true, erreur: Boolean = false) {
    OutlinedTextField(
        value = valeur, onValueChange = auChangement, modifier = Modifier.fillMaxWidth(), singleLine = true, enabled = actif, isError = erreur,
        shape = RoundedCornerShape(16.dp), label = { Text(libelle) }, suffix = if (suffixe != null) ({ Text(suffixe, color = Gris, fontWeight = FontWeight.Bold) }) else null,
        keyboardOptions = KeyboardOptions(keyboardType = clavier),
        colors = OutlinedTextFieldDefaults.colors(focusedContainerColor = Color.White, unfocusedContainerColor = Color.White, disabledContainerColor = Fond, focusedBorderColor = Vert, unfocusedBorderColor = Trait, focusedLabelColor = Vert),
    )
}

/** Cadre commun des ecrans de saisie : bandeau, contenu qui defile, bouton fixe en bas. */
@Composable
private fun Formulaire(titre: String, sousTitre: String?, navigation: Navigation, bouton: String, actif: Boolean, auClic: () -> Unit, contenu: @Composable ColumnScope.() -> Unit) {
    Column(Modifier.fillMaxSize()) {
        Entete(titre, sousTitre, { navigation.retour() })
        Column(Modifier.weight(1f).verticalScroll(rememberScrollState()).padding(16.dp), verticalArrangement = Arrangement.spacedBy(14.dp), content = contenu)
        Surface(color = Color.White, shadowElevation = 8.dp) {
            Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                GrandBouton(bouton, actif = actif, auClic = auClic)
                Text("Enregistré dans le téléphone, envoyé dès qu'il y a du réseau.", color = Gris, fontSize = 12.sp, modifier = Modifier.align(Alignment.CenterHorizontally))
            }
        }
    }
}

@Composable
fun EcranCotisation(depot: Depot, navigation: Navigation, carnetId: Long) {
    val etat = depot.etat
    val carnet = etat.carnets.firstOrNull { it.id == carnetId }
    val client = carnet?.let { etat.client(it.clientId) }
    if (carnet == null || client == null) {
        Column(Modifier.fillMaxSize()) { Entete("Carnet introuvable", auRetour = { navigation.retour() }) }
        return
    }
    var mises by remember { mutableStateOf(if (carnet.partsRestantes > 0) 1 else 0) }
    var montant by remember { mutableStateOf("") }
    val centimes = if (carnet.libre) enCentimes(montant) ?: 0L else mises * carnet.mise
    Formulaire("Cotisation", client.nom, navigation, if (centimes > 0) "Enregistrer " + argent(centimes, etat) else "Enregistrer", centimes > 0, {
        depot.cotiser(carnet, if (carnet.libre) centimes else mises.toLong())
        depot.message = "Cotisation enregistrée : " + argent(centimes, etat)
        navigation.terminer()
    }) {
        Carte(Modifier.fillMaxWidth()) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                Avatar(client.nom)
                Column(Modifier.weight(1f)) {
                    Text(client.nom, fontSize = 15.sp, fontWeight = FontWeight.ExtraBold)
                    Text((if (carnet.numero > 0) "Carnet #${carnet.numero} · " else "") + carnet.typeNom, color = Gris, fontSize = 12.sp)
                }
                Text(argent(carnet.epargne, etat), fontSize = 14.sp, fontWeight = FontWeight.ExtraBold)
            }
            if (!carnet.libre) {
                Barre(if (carnet.nombreParts > 0) (carnet.partsValidees + carnet.partsEnAttente).toFloat() / carnet.nombreParts else 0f)
                Ligne("Mises faites", "${carnet.partsValidees + carnet.partsEnAttente} / ${carnet.nombreParts}")
                Ligne("Mises restantes", carnet.partsRestantes.toString(), gras = true)
            }
            if (carnet.enAttente > 0) Ligne("En attente de contrôle", argent(carnet.enAttente, etat), couleur = OrEncre)
        }
        if (carnet.libre) {
            Text("Montant reçu", fontSize = 15.sp, fontWeight = FontWeight.ExtraBold)
            Champ(montant, { montant = it }, "Montant", KeyboardType.Decimal, etat.devise)
        } else if (carnet.partsRestantes == 0) {
            Carte(Modifier.fillMaxWidth(), couleur = OrFond, bordure = null) { Text("Ce carnet est complet : plus aucune mise à recevoir.", color = OrEncre, fontWeight = FontWeight.Bold, fontSize = 14.sp) }
        } else {
            Text("Combien de mises ?", fontSize = 15.sp, fontWeight = FontWeight.ExtraBold)
            Column(Modifier.fillMaxWidth(), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Compteur(mises, carnet.partsRestantes, { mises = it }, grand = true)
                Text("mise de " + argent(carnet.mise, etat), color = Gris, fontSize = 13.sp)
                Row(Modifier.horizontalScroll(rememberScrollState()), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    listOf(1, 2, 5, 7, 10).filter { it <= carnet.partsRestantes }.forEach { n -> Puce("$n", mises == n) { mises = n } }
                    if (carnet.partsRestantes > 1) Puce("Tout (${carnet.partsRestantes})", mises == carnet.partsRestantes) { mises = carnet.partsRestantes }
                }
            }
        }
    }
}

/** Toute la tournee sur un seul ecran : un compteur par carnet, un seul bouton pour tout enregistrer. */
@Composable
fun EcranMasse(depot: Depot, navigation: Navigation) {
    val etat = depot.etat
    val mises = remember { mutableStateMapOf<Long, Int>() }
    val montants = remember { mutableStateMapOf<Long, String>() }
    var recherche by remember { mutableStateOf("") }
    var seulementSaisis by remember { mutableStateOf(false) }
    val mots = recherche.trim().lowercase()
    fun valeur(id: Long, libre: Boolean, mise: Long): Long = if (libre) enCentimes(montants[id] ?: "") ?: 0L else (mises[id] ?: 0) * mise
    val carnets = etat.carnets.filter { k -> k.libre || k.partsRestantes > 0 }
    val visibles = carnets.filter { k ->
        val nom = etat.client(k.clientId)?.nom ?: ""
        (mots.isEmpty() || nom.lowercase().contains(mots)) && (!seulementSaisis || valeur(k.id, k.libre, k.mise) > 0)
    }.sortedBy { etat.client(it.clientId)?.nom ?: "" }
    val total = carnets.sumOf { valeur(it.id, it.libre, it.mise) }
    val nombre = carnets.count { valeur(it.id, it.libre, it.mise) > 0 }

    Column(Modifier.fillMaxSize()) {
        Entete("Collecte en masse", "${carnets.size} carnet(s) ouverts", { navigation.retour() })
        Column(Modifier.padding(start = 16.dp, end = 16.dp, top = 14.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            ChampRecherche(recherche, { recherche = it }, "Chercher un client")
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Puce("Tous", !seulementSaisis) { seulementSaisis = false }
                Puce("Saisis ($nombre)", seulementSaisis) { seulementSaisis = true }
            }
        }
        LazyColumn(Modifier.weight(1f), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
            if (visibles.isEmpty()) item { Text("Aucun carnet à afficher.", color = Gris, fontSize = 14.sp) }
            items(visibles, key = { it.id }) { k ->
                val nom = etat.client(k.clientId)?.nom ?: "Client"
                val saisi = valeur(k.id, k.libre, k.mise) > 0
                Carte(Modifier.fillMaxWidth(), couleur = if (saisi) VertClair else Color.White, bordure = if (saisi) Vert else Trait) {
                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                        Avatar(nom, 40.dp)
                        Column(Modifier.weight(1f)) {
                            Text(nom, fontSize = 14.sp, fontWeight = FontWeight.ExtraBold, maxLines = 1)
                            Text(if (k.libre) "#${k.numero} · dépôt libre" else "#${k.numero} · mise ${argent(k.mise, etat, false)} · reste ${k.partsRestantes}", color = Gris, fontSize = 12.sp, maxLines = 1)
                        }
                        if (!k.libre) Compteur(mises[k.id] ?: 0, k.partsRestantes, { n -> if (n > 0) mises[k.id] = n else mises.remove(k.id) })
                    }
                    if (k.libre) Champ(montants[k.id] ?: "", { montants[k.id] = it }, "Montant reçu", KeyboardType.Decimal, etat.devise)
                }
            }
        }
        Surface(color = Color.White, shadowElevation = 8.dp) {
            Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                    Text("$nombre cotisation(s)", color = Gris, fontSize = 13.sp, fontWeight = FontWeight.Bold)
                    Text(argent(total, etat), fontSize = 22.sp, fontWeight = FontWeight.ExtraBold)
                }
                GrandBouton("Tout enregistrer", actif = nombre > 0) {
                    carnets.forEach { k ->
                        val v = if (k.libre) enCentimes(montants[k.id] ?: "") ?: 0L else (mises[k.id] ?: 0).toLong()
                        if (v > 0) depot.cotiser(k, v)
                    }
                    depot.message = "$nombre cotisation(s) enregistrée(s) : " + argent(total, etat)
                    navigation.terminer()
                }
            }
        }
    }
}

@Composable
fun EcranRemboursement(depot: Depot, navigation: Navigation, creditId: Long) {
    val etat = depot.etat
    val credit = etat.credits.firstOrNull { it.id == creditId }
    if (credit == null) {
        Column(Modifier.fillMaxSize()) { Entete("Crédit introuvable", auRetour = { navigation.retour() }) }
        return
    }
    val client = etat.client(credit.clientId)
    val resteASaisir = (credit.restant - credit.enAttente).coerceAtLeast(0)
    var montant by remember { mutableStateOf("") }
    val centimes = enCentimes(montant) ?: 0L
    val trop = centimes > resteASaisir
    Formulaire("Remboursement", client?.nom, navigation, if (centimes > 0 && !trop) "Enregistrer " + argent(centimes, etat) else "Enregistrer", centimes > 0 && !trop, {
        depot.rembourser(credit, centimes)
        depot.message = "Remboursement enregistré : " + argent(centimes, etat)
        navigation.terminer()
    }) {
        Carte(Modifier.fillMaxWidth()) {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                Text("#${credit.numero} · ${credit.produitNom}", fontSize = 14.sp, fontWeight = FontWeight.ExtraBold)
                if (credit.enRetard) Pastille("En retard", RougeFond, Rouge) else Pastille("En cours", VertPastille, Vert)
            }
            Barre(if (credit.totalDu > 0) credit.rembourse.toFloat() / credit.totalDu else 0f, Or)
            Ligne("Total dû", argent(credit.totalDu, etat))
            Ligne("Déjà payé", argent(credit.rembourse, etat))
            if (credit.enAttente > 0) Ligne("En attente de contrôle", argent(credit.enAttente, etat), couleur = OrEncre)
            Ligne("Reste à recevoir", argent(resteASaisir, etat), gras = true)
            Ligne("Échéance", jourLisible(credit.echeance), couleur = if (credit.enRetard) Rouge else Encre)
        }
        Text("Montant reçu", fontSize = 15.sp, fontWeight = FontWeight.ExtraBold)
        Champ(montant, { montant = it }, "Montant", KeyboardType.Decimal, etat.devise, erreur = trop)
        if (trop) Text("Le montant dépasse ce qui reste à recevoir.", color = Rouge, fontSize = 13.sp, fontWeight = FontWeight.Bold)
        if (resteASaisir > 0) Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            Puce("Tout le reste", centimes == resteASaisir) { montant = argent(resteASaisir, etat, false) }
            Puce("La moitié", false) { montant = argent(resteASaisir / 200 * 100, etat, false) }
        }
    }
}

@Composable
fun EcranDemande(depot: Depot, navigation: Navigation, clientId: Long) {
    val etat = depot.etat
    val client = etat.client(clientId)
    if (client == null) {
        Column(Modifier.fillMaxSize()) { Entete("Client introuvable", auRetour = { navigation.retour() }) }
        return
    }
    var produitId by remember { mutableStateOf(etat.produits.firstOrNull { eligibilite(etat, clientId, it).second == null }?.id ?: etat.produits.firstOrNull()?.id ?: 0L) }
    var montant by remember { mutableStateOf("") }
    val produit = etat.produits.firstOrNull { it.id == produitId }
    val (plafond, raison) = if (produit != null) eligibilite(etat, clientId, produit) else (null to "Aucun produit de crédit n'est proposé.")
    val centimes = enCentimes(montant) ?: 0L
    val erreur = when {
        produit == null || raison != null || centimes == 0L -> null
        produit.minimum != null && centimes < produit.minimum -> "Minimum : " + argent(produit.minimum, etat)
        plafond != null && centimes > plafond -> "Maximum pour ce client : " + argent(plafond, etat)
        else -> null
    }
    val pret = produit != null && raison == null && centimes > 0 && erreur == null
    Formulaire("Demande de crédit", client.nom, navigation, "Envoyer la demande", pret, {
        depot.demanderCredit(client, produit!!, centimes)
        depot.message = "Demande enregistrée : " + argent(centimes, etat)
        navigation.terminer()
    }) {
        Text("Quel crédit ?", fontSize = 15.sp, fontWeight = FontWeight.ExtraBold)
        etat.produits.forEach { p ->
            val (max, motif) = eligibilite(etat, clientId, p)
            val choisi = p.id == produitId
            Carte(Modifier.fillMaxWidth(), couleur = if (choisi) VertClair else Color.White, bordure = if (choisi) Vert else Trait, auClic = { produitId = p.id }) {
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                    Text(p.nom, fontSize = 14.sp, fontWeight = FontWeight.ExtraBold)
                    if (motif == null) Pastille("Éligible", VertPastille, Vert) else Pastille("Pas éligible", RougeFond, Rouge)
                }
                Text(descriptionProduit(p), color = Gris, fontSize = 12.sp)
                Text(motif ?: if (max != null) "Jusqu'à " + argent(max, etat) else "Montant à convenir.", color = if (motif == null) Gris else Rouge, fontSize = 12.sp, fontWeight = FontWeight.Bold)
            }
        }
        if (produit != null && raison == null) {
            Text("Montant demandé", fontSize = 15.sp, fontWeight = FontWeight.ExtraBold)
            Champ(montant, { montant = it }, "Montant", KeyboardType.Decimal, etat.devise, erreur = erreur != null)
            if (erreur != null) Text(erreur, color = Rouge, fontSize = 13.sp, fontWeight = FontWeight.Bold)
            if (centimes > 0 && erreur == null) Carte(Modifier.fillMaxWidth(), couleur = VertClair, bordure = null) {
                Ligne("Montant demandé", argent(centimes, etat))
                if (produit.modeCalcul == "FIXE") {
                    val interet = Math.round(centimes * produit.taux / 100.0)
                    Ligne("Intérêt estimé", argent(interet, etat))
                    Ligne("Total à rembourser (estimation)", argent(centimes + interet, etat), gras = true)
                } else {
                    Ligne("Intérêts", "selon le tableau d'amortissement")
                }
                produit.garantiePct?.let { g -> Ligne("Garantie (épargne bloquée)", argent(Math.round(centimes * g / 100.0), etat)) }
            }
            Text("La demande part chez les responsables. Le client et vous serez prévenus de la décision.", color = Gris, fontSize = 12.sp)
        }
    }
}

/** Nouveau client (avec ou sans premier carnet), ou vente d'un carnet a un client existant. */
@Composable
fun EcranNouveauClient(depot: Depot, navigation: Navigation, clientId: Long?) {
    val etat = depot.etat
    val client = clientId?.let { etat.client(it) }
    if (clientId != null && client == null) {
        Column(Modifier.fillMaxSize()) { Entete("Client introuvable", auRetour = { navigation.retour() }) }
        return
    }
    var nom by remember { mutableStateOf("") }
    var telephone by remember { mutableStateOf("") }
    var type by remember { mutableStateOf<TypeCarnet?>(if (clientId != null) etat.types.firstOrNull() else null) }
    var mise by remember { mutableStateOf("") }
    var versement by remember { mutableStateOf(0) }
    // compte bloque : duree choisie par le client (en mois)
    var duree by remember { mutableStateOf<Int?>(null) }

    val leType = type
    val miseFixe = leType != null && !leType.libre && leType.miseMaximum != null && leType.miseMaximum == leType.miseMinimum
    val miseC = when {
        leType == null || leType.libre -> 0L
        miseFixe -> leType.miseMinimum
        else -> enCentimes(mise) ?: 0L
    }
    val erreurMise = when {
        leType == null || leType.libre || miseC == 0L -> null
        miseC < leType.miseMinimum -> "Mise minimum : " + argent(leType.miseMinimum, etat)
        leType.miseMaximum != null && miseC > leType.miseMaximum -> "Mise maximum : " + argent(leType.miseMaximum, etat)
        else -> null
    }
    val dureePrete = leType == null || !leType.bloque || leType.options.size <= 1 || duree != null
    val carnetPret = (leType == null || leType.libre || (miseC > 0 && erreurMise == null)) && dureePrete
    val dureeEnvoyee = if (leType != null && leType.bloque) (duree ?: leType.options.singleOrNull()?.mois) else null
    val pret = if (client == null) nom.trim().length >= 2 && carnetPret else leType != null && carnetPret
    val aEncaisser = (leType?.prix ?: 0L) + versement * miseC

    Formulaire(if (client == null) "Nouveau client" else "Vendre un carnet", client?.nom, navigation, if (aEncaisser > 0) "Enregistrer · encaisser " + argent(aEncaisser, etat) else "Enregistrer", pret, {
        if (client == null) depot.creerClient(nom, telephone, leType, miseC, versement, dureeEnvoyee) else depot.vendreCarnet(client, leType!!, miseC, versement, dureeEnvoyee)
        depot.message = if (client == null) "Client enregistré : ${nom.trim()}" else "Carnet enregistré pour ${client.nom}"
        navigation.terminer()
    }) {
        if (client == null) {
            Text("Le client", fontSize = 15.sp, fontWeight = FontWeight.ExtraBold)
            Champ(nom, { nom = it }, "Nom complet")
            Champ(telephone, { telephone = it }, "Téléphone (facultatif)", KeyboardType.Phone)
        }
        Text(if (client == null) "Son premier carnet" else "Quel carnet ?", fontSize = 15.sp, fontWeight = FontWeight.ExtraBold)
        if (etat.types.isEmpty()) Text("Aucun type de carnet n'est proposé par l'organisation.", color = Gris, fontSize = 13.sp)
        Row(Modifier.horizontalScroll(rememberScrollState()), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            if (client == null) Puce("Sans carnet", leType == null) { type = null; versement = 0 }
            etat.types.forEach { t -> Puce(t.nom, leType?.id == t.id) { type = t; versement = 0; mise = ""; duree = t.options.singleOrNull()?.mois } }
        }
        if (leType != null && leType.bloque && leType.options.isNotEmpty()) {
            Text("Durée du blocage", fontSize = 15.sp, fontWeight = FontWeight.ExtraBold)
            Text(if (leType.blocage == "TOUT") "L'argent reste bloqué jusqu'à l'échéance." else "Pas de retrait avant l'échéance.", color = Gris, fontSize = 12.sp)
            Row(Modifier.horizontalScroll(rememberScrollState()), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                val base = when (leType.tauxBase) { "MOIS" -> " par mois"; "DUREE" -> " sur la durée"; else -> " par an" }
                leType.options.forEach { o -> Puce("${o.mois} mois · ${o.taux.toDoubleOrNull()?.let { java.text.DecimalFormat("0.##").format(it) } ?: o.taux} %$base", duree == o.mois) { duree = o.mois } }
            }
            if (!dureePrete) Text("Choisissez la durée avec le client.", color = Rouge, fontSize = 13.sp, fontWeight = FontWeight.Bold)
        }
        if (leType != null) {
            Carte(Modifier.fillMaxWidth()) {
                Ligne("Prix du carnet", argent(leType.prix, etat), gras = true)
                if (leType.libre) Ligne("Fonctionnement", "Dépôt libre")
                else {
                    Ligne("Nombre de mises", leType.nombreParts.toString())
                    Ligne("Mise", if (miseFixe) argent(leType.miseMinimum, etat) else "de " + argent(leType.miseMinimum, etat, false) + (leType.miseMaximum?.let { " à " + argent(it, etat) } ?: " et plus"))
                }
            }
            if (!leType.libre) {
                if (!miseFixe) {
                    Champ(mise, { mise = it }, "Mise choisie par le client", KeyboardType.Decimal, etat.devise, erreur = erreurMise != null)
                    if (erreurMise != null) Text(erreurMise, color = Rouge, fontSize = 13.sp, fontWeight = FontWeight.Bold)
                }
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                    Column(Modifier.weight(1f)) {
                        Text("Mises versées tout de suite", fontSize = 14.sp, fontWeight = FontWeight.Bold)
                        Text("Laissez 0 s'il ne verse rien aujourd'hui.", color = Gris, fontSize = 12.sp)
                    }
                    Compteur(versement, leType.nombreParts, { versement = it })
                }
            }
            Carte(Modifier.fillMaxWidth(), couleur = VertClair, bordure = null) {
                Ligne("Carnet", argent(leType.prix, etat))
                if (versement > 0) Ligne("$versement mise(s)", argent(versement * miseC, etat))
                Ligne("À encaisser maintenant", argent(aEncaisser, etat), gras = true)
            }
        }
    }
}

/** Ce que le recolteur dit du produit au client : taux, calcul, duree ou echeances. */
fun descriptionProduit(p: ProduitCredit): String {
    val taux = p.taux.toString().removeSuffix(".0") + " %"
    val calcul = when (p.modeCalcul) { "DEGRESSIF" -> " dégressif"; "DEGRESSIF_CAPITAL" -> " dégressif (capital fixe)"; else -> "" }
    val unite = when (p.echeancesUnite) { "MOIS" -> "mois"; "SEMAINE" -> "semaine(s)"; else -> "jour(s)" }
    val duree = if (p.echeances > 0) "${p.echeances} échéance(s), tous les ${p.echeancesIntervalle} $unite" else "${p.dureeJours} jours"
    val garantie = p.garantiePct?.let { " · garantie ${it.toString().removeSuffix(".0")} %" } ?: ""
    return "Intérêt $taux$calcul · $duree$garantie"
}
