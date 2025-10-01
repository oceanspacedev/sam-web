<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Composite indexes untuk outlets (improve role-based queries)
        Schema::table('outlets', function (Blueprint $table) {
            // Index untuk ASM queries (badanusaha + divisi)
            $table->index(['badanusaha_id', 'divisi_id'], 'outlets_bu_div_idx');

            // Index untuk ASC queries (badanusaha + divisi + region)
            $table->index(['badanusaha_id', 'divisi_id', 'region_id'], 'outlets_bu_div_reg_idx');

            // Index untuk DSF/DM queries (badanusaha + divisi + region + cluster)
            $table->index(['badanusaha_id', 'divisi_id', 'region_id', 'cluster_id'], 'outlets_bu_div_reg_clus_idx');

            // Index untuk soft delete queries
            $table->index(['deleted_at', 'badanusaha_id'], 'outlets_deleted_bu_idx');
        });

        // Composite indexes untuk users
        Schema::table('users', function (Blueprint $table) {
            $table->index(['badanusaha_id', 'divisi_id'], 'users_bu_div_idx');
            $table->index(['badanusaha_id', 'divisi_id', 'region_id'], 'users_bu_div_reg_idx');
            $table->index(['badanusaha_id', 'divisi_id', 'region_id', 'cluster_id'], 'users_bu_div_reg_clus_idx');
            $table->index(['deleted_at', 'role_id'], 'users_deleted_role_idx');
        });

        // Composite indexes untuk noos (register)
        Schema::table('noos', function (Blueprint $table) {
            $table->index(['badanusaha_id', 'divisi_id'], 'noos_bu_div_idx');
            $table->index(['badanusaha_id', 'divisi_id', 'region_id'], 'noos_bu_div_reg_idx');
            $table->index(['badanusaha_id', 'divisi_id', 'region_id', 'cluster_id'], 'noos_bu_div_reg_clus_idx');
            $table->index(['status', 'deleted_at'], 'noos_status_deleted_idx');
        });

        // Index untuk visits (improve monitoring queries)
        Schema::table('visits', function (Blueprint $table) {
            $table->index(['user_id', 'tanggal_visit'], 'visits_user_date_idx');
            $table->index(['outlet_id', 'tanggal_visit'], 'visits_outlet_date_idx');
            $table->index(['deleted_at', 'tanggal_visit'], 'visits_deleted_date_idx');
        });

        // Index untuk plan_visits
        Schema::table('plan_visits', function (Blueprint $table) {
            $table->index(['user_id', 'tanggal_visit'], 'plan_visits_user_date_idx');
            $table->index(['outlet_id', 'tanggal_visit'], 'plan_visits_outlet_date_idx');
            $table->index(['deleted_at', 'tanggal_visit'], 'plan_visits_deleted_date_idx');
        });

        // Index untuk sync API queries (timestamp-based)
        Schema::table('outlets', function (Blueprint $table) {
            $table->index(['updated_at', 'deleted_at'], 'outlets_updated_deleted_idx');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index(['updated_at', 'deleted_at'], 'users_updated_deleted_idx');
        });

        Schema::table('visits', function (Blueprint $table) {
            $table->index(['updated_at', 'deleted_at'], 'visits_updated_deleted_idx');
        });

        Schema::table('plan_visits', function (Blueprint $table) {
            $table->index(['updated_at', 'deleted_at'], 'plan_visits_updated_deleted_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->dropIndex('outlets_bu_div_idx');
            $table->dropIndex('outlets_bu_div_reg_idx');
            $table->dropIndex('outlets_bu_div_reg_clus_idx');
            $table->dropIndex('outlets_deleted_bu_idx');
            $table->dropIndex('outlets_updated_deleted_idx');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_bu_div_idx');
            $table->dropIndex('users_bu_div_reg_idx');
            $table->dropIndex('users_bu_div_reg_clus_idx');
            $table->dropIndex('users_deleted_role_idx');
            $table->dropIndex('users_updated_deleted_idx');
        });

        Schema::table('noos', function (Blueprint $table) {
            $table->dropIndex('noos_bu_div_idx');
            $table->dropIndex('noos_bu_div_reg_idx');
            $table->dropIndex('noos_bu_div_reg_clus_idx');
            $table->dropIndex('noos_status_deleted_idx');
        });

        Schema::table('visits', function (Blueprint $table) {
            $table->dropIndex('visits_user_date_idx');
            $table->dropIndex('visits_outlet_date_idx');
            $table->dropIndex('visits_deleted_date_idx');
            $table->dropIndex('visits_updated_deleted_idx');
        });

        Schema::table('plan_visits', function (Blueprint $table) {
            $table->dropIndex('plan_visits_user_date_idx');
            $table->dropIndex('plan_visits_outlet_date_idx');
            $table->dropIndex('plan_visits_deleted_date_idx');
            $table->dropIndex('plan_visits_updated_deleted_idx');
        });
    }
};
