package com.avecxy.recolteur.donnees

import org.json.JSONArray
import org.json.JSONObject

/** Une saisie faite sur le telephone, en attente d'envoi (ou refusee par le serveur). */
data class SaisieLocale(
    val uuid: String, val type: String, val donnees: JSONObject, val jour: String, val creeLe: Long,
    val statut: String,          // A_ENVOYER, ENVOYEE, REJETEE
    val message: String?,
)

data class Client(val id: Long, val uuid: String?, val code: String, val nom: String, val telephone: String?, val local: Boolean = false)

data class Carnet(
    val id: Long, val uuid: String?, val clientId: Long, val clientUuid: String?, val numero: Int, val typeNom: String, val libre: Boolean,
    val mise: Long, val nombreParts: Int, val partsValidees: Int, val partsEnAttente: Int, val epargne: Long, val enAttente: Long, val local: Boolean = false,
) {
    val partsRestantes: Int get() = (nombreParts - partsValidees - partsEnAttente).coerceAtLeast(0)
}

data class Credit(
    val id: Long, val clientId: Long, val numero: Int, val produitNom: String, val enRetard: Boolean,
    val totalDu: Long, val rembourse: Long, val restant: Long, val enAttente: Long, val echeance: String,
)

/** Une duree proposee pour un compte bloque, avec son taux d'interet (texte du serveur, ex. "6.50"). */
data class OptionBlocage(val mois: Int, val taux: String)

data class TypeCarnet(
    val id: Long, val nom: String, val libre: Boolean, val prix: Long, val nombreParts: Int, val miseMinimum: Long, val miseMaximum: Long?,
    /** Compte bloque : ce qui est bloque (null = carnet ordinaire), durees au choix du client. */
    val blocage: String? = null, val options: List<OptionBlocage> = emptyList(), val tauxBase: String = "AN",
) {
    val bloque: Boolean get() = blocage != null
}

data class ProduitCredit(
    val id: Long, val nom: String, val taux: Double, val dureeJours: Int, val minimum: Long?, val maximum: Long?,
    val multipleEpargne: Double?, val simultanesMax: Int, val sansRetard: Boolean,
    /** FIXE, DEGRESSIF ou DEGRESSIF_CAPITAL ; garantie = part du pret couverte par l'epargne (en %). */
    val modeCalcul: String = "FIXE", val garantiePct: Double? = null, val echeances: Int = 0, val echeancesUnite: String = "JOUR", val echeancesIntervalle: Int = 1,
)

/** Une ligne de la liste « Mes saisies » : venue du serveur, ou encore dans le telephone. */
data class Saisie(
    val cle: String, val type: String, val idServeur: Long, val uuid: String?, val jour: String, val clientNom: String, val detail: String,
    val montant: Long, val etat: String,      // A_ENVOYER, EN_ATTENTE, VALIDEE, REJETEE
    val message: String?, val modifiable: Boolean, val objetId: Long, val parts: Int?,
)

data class JourADeposer(val jour: String, val carnets: Long, val cotisations: Long, val paiements: Long) {
    val total: Long get() = carnets + cotisations + paiements
}

data class SoldeMois(val part: Long, val manquants: Long, val net: Long, val dejaPaye: Boolean)

data class Notification(val texte: String, val le: String, val lue: Boolean)

/** Tout ce que les ecrans affichent : le dernier portefeuille recu, plus les saisies encore dans le telephone. */
data class Etat(
    val utilisateurNom: String = "", val identifiant: String = "", val organisation: String = "", val devise: String = "", val decimales: Int = 0,
    val avecCredit: Boolean = false, val jourServeur: String = "",
    val clients: List<Client> = emptyList(), val carnets: List<Carnet> = emptyList(), val credits: List<Credit> = emptyList(),
    val types: List<TypeCarnet> = emptyList(), val produits: List<ProduitCredit> = emptyList(), val saisies: List<Saisie> = emptyList(),
    val aDeposer: List<JourADeposer> = emptyList(), val solde: SoldeMois = SoldeMois(0, 0, 0, false), val notifications: List<Notification> = emptyList(),
    val aEnvoyer: Int = 0, val refusees: Int = 0, val derniereSynchro: Long = 0,
) {
    fun client(id: Long): Client? = clients.firstOrNull { it.id == id }
    fun carnetsDe(clientId: Long): List<Carnet> = carnets.filter { it.clientId == clientId }
    fun creditsDe(clientId: Long): List<Credit> = credits.filter { it.clientId == clientId }
    val totalCredits: Long get() = credits.sumOf { it.restant }
    val totalEpargne: Long get() = carnets.sumOf { it.epargne }
}

internal fun JSONObject.texte(cle: String): String? = if (isNull(cle)) null else optString(cle)
internal fun JSONObject.longOuNull(cle: String): Long? = if (isNull(cle)) null else optLong(cle)
internal fun JSONArray.objets(): List<JSONObject> = (0 until length()).map { getJSONObject(it) }
