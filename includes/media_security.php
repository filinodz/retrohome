<?php
/**
 * Sécurité des fichiers déposés dans roms/ et assets/ (ROMs, jaquettes, vidéos).
 *
 * Ces dossiers sont servis par le serveur web : un fichier avec une extension
 * exécutable (.php…) y serait exécuté. On n'accepte donc que des extensions
 * connues, et l'extension des médias téléchargés est déduite de leur CONTENU.
 */

/** Extensions de ROM / images disque acceptées (tous systèmes EmulatorJS). */
const RH_ROM_EXTENSIONS = [
    'zip', '7z', 'bin', 'cue', 'iso', 'img', 'chd', 'pbp', 'ccd', 'mds', 'm3u',
    'nes', 'fds', 'unf', 'unif', 'sfc', 'smc', 'fig', 'swc', 'n64', 'z64', 'v64', 'nds',
    'gb', 'gbc', 'gba', 'vb', 'md', 'gen', 'smd', 'mgd', '32x', 'sms', 'gg', 'sg',
    'pce', 'sgx', 'ngp', 'ngc', 'ws', 'wsc', 'col', 'a26', 'a52', 'a78', 'j64', 'jag',
    'lnx', 'vec', 'int', 'rom', 'mx1', 'mx2', 'dsk', 'cas', 'd64', 't64', 'prg', 'tap',
    'adf', 'st', 'min', 'o2', 'chf', 'cdi', 'gdi', 'neo',
];

const RH_IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
const RH_VIDEO_EXTENSIONS = ['mp4', 'webm'];

function rh_is_allowed_rom_ext(string $ext): bool
{
    return in_array(strtolower($ext), RH_ROM_EXTENSIONS, true);
}

/** Extension d'image d'après la signature binaire, ou null si ce n'est pas une image. */
function rh_image_ext_from_bytes(string $head): ?string
{
    if (strncmp($head, "\x89PNG", 4) === 0) return 'png';
    if (strncmp($head, "\xFF\xD8\xFF", 3) === 0) return 'jpg';
    if (strncmp($head, 'GIF8', 4) === 0) return 'gif';
    if (strncmp($head, 'RIFF', 4) === 0 && substr($head, 8, 4) === 'WEBP') return 'webp';
    return null;
}

/**
 * Corrige l'extension d'une image téléchargée selon son contenu.
 * Renvoie le nouveau chemin (fichier renommé), ou null si ce n'est pas une image
 * (le fichier est alors supprimé).
 */
function rh_fix_image_file(string $path): ?string
{
    if (!is_file($path)) return null;
    $head = (string)@file_get_contents($path, false, null, 0, 16);
    $ext = rh_image_ext_from_bytes($head);
    if ($ext === null) {
        @unlink($path);
        return null;
    }
    $cur = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($cur === $ext || ($cur === 'jpeg' && $ext === 'jpg')) return $path;
    $new = substr($path, 0, strlen($path) - strlen($cur)) . $ext;
    if ($cur === '') $new = $path . '.' . $ext;
    if (@rename($path, $new)) return $new;
    return $path;
}

/** Extension sûre pour un média d'après une URL (repli si absente/non autorisée). */
function rh_safe_media_ext(string $url, string $default, array $allowed): string
{
    $ext = strtolower(pathinfo((string)parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
    return in_array($ext, $allowed, true) ? $ext : $default;
}
