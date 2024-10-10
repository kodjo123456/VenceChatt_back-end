<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddMemberRequest;
use App\Http\Requests\InvitationRequest;
use App\Mail\Invitation2;
use App\Mail\InvitationMail;
use App\Models\Group;
use App\Models\Invitations;
use App\Models\Members;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GroupController extends Controller
{
    public function CreateGroup(Request $request)
    {
        // return response()->json(['messages' => $request->all()]);
        $request->validate([
            'name' => 'required|string|max:255',
            // 'description' => 'string|max:255',
        ]);

        $group = new Group;
        $group->name = $request->name;
        // $group->description = $request->description;
        $group->admin_id = $request->admin_id;

        //move group avatar to public folder
        if ($request->hasFile('avatar')) {
            $avatar = $request->file('avatar');
            $avatarName = time() . '.' . $avatar->getClientOriginalExtension();
            $avatar->move(public_path('uploads'), $avatarName);
            $group->avatar = $avatarName;
        }
        // Créer le groupe
        $group->save();

        // Ajouter l'admin au groupe 
        $member = new Members();
        $member->group_id = $group->id;
        $member->member_id = $request->admin_id;
        $member->save();

        // Retourner les informations du groupe et du membre
        return response()->json([
            'group' => $group,
            'member' => $member,
            'message' => 'Group created successfully, and admin added as a member',
        ], 201);
    }

    public function AddMember(AddMemberRequest $request, $groupId)
    {
        // Valider les données de la requête
        $data = $request->validate([
            'email' => 'required|string|email',
            'sender_id' => 'required|integer',
        ]);
    
        // Recherche l'utilisateur par email
        $memberSearch = User::where('email', $data['email'])->first();
    
        // Obtenir les informations du groupe
        $group = Group::findOrFail($groupId);
    
        // Si l'utilisateur n'existe pas, envoyer une invitation par email
        if (!$memberSearch) {
            $groupLink = url('/register'); // URL pour le registre
            Mail::to($data['email'])->send(new Invitation2($group->name, $groupLink));
    
            return response()->json([
                'message' => 'User not found. An email has been sent to invite them to the group.'
            ], 200);
        }
    
        // Vérifier si l'utilisateur est déjà membre du groupe
        if (Members::where('group_id', $groupId)->where('member_id', $memberSearch->id)->exists()) {
            return response()->json(['message' => 'User is already a member of the group'], 400);
        }
    
        // Ajouter l'utilisateur comme membre du groupe
        $member = new Members();
        $member->group_id = $groupId;
        $member->member_id = $memberSearch->id;
        $member->save();
    
        // Envoyer un email aux autres membres du groupe pour les informer de l'ajout
        $Sender = User::findOrFail($data['sender_id']);
        Mail::to($data['email'])->send(new InvitationMail($Sender->name, $group->name));
    
        return response()->json(['message' => 'Member added successfully'], 200);
    }
    

    // public function addUserToGroup(Request $request, $groupId)
    // {
    //     $data = $request->validate([
    //         'email' => 'required|email',
    //     ]);

    //     $user = User::where('email', $data['email'])->first();

    //     $groupLink = Url('/register');

    //     $group = Groupe::findOrFail($groupId);

    //     if (!$user) {
    //         Mail::to($data['email'])->send(new invitation($group->name, $groupLink));

    //         return response()->json(['message' => 'User not found. An email has been sent to notify the user of their addition to the group.'], 200);
    //     }

    //     if ($group->users()->where('user_id', $user->id)->exists()) {
    //         return response()->json(['message' => 'User is already in the group'], 400);
    //     }



    //     $Crew = Groupe::find($groupId);
    //     $groupe = Groupe::with('users')->find($groupId);
    //     $emails = $groupe->users->pluck('email');
    //     // $Sender = User::find($request->sender_id);
    //     // return response()->json(['message' => $], 201);
    //     foreach ($emails as $email) {

    //         Mail::to($email)->send(new userNotification( $Crew->name));


    //     }
    //     $group->users()->attach($user->id);

    //     //message pour les autres membres



    //     $group->save();


    //     return response()->json(['message' => 'User added to group'], 201);



    // }


    public function SelectGroupOfaMember(Request $request)
    {
        //   return response()->json(['messages' => $request->all()]);
        $userId = $request->member_id;

        if (!$userId) {
            return response()->json(['message' => 'Invalid member id'], 400);
        }

        // Récupère tous les groupes dans lesquels le membre est membre
        $groups = Group::join('members', 'groups.id', '=', 'members.group_id')
            ->where('members.member_id', $userId)
            ->get(['groups.*']); // Sélectionne toutes les colonnes de la table 'groups'

        return response()->json([
            'groups' => $groups,
        ], 200);
    }
    public function DeleteMember($group_id, $member_id)
    {
        // Vérifier si le groupe existe
        $group = Group::find($group_id);
        if (!$group) {
            return response()->json(['message' => 'Group not found'], 404);
        }

        // Vérifier si le membre est déjà dans le groupe
        if (!$group->members()->where('user_id', $member_id)->exists()) {
            return response()->json(['message' => 'Member not found in the group'], 404);
        }

        // Supprimer le membre du groupe
        $group->members()->detach($member_id);

        return response()->json([
            'message' => 'Member deleted successfully',
        ], 200);
    }

    public function InviteMember(InvitationRequest $request)
    {
        $request->validate([
            'group_id' => 'required|integer',
            'email' => 'required|email',
            'id' => 'required|string',
        ]);

        $groupId = $request->group_id;
        $email = $request->email;
        $adderId = $request->id;

        // Vérifiez si le groupe existe
        $group = Group::find($groupId);
        if (!$group) {
            return response()->json(['message' => 'Group not found'], 404);
        }

        // Obtenez les informations sur le groupe et l'adder
        $groupInfo = DB::table('groups')->where('id', $groupId)->first();
        $adder = DB::table('users')->where('id', $adderId)->first();

        if (!$adder) {
            return response()->json(['message' => 'Adder not found'], 404);
        }

        $adderEmail = $adder->email;
        $groupName = $groupInfo->name;

        $invite = new Invitations();
        $invite->group_id = $groupId;
        $invite->email = $email;
        $invite->save();

        Mail::to($email)->send(new Invitation2($adderEmail, $groupName));

        return response()->json(['message' => 'Invitation envoyée'], 200);
    }


    public function memberListForAGroup(Request $request)
    {
        //  return response()->json(['messages' => $request->all()]);
        $request->validate([
            'group_id' => 'required|integer',
        ]);
        $groupId = $request->group_id;

        // Vérifier si le groupe existe
        $group = DB::table('groups')->where('id', $groupId)->first();
        if (!$group) {
            return response()->json(['message' => 'Group not found'], 404);
        }

        // Récupérer les membres du groupe via une requête SQL simple
        $members = DB::table('members')
            ->join('users', 'members.member_id', '=', 'users.id')
            ->where('members.group_id', $groupId)
            ->select('users.id', 'users.name', 'users.email', 'users.avatar') // Sélectionner uniquement les colonnes nécessaires
            ->get();

        return response()->json([
            'group' => $group,   // Informations sur le groupe
            'members' => $members,  // Liste des membres du groupe
        ], 200);
    }
}
