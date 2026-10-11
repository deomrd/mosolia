package com.avecxy.recolteur.donnees

import android.content.Context
import android.os.Build
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject
import java.io.IOException
import java.security.SecureRandom
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale
import java.util.UUID
import javax.crypto.SecretKeyFactory
import javax.crypto.spec.PBEKeySpec

/**
 * Le coeur de l'application : il garde les saisies dans SQLite, construit ce que les ecrans affichent
 * (dernier portefeuille recu + saisies encore dans le telephone) et parle au serveur quand il y a du reseau.
 */
class Depot(contexte: Context) {
    private val base = Base(contexte.applicationContext)
    private val verrou = Mutex()

    var etat by mutableStateOf(Etat())
        private set
    var synchronisation by mutableStateOf(false)
        private set
    /** Message de la derniere synchronisation (succes ou raison de l'echec), a montrer une fois. */
    var message by mutableStateOf<String?>(null)
    /** Le serveur ne reconnait plus ce telephone (desactive, ou jeton expire) : il faut se reconnecter. */
    var reconnexionRequise by mutableStateOf(false)
        private set

    /** Augmente a chaque changement des reglages (jeton, PIN) pour que les ecrans se mettent a jour. */
    private var version by mutableStateOf(0)

    /** RECOLTEUR (tournee hors ligne), GESTION (le site, en ligne), LES_DEUX, ou CLIENT (espace client). */
    val profil: String get() = (if (version >= 0) base.lireMeta("profil") else null) ?: "RECOLTEUR"
    val estClient: Boolean get() = profil == "CLIENT"
    /** Fait la tournee (hors ligne) : il lui faut le portefeuille. */
    val faitLaTournee: Boolean get() = profil == "RECOLTEUR" || profil == "LES_DEUX"
    /** Pour un profil LES_DEUX : TOURNEE ou GESTION, au choix de la personne. */
    val mode: String get() = when (profil) {
        "GESTION" -> "GESTION"
        "LES_DEUX" -> (if (version >= 0) base.lireMeta("mode") else null) ?: "TOURNEE"
        else -> "TOURNEE"
    }
    val estConnecte: Boolean get() = version >= 0 && base.lireMeta("jeton") != null && (!faitLaTournee || base.lireInstantane() != null)
    val aUnPin: Boolean get() = version >= 0 && base.lireMeta("pin") != null
    /** Le secret qui ouvre l'application est le PIN du compte (sinon son mot de passe). */
    val secretEstPin: Boolean get() = version >= 0 && base.lireMeta("pin_type") == "PIN"
    val longueurPin: Int get() = base.lireMeta("pin_longueur")?.toIntOrNull() ?: 4
    /** Adresse integree a l'application, ou celle que la plateforme a donnee depuis. */
    val adresse: String get() = base.lireMeta("adresse") ?: com.avecxy.recolteur.BuildConfig.ADRESSE_SERVEUR
    /** Dernier identifiant (ou code client) tape, propose a la connexion suivante. */
    val dernierIdentifiant: String get() = base.lireMeta("dernier_identifiant") ?: ""
    val empreinteActive: Boolean get() = base.lireMeta("empreinte") == "1"

    init {
        recharger()
    }

    // ------------------------------------------------------------------
    // Connexion, PIN
    // ------------------------------------------------------------------

