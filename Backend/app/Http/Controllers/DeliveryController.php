<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\StatusHistory;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DeliveryController extends Controller
{
    // Liste des courses avec possibilité de filtrer
    public function index(Request $request)
    {
        $query = Delivery::with('deliverer');

        if ($request->has('statut')) {
            $query->where('statut', $request->statut);
        }

        return response()->json($query->orderBy('created_at', 'desc')->get());
    }

    // Créer une course
    public function store(Request $request)
    {
        $validated = $request->validate([
            'adresse_depart' => 'required|string',
            'adresse_arrivee' => 'required|string',
            'montant' => 'required|numeric|min:0',
            'id_livreur' => 'nullable|exists:deliverers,id_livreur',
            'force' => 'nullable|boolean', // Pour confirmer malgré le conflit d'affectation
        ]);

        // Avertissement de conflit si le livreur a déjà une course en cours
        if (!empty($validated['id_livreur']) && empty($request->force)) {
            $hasOngoingDelivery = Delivery::where('id_livreur', $validated['id_livreur'])
                ->whereIn('statut', ['en_attente', 'prise_en_charge'])
                ->exists();

            if ($hasOngoingDelivery) {
                return response()->json([
                    'warning' => true,
                    'message' => 'Ce livreur a déjà une course en cours. Confirmez-vous cette affectation ?'
                ], 409);
            }
        }

        if (!empty($validated['id_livreur'])) {
            $validated['date_affectation'] = now();
        }

        $delivery = Delivery::create($validated);

        // Historique
        StatusHistory::create([
            'id_course' => $delivery->id_course,
            'ancien_statut' => null,
            'nouveau_statut' => 'en_attente',
        ]);

        return response()->json($delivery, 201);
    }

    // Changer le statut d'une course (RG1, RG3)
    public function changeStatus(Request $request, $id)
    {
        $delivery = Delivery::findOrFail($id);
        $newStatus = $request->input('statut');

        $request->validate([
            'statut' => 'required|in:en_attente,prise_en_charge,livree,annulee',
            'motif_annulation' => 'required_if:statut,annulee|nullable|string',
        ]);

        $oldStatus = $delivery->statut;

        // RG1 : Une course ne peut être livrée sans passer par 'prise_en_charge'
        if ($newStatus === 'livree' && $oldStatus !== 'prise_en_charge') {
            return response()->json([
                'message' => 'RG1 : La course doit être prise en charge avant d’être marquée comme livrée.'
            ], 422);
        }

        // Mettre à jour le statut et les horodatages
        $delivery->statut = $newStatus;

        if ($newStatus === 'prise_en_charge') {
            $delivery->date_prise_en_charge = now();
        } elseif ($newStatus === 'livree') {
            $delivery->date_livraison = now();
        } elseif ($newStatus === 'annulee') {
            $delivery->date_annulation = now();
            $delivery->motif_annulation = $request->motif_annulation;
        }

        $delivery->save();

        // Enregistrer dans l'historique
        StatusHistory::create([
            'id_course' => $delivery->id_course,
            'ancien_statut' => $oldStatus,
            'nouveau_statut' => $newStatus,
        ]);

        return response()->json($delivery);
    }

    // RG3 : Empêcher la modification du montant si livrée
    public function update(Request $request, $id)
    {
        $delivery = Delivery::findOrFail($id);

        if ($delivery->statut === 'livree' && $request->has('montant') && $request->montant != $delivery->montant) {
            return response()->json([
                'message' => 'RG3 : Le montant d’une course livrée ne peut plus être modifié.'
            ], 422);
        }

        $delivery->update($request->only(['adresse_depart', 'adresse_arrivee', 'montant', 'id_livreur']));
        return response()->json($delivery);
    }

    // Suivi du Chiffre d'Affaires (RG4 & RG5)
    public function revenue(Request $request)
    {
        $request->validate([
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_debut',
            'id_livreur' => 'nullable|exists:deliverers,id_livreur',
        ]);

        // RG5 : Basé sur la date_livraison et les courses 'livree' uniquement
        $query = Delivery::where('statut', 'livree')
            ->whereBetween('date_livraison', [
                Carbon::parse($request->date_debut)->startOfDay(),
                Carbon::parse($request->date_fin)->endOfDay()
            ]);

        if ($request->filled('id_livreur')) {
            $query->where('id_livreur', $request->id_livreur);
        }

        $totalRevenue = $query->sum('montant');
        $totalDeliveries = $query->count();

        return response()->json([
            'periode' => ['debut' => $request->date_debut, 'fin' => $request->date_fin],
            'nombre_courses' => $totalDeliveries,
            'chiffre_affaires' => $totalRevenue,
        ]);
    }
}