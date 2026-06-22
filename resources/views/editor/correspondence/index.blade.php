@extends('layouts.app')

@section('content')
<style>
    .page-title{
        font-weight: 800;
        color: #0f172a;
    }

    .correspondence-hero{
        border: none;
        border-radius: 1.5rem;
        background:
            linear-gradient(135deg, rgba(14,165,233,.95), rgba(15,23,42,.92)),
            url('{{ asset('images/backgrounds/correspondence-hero.jpg') }}') center center / cover no-repeat;
        color: #fff;
        overflow: hidden;
        box-shadow: 0 20px 50px rgba(15,23,42,.16);
    }

    .panel-card{
        border: none;
        border-radius: 1.25rem;
        box-shadow: 0 18px 40px rgba(15,23,42,.08);
    }

    .message-card{
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        padding: 1.1rem;
        transition: .3s ease;
        background: #fff;
    }

    .message-card:hover{
        transform: translateY(-3px);
        box-shadow: 0 14px 30px rgba(15,23,42,.08);
        border-color: #cbd5e1;
    }

    .message-label{
        font-size: .8rem;
        text-transform: uppercase;
        color: #64748b;
        letter-spacing: .35px;
    }

    .message-title{
        font-weight: 700;
        color: #0f172a;
    }
</style>

<div class="container py-4">
    <div class="card correspondence-hero mb-4" data-aos="fade-up">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <span class="badge bg-light text-info mb-3 px-3 py-2">Editorial Correspondence</span>
                    <h2 class="fw-bold mb-3">Author & Reviewer Communication</h2>
                    <p class="mb-0 text-white-50">
                        Monitor editorial communication history, status updates, and decision-related messages across submissions.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                    <i class="bi bi-chat-left-dots" style="font-size: 5rem; opacity: .18;"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="mb-4" data-aos="fade-up">
        <h3 class="page-title mb-1">Correspondence Log</h3>
        <p class="text-muted mb-0">Track communication exchanged throughout the editorial workflow.</p>
    </div>

    <div class="card panel-card" data-aos="fade-up">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
            <h5 class="fw-bold mb-1">Messages</h5>
            <p class="text-muted mb-3">Recent communication records linked to journal submissions.</p>
        </div>

        <div class="card-body px-4 pb-4">
            <div class="row g-3">
                @forelse($messages ?? [] as $message)
                    <div class="col-12">
                        <div class="message-card">
                            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                                <div class="flex-grow-1">
                                    <div class="message-label mb-2">
                                        Submission
                                    </div>
                                    <div class="message-title mb-2">
                                        {{ $message->submission->title ?? 'Untitled Submission' }}
                                    </div>

                                    @if(!empty($message->subject))
                                        <div class="fw-semibold mb-2">
                                            {{ $message->subject }}
                                        </div>
                                    @endif

                                    <div class="text-muted mb-2">
                                        {{ \Illuminate\Support\Str::limit($message->message ?? $message->body ?? 'No message content available.', 220) }}
                                    </div>

                                    <div class="small text-muted">
                                        <i class="bi bi-clock me-1"></i>
                                        {{ $message->created_at?->format('d M Y, h:i A') }}
                                    </div>
                                </div>

                                <div class="text-lg-end">
                                    @if(!empty($message->type))
                                        <span class="badge bg-primary mb-2">
                                            {{ ucfirst(str_replace('_', ' ', $message->type)) }}
                                        </span>
                                    @endif

                                    @if(!empty($message->status))
                                        <div>
                                            <span class="badge bg-secondary">
                                                {{ ucfirst($message->status) }}
                                            </span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="text-center text-muted py-5">
                            No correspondence records found.
                        </div>
                    </div>
                @endforelse
            </div>

            @if(method_exists($messages ?? null, 'links'))
                <div class="mt-4">
                    {{ $messages->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