    /**
     * Un seul ecran de connexion pour tout le monde. Le serveur reconnait un code client (espace client)
     * ou un compte du personnel (recolteur, gestion, ou les deux).
     * @return null si tout va bien, sinon le message a afficher
     */
    suspend fun connecter(identifiant: String, motDePasse: String): String? = withContext(Dispatchers.IO) {
        try {
            val appareil = base.lireMeta("appareil") ?: UUID.randomUUID().toString().also { base.ecrireMeta("appareil", it) }
            val r = Api(adresse, null).connexion(identifiant.trim(), motDePasse, appareil, "${Build.MANUFACTURER} ${Build.MODEL}".trim())
            suivreAdresse(r.texte("adresse"))
            base.ecrireMeta("dernier_identifiant", identifiant.trim())
            val profil = r.optString("profil", "RECOLTEUR")
            if (profil == "CLIENT") {
                // l'espace client s'ouvre dans l'application ; aucune donnee n'est gardee hors ligne
                if (base.saisies().any { it.statut == "A_ENVOYER" }) {
                    return@withContext "Ce téléphone contient des saisies non envoyées d'un récolteur. Reconnectez ce compte et synchronisez d'abord."
                }
                base.ecrireMeta("jeton", null)
                base.ecrireMeta("profil", "CLIENT")
                withContext(Dispatchers.Main) { passageEnAttente = "/passage/" + r.getString("passage"); reconnexionRequise = false; recharger(); version++ }
                return@withContext null
            }
            // un autre compte que le precedent : les saisies du compte precedent ne doivent pas partir sous ce nom
            val nouveau = r.getJSONObject("utilisateur").optString("identifiant")
            if (base.lireMeta("identifiant") != null && base.lireMeta("identifiant") != nouveau && base.saisies().any { it.statut == "A_ENVOYER" }) {
                return@withContext "Ce téléphone contient des saisies non envoyées d'un autre compte. Reconnectez ce compte et synchronisez d'abord."
            }
            base.ecrireMeta("jeton", r.getString("jeton"))
            base.ecrireMeta("identifiant", nouveau)
            // l'application s'ouvre ensuite avec le meme secret que la connexion (PIN ou mot de passe), meme hors ligne
            definirSecretLocal(motDePasse)
            base.ecrireMeta("profil", profil)
            base.ecrireMeta("nom", r.getJSONObject("utilisateur").optString("nom"))
            base.ecrireMeta("organisation", r.optJSONObject("organisation")?.optString("nom") ?: "")
            if (!r.isNull("portefeuille")) {
                base.ecrireInstantane(r.getJSONObject("portefeuille").toString())
                base.ecrireMeta("synchro", System.currentTimeMillis().toString())
            }
            withContext(Dispatchers.Main) { reconnexionRequise = false; recharger(); version++ }
            null
        } catch (e: ErreurApi) {
            e.message
        } catch (e: IOException) {
            "Pas de connexion au serveur. Vérifiez le réseau (données mobiles ou wifi)."
        } catch (e: Exception) {
            "Connexion impossible : ${e.message}"
        }
    }

    /** Premiere page a ouvrir dans l'application apres une connexion client (passage a usage unique). */
    var passageEnAttente by mutableStateOf<String?>(null)

    /** Nom et organisation de la personne connectee (aussi pour la gestion, qui n'a pas de portefeuille). */
    val nomUtilisateur: String get() = etat.utilisateurNom.ifEmpty { base.lireMeta("nom") ?: "" }
    val nomOrganisation: String get() = etat.organisation.ifEmpty { base.lireMeta("organisation") ?: "" }
    val identifiantConnecte: String get() = etat.identifiant.ifEmpty { base.lireMeta("identifiant") ?: "" }

    /** La plateforme peut deplacer le serveur : l'application suit la nouvelle adresse (https seulement). */
    private fun suivreAdresse(nouvelle: String?) {
        val a = nouvelle?.trim()?.trimEnd('/') ?: return
        if (a.startsWith("https://") && a.length > 10 && a != adresse) base.ecrireMeta("adresse", a)
    }

    /** Ma tournee (hors ligne) ou Gestion (le site), pour qui fait les deux. */
    fun basculer(mode: String) {
        if (profil != "LES_DEUX") return
        base.ecrireMeta("mode", mode)
        version++
        if (mode == "TOURNEE") recharger()
    }

