<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TcnRelayClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TcnRelayClientController extends Controller
{
    public function index(): View
    {
        $clients = TcnRelayClient::latest()->get();
        return view('admin.tcn-relay-clients.index', compact('clients'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'   => 'required|string|max:255',
            'domain' => 'required|url|max:255|unique:tcn_relay_clients,domain',
            'notes'  => 'nullable|string|max:1000',
        ]);

        $data['domain']    = rtrim($data['domain'], '/');
        $data['is_active'] = true;

        TcnRelayClient::create($data);

        return back()->with('success', 'Client "' . $data['name'] . '" added to relay whitelist.');
    }

    public function update(Request $request, TcnRelayClient $tcnRelayClient): RedirectResponse
    {
        $data = $request->validate([
            'name'   => 'required|string|max:255',
            'domain' => 'required|url|max:255|unique:tcn_relay_clients,domain,' . $tcnRelayClient->id,
            'notes'  => 'nullable|string|max:1000',
        ]);

        $data['domain'] = rtrim($data['domain'], '/');
        $tcnRelayClient->update($data);

        return back()->with('success', 'Client updated.');
    }

    public function toggle(TcnRelayClient $tcnRelayClient): JsonResponse
    {
        $tcnRelayClient->update(['is_active' => !$tcnRelayClient->is_active]);

        return response()->json([
            'is_active' => $tcnRelayClient->is_active,
            'label'     => $tcnRelayClient->is_active ? 'Active' : 'Inactive',
        ]);
    }

    public function destroy(TcnRelayClient $tcnRelayClient): RedirectResponse
    {
        $name = $tcnRelayClient->name;
        $tcnRelayClient->delete();

        return back()->with('success', '"' . $name . '" removed from relay whitelist.');
    }
}
