<?php
namespace AvecXY\Infrastructure;

use AvecXY\Domaine\ErreurMetier;

/**
 * Lit et ecrit des classeurs Excel (.xlsx) sans bibliotheque externe.
 * Un .xlsx est une archive zip de fichiers XML : seule l'extension zlib de PHP est necessaire.
 * Sert au modele de reprise de l'existant (ecriture) et a son import (lecture).
 */
final class Tableur
{
    private const TAILLE_MAX = 30_000_000;   // octets une fois decompresse
    public const LIGNES_MAX = 5000;

    // styles du classeur ecrit (index dans cellXfs)
    public const NORMAL = 0;
    public const TITRE_OBLIGATOIRE = 1;
    public const TITRE_FACULTATIF = 2;
    public const NOTE = 3;
    public const DATE = 4;
    public const TEXTE = 5;
    public const GRAND_TITRE = 6;

    // ------------------------------------------------------------------
    // Ecriture
    // ------------------------------------------------------------------

    /**
     * @param array<int,array{nom:string,lignes:array<int,array<int,mixed>>,largeurs?:int[],styles_colonnes?:array<int,int>,
     *   styles_lignes?:array<int,int|int[]>,validations?:array<int,array>,fige?:bool,cachee?:bool}> $feuilles
     *   validations : ['colonne' => 0, 'type' => 'liste'|'entier'|'decimal'|'date', 'source' => "'Listes'!$A$2:$A$20", 'message' => '...']
     * @return string contenu binaire du fichier .xlsx
     */
    public static function ecrire(array $feuilles): string
    {
        $fichiers = [];
        $types = '';
        $liens = '';
        $classeur = '';
        foreach (array_values($feuilles) as $i => $f) {
            $n = $i + 1;
            $fichiers["xl/worksheets/sheet$n.xml"] = self::feuille($f);
            $types .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $liens .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
            $classeur .= '<sheet name="' . self::x(mb_substr($f['nom'], 0, 31)) . '" sheetId="' . $n . '"' . (!empty($f['cachee']) ? ' state="hidden"' : '') . ' r:id="rId' . $n . '"/>';
        }
        $style = count($feuilles) + 1;
        $tete = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $fichiers = [
            '[Content_Types].xml' => $tete . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
                . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' . $types . '</Types>',
            '_rels/.rels' => $tete . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => $tete . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<bookViews><workbookView activeTab="0"/></bookViews><sheets>' . $classeur . '</sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => $tete . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $liens
                . '<Relationship Id="rId' . $style . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'xl/styles.xml' => $tete . self::styles(),
        ] + $fichiers;
        return self::zip($fichiers);
    }

