<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RestaurantTable;
use App\Support\TableQrUrl;
use App\Services\TableSessionStatusService;
use App\Services\RestaurantTableQrService;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TableQrController extends Controller
{
    public function index(): View
    {
        $tables = $this->orderedTables();
        $qrEnabled = $this->qrEnabled();
        $activeCount = $qrEnabled ? $tables->where('is_enabled', true)->count() : 0;

        return view('admin.table-qr', compact('tables', 'qrEnabled', 'activeCount'));
    }

    public function toggle(Request $request, RestaurantTable $restaurantTable, RestaurantTableQrService $qr): RedirectResponse
    {
        if (! $qr->isGloballyEnabled()) {
            return back()->with('error', 'Customer QR menus are globally disabled in System Settings.');
        }

        $table = $qr->toggle($restaurantTable, $request->user());

        return back()->with('success', $table->is_enabled
            ? "{$table->name} QR ordering enabled."
            : "{$table->name} QR ordering disabled.");
    }

    public function referenceToggle(Request $request, RestaurantTable $restaurantTable, RestaurantTableQrService $qr): JsonResponse
    {
        if (! $qr->isGloballyEnabled()) {
            return response()->json(['message' => 'Customer QR menus are globally disabled.'], 409);
        }
        return response()->json($this->referenceTable($qr->toggle($restaurantTable, $request->user())));
    }

    public function referenceStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'table_number' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9 -]+$/', 'unique:restaurant_tables,table_number'],
            'name' => ['required', 'string', 'max:80'],
        ]);
        $table = RestaurantTable::create([
            ...$validated,
            'is_enabled' => false,
            'qr_token' => RestaurantTable::generateUniqueQrToken(),
        ]);
        return response()->json($this->referenceTable($table), 201);
    }

    public function referenceUpdate(Request $request, RestaurantTable $restaurantTable): JsonResponse
    {
        $validated = $request->validate([
            'table_number' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9 -]+$/', 'unique:restaurant_tables,table_number,'.$restaurantTable->id],
            'name' => ['required', 'string', 'max:80'],
        ]);
        $restaurantTable->update($validated);
        return response()->json($this->referenceTable($restaurantTable->fresh()));
    }

    public function referenceRegenerate(RestaurantTable $restaurantTable): JsonResponse
    {
        $table = DB::transaction(function () use ($restaurantTable): RestaurantTable {
            $table = RestaurantTable::query()->lockForUpdate()->findOrFail($restaurantTable->id);
            $table->update(['qr_token' => RestaurantTable::generateUniqueQrToken()]);

            return $table->fresh();
        }, 3);

        return response()->json($this->referenceTable($table));
    }

    public function referenceDelete(RestaurantTable $restaurantTable): JsonResponse
    {
        abort_if($restaurantTable->is_enabled, 422, 'Disable QR ordering before deleting this table.');
        $restaurantTable->delete();
        return response()->json(['ok' => true]);
    }

    public function image(Request $request, RestaurantTable $restaurantTable): Response|JsonResponse
    {
        $url = TableQrUrl::forToken($restaurantTable->qr_token);
        if ($url === null) {
            return response()->json(['message' => TableQrUrl::configurationMessage()], 409);
        }

        $svg = $this->qrSvg($url);
        $headers = [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($request->boolean('download')) {
            $filename = 'table-'.Str::slug($restaurantTable->table_number).'-qr.svg';
            $headers['Content-Disposition'] = 'attachment; filename="'.$filename.'"';
        }

        return response($svg, 200, $headers);
    }

    public function printSheet(): View
    {
        $tables = $this->orderedTables();
        $qrUrlConfigured = TableQrUrl::isConfigured();

        return view('admin.table-qr-print', compact('tables', 'qrUrlConfigured'));
    }

    public function downloadSheet(): Response|JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $tables = $this->orderedTables();
        if (! TableQrUrl::isConfigured()) {
            return response()->json(['message' => TableQrUrl::configurationMessage()], 409);
        }

        $path = tempnam(sys_get_temp_dir(), 'passenger-seat-qrs-');
        $zip = new \ZipArchive();
        if ($path === false || $zip->open($path, \ZipArchive::OVERWRITE) !== true) {
            return response()->json(['message' => 'The QR archive could not be created.'], 500);
        }

        foreach ($tables as $table) {
            $zip->addFromString('table-'.Str::slug($table->table_number).'-qr.svg', $this->qrSvg(
                (string) TableQrUrl::forToken($table->qr_token),
            ));
        }
        $zip->close();

        return response()->download($path, 'passenger-seat-table-qrs.zip')->deleteFileAfterSend(true);
    }

    private function qrSvg(string $url): string
    {
        $qrCode = new QrCode(
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 360,
            margin: 16,
        );

        return (new SvgWriter())->write($qrCode)->getString();
    }

    private function orderedTables(): \Illuminate\Database\Eloquent\Collection
    {
        return RestaurantTable::query()->get()
            ->sort(fn (RestaurantTable $a, RestaurantTable $b): int => strnatcasecmp($a->table_number, $b->table_number))
            ->values();
    }

    private function referenceTable(RestaurantTable $table): array
    {
        return [
            'id' => $table->id,
            'name' => $table->name,
            'table_number' => $table->table_number,
            'enabled' => (bool) $table->is_enabled,
            'qr_url' => TableQrUrl::forToken($table->qr_token),
            'qr_image_url' => route('admin.api.reference.table-qr.image', ['restaurantTable' => $table], false),
            'guests' => 0,
            'minutes' => 180,
            ...app(TableSessionStatusService::class)->forTables(collect([$table]))->get($table->id, []),
        ];
    }

    private function qrEnabled(): bool
    {
        return app(RestaurantTableQrService::class)->isGloballyEnabled();
    }
}
