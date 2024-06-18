<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Administration\EmployeeRequestType;
use App\Models\Administration\EmployeeRequestTypeElement;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('adm_request_type_elements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('adm_request_type_id');
            $table->foreign('adm_request_type_id')->references('id')->on('adm_request_types');
            $table->string('name');
            $table->string('data_type');
            $table->timestamp('created_at', 0)->useCurrent();
            $table->timestamp('updated_at', 0)->useCurrent();
            $table->softDeletes();
        });

        $type = EmployeeRequestType::create([
            'id' => 1,
            'name' => 'Solicitud de Creación de Usuario'
        ]);

        EmployeeRequestTypeElement::created([
            'id' => 1,
            'adm_request_type_id' => $type->id,
            'name' => 'Computadora de Escritorio',
            'data_type' => 'boolean',
        ]);

        EmployeeRequestTypeElement::created([
            'id' => 2,
            'adm_request_type_id' => $type->id,
            'name' => 'Computadora Portátil',
            'data_type' => 'boolean',
        ]);

        EmployeeRequestTypeElement::created([
            'id' => 3,
            'adm_request_type_id' => $type->id,
            'name' => 'Teléfono móvil',
            'data_type' => 'boolean',
        ]);

        EmployeeRequestTypeElement::created([
            'id' => 4,
            'adm_request_type_id' => $type->id,
            'name' => 'Correo institucional',
            'data_type' => 'boolean',
        ]);

        EmployeeRequestTypeElement::created([
            'id' => 5,
            'adm_request_type_id' => $type->id,
            'name' => 'Teléfono IP',
            'data_type' => 'boolean',
        ]);

        $type = EmployeeRequestType::create([
            'id' => 2,
            'name' => 'Solicitud de Baja de Usuario y Empleado'
        ]);

        EmployeeRequestTypeElement::create([
            'id' => 6,
            'adm_request_type_id' => $type->id,
            'name' => 'Justificación',
            'data_type' => 'string',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adm_request_type_elements');
    }
};
