package com.avecxy.recolteur.donnees

import org.json.JSONObject
import java.io.IOException
import java.net.HttpURLConnection
import java.net.URL

class ErreurApi(val codeHttp: Int, val code: String, message: String) : Exception(message)

/** Appels au serveur Mosolia. Tout est en JSON ; le jeton voyage dans l'en-tete Authorization. */
class Api(private val adresse: String, private val jeton: String?) {

    fun connexion(identifiant: String, motDePasse: String, appareilId: String, appareilNom: String): JSONObject =
        appeler("POST", "/api/v1/connexion", JSONObject().apply {
            put("identifiant", identifiant); put("mot_de_passe", motDePasse); put("appareil_id", appareilId); put("appareil_nom", appareilNom)
        })

    /** Passage a usage unique vers une page du site (gestion), ouverte dans l'application sans redemander le mot de passe. */
    fun passage(cible: String): JSONObject = appeler("POST", "/api/v1/passage", JSONObject().put("cible", cible))

    fun portefeuille(): JSONObject = appeler("GET", "/api/v1/portefeuille", null)

    fun synchroniser(operations: org.json.JSONArray): JSONObject = appeler("POST", "/api/v1/synchroniser", JSONObject().put("operations", operations))

    fun notificationsLues() { appeler("POST", "/api/v1/notifications/lues", JSONObject()) }

    fun deconnexion() { appeler("POST", "/api/v1/deconnexion", JSONObject()) }

    @Throws(IOException::class, ErreurApi::class)
    private fun appeler(methode: String, chemin: String, corps: JSONObject?): JSONObject {
        val connexion = URL(adresse.trimEnd('/') + chemin).openConnection() as HttpURLConnection
        try {
            connexion.requestMethod = methode
            connexion.connectTimeout = 15_000
            connexion.readTimeout = 45_000
            connexion.setRequestProperty("Accept", "application/json")
            connexion.setRequestProperty("User-Agent", "MosoliaApp/" + com.avecxy.recolteur.BuildConfig.VERSION_NAME + " (Android " + android.os.Build.VERSION.RELEASE + ")")
            connexion.setRequestProperty("Accept-Language", java.util.Locale.getDefault().language)
            if (jeton != null) connexion.setRequestProperty("Authorization", "Bearer $jeton")
            if (corps != null) {
                connexion.doOutput = true
                connexion.setRequestProperty("Content-Type", "application/json; charset=utf-8")
                connexion.outputStream.use { it.write(corps.toString().toByteArray(Charsets.UTF_8)) }
            }
            val code = connexion.responseCode
            val flux = if (code in 200..299) connexion.inputStream else connexion.errorStream
            val texte = flux?.bufferedReader(Charsets.UTF_8)?.use { it.readText() } ?: ""
            val json = try { JSONObject(texte) } catch (e: Exception) { throw IOException("Réponse illisible du serveur ($code)") }
            if (code !in 200..299) {
                throw ErreurApi(code, json.optString("code", "ERREUR"), json.optString("erreur", "Erreur $code"))
            }
            return json
        } finally {
            connexion.disconnect()
        }
    }
}
