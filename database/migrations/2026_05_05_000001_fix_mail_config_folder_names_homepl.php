<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fix mail folder names for home.pl (uses uppercase: SENT, TRASH, DRAFTS, SPAM).
 * Adds imap_drafts_folder and imap_spam_folder columns.
 * Updates any existing rows that still have the old lowercase defaults.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_mail_configs', function (Blueprint $table) {
            if (!Schema::hasColumn('crm_mail_configs', 'imap_drafts_folder')) {
                $table->string('imap_drafts_folder')->default('DRAFTS')->after('imap_trash_folder');
            }
            if (!Schema::hasColumn('crm_mail_configs', 'imap_spam_folder')) {
                $table->string('imap_spam_folder')->default('SPAM')->after('imap_drafts_folder');
            }
        });

        // Fix existing rows with wrong (mixed-case) folder names from the old default
        DB::table('crm_mail_configs')
            ->where('imap_sent_folder', 'Sent')
            ->update(['imap_sent_folder' => 'SENT']);

        DB::table('crm_mail_configs')
            ->where('imap_trash_folder', 'Trash')
            ->update(['imap_trash_folder' => 'TRASH']);
    }

    public function down(): void
    {
        Schema::table('crm_mail_configs', function (Blueprint $table) {
            $table->dropColumn(['imap_drafts_folder', 'imap_spam_folder']);
        });

        DB::table('crm_mail_configs')
            ->where('imap_sent_folder', 'SENT')
            ->update(['imap_sent_folder' => 'Sent']);

        DB::table('crm_mail_configs')
            ->where('imap_trash_folder', 'TRASH')
            ->update(['imap_trash_folder' => 'Trash']);
    }
};
