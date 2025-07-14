<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('salutation')->nullable()->after('member_number');
            $table->string('address_addition')->nullable()->after('address');
            $table->string('iban')->nullable()->after('country');
            $table->string('account_holder')->nullable()->after('iban');
            $table->boolean('sepa_mandate_accepted')->default(false)->after('account_holder');
            $table->timestamp('sepa_mandate_date')->nullable()->after('sepa_mandate_accepted');
            $table->string('voucher_code')->nullable()->after('sepa_mandate_date');
        });
    }

    public function down()
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn([
                'salutation',
                'address_addition',
                'iban',
                'account_holder',
                'sepa_mandate_accepted',
                'sepa_mandate_date',
                'voucher_code'
            ]);
        });
    }
};
