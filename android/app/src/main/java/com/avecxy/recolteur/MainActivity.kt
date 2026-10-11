package com.avecxy.recolteur

import android.content.Context
import android.net.ConnectivityManager
import android.net.Network
import android.os.Build
import android.os.Bundle
import androidx.activity.compose.BackHandler
import androidx.activity.compose.setContent
import androidx.biometric.BiometricManager
import androidx.biometric.BiometricPrompt
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.core.content.ContextCompat
import androidx.fragment.app.FragmentActivity
import androidx.lifecycle.lifecycleScope
import com.avecxy.recolteur.donnees.Depot
import com.avecxy.recolteur.ecrans.Application
import com.avecxy.recolteur.ecrans.Ecran
import com.avecxy.recolteur.ecrans.ThemeMosolia
import kotlinx.coroutines.launch

class MainActivity : FragmentActivity() {
    private lateinit var depot: Depot
    /** Le PIN (ou l'empreinte) est redemande a chaque retour dans l'application. */
    private var deverrouille by mutableStateOf(false)
    private val pile = mutableStateListOf<Ecran>(Ecran.Accueil)

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        depot = Depot(this)
        surveillerLeReseau()
        setContent {
            ThemeMosolia {
                BackHandler(enabled = deverrouille && pile.size > 1 && !depot.estClient && depot.mode == "TOURNEE") { pile.removeAt(pile.lastIndex) }
                Application(
                    depot = depot, deverrouille = deverrouille, pile = pile,
                    auDeverrouillage = { deverrouille = true; synchroniser(silencieux = true) },
                    empreinteDisponible = empreinteDisponible(),
                    demanderEmpreinte = { demanderEmpreinte() },
                    synchroniser = { synchroniser(silencieux = false) },
                    lancer = { action -> lifecycleScope.launch { action() } },
                )
            }
        }
    }

    override fun onStop() {
        super.onStop()
        if (!isChangingConfigurations) deverrouille = false
    }

    private fun synchroniser(silencieux: Boolean) {
        if (depot.estConnecte) lifecycleScope.launch { depot.synchroniser(silencieux) }
    }

    /** Des que le reseau revient, les saisies en attente partent toutes seules. */
    private fun surveillerLeReseau() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.N) return
        val reseau = getSystemService(Context.CONNECTIVITY_SERVICE) as ConnectivityManager
        reseau.registerDefaultNetworkCallback(object : ConnectivityManager.NetworkCallback() {
            override fun onAvailable(network: Network) {
                if (depot.etat.aEnvoyer > 0) synchroniser(silencieux = true)
            }
        })
    }

    private fun empreinteDisponible(): Boolean =
        BiometricManager.from(this).canAuthenticate(BiometricManager.Authenticators.BIOMETRIC_WEAK) == BiometricManager.BIOMETRIC_SUCCESS

    private fun demanderEmpreinte() {
        val invite = BiometricPrompt(this, ContextCompat.getMainExecutor(this), object : BiometricPrompt.AuthenticationCallback() {
            override fun onAuthenticationSucceeded(result: BiometricPrompt.AuthenticationResult) {
                deverrouille = true
                synchroniser(silencieux = true)
            }
        })
        invite.authenticate(
            BiometricPrompt.PromptInfo.Builder().setTitle("Mosolia").setSubtitle("Posez votre doigt pour ouvrir l'application")
                .setNegativeButtonText("Utiliser le PIN").setAllowedAuthenticators(BiometricManager.Authenticators.BIOMETRIC_WEAK).build()
        )
    }
}
