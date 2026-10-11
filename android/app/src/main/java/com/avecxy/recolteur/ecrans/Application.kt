package com.avecxy.recolteur.ecrans

import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.List
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material3.Icon
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.NavigationBarItemDefaults
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.remember
import androidx.compose.runtime.snapshots.SnapshotStateList
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.sp
import com.avecxy.recolteur.donnees.Depot

/** Les ecrans de l'application. Ceux qui portent un identifiant concernent un client, un carnet ou un credit. */
sealed class Ecran {
    object Accueil : Ecran()
    object Clients : Ecran()
    object Saisies : Ecran()
    object Synchro : Ecran()
    object Masse : Ecran()
    object NouveauClient : Ecran()
    object Notifications : Ecran()
    data class Client(val id: Long) : Ecran()
    data class Cotisation(val carnetId: Long) : Ecran()
    data class Remboursement(val creditId: Long) : Ecran()
    data class Demande(val clientId: Long) : Ecran()
    data class Carnet(val clientId: Long) : Ecran()
    /** Choisir d'abord un client, puis faire l'action (cotisation, remboursement, demande). */
    data class Choisir(val action: String) : Ecran()
}

class Navigation(private val pile: SnapshotStateList<Ecran>) {
    fun ouvrir(ecran: Ecran) { pile.add(ecran) }
    fun retour() { if (pile.size > 1) pile.removeAt(pile.lastIndex) }
    fun onglet(ecran: Ecran) { pile.clear(); pile.add(ecran) }
    /** Apres une saisie : retour a l'ecran d'ou l'on venait. */
    fun terminer() { retour() }
}

@Composable
fun Application(
    depot: Depot, deverrouille: Boolean, pile: SnapshotStateList<Ecran>, auDeverrouillage: () -> Unit, empreinteDisponible: Boolean,
    demanderEmpreinte: () -> Unit, synchroniser: () -> Unit, lancer: (suspend () -> Unit) -> Unit,
) {
    val navigation = remember { Navigation(pile) }
    val etat = depot.etat
    when {
        // client : son espace, dans l'application (il a tape son code secret ; pas de PIN du telephone)
        depot.estClient -> EcranEspaceClient(depot)
        !depot.estConnecte || depot.reconnexionRequise || depot.identifiantConnecte.isEmpty() || !depot.aUnPin ->
            EcranConnexion(depot, empreinteDisponible, auDeverrouillage, lancer)
        !deverrouille -> EcranPin(depot, empreinteDisponible, demanderEmpreinte, auDeverrouillage)
        // gestion : le site, en ligne, avec seulement les pages permises par ses droits
        depot.mode == "GESTION" -> EcranGestion(depot, lancer)
        else -> {
            val bulles = remember { SnackbarHostState() }
            LaunchedEffect(depot.message) {
                depot.message?.let { bulles.showSnackbar(it); depot.message = null }
            }
            val courant = pile.last()
            val principal = courant is Ecran.Accueil || courant is Ecran.Clients || courant is Ecran.Saisies || courant is Ecran.Synchro
            Scaffold(
                containerColor = Fond,
                snackbarHost = { SnackbarHost(bulles) },
                bottomBar = {
                    if (principal) {
                        NavigationBar(containerColor = Color.White) {
                            listOf(Ecran.Accueil to ("Accueil" to Icons.Filled.Home), Ecran.Clients to ("Clients" to Icons.Filled.Person),
                                Ecran.Saisies to ("Saisies" to Icons.Filled.List), Ecran.Synchro to ("Synchro" to Icons.Filled.Refresh)).forEach { (ecran, libelle) ->
                                NavigationBarItem(
                                    selected = courant == ecran, onClick = { navigation.onglet(ecran) },
                                    icon = { Icon(libelle.second, contentDescription = null) },
                                    label = { Text(libelle.first, fontSize = 11.sp, fontWeight = FontWeight.Bold) },
                                    colors = NavigationBarItemDefaults.colors(selectedIconColor = Vert, selectedTextColor = Vert, indicatorColor = VertClair, unselectedIconColor = Gris, unselectedTextColor = Gris),
                                )
                            }
                        }
                    }
                },
            ) { marges ->
                Box(Modifier.fillMaxSize().padding(marges)) {
                    when (courant) {
                        is Ecran.Accueil -> EcranAccueil(depot, navigation, synchroniser)
                        is Ecran.Clients -> EcranClients(depot, navigation, null)
                        is Ecran.Choisir -> EcranClients(depot, navigation, courant.action)
                        is Ecran.Client -> EcranClient(depot, navigation, courant.id)
                        is Ecran.Cotisation -> EcranCotisation(depot, navigation, courant.carnetId)
                        is Ecran.Masse -> EcranMasse(depot, navigation)
                        is Ecran.Remboursement -> EcranRemboursement(depot, navigation, courant.creditId)
                        is Ecran.Demande -> EcranDemande(depot, navigation, courant.clientId)
                        is Ecran.NouveauClient -> EcranNouveauClient(depot, navigation, null)
                        is Ecran.Carnet -> EcranNouveauClient(depot, navigation, courant.clientId)
                        is Ecran.Saisies -> EcranSaisies(depot)
                        is Ecran.Synchro -> EcranSynchro(depot, synchroniser, lancer)
                        is Ecran.Notifications -> EcranNotifications(depot, navigation, lancer)
                    }
                }
            }
        }
    }
}
