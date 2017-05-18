<?php

use Illuminate\Database\Seeder;
use App\Role;
use App\User;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Membuat role admin
        $adminRole = new Role();
        $adminRole->name = 'admin';
        $adminRole->display_name = 'Admin';
        $adminRole->save();

        // Membuat role member
        $memberRole = new Role();
        $memberRole->name = 'member';
        $memberRole->display_name = 'Member';
        $memberRole->save();

        // Membuat sample admin
        $admin = new User();
        $admin->name = 'Admin Larapus';
        $admin->email = 'admin@larapus.dev';
        $admin->password = bcrypt('admini');
        $admin->save();
        $admin->attachRole($adminRole);

        // Membuat sample member
        $member = new User();
        $member->name = 'Sample Member';
        $member->email = 'member@larapus.dev';
        $member->password = bcrypt('member');
        $member->save();
        $member->attachRole($memberRole);
    }
}
