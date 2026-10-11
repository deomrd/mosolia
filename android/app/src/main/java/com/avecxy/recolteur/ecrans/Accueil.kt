package com.avecxy.recolteur.ecrans

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Notifications
import androidx.compose.material3.FilterChip
import androidx.compose.material3.FilterChipDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.avecxy.recolteur.donnees.Depot
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

@Composable
fun EcranAccueil(depot: Depot, navigation: Navigation, synchroniser: () -> Unit) {
    val etat = depot.etat
    // filtre de l'argent a deposer : "" = tous les jours pas encore passes au controle, sinon un seul jour
    var jourChoisi by remember { mutableStateOf("") }
    val jours = etat.aDeposer.filter { jourChoisi.isEmpty() || it.jour == jourChoisi }
    val nonLues = etat.notifications.count { !it.lue }

    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState())) {
        // en-tete : qui, quel jour, etat de la synchronisation
        Column(Modifier.fillMaxWidth().clip(RoundedCornerShape(bottomStart = 28.dp, bottomEnd = 28.dp)).background(VertFonce).padding(start = 20.dp, end = 20.dp, top = 20.dp, bottom = 72.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                    Avatar(etat.utilisateurNom, 44.dp, Color(0xFF1C6B5E), Color.White)
                    Column {
                        Text(SimpleDateFormat("EEEE d MMMM", Locale.FRENCH).format(Date()).replaceFirstChar { it.uppercase() }, color = Color(0xFFB9DAD2), fontSize = 13.sp)
                        Text(etat.utilisateurNom, color = Color.White, fontSize = 17.sp, fontWeight = FontWeight.ExtraBold)
                    }
                }
                Row(verticalAlignment = Alignment.CenterVertically) {
                // qui est aussi gestionnaire passe au site (en ligne) et revient a sa tournee
                if (depot.profil == "LES_DEUX") Text(
                    "Gestion", color = VertFonce, fontSize = 12.sp, fontWeight = FontWeight.ExtraBold,
                    modifier = Modifier.padding(end = 8.dp).clip(RoundedCornerShape(10.dp)).background(Or).clickable { depot.basculer("GESTION") }.padding(horizontal = 12.dp, vertical = 9.dp),
                )
                Box(Modifier.size(44.dp).clip(RoundedCornerShape(14.dp)).background(Color(0x1FFFFFFF)).clickable { navigation.ouvrir(Ecran.Notifications) }, contentAlignment = Alignment.Center) {
                    Icon(Icons.Filled.Notifications, contentDescription = "Notifications", tint = Color.White)
                    if (nonLues > 0) Text(nonLues.toString(), color = VertFonce, fontSize = 10.sp, fontWeight = FontWeight.ExtraBold,
                        modifier = Modifier.align(Alignment.TopEnd).padding(4.dp).clip(RoundedCornerShape(8.dp)).background(Or).padding(horizontal = 4.dp))
                }
                }
            }
            Row(Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp)).background(Color(0x29F2B544)).padding(horizontal = 14.dp, vertical = 10.dp), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                Column(Modifier.weight(1f)) {
                    Text(if (etat.aEnvoyer > 0) "${etat.aEnvoyer} saisie(s) à envoyer" else "Tout est envoyé", color = Color.White, fontSize = 13.sp, fontWeight = FontWeight.Bold)
                    Text("Dernière synchronisation : " + SimpleDateFormat("d MMM HH:mm", Locale.FRENCH).format(Date(etat.derniereSynchro)), color = Color(0xFFD5E9E4), fontSize = 12.sp)
                }
                Text(if (depot.synchronisation) "Envoi…" else "Envoyer", color = VertFonce, fontSize = 12.sp, fontWeight = FontWeight.ExtraBold,
                    modifier = Modifier.clip(RoundedCornerShape(10.dp)).background(Or).clickable(enabled = !depot.synchronisation, onClick = synchroniser).padding(horizontal = 14.dp, vertical = 10.dp))
            }
        }

        // argent a deposer au controle
        Carte(Modifier.padding(horizontal = 20.dp).offset(y = (-52).dp), bordure = null) {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                Etiquette("Argent à déposer au contrôle")
                if (jourChoisi.isEmpty() && etat.aDeposer.size > 1) Pastille("${etat.aDeposer.size} jours", OrFond, OrEncre)
            }
            Text(argent(jours.sumOf { it.total }, etat), fontSize = 32.sp, fontWeight = FontWeight.ExtraBold)
            Row(Modifier.horizontalScroll(rememberScrollState()), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                Puce("Tout", jourChoisi.isEmpty()) { jourChoisi = "" }
                etat.aDeposer.forEach { j -> Puce(jourLisible(j.jour), jourChoisi == j.jour) { jourChoisi = j.jour } }
            }
            Ligne("Carnets", argent(jours.sumOf { it.carnets }, etat), gras = true)
            Ligne("Cotisations", argent(jours.sumOf { it.cotisations }, etat), gras = true)
            if (etat.avecCredit) Ligne("Paiements de crédit", argent(jours.sumOf { it.paiements }, etat), gras = true)
        }

        Column(Modifier.padding(horizontal = 20.dp).offset(y = (-36).dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                Carte(Modifier.weight(1f)) {
                    Etiquette("Mon solde du mois")
                    Text(argent(etat.solde.net, etat), fontSize = 20.sp, fontWeight = FontWeight.ExtraBold)
                    Text(if (etat.solde.dejaPaye) "Déjà payé" else "Net à payer", color = Gris, fontSize = 12.sp)
                    Ligne("Ma part", argent(etat.solde.part, etat, false))
                    if (etat.solde.manquants > 0) Ligne("Manquants", "− " + argent(etat.solde.manquants, etat, false), couleur = Rouge)
                }
                if (etat.avecCredit) Carte(Modifier.weight(1f), auClic = { navigation.onglet(Ecran.Clients) }) {
                    Etiquette("Crédits de mes clients")
                    Text(argent(etat.totalCredits, etat), fontSize = 20.sp, fontWeight = FontWeight.ExtraBold)
                    Text("Reste dû · ${etat.credits.size} crédit(s)", color = Gris, fontSize = 12.sp)
                    val retards = etat.credits.filter { it.enRetard }
                    if (retards.isNotEmpty()) Ligne("En retard", "${retards.size} · " + argent(retards.sumOf { it.restant }, etat, false), couleur = Rouge)
                }
            }

            Text("Que voulez-vous faire ?", fontSize = 15.sp, fontWeight = FontWeight.ExtraBold)
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                Action("Cotisation", "Un client à la fois", Vert, Color.White, Modifier.weight(1f)) { navigation.ouvrir(Ecran.Choisir("cotisation")) }
                Action("Collecte en masse", "Toute la tournée", Or, Encre, Modifier.weight(1f)) { navigation.ouvrir(Ecran.Masse) }
            }
            if (etat.avecCredit) Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                ActionSimple("Remboursement", Modifier.weight(1f)) { navigation.ouvrir(Ecran.Choisir("remboursement")) }
                ActionSimple("Demande de crédit", Modifier.weight(1f)) { navigation.ouvrir(Ecran.Choisir("demande")) }
            }
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                ActionSimple("Nouveau client", Modifier.weight(1f)) { navigation.ouvrir(Ecran.NouveauClient) }
                ActionSimple("Vendre un carnet", Modifier.weight(1f)) { navigation.ouvrir(Ecran.Choisir("carnet")) }
            }

            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                Text("Dernières saisies", fontSize = 15.sp, fontWeight = FontWeight.ExtraBold)
                TextButton(onClick = { navigation.onglet(Ecran.Saisies) }) { Text("Tout voir", color = Vert, fontWeight = FontWeight.Bold) }
            }
            if (etat.saisies.isEmpty()) Text("Aucune saisie pour le moment.", color = Gris, fontSize = 13.sp)
            etat.saisies.take(4).forEach { s -> LigneSaisie(depot, s) }
            Spacer(Modifier.height(8.dp))
        }
    }
}

