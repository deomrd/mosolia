package com.avecxy.recolteur.ecrans

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
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
import com.avecxy.recolteur.donnees.Saisie
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

private fun nomDuType(type: String): String = when (type) {
    "cotisation" -> "Cotisation"
    "remboursement" -> "Remboursement"
    "carnet" -> "Carnet vendu"
    "client" -> "Nouveau client"
    "demande" -> "Demande de crédit"
    "correction" -> "Correction"
    "annulation" -> "Annulation"
    else -> "Saisie"
}

/** Une saisie : corrigeable tant qu'elle n'est pas validee ; ensuite seules les personnes autorisees peuvent la changer. */
@Composable
fun LigneSaisie(depot: Depot, s: Saisie) {
    val etat = depot.etat
    var corriger by remember(s.cle) { mutableStateOf(false) }
    var annuler by remember(s.cle) { mutableStateOf(false) }
    Carte(Modifier.fillMaxWidth(), bordure = if (s.etat == "REJETEE") Rouge else Trait) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(3.dp)) {
                Text(s.clientNom.ifEmpty { nomDuType(s.type) }, fontSize = 14.sp, fontWeight = FontWeight.ExtraBold, maxLines = 1)
                Text(listOf(nomDuType(s.type), s.detail, jourLisible(s.jour)).filter { it.isNotBlank() }.joinToString(" · "), color = Gris, fontSize = 12.sp, maxLines = 2)
            }
            Column(horizontalAlignment = Alignment.End, verticalArrangement = Arrangement.spacedBy(4.dp)) {
                if (s.montant != 0L) Text(argent(s.montant, etat), fontSize = 14.sp, fontWeight = FontWeight.ExtraBold)
                PastilleEtat(s.etat)
            }
        }
        if (s.etat == "REJETEE") {
            Text(s.message ?: "Le serveur a refusé cette saisie.", color = Rouge, fontSize = 13.sp, fontWeight = FontWeight.Bold)
            Text("Elle n'a pas été comptée. Refaites-la si besoin, puis effacez cette ligne.", color = Gris, fontSize = 12.sp)
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.End) {
                TextButton(onClick = { s.uuid?.let { depot.effacerRefusee(it) } }) { Text("Effacer", color = Rouge, fontWeight = FontWeight.Bold) }
            }
        } else if (s.modifiable) {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.End) {
                TextButton(onClick = { annuler = true }) { Text("Annuler", color = Rouge, fontWeight = FontWeight.Bold) }
                if (s.type == "cotisation") TextButton(onClick = { corriger = true }) { Text("Corriger", color = Vert, fontWeight = FontWeight.Bold) }
            }
        } else if (s.etat == "VALIDEE") {
            Text("Verrouillée : validée au contrôle. Seul un responsable autorisé peut la modifier.", color = Gris, fontSize = 12.sp)
        }
    }

    if (annuler) AlertDialog(
        onDismissRequest = { annuler = false },
        title = { Text("Annuler cette saisie ?", fontWeight = FontWeight.ExtraBold) },
        text = { Text("${nomDuType(s.type)} · ${s.clientNom}" + if (s.montant != 0L) " · " + argent(s.montant, etat) else "") },
        confirmButton = { TextButton(onClick = { depot.annuler(s); annuler = false; depot.message = "Saisie annulée." }) { Text("Oui, annuler", color = Rouge, fontWeight = FontWeight.Bold) } },
        dismissButton = { TextButton(onClick = { annuler = false }) { Text("Garder", color = Gris) } },
    )

    if (corriger) {
        val enMises = s.parts != null
        val carnet = etat.carnets.firstOrNull { it.id == s.objetId }
        val maximum = if (carnet != null) carnet.partsRestantes + (s.parts ?: 0) else 99
        var mises by remember(s.cle) { mutableStateOf(s.parts ?: 0) }
        var montant by remember(s.cle) { mutableStateOf(argent(s.montant, etat, false)) }
        val valeur = if (enMises) mises.toLong() else enCentimes(montant) ?: 0L
        AlertDialog(
            onDismissRequest = { corriger = false },
            title = { Text("Corriger la cotisation", fontWeight = FontWeight.ExtraBold) },
            text = {
                Column(Modifier.fillMaxWidth(), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    Text(s.clientNom + " · " + s.detail, color = Gris, fontSize = 13.sp)
                    if (enMises) { Compteur(mises, maximum.coerceAtLeast(1), { mises = it }); Text("nombre de mises", color = Gris, fontSize = 12.sp) }
                    else Champ(montant, { montant = it }, "Montant", KeyboardType.Decimal, etat.devise)
                }
            },
            confirmButton = { TextButton(enabled = valeur > 0, onClick = { depot.corriger(s, valeur); corriger = false; depot.message = "Correction enregistrée." }) { Text("Enregistrer", fontWeight = FontWeight.Bold) } },
            dismissButton = { TextButton(onClick = { corriger = false }) { Text("Fermer", color = Gris) } },
        )
    }
}

@Composable
fun EcranSaisies(depot: Depot) {
    val etat = depot.etat
    var filtre by remember { mutableStateOf("") }
    val saisies = etat.saisies.filter { filtre.isEmpty() || it.etat == filtre }
    Column(Modifier.fillMaxSize()) {
        Entete("Mes saisies", "${etat.aEnvoyer} à envoyer · ${etat.refusees} refusée(s)")
        Row(Modifier.padding(start = 16.dp, end = 16.dp, top = 12.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            Puce("Toutes", filtre.isEmpty()) { filtre = "" }
            Puce("À envoyer", filtre == "A_ENVOYER") { filtre = "A_ENVOYER" }
            Puce("Au contrôle", filtre == "EN_ATTENTE") { filtre = "EN_ATTENTE" }
            Puce("Refusées", filtre == "REJETEE") { filtre = "REJETEE" }
        }
        LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
            if (saisies.isEmpty()) item { Text("Aucune saisie ici.", color = Gris, fontSize = 14.sp) }
            items(saisies, key = { it.cle }) { s -> LigneSaisie(depot, s) }
        }
    }
}

