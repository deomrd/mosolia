package com.avecxy.recolteur.ecrans

import android.annotation.SuppressLint
import android.content.ActivityNotFoundException
import android.content.Intent
import android.graphics.Bitmap
import android.net.Uri
import android.os.Build
import android.webkit.CookieManager
import android.webkit.WebChromeClient
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.Toast
import androidx.activity.compose.BackHandler
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
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.LinearProgressIndicator
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.viewinterop.AndroidView
import com.avecxy.recolteur.BuildConfig
import com.avecxy.recolteur.donnees.Depot

/** Marque ajoutee a l'agent du navigateur : le serveur cache alors ce qui ne sert que sur ordinateur (fichiers, impression). */
const val MARQUE_APPLICATION = "MosoliaApp"

/** Efface la session du site gardee dans l'application (deconnexion du telephone). */
fun oublierSessionWeb() {
    val cookies = CookieManager.getInstance()
    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
        cookies.removeAllCookies(null)
        cookies.flush()
    }
}

/**
 * Le site dans l'application, pour la gestion et pour l'espace client. Il faut internet.
 * @param premiereAdresse chemin a ouvrir d'abord (un passage "/passage/..." ou une page)
 * @param pagesDeSortie chemins qui signifient « la session du site est finie » (page de connexion)
 * @param surSortie appele quand le site revient a sa page de connexion
 * @param barre contenu facultatif au-dessus du site
 * @param surLienApplication liens /application/... du site (ex. /application/tournee) : actions de l'application
 */
