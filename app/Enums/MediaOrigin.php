<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasBadge;

/**
 * Probable origin of an uploaded media, guessed from its hidden metadata.
 * An indication for the organizer, never a proof (metadata can be edited).
 */
enum MediaOrigin: string implements HasBadge
{
    use EnumHelpers;

    case Device = 'appareil';
    case EditingApp = 'montage';
    case Platform = 'plateforme';
    case Reencoded = 'reencode';
    case Stripped = 'efface';
    case Unknown = 'inconnu';

    public function label(): string
    {
        return match ($this) {
            self::Device => 'Filmé avec un appareil',
            self::EditingApp => 'Passé par une app de montage',
            self::Platform => 'Provient d\'une plateforme',
            self::Reencoded => 'Ré-encodé',
            self::Stripped => 'Métadonnées effacées',
            self::Unknown => 'Origine inconnue',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Device => 'green',
            self::EditingApp => 'blue',
            self::Platform => 'red',
            self::Reencoded, self::Stripped => 'amber',
            self::Unknown => 'gray',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Device => 'Fichier original d\'un téléphone ou d\'une caméra.',
            self::EditingApp => 'Exporté par une application de montage : la date peut être celle de l\'export.',
            self::Platform => 'Traces d\'un réseau social (téléchargé ou ré-enregistré depuis une plateforme).',
            self::Reencoded => 'Converti par un logiciel (réseau social, messagerie ou convertisseur) : l\'appareil d\'origine n\'est plus visible.',
            self::Stripped => 'Aucune information cachée : typique de WhatsApp, d\'un téléchargement ou d\'une capture d\'écran.',
            self::Unknown => 'Format sans métadonnées exploitables (ex. certains fichiers audio).',
        };
    }
}
