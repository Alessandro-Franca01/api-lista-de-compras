<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class FriendshipController extends Controller
{
    /**
     * Enviar solicitação de amizade
     */
    public function sendRequest(Request $request, User $friend)
    {
        // Verifica se não está tentando adicionar a si mesmo
        if ($request->user()->id === $friend->id) {
            return response()->json([
                'message' => 'Você não pode enviar solicitação de amizade para si mesmo'
            ], 400);
        }

        // Verifica se já existe uma solicitação
        if ($request->user()->friends()->where('friend_id', $friend->id)->exists() ||
            $request->user()->pendingFriendRequests()->where('user_id', $friend->id)->exists()) {
            return response()->json([
                'message' => 'Solicitação de amizade já existe'
            ], 400);
        }

        $request->user()->friends()->attach($friend->id, ['status' => 'pending']);

        return response()->json([
            'message' => 'Solicitação de amizade enviada'
        ]);
    }

    /**
     * Aceitar solicitação de amizade
     */
    public function acceptRequest(Request $request, User $friend)
    {
        $request->user()->pendingFriendRequests()
            ->where('user_id', $friend->id)
            ->updateExistingPivot($friend->id, ['status' => 'accepted']);

        return response()->json([
            'message' => 'Solicitação de amizade aceita'
        ]);
    }

    /**
     * Rejeitar/cancelar solicitação de amizade
     */
    public function rejectRequest(Request $request, User $friend)
    {
        $request->user()->pendingFriendRequests()
            ->where('user_id', $friend->id)
            ->updateExistingPivot($friend->id, ['status' => 'rejected']);

        return response()->json([
            'message' => 'Solicitação de amizade rejeitada'
        ]);
    }

    /**
     * Remover amizade
     */
    public function removeFriend(Request $request, User $friend)
    {
        $request->user()->friends()->detach($friend->id);
        
        return response()->json([
            'message' => 'Amizade removida com sucesso'
        ]);
    }

    /**
     * Listar amigos
     */
    public function listFriends(Request $request)
    {
        return response()->json([
            'friends' => $request->user()->friends,
            'pending_requests' => $request->user()->pendingFriendRequests,
            'sent_requests' => $request->user()->sentFriendRequests
        ]);
    }
} 