    /**
     * Demande au serveur un passage vers une page du site, pour l'ouvrir dans l'application.
     * @return le chemin a ouvrir ("/passage/...") ou une erreur
     */
    suspend fun passage(cible: String = "/"): Result<String> = withContext(Dispatchers.IO) {
        val jeton = base.lireMeta("jeton") ?: return@withContext Result.failure(Exception("Reconnectez-vous."))
        try {
            val r = Api(adresse, jeton).passage(cible)
            val nouveau = r.optString("profil")
            if (nouveau.isNotEmpty() && nouveau != profil) withContext(Dispatchers.Main) { changerProfil(nouveau) }
            Result.success(r.getString("chemin"))
        } catch (e: ErreurApi) {
            if (e.codeHttp == 401) withContext(Dispatchers.Main) { reconnexionRequise = true }
            Result.failure(Exception(e.message))
        } catch (e: IOException) {
            Result.failure(Exception("Pas de réseau. La gestion a besoin d'internet."))
        } catch (e: Exception) {
            Result.failure(Exception("Le serveur ne répond pas correctement."))
        }
    }

    /** Les droits ont change sur le serveur (plus de recolteur, ou un recolteur en plus). */
    private fun changerProfil(nouveau: String) {
        base.ecrireMeta("profil", nouveau)
        version++
    }

    /** Fin de l'espace client dans l'application : on revient a l'ecran de connexion. */
    fun quitterEspaceClient() {
        if (!estClient) return
        base.ecrireMeta("profil", null)
        passageEnAttente = null
        version++
    }

    /** Garde (hache) le secret de connexion pour ouvrir l'application sans reseau. */
    private fun definirSecretLocal(secret: String) {
        val sel = ByteArray(16).also { SecureRandom().nextBytes(it) }
        base.ecrireMeta("pin", hex(sel) + ":" + hex(hacher(secret, sel)))
        val pin = Regex("^\\d{4,6}$").matches(secret)
        base.ecrireMeta("pin_type", if (pin) "PIN" else "MOT_DE_PASSE")
        base.ecrireMeta("pin_longueur", if (pin) secret.length.toString() else null)
        base.ecrireMeta("essais", "0")
    }

    /** Verifie le PIN, sans reseau. Apres 5 erreurs il faut se reconnecter avec le mot de passe. */
    fun verifierPin(pin: String): Boolean {
        val enregistre = base.lireMeta("pin")?.split(":") ?: return false
        if (essaisRestants() <= 0) return false
        val bon = hex(hacher(pin, deHex(enregistre[0]))) == enregistre[1]
        base.ecrireMeta("essais", if (bon) "0" else ((base.lireMeta("essais")?.toIntOrNull() ?: 0) + 1).toString())
        return bon
    }

    fun essaisRestants(): Int = 5 - (base.lireMeta("essais")?.toIntOrNull() ?: 0)

    fun activerEmpreinte(active: Boolean) {
        base.ecrireMeta("empreinte", if (active) "1" else null)
        version++
    }

    /** Oublie le PIN (PIN oublie, ou trop d'erreurs) : le mot de passe sera redemande. Les saisies restent. */
    fun oublierPin() {
        base.ecrireMeta("pin", null)
        base.ecrireMeta("jeton", null)
        base.ecrireMeta("essais", "0")
        version++
    }

    suspend fun deconnecter(): String? = withContext(Dispatchers.IO) {
        if (base.saisies().any { it.statut == "A_ENVOYER" }) {
            return@withContext "Synchronisez d'abord : des saisies n'ont pas encore été envoyées."
        }
        try { base.lireMeta("jeton")?.let { Api(adresse, it).deconnexion() } } catch (e: Exception) { /* hors ligne : on deconnecte quand meme le telephone */ }
        val adresseGardee = base.lireMeta("adresse")
        val appareil = base.lireMeta("appareil")
        val dernier = base.lireMeta("dernier_identifiant")
        base.toutEffacer()
        adresseGardee?.let { base.ecrireMeta("adresse", it) }
        appareil?.let { base.ecrireMeta("appareil", it) }
        dernier?.let { base.ecrireMeta("dernier_identifiant", it) }
        passageEnAttente = null
        withContext(Dispatchers.Main) { recharger(); version++ }
        null
    }

    private fun hacher(pin: String, sel: ByteArray): ByteArray =
        SecretKeyFactory.getInstance("PBKDF2WithHmacSHA1").generateSecret(PBEKeySpec(pin.toCharArray(), sel, 20_000, 256)).encoded

