package com.avecxy.recolteur.donnees

import android.content.ContentValues
import android.content.Context
import android.database.sqlite.SQLiteDatabase
import android.database.sqlite.SQLiteOpenHelper

/**
 * Base SQLite du telephone. Trois tables :
 *  - meta        : reglages (adresse du serveur, jeton, PIN hache, date de la derniere synchronisation) ;
 *  - instantane  : le portefeuille tel que le serveur l'a envoye la derniere fois (un seul document JSON) ;
 *  - file        : les saisies faites sur le telephone, une ligne chacune, jusqu'a ce que le serveur les ait recues.
 * Une saisie est ecrite dans "file" avant tout autre traitement : elle ne se perd pas si l'application est fermee.
 */
class Base(contexte: Context) : SQLiteOpenHelper(contexte, "avecxy.db", null, 1) {

    override fun onCreate(bd: SQLiteDatabase) {
        bd.execSQL("CREATE TABLE meta (cle TEXT PRIMARY KEY, valeur TEXT NOT NULL)")
        bd.execSQL("CREATE TABLE instantane (id INTEGER PRIMARY KEY CHECK (id = 1), json TEXT NOT NULL, recu_le INTEGER NOT NULL)")
        bd.execSQL(
            "CREATE TABLE file (uuid TEXT PRIMARY KEY, type TEXT NOT NULL, donnees TEXT NOT NULL, jour TEXT NOT NULL, " +
                "cree_le INTEGER NOT NULL, statut TEXT NOT NULL DEFAULT 'A_ENVOYER', message TEXT)"
        )
        bd.execSQL("CREATE INDEX idx_file_statut ON file (statut, cree_le)")
    }

    override fun onUpgrade(bd: SQLiteDatabase, ancienne: Int, nouvelle: Int) = Unit

    fun lireMeta(cle: String): String? =
        readableDatabase.rawQuery("SELECT valeur FROM meta WHERE cle = ?", arrayOf(cle)).use { c -> if (c.moveToFirst()) c.getString(0) else null }

    fun ecrireMeta(cle: String, valeur: String?) {
        if (valeur == null) {
            writableDatabase.delete("meta", "cle = ?", arrayOf(cle))
        } else {
            writableDatabase.insertWithOnConflict("meta", null, ContentValues().apply { put("cle", cle); put("valeur", valeur) }, SQLiteDatabase.CONFLICT_REPLACE)
        }
    }

    fun lireInstantane(): Pair<String, Long>? =
        readableDatabase.rawQuery("SELECT json, recu_le FROM instantane WHERE id = 1", null).use { c -> if (c.moveToFirst()) c.getString(0) to c.getLong(1) else null }

    fun ecrireInstantane(json: String) {
        writableDatabase.insertWithOnConflict(
            "instantane", null,
            ContentValues().apply { put("id", 1); put("json", json); put("recu_le", System.currentTimeMillis()) },
            SQLiteDatabase.CONFLICT_REPLACE
        )
    }

    fun ajouterSaisie(s: SaisieLocale) {
        writableDatabase.insertOrThrow("file", null, ContentValues().apply {
            put("uuid", s.uuid); put("type", s.type); put("donnees", s.donnees.toString()); put("jour", s.jour)
            put("cree_le", s.creeLe); put("statut", s.statut); put("message", s.message)
        })
    }

    fun modifierSaisie(uuid: String, donnees: String) {
        writableDatabase.update("file", ContentValues().apply { put("donnees", donnees); put("statut", "A_ENVOYER"); putNull("message") }, "uuid = ?", arrayOf(uuid))
    }

    fun marquerSaisie(uuid: String, statut: String, message: String?) {
        writableDatabase.update("file", ContentValues().apply { put("statut", statut); put("message", message) }, "uuid = ?", arrayOf(uuid))
    }

    fun supprimerSaisie(uuid: String) {
        writableDatabase.delete("file", "uuid = ?", arrayOf(uuid))
    }

    /** Les saisies envoyees et acceptees sont retirees : le serveur les renvoie desormais dans le portefeuille. */
    fun purgerEnvoyees() {
        writableDatabase.delete("file", "statut = 'ENVOYEE'", null)
    }

    fun saisies(): List<SaisieLocale> =
        readableDatabase.rawQuery("SELECT uuid, type, donnees, jour, cree_le, statut, message FROM file ORDER BY cree_le", null).use { c ->
            val liste = ArrayList<SaisieLocale>()
            while (c.moveToNext()) {
                liste.add(SaisieLocale(c.getString(0), c.getString(1), org.json.JSONObject(c.getString(2)), c.getString(3), c.getLong(4), c.getString(5), if (c.isNull(6)) null else c.getString(6)))
            }
            liste
        }

    fun toutEffacer() {
        writableDatabase.delete("meta", null, null)
        writableDatabase.delete("instantane", null, null)
        writableDatabase.delete("file", null, null)
    }
}
