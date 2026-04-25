<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StrukturaHandlowaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $usersData = require __DIR__ . '/data/sales_structure.php';

        $password = Hash::make('123');
        // Retrieve admin once
        $adminUser = User::where('email', 'admin@stratton.pl')->first();
        $processedIds = [];

        // Pass 1: Create or Update Users
        foreach ($usersData as $data) {
            // Determine Role Code
            $rank = strtolower($data['rank'] ?? '');
            
            $roleCode = 'CONSULTANT'; // Default
            if (str_contains($rank, 'dyrektor') || str_contains($rank, 'director')) {
                $roleCode = 'DIRECTOR';
            } elseif (str_contains($rank, 'menadżer') || str_contains($rank, 'kierownik') || str_contains($rank, 'manager')) {
                $roleCode = 'MANAGER';
            } elseif (str_contains($rank, 'handlowiec') || str_contains($rank, 'sales')) {
                $roleCode = 'SALES';
            }

            // Ensure Role exists
            $role = Role::firstOrCreate(['code' => $roleCode], ['name' => ucfirst(strtolower($roleCode))]);

            $user = User::where('email', $data['email'])->first();

            if (!$user) {
                $user = new User();
                $user->email = $data['email'];
                $user->password = $password;
            }

            if (empty($user->supabase_id)) {
                $user->supabase_id = 'dev-user-' . (string) Str::uuid();
            }

            $user->name = $data['name'];
            $user->hierarchical_id = $data['hierarchical_id'];
            $user->rank = $data['rank'];
            $user->role_id = $role->id;
            $user->role_cached = $roleCode;
            
            // Handle Phone
            if (!empty($data['phone'])) {
                $user->phone = $data['phone'];
            }
            
            // Basic defaults
            $user->active = true;
            $user->enabled = true;
            $user->is_removed_from_structure = false; // Ensure they are visible
            
            $user->save();
            $processedIds[] = $user->id;
        }

        // Pass 2: Link Parents
        foreach ($usersData as $data) {
            $user = User::where('hierarchical_id', $data['hierarchical_id'])->first();
            
            $parent = null;
            if (!empty($data['parent_hierarchical_id'])) {
                $parent = User::where('hierarchical_id', $data['parent_hierarchical_id'])->first();
            }

            // Fallback: If no parent defined OR parent not found, assign to Admin (Root)
            // Unless the user intends to be root (e.g. they are their own parent or no parent specified)
            // But strict hierarchy usually implies one root. 
            // In this specific dataset, GDJJ is top. Parent is GDJJ. So GDJJ is parent of self?
            // "GDJJ,GDJJ,dyrektor..." -> user ID GDJJ, Parent ID GDJJ.
            
            if ($user) {
                if ($parent && $parent->id !== $user->id) {
                    $user->parent_id = $parent->id;
                    $user->parent_supabase_id = $parent->supabase_id;
                } else {
                    // Start of tree or detached
                    // Attach to System Admin if not self-referencing to keep tree unified
                    if ($adminUser && $user->id !== $adminUser->id) {
                         $user->parent_id = $adminUser->id;
                         $user->parent_supabase_id = $adminUser->supabase_id;
                    } else {
                         $user->parent_id = null;
                         $user->parent_supabase_id = null;
                    }
                }
                $user->save();
            }
        }

        // Pass 3: Cleanup old users (Hide them)
        if ($adminUser) {
            $processedIds[] = $adminUser->id;
        }
        
        // Mark anyone not in this import as removed
        User::whereNotIn('id', $processedIds)
            ->where('role_cached', '!=', 'ADMIN') // Safety check
            ->update(['is_removed_from_structure' => true]);
            
        Log::info('StrukturaHandlowaSeeder: Imported ' . count($processedIds) . ' users.');
    }
}
