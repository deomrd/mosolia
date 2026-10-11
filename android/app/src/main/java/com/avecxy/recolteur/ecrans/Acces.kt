package com.avecxy.recolteur.ecrans

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.avecxy.recolteur.donnees.Depot

/**
 * Un seul ecran de connexion pour tout le monde : le personnel (identifiant et mot de passe)
 * et les clients (code client et code secret). Le serveur reconnait qui se connecte.
 */
@Composable
fun EcranConnexion(depot: Depot, lancer: (suspend () -> Unit) -> Unit) {
    var identifiant by remember { mutableStateOf(depot.identifiantConnecte.ifEmpty { depot.dernierIdentifiant }) }
    var motDePasse by remember { mutableStateOf("") }
    var erreur by remember { mutableStateOf<String?>(null) }
    var enCours by remember { mutableStateOf(false) }
    val codeClient = Regex("^[A-Za-z0-9]{4}-\\d+$").matches(identifiant.trim())
    Column(Modifier.fillMaxSize().background(VertFonce).verticalScroll(rememberScrollState()).padding(24.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
        Spacer(Modifier.height(32.dp))
        Text("Mosolia", color = Color.White, fontSize = 30.sp, fontWeight = FontWeight.ExtraBold)
        Text("Épargne, crédits et tournées", color = Color(0xFFB9DAD2), fontSize = 15.sp)
        Spacer(Modifier.height(8.dp))
        Carte(bordure = null) {
            Text("Connexion", fontSize = 20.sp, fontWeight = FontWeight.ExtraBold)
            Text(
                if (depot.reconnexionRequise) "Ce téléphone doit être reconnecté. Vos saisies non envoyées sont conservées."
                else "Personnel : votre identifiant et votre mot de passe. Client : votre code client et votre code secret.",
                color = Gris, fontSize = 13.sp,
            )
            OutlinedTextField(identifiant, { identifiant = it }, label = { Text("Identifiant ou code client") }, singleLine = true, modifier = Modifier.fillMaxWidth(), keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email))
            OutlinedTextField(
                motDePasse, { motDePasse = it }, label = { Text(if (codeClient) "Code secret" else "Mot de passe") }, singleLine = true, modifier = Modifier.fillMaxWidth(),
                visualTransformation = PasswordVisualTransformation(),
                keyboardOptions = KeyboardOptions(keyboardType = if (codeClient) KeyboardType.NumberPassword else KeyboardType.Password),
            )
            erreur?.let { Text(it, color = Rouge, fontSize = 13.sp, fontWeight = FontWeight.Bold) }
            GrandBouton(if (enCours) "Connexion…" else "Se connecter", actif = !enCours && identifiant.isNotBlank() && motDePasse.isNotEmpty()) {
                enCours = true
                erreur = null
                lancer {
                    erreur = depot.connecter(identifiant, motDePasse)
                    enCours = false
                }
            }
            Text("La première connexion demande internet. Le récolteur peut ensuite travailler sans réseau.", color = Gris, fontSize = 12.sp)
        }
    }
}

@Composable
private fun PaveNumerique(surChiffre: (Char) -> Unit, surEffacer: () -> Unit, boutonGauche: (@Composable () -> Unit)? = null) {
    Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
        listOf("123", "456", "789").forEach { rangee ->
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                rangee.forEach { chiffre -> Touche(chiffre.toString(), Modifier.weight(1f)) { surChiffre(chiffre) } }
            }
        }
        Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            Box(Modifier.weight(1f).height(64.dp), contentAlignment = Alignment.Center) { boutonGauche?.invoke() }
            Touche("0", Modifier.weight(1f)) { surChiffre('0') }
            Touche("⌫", Modifier.weight(1f), fond = Color.Transparent, surClic = surEffacer)
        }
    }
}

@Composable
private fun Touche(texte: String, modifier: Modifier, fond: Color = Color(0x1AFFFFFF), surClic: () -> Unit) {
    Box(modifier.height(64.dp).clip(RoundedCornerShape(18.dp)).background(fond).clickable(onClick = surClic), contentAlignment = Alignment.Center) {
        Text(texte, color = Color.White, fontSize = 24.sp, fontWeight = FontWeight.Bold)
    }
}

@Composable
private fun PointsPin(longueur: Int) {
    Row(horizontalArrangement = Arrangement.spacedBy(18.dp)) {
        repeat(4) { i ->
            Box(Modifier.size(16.dp).clip(CircleShape).background(if (i < longueur) Or else Color(0x337FB3A8)))
        }
    }
}

