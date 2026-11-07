package com.avecxy.recolteur.ecrans

import androidx.activity.compose.BackHandler
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.avecxy.recolteur.donnees.Commission
import com.avecxy.recolteur.donnees.Depot
import java.text.SimpleDateFormat
import java.util.Locale

/** Ecran natif : donnees JSON authentifiees, aucune page web ni navigateur. */
@Composable
fun EcranCommissions(depot: Depot, navigation: Navigation) {
    var lignes by remember { mutableStateOf<List<Commission>>(emptyList()) }
    var erreur by remember { mutableStateOf<String?>(null) }
    var chargement by remember { mutableStateOf(true) }
    var essai by remember { mutableStateOf(0) }
    BackHandler { navigation.retour() }
    LaunchedEffect(essai) {
        chargement = true
        erreur = null
        depot.commissions().onSuccess { lignes = it }.onFailure { erreur = it.message }
        chargement = false
    }
    Column(Modifier.fillMaxSize()) {
        Entete("Historique de commissions", "Vos commissions des mois passés", { navigation.retour() })
        LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            item {
                TextButton(onClick = { essai++ }, enabled = !chargement) { Text("Actualiser", color = Vert) }
            }
            when {
                chargement -> item { CircularProgressIndicator(color = Vert) }
                erreur != null -> item {
                    Carte(Modifier.fillMaxWidth(), couleur = RougeFond) {
                        Text(erreur!!, color = Rouge)
                        TextButton(onClick = { essai++ }) { Text("Réessayer", color = Vert) }
                    }
                }
                lignes.isEmpty() -> item { Text("Aucune commission pour les mois passés.", color = Gris) }
                else -> items(lignes, key = { it.mois }) { l ->
                    Carte(Modifier.fillMaxWidth()) {
                        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                            Text(moisCommission(l.mois), fontSize = 16.sp, fontWeight = FontWeight.ExtraBold)
                            Pastille(if (l.paye) "Payé" else "Non payé", if (l.paye) VertPastille else OrFond, if (l.paye) Vert else OrEncre)
                        }
                        Ligne("Commission", argent(l.part, depot.etat))
                        if (l.paye) {
                            Ligne("Manquants déduits", argent(l.deductions ?: 0L, depot.etat))
                            Ligne("Montant versé", argent(l.verse ?: 0L, depot.etat))
                            l.datePaiement?.let { Ligne("Date de paiement", dateCommission(it)) }
                        }
                    }
                }
            }
        }
    }
}

private fun moisCommission(mois: String): String = try {
    SimpleDateFormat("MMMM yyyy", Locale.FRENCH).format(SimpleDateFormat("yyyy-MM", Locale.US).parse(mois)!!)
} catch (e: Exception) { mois }

private fun dateCommission(date: String): String = try {
    SimpleDateFormat("dd/MM/yyyy", Locale.FRENCH).format(SimpleDateFormat("yyyy-MM-dd", Locale.US).parse(date)!!)
} catch (e: Exception) { date }