@Composable
fun Puce(texte: String, choisie: Boolean, auClic: () -> Unit) {
    FilterChip(
        selected = choisie, onClick = auClic, label = { Text(texte, fontSize = 12.sp, fontWeight = FontWeight.Bold) }, shape = RoundedCornerShape(50),
        colors = FilterChipDefaults.filterChipColors(selectedContainerColor = Vert, selectedLabelColor = Color.White, containerColor = Color.White, labelColor = Encre),
    )
}

@Composable
private fun Action(titre: String, sousTitre: String, fond: Color, encre: Color, modifier: Modifier, auClic: () -> Unit) {
    Column(modifier.clip(RoundedCornerShape(18.dp)).background(fond).clickable(onClick = auClic).padding(16.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
        Box(Modifier.size(40.dp).clip(RoundedCornerShape(12.dp)).background(encre.copy(alpha = 0.14f)), contentAlignment = Alignment.Center) {
            Text("+", color = encre, fontSize = 22.sp, fontWeight = FontWeight.Bold)
        }
        Spacer(Modifier.height(6.dp))
        Text(titre, color = encre, fontSize = 15.sp, fontWeight = FontWeight.ExtraBold)
        Text(sousTitre, color = encre.copy(alpha = 0.8f), fontSize = 12.sp)
    }
}

@Composable
private fun ActionSimple(titre: String, modifier: Modifier, auClic: () -> Unit) {
    Carte(modifier, auClic = auClic) { Text(titre, fontSize = 14.sp, fontWeight = FontWeight.Bold, color = Encre) }
}
