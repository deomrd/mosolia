package com.avecxy.recolteur.ecrans

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.avecxy.recolteur.donnees.Etat
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

// Couleurs de la maquette
val Vert = Color(0xFF0E5247)
val VertFonce = Color(0xFF0E3F37)
val VertClair = Color(0xFFEAF4F1)
val VertPastille = Color(0xFFDDF0EA)
val Or = Color(0xFFF2B544)
val OrFond = Color(0xFFFFF1D2)
val OrEncre = Color(0xFF6B4A00)
val Fond = Color(0xFFF4F6F5)
val Encre = Color(0xFF14201D)
val Gris = Color(0xFF56655F)
val Trait = Color(0xFFDCE4E1)
val Rouge = Color(0xFFA3271A)
val RougeFond = Color(0xFFFBE3E0)
val BleuFond = Color(0xFFE6ECF7)
val BleuEncre = Color(0xFF1F3F7A)

@Composable
fun ThemeMosolia(contenu: @Composable () -> Unit) {
    MaterialTheme(
        colorScheme = lightColorScheme(
            primary = Vert, onPrimary = Color.White, secondary = Or, onSecondary = Encre, background = Fond, onBackground = Encre,
            surface = Color.White, onSurface = Encre, surfaceVariant = VertClair, onSurfaceVariant = Gris, error = Rouge, outline = Trait,
        ),
        content = contenu,
    )
}

/** Montant en centimes -> "186 500 FC". */
fun argent(centimes: Long, etat: Etat, avecDevise: Boolean = true): String {
    val negatif = centimes < 0
    val absolu = Math.abs(centimes)
    val entier = (absolu / 100).toString().reversed().chunked(3).joinToString(" ").reversed()
    val texte = if (etat.decimales > 0) entier + "," + String.format(Locale.US, "%02d", absolu % 100) else entier
    return (if (negatif) "−" else "") + texte + if (avecDevise && etat.devise.isNotEmpty()) " " + etat.devise else ""
}

/** Texte saisi ("1500", "1 500,50") -> centimes ; null si ce n'est pas un montant. */
fun enCentimes(texte: String): Long? {
    val propre = texte.replace(" ", "").replace(" ", "").replace(",", ".").trim()
    if (propre.isEmpty()) return null
    val valeur = propre.toBigDecimalOrNull() ?: return null
    if (valeur.signum() < 0) return null
    return valeur.movePointRight(2).setScale(0, java.math.RoundingMode.HALF_UP).toLong()
}

fun jourLisible(jour: String): String = try {
    val date = SimpleDateFormat("yyyy-MM-dd", Locale.US).parse(jour)!!
    val aujourdhui = SimpleDateFormat("yyyy-MM-dd", Locale.US).format(Date())
    val hier = SimpleDateFormat("yyyy-MM-dd", Locale.US).format(Date(System.currentTimeMillis() - 86_400_000))
    when (jour) {
        aujourdhui -> "Aujourd'hui"
        hier -> "Hier"
        else -> SimpleDateFormat("EEE d MMM", Locale.FRENCH).format(date)
    }
} catch (e: Exception) { jour }

fun initiales(nom: String): String = nom.split(" ").filter { it.isNotBlank() }.take(2).joinToString("") { it.first().uppercase() }

@Composable
fun Carte(modifier: Modifier = Modifier, couleur: Color = Color.White, bordure: Color? = Trait, auClic: (() -> Unit)? = null, contenu: @Composable ColumnScope.() -> Unit) {
    Surface(
        modifier = if (auClic != null) modifier.clip(RoundedCornerShape(18.dp)).clickable(onClick = auClic) else modifier,
        shape = RoundedCornerShape(18.dp), color = couleur, border = bordure?.let { BorderStroke(1.dp, it) },
    ) {
        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp), content = contenu)
    }
}

@Composable
fun Etiquette(texte: String, couleur: Color = Gris) {
    Text(texte.uppercase(Locale.FRENCH), color = couleur, fontSize = 11.sp, fontWeight = FontWeight.Bold, letterSpacing = 0.6.sp)
}