    private fun hex(octets: ByteArray): String = octets.joinToString("") { "%02x".format(it) }
    private fun deHex(texte: String): ByteArray = ByteArray(texte.length / 2) { texte.substring(it * 2, it * 2 + 2).toInt(16).toByte() }

    // ------------------------------------------------------------------
    // Saisies (toujours possibles, avec ou sans reseau)
    // ------------------------------------------------------------------

    private fun ajouter(type: String, donnees: JSONObject, jour: String = aujourdhui()): String {
        val uuid = UUID.randomUUID().toString()
        donnees.put("jour", jour)
        base.ajouterSaisie(SaisieLocale(uuid, type, donnees, jour, System.currentTimeMillis(), "A_ENVOYER", null))
        recharger()
        return uuid
    }

    private fun JSONObject.reference(nom: String, id: Long, uuid: String?): JSONObject =
        if (id > 0) put("${nom}_id", id) else put("${nom}_uuid", uuid)

    /** @param valeur nombre de mises, ou montant en centimes pour un carnet a depot libre */
    fun cotiser(carnet: Carnet, valeur: Long, jour: String = aujourdhui()) {
        val client = etat.clients.firstOrNull { it.id == carnet.clientId }
        ajouter("cotisation.saisir", JSONObject().reference("carnet", carnet.id, carnet.uuid).apply {
            put("valeur", if (carnet.libre) pourServeur(valeur) else valeur.toString())
            put("_client", client?.nom ?: ""); put("_carnet", carnet.numero); put("_libre", carnet.libre)
            put("_montant_c", if (carnet.libre) valeur else valeur * carnet.mise); put("_parts", if (carnet.libre) 0 else valeur)
        }, jour)
    }

    /** @param duree duree choisie (en mois) pour un compte bloque, sinon null */
    fun creerClient(nom: String, telephone: String, type: TypeCarnet?, mise: Long, versement: Int, duree: Int? = null) {
        ajouter("client.creer", JSONObject().apply {
            put("nom", nom.trim()); put("telephone", telephone.trim())
            if (type != null) { put("type_id", type.id); put("mise", pourServeur(mise)); put("versement", if (versement > 0) versement.toString() else "") }
            if (duree != null) put("option_blocage", duree.toString())
            put("_type_nom", type?.nom ?: ""); put("_prix_c", type?.prix ?: 0); put("_mise_c", mise); put("_parts", versement); put("_libre", type?.libre ?: false)
            put("_nombre_parts", type?.nombreParts ?: 0)
        })
    }

    fun vendreCarnet(client: Client, type: TypeCarnet, mise: Long, versement: Int, duree: Int? = null) {
        ajouter("carnet.vendre", JSONObject().reference("client", client.id, client.uuid).apply {
            put("type_id", type.id); put("mise", pourServeur(mise)); put("versement", if (versement > 0) versement.toString() else "")
            if (duree != null) put("option_blocage", duree.toString())
            put("_client", client.nom); put("_type_nom", type.nom); put("_prix_c", type.prix); put("_mise_c", mise); put("_parts", versement); put("_libre", type.libre)
            put("_nombre_parts", type.nombreParts)
        })
    }

    fun rembourser(credit: Credit, montant: Long) {
        ajouter("remboursement.saisir", JSONObject().apply {
            put("credit_id", credit.id); put("montant", pourServeur(montant))
            put("_client", etat.client(credit.clientId)?.nom ?: ""); put("_credit", credit.numero); put("_montant_c", montant)
        })
    }

    fun demanderCredit(client: Client, produit: ProduitCredit, montant: Long) {
        ajouter("credit.demander", JSONObject().reference("client", client.id, client.uuid).apply {
            put("produit_id", produit.id); put("montant", pourServeur(montant)); put("_client", client.nom); put("_produit", produit.nom); put("_montant_c", montant)
        })
    }

