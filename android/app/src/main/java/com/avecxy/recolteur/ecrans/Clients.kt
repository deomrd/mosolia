package com.avecxy.recolteur.ecrans

import android.content.Intent
import android.net.Uri
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.Phone
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.avecxy.recolteur.donnees.Carnet
import com.avecxy.recolteur.donnees.Depot
import com.avecxy.recolteur.donnees.Etat
import com.avecxy.recolteur.donnees.ProduitCredit

/** Bandeau du haut des ecrans secondaires : retour, titre, sous-titre. */
@Composable
fun Entete(titre: String, sousTitre: String? = null, auRetour: (() -> Unit)? = null, droite: (@Composable () -> Unit)? = null) {
    Row(
        Modifier.fillMaxWidth().clip(RoundedCornerShape(bottomStart = 24.dp, bottomEnd = 24.dp)).background(VertFonce).padding(horizontal = 16.dp, vertical = 16.dp),
        verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        if (auRetour != null) BoutonRond(auRetour) { Icon(Icons.Filled.ArrowBack, contentDescription = "Retour", tint = Color.White) }
        Column(Modifier.weight(1f)) {
            Text(titre, color = Color.White, fontSize = 19.sp, fontWeight = FontWeight.ExtraBold, maxLines = 1)
            if (sousTitre != null) Text(sousTitre, color = Color(0xFFB9DAD2), fontSize = 13.sp, maxLines = 1)
        }
        droite?.invoke()
    }
}

@Composable
fun BoutonRond(auClic: () -> Unit, contenu: @Composable () -> Unit) {
    Box(Modifier.size(44.dp).clip(RoundedCornerShape(14.dp)).background(Color(0x1FFFFFFF)).clickable(onClick = auClic), contentAlignment = Alignment.Center) { contenu() }
}

/** Barre de progression aux couleurs de l'application. */
@Composable
fun Barre(part: Float, couleur: Color = Vert) {
    Box(Modifier.fillMaxWidth().height(8.dp).clip(RoundedCornerShape(4.dp)).background(Trait)) {
        Box(Modifier.fillMaxWidth(part.coerceIn(0f, 1f)).height(8.dp).clip(RoundedCornerShape(4.dp)).background(couleur))
    }
}

@Composable
fun ChampRecherche(valeur: String, auChangement: (String) -> Unit, indication: String) {
    OutlinedTextField(
        value = valeur, onValueChange = auChangement, modifier = Modifier.fillMaxWidth(), singleLine = true, shape = RoundedCornerShape(16.dp),
        placeholder = { Text(indication, color = Gris) }, leadingIcon = { Icon(Icons.Filled.Search, contentDescription = null, tint = Gris) },
        colors = OutlinedTextFieldDefaults.colors(focusedContainerColor = Color.White, unfocusedContainerColor = Color.White, focusedBorderColor = Vert, unfocusedBorderColor = Trait),
    )
}

/**
 * Ce que le telephone sait de l'eligibilite d'un client a un produit de credit.
 * @return le plafond en centimes (null = pas de plafond connu) et, si le client n'est pas eligible, la raison.
 * Le serveur refait toujours le controle a la synchronisation.
 */
fun eligibilite(etat: Etat, clientId: Long, produit: ProduitCredit): Pair<Long?, String?> {
    val credits = etat.creditsDe(clientId)
    if (produit.simultanesMax > 0 && credits.size >= produit.simultanesMax) return null to "Un crédit est déjà en cours (maximum ${produit.simultanesMax})."
    if (produit.sansRetard && credits.any { it.enRetard }) return null to "Un crédit est en retard de paiement."
    val epargne = etat.carnetsDe(clientId).sumOf { it.epargne }
    var plafond: Long? = produit.maximum
    if (produit.multipleEpargne != null) {
        val selonEpargne = (epargne * produit.multipleEpargne).toLong()
        if (selonEpargne <= 0) return null to "Pas encore d'épargne validée."
        plafond = if (plafond == null) selonEpargne else minOf(plafond, selonEpargne)
    }
    if (produit.garantiePct != null && produit.garantiePct > 0) {
        // garantie : une part du pret doit etre couverte par l'epargne (le serveur deduit aussi les garanties deja prises)
        val selonGarantie = (epargne * 100 / produit.garantiePct).toLong()
        if (selonGarantie <= 0) return null to "Pas d'épargne pour la garantie (${produit.garantiePct.toString().removeSuffix(".0")} %)."
        plafond = if (plafond == null) selonGarantie else minOf(plafond, selonGarantie)
    }
    if (plafond != null && produit.minimum != null && plafond < produit.minimum) return null to "Épargne insuffisante pour le montant minimum."
    return plafond to null
}

