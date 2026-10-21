<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RestaurantTable;
use App\Models\SystemSetting;
use App\Support\TableQrUrl;
use App\Services\TableSessionStatusService;
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
        $tables = RestaurantTable::query()->orderBy('table_number')->get();
        $qrEnabled = $this->qrEnabled();
        $activeCount = $qrEnabled ? $tables->where('is_enabled', true)->count() : 0;

        return view('admin.table-qr', compact('tables', 'qrEnabled', 'activeCount'));
    }

    public function toggle(RestaurantTable $restaurantTable): RedirectResponse
    {
        if (! $this->qrEnabled()) {
            return back()->with('error', 'Customer QR menus are globally disabled in System Settings.');
        }

        DB::transaction(function () use ($restaurantTable): void {
            $table = RestaurantTable::query()->lockForUpdate()->findOrFail($restaurantTable->id);
            $enabled = ! $table->is_enabled;
            $table->update([
                'is_enabled' => $enabled,
                'qr_token' => $enabled && ! $table->qr_token ? RestaurantTable::generateUniqueQrToken() : $table->qr_token,
            ]);
        }, 3);

        return back()->with('success', $restaurantTable->fresh()->is_enabled
            ? "{$restaurantTable->name} QR ordering enabled."
            : "{$restaurantTable->name} QR ordering disabled.");
    }

    public function referenceToggle(RestaurantTable $restaurantTable): JsonResponse
    {
        if (! $this->qrEnabled()) {
            return response()->json(['message' => 'Customer QR menus are globally disabled.'], 409);
        }
        DB::transaction(function () use ($restaurantTable): void {
            $table = RestaurantTable::query()->lockForUpdate()->findOrFail($restaurantTable->id);
            $table->update([
                'is_enabled' => ! $table->is_enabled,
                'qr_token' => ! $table->is_enabled && ! $table->qr_token ? RestaurantTable::generateUniqueQrToken() : $table->qr_token,
            ]);
        }, 3);
        return response()->json($this->referenceTable($restaurantTable->fresh()));
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

        $qrCode = new QrCode(
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 360,
            margin: 16,
        );
        $svg = (new SvgWriter())->write($qrCode)->getString();
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
        $tables = RestaurantTable::query()->orderBy('table_number')->get();
        $qrUrlConfigured = TableQrUrl::isConfigured();

        return view('admin.table-qr-print', compact('tables', 'qrUrlConfigured'));
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
        $setting = SystemSetting::query()->find('qr_enabled');
        if ($setting === null || $setting->value === null) {
            return true;
        }

        return filter_var($setting->value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    }
}
