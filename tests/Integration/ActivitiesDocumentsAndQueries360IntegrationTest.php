<?php

use App\Livewire\ActivitiesPage;
use App\Livewire\DocumentsPage;
use App\Livewire\QueriesPage;
use App\Models\InstitutionalDocument;
use App\Models\RecordAttachment;
use App\Models\Student;
use App\Models\User;
use App\Models\Workshop;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    Storage::fake('local');
});

it('integrates workshops attendance, versioned document repository without physical delete, and Ventanilla 360 rapid queries', function () {
    $admin = User::where('email', 'superadmin@cecap.or.cr')->firstOrFail();

    $student = Student::create([
        'student_code' => Student::nextCode(),
        'first_name' => 'Valeria',
        'last_name' => 'Araya',
        'identification_type' => 'cedula_nacional',
        'identification_number' => '1-6644-3322',
        'email' => 'valeria.araya@correo.cr',
        'phone' => '8866-4433',
        'gender' => 'femenino',
    ]);

    // 1. Talleres y participantes
    Livewire::actingAs($admin)
        ->test(ActivitiesPage::class)
        ->set('title', 'Taller de Finanzas para Emprendedoras')
        ->set('facilitator_name', 'Licda. Andrea Solís')
        ->set('event_date', now()->addDays(3)->toDateString())
        ->set('start_time', '09:00')
        ->set('end_time', '12:00')
        ->call('createWorkshop')
        ->assertHasNoErrors();

    $workshop = Workshop::firstOrFail();

    Livewire::actingAs($admin)
        ->test(ActivitiesPage::class)
        ->call('selectWorkshop', $workshop->id)
        ->set('participant_student_id', $student->id)
        ->call('addParticipant')
        ->assertHasNoErrors();

    expect($workshop->participants()->count())->toBe(1);

    // 2. Repositorio documental con versionado v1 -> v2 sin borrado físico
    Livewire::actingAs($admin)
        ->test(DocumentsPage::class)
        ->set('title', 'Reglamento Académico CeCap')
        ->set('category', 'reglamento')
        ->set('visibility', 'alumnas')
        ->set('file', UploadedFile::fake()->create('reglamento_v1.pdf', 110, 'application/pdf'))
        ->call('createDocument')
        ->assertHasNoErrors();

    $doc = InstitutionalDocument::firstOrFail();
    expect($doc->current_version)->toBe(1);

    Livewire::actingAs($admin)
        ->test(DocumentsPage::class)
        ->call('startNewVersion', $doc->id)
        ->set('new_version_file', UploadedFile::fake()->create('reglamento_v2.pdf', 120, 'application/pdf'))
        ->call('uploadNewVersion')
        ->assertHasNoErrors();

    expect($doc->fresh()->current_version)->toBe(2);
    expect(RecordAttachment::where('attachable_type', InstitutionalDocument::class)->where('attachable_id', $doc->id)->count())->toBe(2);

    // 3. Expediente Alumnas búsqueda rápida por cédula y ficha completa de datos personales
    $student->update(['birth_date' => '1996-04-12']);

    Livewire::actingAs($admin)
        ->test(QueriesPage::class)
        ->set('search', '1-6644-3322')
        ->call('selectStudent', $student->id)
        ->assertSee('Valeria Araya')
        ->assertSee('Tipo de Identificación')
        ->assertSee('Cédula Nacional (CR)')
        ->assertSee('1-6644-3322')
        ->assertSee('12/04/1996')
        ->assertSee('Saldo pendiente');
});
