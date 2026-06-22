@extends('layouts.app')

@section('content')
<style>
    .author-hero {
        border: none; border-radius: 1.5rem;
        background: linear-gradient(135deg, rgba(13,110,253,.92), rgba(15,23,42,.92));
        color: #fff; overflow: hidden;
        box-shadow: 0 20px 50px rgba(15,23,42,.16);
    }
    .panel-card { border: none; border-radius: 1.25rem; box-shadow: 0 18px 40px rgba(15,23,42,.08); }
    .status-badge { font-size: .78rem; padding: .35em .75em; border-radius: 50px; }
</style>

<div class="container py-4">
    {{-- Hero --}}
    <div class="card author-hero mb-4" data-aos="fade-up">
        <div class="card-body p-4 p-lg-5">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h2 class="fw-bold mb-2">My Submissions</h2>
                    <p class="mb-0 text-white-50">Track and manage all your manuscript submissions.</p>
                </div>
                <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                    <a href="{{ route('author.submissions.create') }}" class="btn btn-warning btn-lg fw-semibold">
                        <i class="bi bi-plus-circle me-2"></i>New Submission
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="card panel-card" data-aos="fade-up">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4">Sub. No.</th>
                            <th>Title</th>
                            <th>Files</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th class="pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($submissions as $sub)
                        <tr>
                            <td class="px-4 fw-semibold text-primary">{{ $sub->submission_no }}</td>
                            <td>{{ Str::limit($sub->title, 55) }}</td>
                            <td><span class="badge bg-secondary">{{ $sub->files->count() }}</span></td>
                            <td>
                                @php
                                    $colors = ['pending_payment'=>'warning','submitted'=>'primary','under_review'=>'info','revision_requested'=>'warning',
                                               'accepted'=>'success','rejected'=>'danger','published'=>'dark'];
                                    $color = $colors[$sub->status] ?? 'secondary';
                                @endphp
                                <span class="badge bg-{{ $color }} status-badge">{{ ucwords(str_replace('_',' ',$sub->status)) }}</span>
                            </td>
                            <td>{{ $sub->created_at->format('d M Y') }}</td>
                            <td class="pe-4">
                                @php
                                    $hasPaidVetting = $sub->payments->where('purpose', 'vetting_fee')->where('status', 'paid')->isNotEmpty();
                                    $hasPaidPublication = $sub->payments->where('purpose', 'publication_fee')->where('status', 'paid')->isNotEmpty();
                                    $isLockedForPayment = !$hasPaidVetting && in_array($sub->status, ['pending_payment', 'submitted'], true);
                                @endphp
                                <a href="{{ route('author.submissions.show', $sub) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i> View
                                </a>
                                @if(!$hasPaidVetting && $sub->status !== 'published')
                                <a href="{{ route('author.submissions.show', ['submission' => $sub->id, 'open_payment' => 'vetting']) }}" class="btn btn-sm btn-warning">
                                    <i class="bi bi-credit-card"></i> Pay
                                </a>
                                @elseif($sub->status === 'accepted' && !$hasPaidPublication)
                                <a href="{{ route('author.submissions.show', ['submission' => $sub->id, 'open_payment' => 'publication']) }}" class="btn btn-sm btn-success">
                                    <i class="bi bi-credit-card"></i> Pay Fee
                                </a>
                                @endif
                                @if($isLockedForPayment)
                                <span class="badge bg-warning text-dark">Awaiting Vetting Payment</span>
                                @elseif($sub->status === 'submitted')
                                <form method="POST" action="{{ route('author.submissions.destroy', $sub) }}" class="d-inline"
                                      onsubmit="return confirm('Delete this submission?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                No submissions yet. <a href="{{ route('author.submissions.create') }}">Submit your first manuscript</a>.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($submissions->hasPages())
        <div class="card-footer bg-white border-0 px-4 pb-4">
            {{ $submissions->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
