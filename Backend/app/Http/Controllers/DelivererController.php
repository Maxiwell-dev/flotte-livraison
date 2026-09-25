<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deliverer;
use Illuminate\Http\Request;

class DelivererController extends Controller
{
    // Lister tous les livreurs
    public function index()
    {
        return response()->json(Deliverer::all());
    }

    // Créer un livreur
    public function store(Request $request)
    {
        $validated = $request->validate([
            'prenom' => 'required|string',
            'nom' => 'required|string',
            'telephone' => 'required|string',
            'vehicule_info' => 'required|string',
            'zone' => 'required|string',
        ]);

        $deliverer = Deliverer::create($validated);
        return response()->json($deliverer, 201);
    }

    // Afficher un livreur
    public function show($id)
    {
        $deliverer = Deliverer::findOrFail($id);
        return response()->json($deliverer);
    }

    // Modifier un livreur
    public function update(Request $request, $id)
    {
        $deliverer = Deliverer::findOrFail($id);

        $validated = $request->validate([
            'prenom' => 'sometimes|string',
            'nom' => 'sometimes|string',
            'telephone' => 'sometimes|string',
            'vehicule_info' => 'sometimes|string',
            'zone' => 'sometimes|string',
            'est_actif' => 'sometimes|boolean',
        ]);

        $deliverer->update($validated);
        return response()->json($deliverer);
    }

    // RG2 : Retirer un livreur (Désactivation/Suppression)
    public function destroy($id)
    {
        $deliverer = Deliverer::findOrFail($id);

        // RG2 : Vérifier si le livreur a une course en cours ou en attente
        $hasActiveDeliveries = $deliverer->deliveries()
            ->whereIn('statut', ['en_attente', 'prise_en_charge'])
            ->exists();

        if ($hasActiveDeliveries) {
            return response()->json([
                'message' => 'Impossible de retirer ce livreur car il a au moins une course en cours ou en attente (RG2).'
            ], 422);
        }

        // On passe est_actif à false au lieu de supprimer physiquement pour garder l'historique
        $deliverer->update(['est_actif' => false]);

        return response()->json(['message' => 'Livreur retiré avec succès.']);
    }
}