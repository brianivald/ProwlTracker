<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StorageManagementController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'status' => (string) $request->query('status', 'all'),
            'older_than' => (string) $request->query('older_than', ''),
            'date_from' => (string) $request->query('date_from', ''),
            'date_to' => (string) $request->query('date_to', ''),
            'device_id' => (string) $request->query('device_id', 'all'),
        ];

        // 1. Métricas globales de almacenamiento por estado
        $breakdown = [
            'falsa_alarma' => ['label' => 'Falsa alarma', 'count' => 0, 'bytes' => 0],
            'personal' => ['label' => 'Personal autorizado', 'count' => 0, 'bytes' => 0],
            'nueva' => ['label' => 'Pendiente de revisión', 'count' => 0, 'bytes' => 0],
            'intrusion' => ['label' => 'Intrusión confirmada', 'count' => 0, 'bytes' => 0],
        ];

        $totalCount = 0;
        $totalBytes = 0;

        Alert::select(['id', 'status', 'image_path'])->chunk(200, function ($chunk) use (&$breakdown, &$totalCount, &$totalBytes) {
            foreach ($chunk as $item) {
                $statusKey = isset($breakdown[$item->status]) ? $item->status : 'nueva';
                $size = $item->evidenceSizeBytes();
                $breakdown[$statusKey]['count']++;
                $breakdown[$statusKey]['bytes'] += $size;
                $totalCount++;
                $totalBytes += $size;
            }
        });

        // 2. Consulta filtrada
        $filteredQuery = $this->buildFilteredQuery($filters);

        $filteredCount = 0;
        $filteredBytes = 0;
        $filteredNonIntrusionCount = 0;
        $filteredNonIntrusionBytes = 0;

        (clone $filteredQuery)->select(['id', 'status', 'image_path'])->chunk(200, function ($chunk) use (
            &$filteredCount,
            &$filteredBytes,
            &$filteredNonIntrusionCount,
            &$filteredNonIntrusionBytes
        ) {
            foreach ($chunk as $item) {
                $size = $item->evidenceSizeBytes();
                $filteredCount++;
                $filteredBytes += $size;
                if ($item->status !== 'intrusion') {
                    $filteredNonIntrusionCount++;
                    $filteredNonIntrusionBytes += $size;
                }
            }
        });

        $alerts = (clone $filteredQuery)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $devices = Alert::select('device_id')
            ->distinct()
            ->orderBy('device_id')
            ->pluck('device_id');

        return view('storage.index', compact(
            'alerts',
            'filters',
            'breakdown',
            'totalCount',
            'totalBytes',
            'filteredCount',
            'filteredBytes',
            'filteredNonIntrusionCount',
            'filteredNonIntrusionBytes',
            'devices',
        ));
    }

    /**
     * Elimina en lote las alertas que coincidan con los filtros indicados.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $filters = [
            'status' => (string) $request->input('status', 'all'),
            'older_than' => (string) $request->input('older_than', ''),
            'date_from' => (string) $request->input('date_from', ''),
            'date_to' => (string) $request->input('date_to', ''),
            'device_id' => (string) $request->input('device_id', 'all'),
        ];

        $protectIntrusions = $request->boolean('protect_intrusions', true);

        $query = $this->buildFilteredQuery($filters);

        if ($protectIntrusions && $filters['status'] !== 'intrusion') {
            $query->where('status', '!=', 'intrusion');
        }

        $deletedCount = 0;
        $freedBytes = 0;

        $query->chunkById(100, function ($chunk) use (&$deletedCount, &$freedBytes) {
            foreach ($chunk as $alert) {
                $freedBytes += $alert->evidenceSizeBytes();
                if ($alert->deleteWithEvidence()) {
                    $deletedCount++;
                }
            }
        });

        if ($deletedCount === 0) {
            return redirect()
                ->route('storage.index', array_filter($filters, fn ($v) => $v !== '' && $v !== 'all'))
                ->with('status', 'No se encontraron registros que coincidieran con los criterios seleccionados para eliminar.');
        }

        return redirect()
            ->route('storage.index', array_filter($filters, fn ($v) => $v !== '' && $v !== 'all'))
            ->with('status', sprintf(
                'Depuración completada: se eliminaron %d %s y se liberaron %s de almacenamiento.',
                $deletedCount,
                $deletedCount === 1 ? 'registro' : 'registros',
                self::formatBytes($freedBytes),
            ));
    }

    /**
     * Elimina una alerta individual y su archivo físico en disco/S3.
     */
    public function destroy(Alert $alert): RedirectResponse
    {
        $alertId = $alert->id;
        $freed = $alert->evidenceSizeBytes();
        $alert->deleteWithEvidence();

        return back()->with('status', sprintf(
            'Evidencia #%d eliminada junto con su archivo físico (%s liberados).',
            $alertId,
            self::formatBytes($freed),
        ));
    }

    private function buildFilteredQuery(array $filters): Builder
    {
        $query = Alert::query();

        $status = $filters['status'] ?? 'all';
        if ($status === 'non_intrusion') {
            $query->whereIn('status', ['falsa_alarma', 'personal']);
        } elseif (in_array($status, ['falsa_alarma', 'personal', 'nueva', 'intrusion'], true)) {
            $query->where('status', $status);
        }

        $olderThan = (int) ($filters['older_than'] ?? 0);
        if ($olderThan > 0) {
            $query->where('created_at', '<=', now()->subDays($olderThan));
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['device_id']) && $filters['device_id'] !== 'all') {
            $query->where('device_id', $filters['device_id']);
        }

        return $query;
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }
}
