package com.avecxy.recolteur.ecrans

import androidx.activity.compose.BackHandler
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import com.avecxy.recolteur.donnees.Depot

/** Ouvre l'historique du serveur avec la session du collecteur deja connecte. */
@Composable
fun EcranCommissions(depot: Depot, navigation: Navigation) {
    var chemin by remember { mutableStateOf<String?>(null) }
    var erreur by remember { mutableStateOf<String?>(null) }
    var essai by remember { mutableStateOf(0) }
    BackHandler { navigation.retour() }
    LaunchedEffect(essai) {
        erreur = null
        depot.passage("/mes-commissions")
            .onSuccess { chemin = it }
            .onFailure { erreur = it.message ?: "Ouverture impossible. Réessayez." }
    }
    val barre: @Composable () -> Unit = {
        BarreWeb("Historique de commissions", depot.nomUtilisateur, "Retour", { navigation.retour() }, null)
    }
    if (chemin != null) {
        EcranWeb(depot, chemin!!, listOf("/connexion"), { navigation.retour() }, barre) { lien ->
            if (lien.startsWith("/application/tournee")) navigation.retour()
        }
    } else {
        Column(Modifier.fillMaxSize().background(Fond)) {
            barre()
            Column(
                Modifier.fillMaxSize().padding(28.dp),
                verticalArrangement = Arrangement.Center,
                horizontalAlignment = Alignment.CenterHorizontally,
            ) {
                Text(erreur ?: "Ouverture de vos commissions…", color = Gris, textAlign = TextAlign.Center)
                if (erreur != null) {
                    Spacer(Modifier.height(20.dp))
                    GrandBouton("Réessayer", couleur = Vert, encre = Color.White) { essai++ }
                }
            }
        }
    }
}
