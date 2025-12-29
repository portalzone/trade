<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates ledger_entries table - immutable double-entry ledger (source of truth for all money movement)
     * CRITICAL: Every financial transaction creates TWO entries (debit + credit) with same transaction_id
     */
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            
            // Transaction grouping (UUID to group related debit + credit)
            $table->uuid('transaction_id');
            
            // Wallet reference
            $table->foreignId('wallet_id')->constrained('wallets')->onDelete('restrict');
            
            // Entry type
            $table->enum('type', ['DEBIT', 'CREDIT']);
            
            // Amount (always positive)
            $table->decimal('amount', 15, 2);
            
            // Description
            $table->string('description', 255);
            
            // Reference to source entity
            $table->string('reference_table', 50)->nullable();
            $table->bigInteger('reference_id')->nullable();
            
            // Timestamp (immutable - no updated_at)
            $table->timestamp('created_at')->useCurrent();
            
            // Indexes for performance
            $table->index('wallet_id');
            $table->index('transaction_id');
            $table->index(['reference_table', 'reference_id']);
            $table->index('created_at'); // For partitioning later
        });

        // Add check constraint (PostgreSQL specific)
        DB::statement('ALTER TABLE ledger_entries ADD CONSTRAINT chk_ledger_amount_positive CHECK (amount > 0)');

        // Create trigger function to validate double-entry balance
        DB::statement("
            CREATE OR REPLACE FUNCTION validate_ledger_balance()
            RETURNS TRIGGER AS $$
            DECLARE
                debit_total DECIMAL(15, 2);
                credit_total DECIMAL(15, 2);
            BEGIN
                -- Calculate totals for this transaction_id
                SELECT 
                    COALESCE(SUM(CASE WHEN type = 'DEBIT' THEN amount ELSE 0 END), 0),
                    COALESCE(SUM(CASE WHEN type = 'CREDIT' THEN amount ELSE 0 END), 0)
                INTO debit_total, credit_total
                FROM ledger_entries
                WHERE transaction_id = NEW.transaction_id;
                
                -- If both sides exist and don't match, raise error
                IF debit_total > 0 AND credit_total > 0 THEN
                    IF ABS(debit_total - credit_total) > 0.01 THEN
                        RAISE EXCEPTION 'Ledger imbalance for transaction %: Debits (%) != Credits (%)', 
                            NEW.transaction_id, debit_total, credit_total;
                    END IF;
                END IF;
                
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        // Create trigger (fires AFTER INSERT to allow both entries to be created)
        DB::statement("
            CREATE TRIGGER check_ledger_balance
            AFTER INSERT ON ledger_entries
            FOR EACH ROW
            EXECUTE FUNCTION validate_ledger_balance();
        ");

        // Prevent UPDATE and DELETE (immutable ledger)
        DB::statement("
            CREATE OR REPLACE FUNCTION prevent_ledger_modifications()
            RETURNS TRIGGER AS $$
            BEGIN
                RAISE EXCEPTION 'Ledger entries are immutable. Create reversal entries instead.';
            END;
            $$ LANGUAGE plpgsql;
        ");

        DB::statement("
            CREATE TRIGGER prevent_ledger_update
            BEFORE UPDATE ON ledger_entries
            FOR EACH ROW
            EXECUTE FUNCTION prevent_ledger_modifications();
        ");

        DB::statement("
            CREATE TRIGGER prevent_ledger_delete
            BEFORE DELETE ON ledger_entries
            FOR EACH ROW
            EXECUTE FUNCTION prevent_ledger_modifications();
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop triggers first
        DB::statement('DROP TRIGGER IF EXISTS check_ledger_balance ON ledger_entries');
        DB::statement('DROP TRIGGER IF EXISTS prevent_ledger_update ON ledger_entries');
        DB::statement('DROP TRIGGER IF EXISTS prevent_ledger_delete ON ledger_entries');
        
        // Drop functions
        DB::statement('DROP FUNCTION IF EXISTS validate_ledger_balance()');
        DB::statement('DROP FUNCTION IF EXISTS prevent_ledger_modifications()');
        
        // Drop table
        Schema::dropIfExists('ledger_entries');
    }
};