    private static function styles(): string
    {
        return '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="5">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FF1A1F24"/><name val="Calibri"/></font>'
            . '<font><i/><sz val="11"/><color rgb="FF5B6470"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="16"/><color rgb="FF176B5F"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="4">'
            . '<fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF176B5F"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFE2E5E9"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="7">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="2" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            . '<xf numFmtId="14" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private static function feuille(array $f): string
    {
        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        if (!empty($f['fige'])) {
            $x .= '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
        }
        $stylesColonnes = $f['styles_colonnes'] ?? [];
        if (!empty($f['largeurs'])) {
            $x .= '<cols>';
            foreach (array_values($f['largeurs']) as $i => $l) {
                $x .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . (int) $l . '" customWidth="1"' . (isset($stylesColonnes[$i]) ? ' style="' . (int) $stylesColonnes[$i] . '"' : '') . '/>';
            }
            $x .= '</cols>';
        }
        $x .= '<sheetData>';
        foreach (array_values($f['lignes']) as $r => $ligne) {
            $styleLigne = $f['styles_lignes'][$r] ?? null;
            $x .= '<row r="' . ($r + 1) . '"' . ($r === 0 && !empty($f['fige']) ? ' ht="34" customHeight="1"' : '') . '>';
            foreach (array_values($ligne) as $col => $v) {
                $style = is_array($styleLigne) ? ($styleLigne[$col] ?? self::NORMAL) : ($styleLigne ?? ($stylesColonnes[$col] ?? self::NORMAL));
                if ($v === null || $v === '') {
                    if ($style !== self::NORMAL) {
                        $x .= '<c r="' . self::colonne($col) . ($r + 1) . '" s="' . $style . '"/>';
                    }
                    continue;
                }
                $ref = self::colonne($col) . ($r + 1);
                if (is_int($v) || is_float($v)) {
                    $x .= '<c r="' . $ref . '" s="' . $style . '"><v>' . $v . '</v></c>';
                } else {
                    $x .= '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">' . self::x((string) $v) . '</t></is></c>';
                }
            }
            $x .= '</row>';
        }
        $x .= '</sheetData>';
        if (!empty($f['validations'])) {
            $x .= '<dataValidations count="' . count($f['validations']) . '">';
            foreach ($f['validations'] as $v) {
                $lettre = self::colonne((int) $v['colonne']);
                $zone = $lettre . '2:' . $lettre . (self::LIGNES_MAX + 1);
                $message = isset($v['message']) ? ' showErrorMessage="1" errorTitle="Mosolia" error="' . self::x(mb_substr((string) $v['message'], 0, 220)) . '"' : ' showErrorMessage="1"';
                $x .= match ($v['type']) {
                    'liste' => '<dataValidation type="list" allowBlank="1" showInputMessage="1"' . $message . ' sqref="' . $zone . '"><formula1>' . self::x((string) $v['source']) . '</formula1></dataValidation>',
                    'entier' => '<dataValidation type="whole" operator="greaterThanOrEqual" allowBlank="1"' . $message . ' sqref="' . $zone . '"><formula1>0</formula1></dataValidation>',
                    'date' => '<dataValidation type="date" operator="greaterThan" allowBlank="1"' . $message . ' sqref="' . $zone . '"><formula1>18264</formula1></dataValidation>',
                    default => '<dataValidation type="decimal" operator="greaterThanOrEqual" allowBlank="1"' . $message . ' sqref="' . $zone . '"><formula1>0</formula1></dataValidation>',
                };
            }
            $x .= '</dataValidations>';
        }
        return $x . '</worksheet>';
    }

    /** 0 => A, 25 => Z, 26 => AA... */
    public static function colonne(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26) . $s;
        }
        return $s;
    }

    private static function x(string $v): string
    {
        // les caracteres de controle sont interdits en XML
        return htmlspecialchars((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $v), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /** @param array<string,string> $fichiers nom => contenu */
    private static function zip(array $fichiers): string
    {
        $corps = '';
        $index = '';
        $heure = ((int) date('H') << 11) | ((int) date('i') << 5) | intdiv((int) date('s'), 2);
        $jour = (((int) date('Y') - 1980) << 9) | ((int) date('n') << 5) | (int) date('j');
        foreach ($fichiers as $nom => $contenu) {
            $compresse = gzdeflate($contenu, 6);
            $commun = pack('v', 0) . pack('v', 8) . pack('v', $heure) . pack('v', $jour) . pack('V', crc32($contenu)) . pack('V', strlen($compresse)) . pack('V', strlen($contenu)) . pack('v', strlen($nom)) . pack('v', 0);
            $position = strlen($corps);
            $corps .= "PK\x03\x04" . pack('v', 20) . $commun . $nom . $compresse;
            $index .= "PK\x01\x02" . pack('v', 20) . pack('v', 20) . $commun . pack('v', 0) . pack('v', 0) . pack('v', 0) . pack('V', 0) . pack('V', $position) . $nom;
        }
        return $corps . $index . "PK\x05\x06" . pack('vvvvVVv', 0, 0, count($fichiers), count($fichiers), strlen($index), strlen($corps), 0);
    }

    // ------------------------------------------------------------------
    // Lecture
    // ------------------------------------------------------------------

    /**
     * @return array<int,array{nom:string,lignes:array<int,array<int,string>>}> feuilles dans l'ordre du classeur ;
     *   lignes indexees par numero de ligne Excel (1 = titres), cellules par colonne (0 = A). Les cellules vides sont absentes.
     */
    public static function lire(string $chemin): array
    {
        $binaire = @file_get_contents($chemin);
        if ($binaire === false || strlen($binaire) < 100 || !str_starts_with($binaire, "PK")) {
            throw new ErreurMetier('reprise.fichier_illisible');
        }
        $fichiers = self::dezip($binaire);
        $classeur = self::xml($fichiers['xl/workbook.xml'] ?? '');
        $liens = self::xml($fichiers['xl/_rels/workbook.xml.rels'] ?? '');
        if ($classeur === null || $liens === null) {
            throw new ErreurMetier('reprise.fichier_illisible');
        }
        $cibles = [];
        foreach ($liens->Relationship as $r) {
            $cible = (string) $r['Target'];
            $cibles[(string) $r['Id']] = str_starts_with($cible, '/') ? ltrim($cible, '/') : 'xl/' . $cible;
        }
        $partagees = [];
        $xml = self::xml($fichiers['xl/sharedStrings.xml'] ?? '');
        if ($xml !== null) {
            foreach ($xml->si as $si) {
                $partagees[] = self::texte($si);
            }
        }
        $feuilles = [];
        foreach ($classeur->sheets->sheet as $s) {
            $rid = (string) $s->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $xml = self::xml($fichiers[$cibles[$rid] ?? ''] ?? '');
            $lignes = [];
            if ($xml !== null && isset($xml->sheetData)) {
                foreach ($xml->sheetData->row as $row) {
                    $numero = (int) $row['r'];
                    if ($numero > self::LIGNES_MAX + 1) {
                        break;
                    }
                    $ligne = [];
                    foreach ($row->c as $c) {
                        $type = (string) $c['t'];
                        $v = match ($type) {
                            's' => $partagees[(int) $c->v] ?? '',
                            'inlineStr' => isset($c->is) ? self::texte($c->is) : '',
                            'b' => (string) $c->v === '1' ? 'OUI' : 'NON',
                            default => (string) $c->v,
                        };
                        $v = trim($v);
                        if ($v !== '') {
                            $ligne[self::indice((string) $c['r'])] = $v;
                        }
                    }
                    if ($ligne) {
                        $lignes[$numero] = $ligne;
                    }
                }
            }
            $feuilles[] = ['nom' => (string) $s['name'], 'lignes' => $lignes];
        }
        return $feuilles;
    }

    /** Texte d'une cellule, y compris quand il est decoupe en morceaux mis en forme. */
    private static function texte(\SimpleXMLElement $e): string
    {
        $t = isset($e->t) ? (string) $e->t : '';
        foreach ($e->r as $r) {
            $t .= (string) $r->t;
        }
        return $t;
    }

    /** "C12" => 2 */
    private static function indice(string $ref): int
    {
        $n = 0;
        foreach (str_split((string) preg_replace('/[^A-Z]/', '', strtoupper($ref))) as $l) {
            $n = $n * 26 + ord($l) - 64;
        }
        return max(0, $n - 1);
    }

    private static function xml(string $contenu): ?\SimpleXMLElement
    {
        if ($contenu === '') {
            return null;
        }
        $avant = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($contenu, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($avant);
        return $xml === false ? null : $xml;
    }

    /** @return array<string,string> nom => contenu, pour les fichiers XML utiles du classeur */
    private static function dezip(string $z): array
    {
        $fin = strrpos($z, "PK\x05\x06");
        if ($fin === false || strlen($z) < $fin + 22) {
            throw new ErreurMetier('reprise.fichier_illisible');
        }
        $e = unpack('vdisque/vdisque2/vici/vnombre/Vtaille/Vposition', substr($z, $fin + 4, 16));
        $p = $e['position'];
        $sortie = [];
        $total = 0;
        for ($i = 0; $i < $e['nombre']; $i++) {
            if (substr($z, $p, 4) !== "PK\x01\x02") {
                throw new ErreurMetier('reprise.fichier_illisible');
            }
            $h = unpack('vmethode', substr($z, $p + 10, 2)) + unpack('Vcompresse/Vtaille/vnom/vextra/vcommentaire', substr($z, $p + 20, 14)) + unpack('Vlocal', substr($z, $p + 42, 4));
            $nom = substr($z, $p + 46, $h['nom']);
            $p += 46 + $h['nom'] + $h['extra'] + $h['commentaire'];
            if (!preg_match('~^(xl/workbook\.xml|xl/_rels/workbook\.xml\.rels|xl/sharedStrings\.xml|xl/worksheets/[^/]+\.xml)$~', $nom)) {
                continue;   // images, styles, macros... : inutiles ici
            }
            $total += $h['taille'];
            if ($total > self::TAILLE_MAX) {
                throw new ErreurMetier('reprise.fichier_trop_gros');
            }
            $l = unpack('vnom/vextra', substr($z, $h['local'] + 26, 4));
            $donnees = substr($z, $h['local'] + 30 + $l['nom'] + $l['extra'], $h['compresse']);
            $contenu = match ($h['methode']) {
                0 => $donnees,
                8 => @gzinflate($donnees, self::TAILLE_MAX),
                default => false,
            };
            if ($contenu === false) {
                throw new ErreurMetier('reprise.fichier_illisible');
            }
            $sortie[$nom] = $contenu;
        }
        return $sortie;
    }

    /** Date d'une cellule : numero de serie Excel, "31/12/2025" ou "2025-12-31". Renvoie AAAA-MM-JJ ou null. */
    public static function date(string $v): ?string
    {
        $v = trim($v);
        if ($v === '') {
            return null;
        }
        if (is_numeric($v) && (float) $v > 18264 && (float) $v < 80000) {
            return gmdate('Y-m-d', (int) round(((float) $v - 25569) * 86400));
        }
        if (preg_match('~^(\d{4})-(\d{1,2})-(\d{1,2})~', $v, $m)) {
            return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]) : null;
        }
        if (preg_match('~^(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{2,4})$~', $v, $m)) {
            $annee = (int) $m[3] < 100 ? 2000 + (int) $m[3] : (int) $m[3];
            return checkdate((int) $m[2], (int) $m[1], $annee) ? sprintf('%04d-%02d-%02d', $annee, $m[2], $m[1]) : null;
        }
        return null;
    }
}
