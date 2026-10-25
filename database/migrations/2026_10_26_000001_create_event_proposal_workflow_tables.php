<?php
declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('event_proposals', function (Blueprint $table): void {
   $table->id(); $table->foreignId('event_inquiry_id')->constrained()->cascadeOnDelete(); $table->unsignedInteger('version'); $table->json('snapshot'); $table->text('personal_note')->nullable(); $table->string('token_hash',64)->unique(); $table->text('token_encrypted'); $table->timestamp('expires_at'); $table->timestamp('sent_at')->nullable(); $table->timestamp('accepted_at')->nullable(); $table->timestamp('superseded_at')->nullable(); $table->timestamps(); $table->unique(['event_inquiry_id','version']);
  });
  Schema::create('event_date_holds', function (Blueprint $table): void {
   $table->id(); $table->date('event_date')->unique(); $table->foreignId('event_inquiry_id')->constrained()->cascadeOnDelete(); $table->foreignId('event_proposal_id')->nullable()->constrained()->nullOnDelete(); $table->string('status',16)->default('held')->index(); $table->timestamp('expires_at')->nullable()->index(); $table->timestamp('released_at')->nullable(); $table->string('release_reason',80)->nullable(); $table->timestamps();
  });
  Schema::create('event_deposits', function (Blueprint $table): void {
   $table->id(); $table->foreignId('event_proposal_id')->constrained()->cascadeOnDelete(); $table->decimal('amount',12,2); $table->date('paid_at')->nullable(); $table->string('method',40)->nullable(); $table->string('status',24)->default('submitted')->index(); $table->string('proof_path')->nullable(); $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamp('reviewed_at')->nullable(); $table->text('review_note')->nullable(); $table->text('override_reason')->nullable(); $table->timestamps();
  });
  Schema::create('event_email_logs', function (Blueprint $table): void {
   $table->id(); $table->foreignId('event_inquiry_id')->nullable()->constrained()->nullOnDelete(); $table->foreignId('event_proposal_id')->nullable()->constrained()->nullOnDelete(); $table->string('type',40); $table->string('recipient',255); $table->string('status',20)->default('queued')->index(); $table->json('attachments')->nullable(); $table->text('error')->nullable(); $table->timestamp('queued_at')->nullable(); $table->timestamp('sent_at')->nullable(); $table->timestamps();
  });
  Schema::table('event_inquiries', function (Blueprint $table): void { $table->string('status',60)->default('pending')->change(); });
 }
 public function down(): void {
  Schema::dropIfExists('event_email_logs'); Schema::dropIfExists('event_deposits'); Schema::dropIfExists('event_date_holds'); Schema::dropIfExists('event_proposals');
  Schema::table('event_inquiries', function (Blueprint $table): void { $table->string('status',20)->default('pending')->change(); });
 }
};