    /**
     * Corrige une saisie tant qu'elle n'est pas validee.
     * Encore dans le telephone : on la modifie sur place. Deja chez le serveur, en attente du controle : on envoie une correction.
     */
    fun corriger(saisie: Saisie, valeur: Long) {
        if (!saisie.modifiable || saisie.type != "cotisation") return
        if (saisie.etat == "A_ENVOYER" || (saisie.idServeur == 0L && saisie.uuid != null)) {
            val locale = base.saisies().firstOrNull { it.uuid == saisie.uuid } ?: return
            val libre = locale.donnees.optBoolean("_libre")
            val mise = if (libre) 0L else locale.donnees.optLong("_montant_c") / locale.donnees.optLong("_parts").coerceAtLeast(1)
            locale.donnees.put("valeur", if (libre) pourServeur(valeur) else valeur.toString())
            locale.donnees.put("_montant_c", if (libre) valeur else valeur * mise).put("_parts", if (libre) 0 else valeur)
            base.modifierSaisie(locale.uuid, locale.donnees.toString())
            recharger()
        } else {
            ajouter("cotisation.corriger", JSONObject().apply {
                put("cotisation_id", saisie.idServeur); put("valeur", if (saisie.parts == null) pourServeur(valeur) else valeur.toString())
                put("_client", saisie.clientNom); put("_detail", saisie.detail)
            }, saisie.jour)
        }
    }

    /** Annule une saisie pas encore validee. */
    fun annuler(saisie: Saisie) {
        if (!saisie.modifiable) return
        if (saisie.idServeur == 0L && saisie.uuid != null) {
            base.supprimerSaisie(saisie.uuid)
            recharger()
        } else when (saisie.type) {
            "cotisation" -> ajouter("cotisation.supprimer", JSONObject().put("cotisation_id", saisie.idServeur).put("_client", saisie.clientNom).put("_detail", saisie.detail))
            "remboursement" -> ajouter("remboursement.supprimer", JSONObject().put("remboursement_id", saisie.idServeur).put("_client", saisie.clientNom).put("_detail", saisie.detail))
        }
    }

    /** Retire de la liste une saisie que le serveur a refusee (apres l'avoir lue). */
    fun effacerRefusee(uuid: String) {
        base.supprimerSaisie(uuid)
        recharger()
    }

    // ------------------------------------------------------------------
    // Synchronisation
    // ------------------------------------------------------------------

    suspend fun synchroniser(silencieux: Boolean = false) {
        val jeton = base.lireMeta("jeton") ?: return
        if (!faitLaTournee) return
        if (!verrou.tryLock()) return
        try {
            withContext(Dispatchers.Main) { synchronisation = true }
            val resultat = withContext(Dispatchers.IO) {
                try {
                    val api = Api(adresse, jeton)
                    val aEnvoyer = base.saisies().filter { it.statut == "A_ENVOYER" }.take(500)
                    var refus = 0
                    val portefeuille = if (aEnvoyer.isEmpty()) {
                        api.portefeuille()
                    } else {
                        val lot = JSONArray()
                        aEnvoyer.forEach { s ->
                            // les champs qui commencent par "_" ne servent qu'a l'affichage dans le telephone
                            val donnees = JSONObject()
                            s.donnees.keys().forEach { cle -> if (!cle.startsWith("_")) donnees.put(cle, s.donnees.get(cle)) }
                            lot.put(JSONObject().put("uuid", s.uuid).put("type", s.type).put("donnees", donnees))
                        }
                        val r = api.synchroniser(lot)
                        r.getJSONArray("operations").objets().forEach { op ->
                            if (op.optString("statut") == "APPLIQUEE") {
                                base.marquerSaisie(op.getString("uuid"), "ENVOYEE", null)
                            } else {
                                refus++
                                base.marquerSaisie(op.getString("uuid"), "REJETEE", op.texte("message") ?: "Refusée par le serveur.")
                            }
                        }
                        r.getJSONObject("portefeuille")
                    }
                    suivreAdresse(portefeuille.optJSONObject("serveur")?.texte("adresse"))
                    portefeuille.texte("profil")?.let { nouveau -> if (nouveau != profil) withContext(Dispatchers.Main) { changerProfil(nouveau) } }
                    base.ecrireInstantane(portefeuille.toString())
                    base.purgerEnvoyees()
                    base.ecrireMeta("synchro", System.currentTimeMillis().toString())
                    when {
                        refus > 0 -> "$refus saisie(s) refusée(s) par le serveur : voyez « Saisies »."
                        aEnvoyer.isNotEmpty() -> "${aEnvoyer.size} saisie(s) envoyée(s)."
                        else -> "Tout est à jour."
                    }
                } catch (e: ErreurApi) {
                    if (e.codeHttp == 401) {
                        withContext(Dispatchers.Main) { reconnexionRequise = true }
                    }
                    // n'est plus recolteur : il reste la gestion
                    if (e.code == "GESTION" && base.saisies().none { it.statut == "A_ENVOYER" }) {
                        withContext(Dispatchers.Main) { changerProfil("GESTION") }
                    }
                    e.message
                } catch (e: IOException) {
                    "Pas de réseau : vos saisies restent dans le téléphone."
                } catch (e: Exception) {
                    "Synchronisation impossible : ${e.message}"
                }
            }
            withContext(Dispatchers.Main) {
                recharger()
                if (!silencieux) message = resultat
                synchronisation = false
            }
        } finally {
            verrou.unlock()
        }
    }

