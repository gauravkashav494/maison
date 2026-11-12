<?php

namespace App\Http\Controllers\Api;

use App\Clients\ClientContext;
use App\Http\Controllers\Controller;
use App\Models\SyncAgent;
use App\Sync\AgentPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/** Keeps an installed agent current, so it never has to be downloaded again. */
class SyncAgentController extends Controller
{
    private function agent(): SyncAgent
    {
        return SyncAgent::forClient(app(ClientContext::class)->client());
    }

    public function config(): JsonResponse
    {
        return response()->json(['mapping' => $this->agent()->mapping]);
    }

    public function package(Request $request, AgentPackage $package): JsonResponse
    {
        $arch = $this->arch($request);

        if (hash_equals((string) $package->version($arch), (string) $request->query('have'))) {
            return response()->json(['current' => true]);
        }
        if (! $package->isSigned($arch)) {
            return response()->json(['message' => 'No signed agent is available.'], 503);
        }

        return response()->json([
            'current' => false,
            'version' => $package->version($arch),
            'signature' => $package->signature($arch),
        ]);
    }

    public function binary(Request $request, AgentPackage $package): Response
    {
        $arch = $this->arch($request);
        abort_unless($package->isSigned($arch), 503, 'No signed agent is available.');

        return response()->file($package->binaryPath($arch), ['Content-Type' => 'application/octet-stream']);
    }

    private function arch(Request $request): string
    {
        return $request->validate(['arch' => ['required', Rule::in(AgentPackage::ARCHITECTURES)]])['arch'];
    }

    public function schema(Request $request): JsonResponse
    {
        $data = $request->validate(['tables' => ['present', 'array', 'max:5000']]);
        $this->agent()->update(['schema_snapshot' => $data['tables']]);

        return response()->json(['stored' => count($data['tables'])]);
    }

    public function report(Request $request): JsonResponse
    {
        $data = $request->validate([
            'agent_version' => ['nullable', 'string', 'max:64'],
            'ok' => ['required', 'boolean'],
            'message' => ['nullable', 'string', 'max:1000'],
            'counts' => ['nullable', 'array'],
            'errors' => ['nullable', 'array', 'max:50'],
            'errors.*' => ['string', 'max:1000'],
        ]);

        $this->agent()->update([
            'agent_version' => $data['agent_version'] ?? null,
            'last_seen_at' => now(),
            'last_report' => $data,
        ]);

        return response()->json(['stored' => true]);
    }
}