/** Apres la premiere connexion : le recolteur choisit le PIN de ce telephone. */
@Composable
fun EcranCreerPin(depot: Depot, empreinteDisponible: Boolean, auDeverrouillage: () -> Unit) {
    var premier by remember { mutableStateOf("") }
    var saisie by remember { mutableStateOf("") }
    var erreur by remember { mutableStateOf<String?>(null) }
    Column(Modifier.fillMaxSize().background(VertFonce).padding(28.dp), horizontalAlignment = Alignment.CenterHorizontally) {
        Spacer(Modifier.height(48.dp))
        Text(if (premier.isEmpty()) "Choisissez votre PIN" else "Retapez votre PIN", color = Color.White, fontSize = 22.sp, fontWeight = FontWeight.ExtraBold)
        Spacer(Modifier.height(8.dp))
        Text("4 chiffres. Il ouvre l'application sur ce téléphone.", color = Color(0xFFB9DAD2), fontSize = 14.sp, textAlign = TextAlign.Center)
        Spacer(Modifier.height(32.dp))
        PointsPin(saisie.length)
        Spacer(Modifier.height(12.dp))
        Text(erreur ?: "", color = Or, fontSize = 13.sp, fontWeight = FontWeight.Bold)
        Spacer(Modifier.weight(1f))
        PaveNumerique(
            surChiffre = { c ->
                if (saisie.length < 4) saisie = saisie + c
                if (saisie.length == 4) {
                    when {
                        premier.isEmpty() && (saisie.toSet().size == 1 || saisie in listOf("1234", "0123", "4321")) -> { erreur = "Ce PIN est trop facile à deviner."; saisie = "" }
                        premier.isEmpty() -> { premier = saisie; saisie = ""; erreur = null }
                        premier == saisie -> { depot.definirPin(saisie); if (empreinteDisponible) depot.activerEmpreinte(true); auDeverrouillage() }
                        else -> { erreur = "Les deux PIN ne sont pas identiques."; premier = ""; saisie = "" }
                    }
                }
            },
            surEffacer = { saisie = saisie.dropLast(1) },
        )
    }
}

/** Ouverture quotidienne : PIN ou empreinte, sans reseau. */
@Composable
fun EcranPin(depot: Depot, empreinteDisponible: Boolean, demanderEmpreinte: () -> Unit, auDeverrouillage: () -> Unit) {
    var saisie by remember { mutableStateOf("") }
    var erreur by remember { mutableStateOf<String?>(null) }
    val etat = depot.etat
    val empreinte = empreinteDisponible && depot.empreinteActive
    LaunchedEffect(Unit) { if (empreinte) demanderEmpreinte() }
    Column(Modifier.fillMaxSize().background(VertFonce).padding(28.dp), horizontalAlignment = Alignment.CenterHorizontally) {
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
            Text("Mosolia", color = Color.White, fontSize = 18.sp, fontWeight = FontWeight.ExtraBold)
            if (etat.aEnvoyer > 0) Pastille("${etat.aEnvoyer} à envoyer", Color(0x33F2B544), Or)
        }
        Spacer(Modifier.height(40.dp))
        Avatar(depot.nomUtilisateur, 76.dp, Color(0xFF1C6B5E), Color.White)
        Spacer(Modifier.height(14.dp))
        Text("Bonjour, ${depot.nomUtilisateur.split(" ").first().lowercase().replaceFirstChar { it.uppercase() }}", color = Color.White, fontSize = 22.sp, fontWeight = FontWeight.ExtraBold)
        Text("${depot.identifiantConnecte} · ${depot.nomOrganisation}", color = Color(0xFFB9DAD2), fontSize = 14.sp, textAlign = TextAlign.Center)
        Spacer(Modifier.height(32.dp))
        Text("Entrez votre PIN", color = Color(0xFFDDEFEA), fontSize = 14.sp, fontWeight = FontWeight.SemiBold)
        Spacer(Modifier.height(16.dp))
        PointsPin(saisie.length)
        Spacer(Modifier.height(12.dp))
        Text(erreur ?: "", color = Or, fontSize = 13.sp, fontWeight = FontWeight.Bold, textAlign = TextAlign.Center)
        Spacer(Modifier.weight(1f))
        PaveNumerique(
            surChiffre = { c ->
                if (saisie.length < 4) saisie = saisie + c
                if (saisie.length == 4) {
                    if (depot.verifierPin(saisie)) {
                        auDeverrouillage()
                    } else {
                        saisie = ""
                        val restants = depot.essaisRestants()
                        if (restants <= 0) {
                            depot.oublierPin()   // l'ecran de connexion revient ; les saisies restent dans le telephone
                        } else {
                            erreur = "PIN incorrect. Encore $restants essai(s)."
                        }
                    }
                }
            },
            surEffacer = { saisie = saisie.dropLast(1) },
            boutonGauche = if (empreinte) ({
                Box(Modifier.fillMaxSize().clip(RoundedCornerShape(18.dp)).background(Or).clickable(onClick = demanderEmpreinte), contentAlignment = Alignment.Center) {
                    Text("Empreinte", color = Encre, fontSize = 13.sp, fontWeight = FontWeight.ExtraBold)
                }
            }) else null,
        )
        TextButton(onClick = { depot.oublierPin() }) { Text("PIN oublié ? Se reconnecter avec le mot de passe", color = Color(0xFFB9DAD2), fontSize = 13.sp) }
    }
}
