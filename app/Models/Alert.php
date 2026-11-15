<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Alert extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'event_type',
        'confidence',
        'image_path',
        'status',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (self $alert): void {
            $alert->deleteEvidenceFile();
        });
    }

    /**
     * Elimina el archivo físico de evidencia del disco configurado (y public por respaldo).
     */
    public function deleteEvidenceFile(): void
    {
        if (blank($this->image_path)) {
            return;
        }

        $disks = array_unique([
            config('prowltracker.evidence_disk', 'public'),
            'public',
        ]);

        foreach ($disks as $diskName) {
            try {
                $disk = Storage::disk($diskName);
                if ($disk->exists($this->image_path)) {
                    $disk->delete($this->image_path);
                }
            } catch (\Throwable) {
                // Ignorar si un disco secundario no está disponible
            }
        }
    }

    /**
     * Elimina tanto el archivo físico como el registro en la base de datos.
     */
    public function deleteWithEvidence(): bool
    {
        $this->deleteEvidenceFile();

        return (bool) $this->delete();
    }

    /**
     * Obtiene el peso en bytes del archivo de evidencia (0 si no existe en disco).
     */
    public function evidenceSizeBytes(): int
    {
        if (blank($this->image_path)) {
            return 0;
        }

        $disks = array_unique([
            config('prowltracker.evidence_disk', 'public'),
            'public',
        ]);

        foreach ($disks as $diskName) {
            try {
                $disk = Storage::disk($diskName);
                if ($disk->exists($this->image_path)) {
                    return (int) $disk->size($this->image_path);
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return 0;
    }
}