@Composable
fun Pastille(texte: String, fond: Color, encre: Color) {
    Text(texte, color = encre, fontSize = 11.sp, fontWeight = FontWeight.Bold, maxLines = 1,
        modifier = Modifier.clip(RoundedCornerShape(50)).background(fond).padding(horizontal = 8.dp, vertical = 3.dp))
}

@Composable
fun PastilleEtat(etat: String) = when (etat) {
    "A_ENVOYER" -> Pastille("À envoyer", OrFond, OrEncre)
    "EN_ATTENTE" -> Pastille("Au contrôle", BleuFond, BleuEncre)
    "VALIDEE" -> Pastille("Validée", VertPastille, Vert)
    else -> Pastille("Refusée", RougeFond, Rouge)
}

@Composable
fun Avatar(nom: String, taille: Dp = 44.dp, fond: Color = VertPastille, encre: Color = Vert) {
    Box(Modifier.size(taille).clip(CircleShape).background(fond), contentAlignment = Alignment.Center) {
        Text(initiales(nom), color = encre, fontWeight = FontWeight.ExtraBold, fontSize = (taille.value / 2.8f).sp)
    }
}

@Composable
fun GrandBouton(texte: String, modifier: Modifier = Modifier, actif: Boolean = true, couleur: Color = Or, encre: Color = Encre, auClic: () -> Unit) {
    Button(
        onClick = auClic, enabled = actif, modifier = modifier.fillMaxWidth().height(56.dp), shape = RoundedCornerShape(16.dp),
        colors = ButtonDefaults.buttonColors(containerColor = couleur, contentColor = encre),
    ) { Text(texte, fontWeight = FontWeight.ExtraBold, fontSize = 16.sp) }
}

@Composable
fun Ligne(gauche: String, droite: String, gras: Boolean = false, couleur: Color = Encre) {
    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
        Text(gauche, color = if (gras) couleur else Gris, fontSize = 13.sp, fontWeight = if (gras) FontWeight.Bold else FontWeight.Medium, modifier = Modifier.weight(1f), overflow = TextOverflow.Ellipsis, maxLines = 1)
        Spacer(Modifier.width(8.dp))
        Text(droite, color = couleur, fontSize = 13.sp, fontWeight = FontWeight.Bold, maxLines = 1)
    }
}

/** Boutons − et + avec la valeur au milieu (cibles de 48 dp). */
@Composable
fun Compteur(valeur: Int, maximum: Int, auChangement: (Int) -> Unit, grand: Boolean = false) {
    val cote = if (grand) 56.dp else 46.dp
    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(if (grand) 20.dp else 8.dp)) {
        Surface(Modifier.size(cote).clip(RoundedCornerShape(14.dp)).clickable(enabled = valeur > 0) { auChangement(valeur - 1) }, shape = RoundedCornerShape(14.dp), color = Color.White, border = BorderStroke(1.dp, Trait)) {
            Box(contentAlignment = Alignment.Center) { Text("−", fontSize = 24.sp, fontWeight = FontWeight.Bold, color = if (valeur > 0) Encre else Trait) }
        }
        Text(valeur.toString(), fontSize = if (grand) 56.sp else 20.sp, fontWeight = FontWeight.ExtraBold, color = if (valeur > 0) Encre else Gris,
            modifier = Modifier.width(if (grand) 80.dp else 30.dp), textAlign = androidx.compose.ui.text.style.TextAlign.Center)
        val peut = valeur < maximum
        Surface(Modifier.size(cote).clip(RoundedCornerShape(14.dp)).clickable(enabled = peut) { auChangement(valeur + 1) }, shape = RoundedCornerShape(14.dp), color = if (peut) Vert else Trait) {
            Box(contentAlignment = Alignment.Center) { Text("+", fontSize = 24.sp, fontWeight = FontWeight.Bold, color = Color.White) }
        }
    }
}