    suspend fun marquerNotificationsLues() = withContext(Dispatchers.IO) {
        try { base.lireMeta("jeton")?.let { Api(adresse, it).notificationsLues() } } catch (e: Exception) { /* sans reseau : elles resteront « nouvelles » */ }
    }

    // ------------------------------------------------------------------
    // Construction de ce que les ecrans affichent
    // ------------------------------------------------------------------

    private fun recharger() {
        val instantane = base.lireInstantane()
        if (instantane == null) {
            etat = Etat()
            return
        }
        val p = JSONObject(instantane.first)
        val org = p.getJSONObject("organisation")
        val file = base.saisies()
        val locales = file.filter { it.statut == "A_ENVOYER" }

        val clients = p.getJSONArray("clients").objets().filter { it.optString("statut") == "ACTIF" }
            .map { Client(it.getLong("id"), null, it.getString("code"), it.getString("nom"), it.texte("telephone")) }.toMutableList()
        val carnets = p.getJSONArray("carnets").objets().map {
            Carnet(
                it.getLong("id"), null, it.getLong("client_id"), null, it.getInt("numero"), it.getString("type_nom"), it.getString("mode") == "LIBRE",
                it.getLong("mise_c"), it.getInt("nombre_parts"), it.getInt("parts_validees"), it.getInt("parts_en_attente"), it.getLong("epargne_c"), it.getLong("en_attente_c"),
            )
        }.toMutableList()
        val credits = p.getJSONArray("credits").objets().map {
            Credit(
                it.getLong("id"), it.getLong("client_id"), it.getInt("numero"), it.getString("produit_nom"), it.getString("statut") == "EN_RETARD",
                it.getLong("total_du_c"), it.getLong("rembourse_c"), it.getLong("restant_c"), it.getLong("en_attente_c"), it.getString("date_echeance"),
            )
        }.toMutableList()
        val jours = p.getJSONArray("a_deposer").objets()
            .associate { it.getString("jour") to JourADeposer(it.getString("jour"), it.getLong("carnets_c"), it.getLong("cotisations_c"), it.getLong("paiements_c")) }.toMutableMap()
        fun deposer(jour: String, carnets: Long = 0, cotisations: Long = 0, paiements: Long = 0) {
            val j = jours[jour] ?: JourADeposer(jour, 0, 0, 0)
            jours[jour] = j.copy(carnets = j.carnets + carnets, cotisations = j.cotisations + cotisations, paiements = j.paiements + paiements)
        }
        fun idLocal(uuid: String): Long = -(Math.abs(uuid.hashCode().toLong()) + 1)
        fun indexCarnet(d: JSONObject): Int = carnets.indexOfFirst { k -> if (d.has("carnet_id")) k.id == d.optLong("carnet_id") else k.uuid == d.optString("carnet_uuid") }

        // les saisies encore dans le telephone s'ajoutent a ce que le serveur a envoye
        locales.forEach { s ->
            val d = s.donnees
            when (s.type) {
                "client.creer" -> {
                    clients.add(Client(idLocal(s.uuid), s.uuid, "Nouveau", d.optString("nom").uppercase(Locale.ROOT), d.texte("telephone"), local = true))
                    if (d.has("type_id")) {
                        val parts = d.optInt("_parts")
                        carnets.add(Carnet(idLocal(s.uuid), s.uuid, idLocal(s.uuid), s.uuid, 0, d.optString("_type_nom"), d.optBoolean("_libre"), d.optLong("_mise_c"), d.optInt("_nombre_parts"), 0, parts, 0, parts * d.optLong("_mise_c"), local = true))
                        deposer(s.jour, carnets = d.optLong("_prix_c"), cotisations = parts * d.optLong("_mise_c"))
                    }
                }
                "carnet.vendre" -> {
                    val clientId = if (d.has("client_id")) d.optLong("client_id") else idLocal(d.optString("client_uuid"))
                    val parts = d.optInt("_parts")
                    carnets.add(Carnet(idLocal(s.uuid), s.uuid, clientId, d.texte("client_uuid"), 0, d.optString("_type_nom"), d.optBoolean("_libre"), d.optLong("_mise_c"), d.optInt("_nombre_parts"), 0, parts, 0, parts * d.optLong("_mise_c"), local = true))
                    deposer(s.jour, carnets = d.optLong("_prix_c"), cotisations = parts * d.optLong("_mise_c"))
                }
                "cotisation.saisir" -> {
                    val i = indexCarnet(d)
                    if (i >= 0) carnets[i] = carnets[i].copy(partsEnAttente = carnets[i].partsEnAttente + d.optInt("_parts"), enAttente = carnets[i].enAttente + d.optLong("_montant_c"))
                    deposer(s.jour, cotisations = d.optLong("_montant_c"))
                }
                "remboursement.saisir" -> {
                    val i = credits.indexOfFirst { it.id == d.optLong("credit_id") }
                    if (i >= 0) credits[i] = credits[i].copy(enAttente = credits[i].enAttente + d.optLong("_montant_c"))
                    deposer(s.jour, paiements = d.optLong("_montant_c"))
                }
            }
        }

        val noms = clients.associate { it.id to it.nom }
        val saisiesLocales = file.filter { it.statut != "ENVOYEE" }.sortedByDescending { it.creeLe }.map { s ->
            val d = s.donnees
            val (type, detail) = when (s.type) {
                "client.creer" -> "client" to ("Nouveau client" + if (d.has("type_id")) " · ${d.optString("_type_nom")}" else "")
                "carnet.vendre" -> "carnet" to "Vente de carnet · ${d.optString("_type_nom")}"
                "cotisation.saisir" -> "cotisation" to ("Cotisation · carnet #${d.optInt("_carnet")}" + if (d.optInt("_parts") > 0) " · ${d.optInt("_parts")} mise(s)" else "")
                "remboursement.saisir" -> "remboursement" to "Remboursement · crédit #${d.optInt("_credit")}"
                "credit.demander" -> "demande" to "Demande de crédit · ${d.optString("_produit")}"
                "cotisation.corriger" -> "correction" to "Correction · ${d.optString("_detail")}"
                else -> "annulation" to "Annulation · ${d.optString("_detail")}"
            }
            val montant = when (s.type) {
                "client.creer", "carnet.vendre" -> d.optLong("_prix_c") + d.optInt("_parts") * d.optLong("_mise_c")
                else -> d.optLong("_montant_c")
            }
            Saisie(
                "L" + s.uuid, type, 0, s.uuid, s.jour, if (s.type == "client.creer") d.optString("nom").uppercase(Locale.ROOT) else d.optString("_client"), detail, montant,
                if (s.statut == "REJETEE") "REJETEE" else "A_ENVOYER", s.message,
                modifiable = s.statut == "A_ENVOYER" && s.type in listOf("cotisation.saisir", "remboursement.saisir", "client.creer", "carnet.vendre", "credit.demander", "cotisation.corriger", "cotisation.supprimer", "remboursement.supprimer"),
                objetId = 0, parts = if (s.type == "cotisation.saisir" && !d.optBoolean("_libre")) d.optInt("_parts") else null,
            )
        }
        val saisiesServeur = p.getJSONArray("saisies").objets().map {
            val type = it.getString("type")
            val parts = if (it.isNull("parts")) null else it.getInt("parts")
            val detail = when (type) {
                "cotisation" -> "Cotisation" + if (parts != null) " · $parts mise(s)" else ""
                "carnet" -> "Vente de carnet"
                else -> "Remboursement de crédit"
            }
            Saisie(
                "S" + type + it.getLong("id"), type, it.getLong("id"), it.texte("uuid"), it.getString("jour"), noms[it.getLong("client_id")] ?: "", detail, it.getLong("montant_c"),
                it.getString("etat"), it.texte("motif"), it.optBoolean("modifiable") && type != "carnet", it.getLong("objet_id"), parts,
            )
        }

        val solde = p.getJSONObject("solde_mois")
        etat = Etat(
            utilisateurNom = p.getJSONObject("utilisateur").getString("nom"), identifiant = p.getJSONObject("utilisateur").getString("identifiant"),
            organisation = org.getString("nom"), devise = org.optString("devise"), decimales = org.optInt("decimales"), avecCredit = org.optBoolean("credit"),
            jourServeur = p.getJSONObject("serveur").optString("jour"),
            clients = clients.sortedBy { it.nom }, carnets = carnets, credits = credits,
            types = p.getJSONArray("types_carnet").objets().map {
                TypeCarnet(
                    it.getLong("id"), it.getString("nom"), it.getString("mode") == "LIBRE", it.getLong("prix_c"), it.getInt("nombre_parts"), it.getLong("mise_minimum_c"), it.longOuNull("mise_maximum_c"),
                    blocage = it.texte("blocage"), tauxBase = it.optString("taux_base", "AN"),
                    options = it.optJSONArray("options")?.objets()?.map { o -> OptionBlocage(o.getInt("mois"), o.optString("taux")) } ?: emptyList(),
                )
            },
            produits = p.getJSONArray("produits_credit").objets().map {
                ProduitCredit(
                    it.getLong("id"), it.getString("nom"), it.getDouble("taux"), it.getInt("duree_jours"), it.longOuNull("minimum_c"), it.longOuNull("maximum_c"),
                    if (it.isNull("multiple_epargne")) null else it.getDouble("multiple_epargne"), it.getInt("simultanes_max"), it.optBoolean("sans_retard"),
                    modeCalcul = it.optString("mode_calcul", "FIXE"), garantiePct = if (it.isNull("garantie_pct") || !it.has("garantie_pct")) null else it.getDouble("garantie_pct"),
                    echeances = it.optInt("echeances"), echeancesUnite = it.optString("echeances_unite", "JOUR"), echeancesIntervalle = it.optInt("echeances_intervalle", 1),
                )
            },
            saisies = saisiesLocales + saisiesServeur,
            aDeposer = jours.values.filter { it.total > 0 }.sortedByDescending { it.jour },
            solde = SoldeMois(solde.getLong("part_c"), solde.getLong("manquants_c"), solde.getLong("net_c"), solde.optBoolean("deja_paye")),
            notifications = p.getJSONArray("notifications").objets().map { Notification(it.getString("texte"), it.getString("le"), it.optBoolean("lue")) },
            aEnvoyer = locales.size, refusees = file.count { it.statut == "REJETEE" },
            derniereSynchro = base.lireMeta("synchro")?.toLongOrNull() ?: instantane.second,
        )
    }

    companion object {
        fun aujourdhui(): String = SimpleDateFormat("yyyy-MM-dd", Locale.US).format(Date())

        /** Montant en centimes -> texte attendu par le serveur ("1500" ou "1500.50"). */
        fun pourServeur(centimes: Long): String =
            if (centimes % 100 == 0L) (centimes / 100).toString() else String.format(Locale.US, "%d.%02d", centimes / 100, centimes % 100)
    }
}