/** Liste des clients. Avec une action, on choisit le client puis on enchaine sur la saisie. */
@Composable
fun EcranClients(depot: Depot, navigation: Navigation, action: String?) {
    val etat = depot.etat
    var recherche by remember { mutableStateOf("") }
    val mots = recherche.trim().lowercase()
    val clients = etat.clients.filter { c ->
        (mots.isEmpty() || c.nom.lowercase().contains(mots) || c.code.lowercase().contains(mots) || (c.telephone ?: "").contains(mots)) &&
            (action != "remboursement" || etat.creditsDe(c.id).isNotEmpty()) && (action != "cotisation" || etat.carnetsDe(c.id).isNotEmpty())
    }
    val titre = when (action) {
        "cotisation" -> "Cotisation : quel client ?"
        "remboursement" -> "Remboursement : quel client ?"
        "demande" -> "Demande de crédit : pour qui ?"
        "carnet" -> "Vendre un carnet : à qui ?"
        else -> "Mes clients"
    }
    Column(Modifier.fillMaxSize()) {
        Entete(titre, "${etat.clients.size} client(s) · épargne " + argent(etat.totalEpargne, etat), if (action != null) ({ navigation.retour() }) else null)
        Column(Modifier.padding(start = 16.dp, end = 16.dp, top = 14.dp)) { ChampRecherche(recherche, { recherche = it }, "Nom, code ou téléphone") }
        LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
            if (clients.isEmpty()) item { Text("Aucun client trouvé.", color = Gris, fontSize = 14.sp) }
            items(clients, key = { it.id }) { c ->
                val carnets = etat.carnetsDe(c.id)
                val credits = etat.creditsDe(c.id)
                Carte(Modifier.fillMaxWidth(), auClic = {
                    when (action) {
                        "cotisation" -> navigation.ouvrir(if (carnets.size == 1) Ecran.Cotisation(carnets[0].id) else Ecran.Client(c.id))
                        "remboursement" -> navigation.ouvrir(if (credits.size == 1) Ecran.Remboursement(credits[0].id) else Ecran.Client(c.id))
                        "demande" -> navigation.ouvrir(Ecran.Demande(c.id))
                        "carnet" -> navigation.ouvrir(Ecran.Carnet(c.id))
                        else -> navigation.ouvrir(Ecran.Client(c.id))
                    }
                }) {
                    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                        Avatar(c.nom)
                        Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(3.dp)) {
                            Text(c.nom, fontSize = 15.sp, fontWeight = FontWeight.ExtraBold, maxLines = 1)
                            Text(listOfNotNull(c.code.ifEmpty { null }, "${carnets.size} carnet(s)").joinToString(" · "), color = Gris, fontSize = 12.sp, maxLines = 1)
                        }
                        Column(horizontalAlignment = Alignment.End, verticalArrangement = Arrangement.spacedBy(4.dp)) {
                            Text(argent(carnets.sumOf { it.epargne }, etat), fontSize = 14.sp, fontWeight = FontWeight.ExtraBold)
                            when {
                                c.local -> Pastille("À envoyer", OrFond, OrEncre)
                                credits.any { it.enRetard } -> Pastille("Crédit en retard", RougeFond, Rouge)
                                credits.isNotEmpty() -> Pastille("Crédit " + argent(credits.sumOf { it.restant }, etat, false), BleuFond, BleuEncre)
                                else -> {}
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun CarteCarnet(carnet: Carnet, etat: Etat, auClic: () -> Unit) {
    Carte(Modifier.fillMaxWidth(), auClic = auClic) {
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
            Text((if (carnet.numero > 0) "#${carnet.numero} · " else "") + carnet.typeNom, fontSize = 14.sp, fontWeight = FontWeight.ExtraBold)
            Text(argent(carnet.epargne, etat), fontSize = 14.sp, fontWeight = FontWeight.ExtraBold)
        }
        if (!carnet.libre && carnet.nombreParts > 0) Barre(carnet.partsValidees.toFloat() / carnet.nombreParts)
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
            Text(if (carnet.libre) "Dépôt libre" else "${carnet.partsValidees} / ${carnet.nombreParts} mises · mise " + argent(carnet.mise, etat, false), color = Gris, fontSize = 12.sp)
            if (carnet.enAttente > 0) Text("+ " + argent(carnet.enAttente, etat, false) + " en attente", color = OrEncre, fontSize = 12.sp, fontWeight = FontWeight.Bold)
            else if (carnet.local) Pastille("À envoyer", OrFond, OrEncre)
        }
    }
}

@Composable
fun EcranClient(depot: Depot, navigation: Navigation, clientId: Long) {
    val etat = depot.etat
    val client = etat.client(clientId)
    if (client == null) {
        Column(Modifier.fillMaxSize()) { Entete("Client introuvable", auRetour = { navigation.retour() }) }
        return
    }
    val carnets = etat.carnetsDe(clientId)
    val credits = etat.creditsDe(clientId)
    val contexte = LocalContext.current
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState())) {
        Column(Modifier.fillMaxWidth().clip(RoundedCornerShape(bottomStart = 28.dp, bottomEnd = 28.dp)).background(VertFonce).padding(20.dp), verticalArrangement = Arrangement.spacedBy(18.dp)) {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                BoutonRond({ navigation.retour() }) { Icon(Icons.Filled.ArrowBack, contentDescription = "Retour", tint = Color.White) }
                if (!client.telephone.isNullOrBlank()) BoutonRond({
                    try { contexte.startActivity(Intent(Intent.ACTION_DIAL, Uri.parse("tel:" + client.telephone))) } catch (e: Exception) { depot.message = "Impossible d'ouvrir le téléphone." }
                }) { Icon(Icons.Filled.Phone, contentDescription = "Appeler", tint = Color.White) }
            }
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(14.dp)) {
                Avatar(client.nom, 58.dp, Color(0xFF1C6B5E), Color.White)
                Column(verticalArrangement = Arrangement.spacedBy(3.dp)) {
                    Text(client.nom, color = Color.White, fontSize = 20.sp, fontWeight = FontWeight.ExtraBold)
                    Text(listOfNotNull(client.code.ifEmpty { null }, client.telephone?.ifBlank { null }, if (client.local) "pas encore envoyé" else null).joinToString(" · "), color = Color(0xFFB9DAD2), fontSize = 13.sp)
                }
            }
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                Chiffre("Épargne", argent(carnets.sumOf { it.epargne }, etat), carnets.sumOf { it.enAttente }.let { if (it > 0) "+ " + argent(it, etat, false) + " en attente" else null }, Modifier.weight(1f))
                if (etat.avecCredit) Chiffre("Crédit à payer", argent(credits.sumOf { it.restant }, etat), credits.minByOrNull { it.echeance }?.let { "Échéance " + jourLisible(it.echeance) }, Modifier.weight(1f))
            }
        }

        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Raccourci("Vendre carnet", Vert, Color.White, Modifier.weight(1f)) { navigation.ouvrir(Ecran.Carnet(clientId)) }
                if (etat.avecCredit) Raccourci("Demander crédit", Color.White, Encre, Modifier.weight(1f)) { navigation.ouvrir(Ecran.Demande(clientId)) }
            }

            Text("Carnets", fontSize = 15.sp, fontWeight = FontWeight.ExtraBold)
            if (carnets.isEmpty()) Text("Aucun carnet ouvert. Vendez-lui un carnet pour commencer.", color = Gris, fontSize = 13.sp)
            carnets.forEach { k -> CarteCarnet(k, etat) { navigation.ouvrir(Ecran.Cotisation(k.id)) } }
            if (carnets.isNotEmpty()) Text("Touchez un carnet pour saisir une cotisation.", color = Gris, fontSize = 12.sp)

            if (etat.avecCredit) {
                Text("Crédits en cours", fontSize = 15.sp, fontWeight = FontWeight.ExtraBold)
                if (credits.isEmpty()) Text("Aucun crédit en cours.", color = Gris, fontSize = 13.sp)
                credits.forEach { cr ->
                    Carte(Modifier.fillMaxWidth()) {
                        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                            Text("#${cr.numero} · ${cr.produitNom}", fontSize = 14.sp, fontWeight = FontWeight.ExtraBold)
                            if (cr.enRetard) Pastille("En retard", RougeFond, Rouge) else Pastille("En cours", VertPastille, Vert)
                        }
                        Barre(if (cr.totalDu > 0) cr.rembourse.toFloat() / cr.totalDu else 0f, Or)
                        Ligne("Total dû", argent(cr.totalDu, etat))
                        Ligne("Déjà payé", argent(cr.rembourse, etat))
                        Ligne("Reste", argent(cr.restant, etat), gras = true)
                        if (cr.enAttente > 0) Ligne("Paiements en attente de contrôle", argent(cr.enAttente, etat), couleur = OrEncre)
                        Ligne("Échéance", jourLisible(cr.echeance), couleur = if (cr.enRetard) Rouge else Encre)
                        GrandBouton("Recevoir un remboursement") { navigation.ouvrir(Ecran.Remboursement(cr.id)) }
                    }
                }

                Text("Nouvelle demande", fontSize = 15.sp, fontWeight = FontWeight.ExtraBold)
                if (etat.produits.isEmpty()) Text("Aucun produit de crédit n'est proposé.", color = Gris, fontSize = 13.sp)
                etat.produits.forEach { p ->
                    val (plafond, raison) = eligibilite(etat, clientId, p)
                    Carte(Modifier.fillMaxWidth()) {
                        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                            Text(p.nom, fontSize = 14.sp, fontWeight = FontWeight.ExtraBold)
                            if (raison == null) Pastille("Éligible", VertPastille, Vert) else Pastille("Pas éligible", RougeFond, Rouge)
                        }
                        Text(raison ?: if (plafond != null) "Jusqu'à " + argent(plafond, etat) else "Montant à convenir avec le client.", color = Gris, fontSize = 12.sp)
                    }
                }
            }
            Spacer(Modifier.height(8.dp))
        }
    }
}

@Composable
private fun Chiffre(titre: String, valeur: String, note: String?, modifier: Modifier) {
    Column(modifier.clip(RoundedCornerShape(16.dp)).background(Color(0x1AFFFFFF)).padding(horizontal = 14.dp, vertical = 12.dp), verticalArrangement = Arrangement.spacedBy(2.dp)) {
        Etiquette(titre, Color(0xFFB9DAD2))
        Text(valeur, color = Color.White, fontSize = 19.sp, fontWeight = FontWeight.ExtraBold, maxLines = 1)
        if (note != null) Text(note, color = Or, fontSize = 12.sp, fontWeight = FontWeight.Bold, maxLines = 1)
    }
}

@Composable
private fun Raccourci(texte: String, fond: Color, encre: Color, modifier: Modifier, auClic: () -> Unit) {
    Surface(modifier.height(52.dp).clip(RoundedCornerShape(16.dp)).clickable(onClick = auClic), shape = RoundedCornerShape(16.dp), color = fond, border = if (fond == Color.White) androidx.compose.foundation.BorderStroke(1.dp, Trait) else null) {
        Box(contentAlignment = Alignment.Center) { Text(texte, color = encre, fontSize = 14.sp, fontWeight = FontWeight.ExtraBold) }
    }
}
