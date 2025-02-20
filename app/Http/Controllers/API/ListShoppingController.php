<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ListShopping;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ListShoppingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(User $user)
    {
        return [
            'my_lists' => $user->lists,
            'shared_lists' => $user->sharedLists()->with('user')->get()
        ];
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'items' => 'required|array',
            'items.*' => 'string|max:255'
        ]);

        $list = $user->lists()->create($validated);
        return response()->json($list, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\ListShopping  $listShopping
     * @return \Illuminate\Http\Response
     */
    public function show(ListShopping $list)
    {
        return $list->load('user');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\ListShopping  $listShopping
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, ListShopping $list)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'items' => 'sometimes|array',
            'items.*' => 'string|max:255'
        ]);

        $list->update($validated);
        return $list;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\ListShopping  $listShopping
     * @return \Illuminate\Http\Response
     */
    public function destroy(ListShopping $list)
    {
        $list->delete();
        return response()->json(null, 204);
    }

    public function share(Request $request, ListShopping $list)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);

        $list->sharedWith()->syncWithoutDetaching([$request->user_id]);
        
        return response()->json([
            'message' => 'List shared successfully',
            'shared_with' => $list->sharedWith
        ]);
    }

    public function unshare(Request $request, ListShopping $list)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);

        $list->sharedWith()->detach($request->user_id);
        
        return response()->json([
            'message' => 'List unshared successfully'
        ]);
    }
}