@SuppressLint("SetJavaScriptEnabled")
@Composable
fun EcranWeb(
    depot: Depot,
    premiereAdresse: String,
    pagesDeSortie: List<String>,
    surSortie: () -> Unit,
    barre: (@Composable () -> Unit)? = null,
    surLienApplication: (String) -> Unit = {},
) {
    val contexte = LocalContext.current
    val base = depot.adresse.trimEnd('/')
    val hote = remember(base) { Uri.parse(base).host ?: "" }
    var chargement by remember { mutableStateOf(0) }
    var horsLigne by remember { mutableStateOf(false) }
    var vue by remember { mutableStateOf<WebView?>(null) }
    var peutRevenir by remember { mutableStateOf(false) }

    fun ouvrirAilleurs(uri: Uri): Boolean {
        return try {
            val intention = if (uri.scheme == "intent") Intent.parseUri(uri.toString(), Intent.URI_INTENT_SCHEME) else Intent(Intent.ACTION_VIEW, uri)
            contexte.startActivity(intention.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK))
            true
        } catch (e: ActivityNotFoundException) {
            Toast.makeText(contexte, "Aucune application pour ouvrir ce lien.", Toast.LENGTH_SHORT).show()
            true
        } catch (e: Exception) {
            true
        }
    }

    /** true = le lien ne s'ouvre pas dans l'application (telephone, WhatsApp, autre site...). */
    fun detourner(uri: Uri): Boolean {
        val schema = uri.scheme ?: return false
        if ((schema == "https" || schema == "http") && uri.host == hote) {
            val chemin = uri.path ?: ""
            if (chemin.startsWith("/application/")) { surLienApplication(chemin); return true }
            return false
        }
        return ouvrirAilleurs(uri)
    }

    BackHandler(enabled = peutRevenir) { vue?.goBack() }

    Column(Modifier.fillMaxSize().background(Fond)) {
        barre?.invoke()
        Box(Modifier.fillMaxSize()) {
            AndroidView(
                modifier = Modifier.fillMaxSize(),
                factory = { ctx ->
                    WebView(ctx).apply {
                        settings.javaScriptEnabled = true
                        settings.domStorageEnabled = true
                        settings.loadWithOverviewMode = true
                        settings.useWideViewPort = true
                        settings.setSupportZoom(false)
                        settings.userAgentString = settings.userAgentString + " $MARQUE_APPLICATION/" + BuildConfig.VERSION_NAME
                        CookieManager.getInstance().setAcceptCookie(true)
                        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) CookieManager.getInstance().setAcceptThirdPartyCookies(this, false)
                        webChromeClient = object : WebChromeClient() {
                            override fun onProgressChanged(view: WebView, progres: Int) { chargement = progres }
                        }
                        webViewClient = object : WebViewClient() {
                            override fun shouldOverrideUrlLoading(view: WebView, request: WebResourceRequest): Boolean = detourner(request.url)

                            @Deprecated("Android 6 et plus ancien")
                            override fun shouldOverrideUrlLoading(view: WebView, url: String): Boolean = detourner(Uri.parse(url))

                            override fun onPageStarted(view: WebView, url: String, favicon: Bitmap?) {
                                horsLigne = false
                                val chemin = Uri.parse(url).path?.trimEnd('/') ?: ""
                                if (Uri.parse(url).host == hote && chemin in pagesDeSortie) {
                                    view.stopLoading()
                                    surSortie()
                                }
                            }

                            override fun onPageFinished(view: WebView, url: String) {
                                peutRevenir = view.canGoBack()
                                CookieManager.getInstance().flush()
                            }

                            override fun onReceivedError(view: WebView, request: WebResourceRequest, error: WebResourceError) {
                                if (request.isForMainFrame) horsLigne = true
                            }

                            @Deprecated("Android 5")
                            override fun onReceivedError(view: WebView, code: Int, description: String?, url: String?) {
                                horsLigne = true
                            }
                        }
                        // pas de fichiers sur le telephone : exports et modeles se font sur ordinateur
                        setDownloadListener { _, _, _, _, _ ->
                            Toast.makeText(ctx, "Ce fichier est disponible sur ordinateur, depuis le navigateur.", Toast.LENGTH_LONG).show()
                        }
                        loadUrl(base + premiereAdresse)
                        vue = this
                    }
                },
            )
            // barre de chargement par-dessus la page (ne decale pas la mise en page du site)
            if (chargement in 1..99) LinearProgressIndicator(progress = chargement / 100f, modifier = Modifier.fillMaxWidth().height(3.dp).align(Alignment.TopCenter), color = Or, trackColor = Color.Transparent)
            if (horsLigne) {
                Column(
                    Modifier.fillMaxSize().background(Fond).padding(28.dp),
                    verticalArrangement = Arrangement.Center, horizontalAlignment = Alignment.CenterHorizontally,
                ) {
                    Text("Pas de connexion", fontSize = 22.sp, fontWeight = FontWeight.ExtraBold)
                    Spacer(Modifier.height(8.dp))
                    Text("Cette partie de l'application a besoin d'internet. Vérifiez les données mobiles ou le wifi.", color = Gris, fontSize = 14.sp, textAlign = TextAlign.Center)
                    Spacer(Modifier.height(20.dp))
                    GrandBouton("Réessayer", couleur = Vert, encre = Color.White) { horsLigne = false; vue?.reload() }
                }
            }
        }
    }
    DisposableEffect(Unit) {
        onDispose { vue?.destroy(); vue = null }
    }
}

/** Barre du haut dans le site : qui est connecte, et la bascule vers la tournee pour qui fait les deux. */
@Composable
fun BarreWeb(titre: String, sousTitre: String, bascule: String?, surBascule: () -> Unit, surQuitter: (() -> Unit)?) {
    Row(
        Modifier.fillMaxWidth().background(VertFonce).padding(horizontal = 16.dp, vertical = 10.dp),
        horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(Modifier.weight(1f)) {
            Text(titre, color = Color.White, fontSize = 16.sp, fontWeight = FontWeight.ExtraBold, maxLines = 1)
            if (sousTitre.isNotEmpty()) Text(sousTitre, color = Color(0xFFB9DAD2), fontSize = 12.sp, maxLines = 1)
        }
        if (bascule != null) {
            Text(
                bascule, color = VertFonce, fontSize = 13.sp, fontWeight = FontWeight.ExtraBold,
                modifier = Modifier.clip(RoundedCornerShape(10.dp)).background(Or).clickable(onClick = surBascule).padding(horizontal = 14.dp, vertical = 9.dp),
            )
        }
        if (surQuitter != null) TextButton(onClick = surQuitter) { Text("Quitter", color = Color(0xFFDDEFEA), fontWeight = FontWeight.Bold, fontSize = 13.sp) }
    }
}

