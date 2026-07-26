<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Collapsing agent_type to a single constant value means conversations that
        // used to be distinct per (user, document, agent_type) — e.g. one document_qa
        // and one math_solver conversation for the same document — would now collide
        // on the (user_id, document_id, agent_type) unique constraint. Merge those
        // duplicates first: keep the oldest conversation per (user, document) and
        // reassign the other conversations' messages onto it before deleting them,
        // so no message history is lost.
        $duplicateGroups = DB::table('conversations')
            ->select('id', 'user_id', 'document_id')
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($row) => $row->user_id.':'.($row->document_id ?? 'null'))
            ->filter(fn ($group) => $group->count() > 1);

        foreach ($duplicateGroups as $group) {
            $keeperId = $group->first()->id;
            $duplicateIds = $group->slice(1)->pluck('id')->all();

            DB::table('messages')->whereIn('conversation_id', $duplicateIds)->update(['conversation_id' => $keeperId]);
            DB::table('conversations')->whereIn('id', $duplicateIds)->delete();
        }

        // Raw SQL to avoid requiring doctrine/dbal just for a default-value change.
        // Sqlite (used in tests) has no ALTER COLUMN SET DEFAULT; the app layer
        // (ConversationFactory, CreateConversation) already writes 'orchestrator'.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE conversations ALTER COLUMN agent_type SET DEFAULT 'orchestrator'");
        }

        DB::table('conversations')->update(['agent_type' => 'orchestrator']);
    }

    public function down(): void
    {
        // The agent_type merge above is destructive (conversations were combined);
        // only the default value is reversible.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE conversations ALTER COLUMN agent_type SET DEFAULT 'document_qa'");
        }
    }
};
