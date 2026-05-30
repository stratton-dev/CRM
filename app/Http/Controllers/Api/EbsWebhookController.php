<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Commission\CommissionCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Stub odbierający webhook z systemu EBS po wystawieniu faktury / opłaceniu
 * okresu rozliczeniowego klienta. EBS wysyła:
 *   {
 *     ebs_invoice_id: string,
 *     period: "2026-10",
 *     handlowiec_email: "i.damps@stratton-prime.pl",  // ALBO supabase_id
 *     fee_amount: 10000.00                            // kwota brutto wpadła do Stratton
 *   }
 *
 * Autentykacja: shared secret w nagłówku X-EBS-Secret (skonfigurowany w
 * config/ebs.php → EBS_WEBHOOK_SECRET na obu stronach).
 *
 * STATUS: stub — endpoint istnieje i działa, ale wymaga zaplanowania
 * pełnej integracji od strony EBS. Po stronie CRM wszystko gotowe.
 */
class EbsWebhookController extends Controller
{
    public function __construct(private readonly CommissionCalculatorService $calculator)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $expectedSecret = (string) config('ebs.webhook_secret', '');
        $providedSecret = (string) $request->header('X-EBS-Secret', '');

        if ($expectedSecret === '' || !hash_equals($expectedSecret, $providedSecret)) {
            Log::warning('EBS webhook unauthorized', [
                'ip' => $request->ip(),
                'has_secret' => $providedSecret !== '',
            ]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'ebs_invoice_id' => 'required|string|max:255',
            'period' => 'required|string|max:16',
            'fee_amount' => 'required|numeric|min:0',
            // Akceptujemy lookup po obu — co kolwiek EBS wyśle
            'handlowiec_supabase_id' => 'required_without:handlowiec_email|string|max:255',
            'handlowiec_email' => 'required_without:handlowiec_supabase_id|email|max:255',
        ]);

        $userQuery = User::query();
        if (!empty($data['handlowiec_supabase_id'])) {
            $userQuery->where('supabase_id', $data['handlowiec_supabase_id']);
        } else {
            $userQuery->where('email', strtolower($data['handlowiec_email']));
        }
        $user = $userQuery->first();

        if (!$user) {
            return response()->json(['error' => 'Handlowiec not found in CRM'], 404);
        }

        $distribution = $this->calculator->persistDistribution(
            $user->supabase_id,
            (float) $data['fee_amount'],
            'EBS_WEBHOOK',
            $data['period'],
            $data['ebs_invoice_id'],
            null,
            null
        );

        Log::info('EBS webhook persisted distribution', [
            'distribution_id' => $distribution->id,
            'ebs_invoice_id' => $data['ebs_invoice_id'],
        ]);

        return response()->json([
            'received' => true,
            'distribution_id' => $distribution->id,
            'items_count' => $distribution->items->count(),
            'total_paid' => $distribution->items->sum('amount'),
        ], 201);
    }
}