@Composable
fun EcranSynchro(depot: Depot, synchroniser: () -> Unit, lancer: (suspend () -> Unit) -> Unit) {
    val etat = depot.etat
    var quitter by remember { mutableStateOf(false) }
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState())) {
        Entete("Synchronisation", etat.organisation)
        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            Carte(Modifier.fillMaxWidth(), couleur = if (etat.aEnvoyer > 0) OrFond else VertClair, bordure = null) {
                Text(if (etat.aEnvoyer > 0) "${etat.aEnvoyer} saisie(s) dans le téléphone" else "Tout est envoyé", fontSize = 18.sp, fontWeight = FontWeight.ExtraBold, color = if (etat.aEnvoyer > 0) OrEncre else Vert)
                Text(
                    if (etat.derniereSynchro > 0) "Dernière synchronisation : " + SimpleDateFormat("EEE d MMM 'à' HH:mm", Locale.FRENCH).format(Date(etat.derniereSynchro)) else "Jamais synchronisé.",
                    color = Gris, fontSize = 13.sp,
                )
                if (depot.synchronisation) Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                    CircularProgressIndicator(Modifier.size(22.dp), color = Vert, strokeWidth = 3.dp)
                    Text("Envoi en cours…", fontWeight = FontWeight.Bold, fontSize = 14.sp)
                } else GrandBouton("Synchroniser maintenant", couleur = Vert, encre = Color.White, auClic = synchroniser)
            }
            if (etat.refusees > 0) Carte(Modifier.fillMaxWidth(), couleur = RougeFond, bordure = null) {
                Text("${etat.refusees} saisie(s) refusée(s) par le serveur", color = Rouge, fontWeight = FontWeight.ExtraBold, fontSize = 15.sp)
                Text("Ouvrez « Saisies » puis le filtre « Refusées » pour lire la raison.", color = Encre, fontSize = 13.sp)
            }
            Carte(Modifier.fillMaxWidth()) {
                Etiquette("Ce téléphone")
                Ligne("Récolteur", etat.utilisateurNom)
                Ligne("Identifiant", etat.identifiant)
                Ligne("Organisation", etat.organisation)
                Ligne("Serveur", depot.adresse)
                Ligne("Clients · carnets", "${etat.clients.size} · ${etat.carnets.size}")
            }
            Carte(Modifier.fillMaxWidth()) {
                Etiquette("Comment ça marche")
                Text("Chaque saisie est d'abord gardée dans le téléphone. Elle part toute seule dès que le réseau revient, et une saisie n'est jamais comptée deux fois, même si l'envoi est coupé.", color = Gris, fontSize = 13.sp)
            }
            TextButton(onClick = { quitter = true }, modifier = Modifier.fillMaxWidth()) { Text("Se déconnecter de ce téléphone", color = Rouge, fontWeight = FontWeight.Bold) }
        }
    }
    if (quitter) AlertDialog(
        onDismissRequest = { quitter = false },
        title = { Text(if (etat.aEnvoyer > 0) "Synchronisez d'abord" else "Se déconnecter ?", fontWeight = FontWeight.ExtraBold) },
        text = {
            Text(if (etat.aEnvoyer > 0) "${etat.aEnvoyer} saisie(s) ne sont pas encore envoyées. Pour ne rien perdre, la déconnexion n'est possible qu'une fois tout envoyé."
            else "Il faudra votre identifiant, votre mot de passe et du réseau pour revenir.")
        },
        confirmButton = {
            if (etat.aEnvoyer > 0) TextButton(onClick = { quitter = false; synchroniser() }) { Text("Synchroniser", color = Vert, fontWeight = FontWeight.Bold) }
            else TextButton(onClick = { quitter = false; lancer { val e = depot.deconnecter(); if (e == null) oublierSessionWeb() else depot.message = e } }) { Text("Se déconnecter", color = Rouge, fontWeight = FontWeight.Bold) }
        },
        dismissButton = { TextButton(onClick = { quitter = false }) { Text("Rester", color = Gris) } },
    )
}

@Composable
fun EcranNotifications(depot: Depot, navigation: Navigation, lancer: (suspend () -> Unit) -> Unit) {
    val etat = depot.etat
    // on garde l'etat « non lue » tel qu'il etait a l'ouverture, pour que les nouveautes restent visibles
    val nonLues = remember { etat.notifications.filter { !it.lue }.map { it.texte + it.le }.toSet() }
    LaunchedEffect(Unit) { if (nonLues.isNotEmpty()) lancer { depot.marquerNotificationsLues() } }
    Column(Modifier.fillMaxSize()) {
        Entete("Notifications", if (nonLues.isEmpty()) "Rien de nouveau" else "${nonLues.size} nouvelle(s)", { navigation.retour() })
        LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
            if (etat.notifications.isEmpty()) item { Text("Aucune notification. Elles arrivent à chaque synchronisation.", color = Gris, fontSize = 14.sp) }
            items(etat.notifications) { n ->
                val nouvelle = (n.texte + n.le) in nonLues
                Carte(Modifier.fillMaxWidth(), couleur = if (nouvelle) VertClair else Color.White, bordure = if (nouvelle) Vert else Trait) {
                    Text(n.texte, fontSize = 14.sp, fontWeight = if (nouvelle) FontWeight.ExtraBold else FontWeight.Medium)
                    Text(n.le, color = Gris, fontSize = 12.sp)
                }
            }
        }
    }
}