/**
 * La gestion (le site, en ligne) : on demande d'abord au serveur un passage pour ne pas redemander le mot de passe,
 * puis le site s'ouvre dans l'application.
 */
@Composable
fun EcranGestion(depot: Depot, lancer: (suspend () -> Unit) -> Unit) {
    var chemin by remember { mutableStateOf<String?>(null) }
    var erreur by remember { mutableStateOf<String?>(null) }
    var essai by remember { mutableStateOf(0) }
    var sorti by remember { mutableStateOf(false) }
    val lesDeux = depot.profil == "LES_DEUX"
    LaunchedEffect(essai) {
        if (sorti) return@LaunchedEffect
        chemin = null
        erreur = null
        depot.passage("/").onSuccess { chemin = it }.onFailure { erreur = it.message }
    }
    val barre: @Composable () -> Unit = {
        BarreWeb(
            depot.nomOrganisation.ifEmpty { "Mosolia" }, depot.nomUtilisateur,
            if (lesDeux) "Ma tournée" else null, { depot.basculer("TOURNEE") }, null,
        )
    }
    when {
        sorti -> Column(Modifier.fillMaxSize().background(Fond)) {
            barre()
            Column(Modifier.fillMaxSize().padding(28.dp), verticalArrangement = Arrangement.Center, horizontalAlignment = Alignment.CenterHorizontally) {
                Text("Vous êtes sorti de la gestion", fontSize = 20.sp, fontWeight = FontWeight.ExtraBold, textAlign = TextAlign.Center)
                Spacer(Modifier.height(20.dp))
                GrandBouton("Rouvrir la gestion", couleur = Vert, encre = Color.White) { sorti = false; essai++ }
                Spacer(Modifier.height(8.dp))
                TextButton(onClick = { lancer { if (depot.deconnecter() == null) oublierSessionWeb() else depot.message = "Synchronisez d'abord vos saisies." } }) {
                    Text("Se déconnecter de ce téléphone", color = Rouge, fontWeight = FontWeight.Bold)
                }
            }
        }
        // le site a deja son en-tete (organisation, menu, notifications) : pas de barre en plus ;
        // « Ma tournee » est un lien du menu du site, intercepte ici
        chemin != null -> EcranWeb(depot, chemin!!, listOf("/connexion"), { sorti = true }, null) { lien ->
            if (lien.startsWith("/application/tournee")) depot.basculer("TOURNEE")
        }
        else -> Column(Modifier.fillMaxSize().background(Fond)) {
            barre()
            Column(Modifier.fillMaxSize().padding(28.dp), verticalArrangement = Arrangement.Center, horizontalAlignment = Alignment.CenterHorizontally) {
                if (erreur == null) {
                    Text("Ouverture de la gestion…", fontSize = 16.sp, fontWeight = FontWeight.Bold, color = Gris)
                } else {
                    Text(erreur!!, fontSize = 15.sp, fontWeight = FontWeight.Bold, textAlign = TextAlign.Center)
                    Spacer(Modifier.height(20.dp))
                    GrandBouton("Réessayer", couleur = Vert, encre = Color.White) { essai++ }
                    if (lesDeux) {
                        Spacer(Modifier.height(8.dp))
                        TextButton(onClick = { depot.basculer("TOURNEE") }) { Text("Revenir à ma tournée (hors ligne)", color = Vert, fontWeight = FontWeight.Bold) }
                    }
                }
            }
        }
    }
}

/** L'espace client dans l'application : ses carnets, ses credits, ses notifications. */
@Composable
fun EcranEspaceClient(depot: Depot) {
    // premiere ouverture : le passage recu a la connexion ; ensuite la session gardee par l'application
    val depart = remember { depot.passageEnAttente ?: "/espace-client" }
    LaunchedEffect(Unit) { depot.passageEnAttente = null }
    EcranWeb(
        depot, depart, listOf("/espace-client/connexion", "/connexion"),
        surSortie = { oublierSessionWeb(); depot.quitterEspaceClient() },
    )
}
